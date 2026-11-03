<?php

namespace App\Shared\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class Html
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TAGS = [
        'h2' => [],
        'h3' => [],
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    /** Tags whose entire subtree is discarded, not unwrapped. */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea'];

    /**
     * Sanitise seller- or AI-authored HTML for rendering with {!! !!}.
     *
     * strip_tags() alone is not enough: it removes the <script> wrapper but
     * keeps onerror= and javascript: URLs. This walks the parsed tree instead
     * and rebuilds it with an allowlist of tags, an allowlist of attributes,
     * and a scheme check on every URL.
     */
    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="html-sanitize-root">'.$html.'</div></body></html>',
            LIBXML_NOWARNING | LIBXML_NOERROR,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('html-sanitize-root');

        if ($root === null) {
            return '';
        }

        return self::cleanChildren($root);
    }

    private static function cleanChildren(DOMNode $node): string
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $out .= htmlspecialchars($child->wholeText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                continue;
            }

            if (! $child instanceof DOMElement) {
                // Comments and processing instructions are dropped.
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                continue;
            }

            if (! array_key_exists($tag, self::ALLOWED_TAGS)) {
                // Unknown tag: keep the inner content, drop the tag itself.
                $out .= self::cleanChildren($child);

                continue;
            }

            $attributes = self::cleanAttributes($tag, $child);

            if ($tag === 'br') {
                $out .= '<br>';

                continue;
            }

            if ($tag === 'img') {
                $out .= '<img'.($attributes === '' ? '' : ' '.$attributes).'>';

                continue;
            }

            $out .= '<'.$tag.($attributes === '' ? '' : ' '.$attributes).'>'
                .self::cleanChildren($child)
                .'</'.$tag.'>';
        }

        return $out;
    }

    private static function cleanAttributes(string $tag, DOMElement $element): string
    {
        $allowed = self::ALLOWED_TAGS[$tag];
        $kept = [];

        foreach ($allowed as $name) {
            if (! $element->hasAttribute($name)) {
                continue;
            }

            $value = trim($element->getAttribute($name));

            $valid = match ($name) {
                'href', 'src' => self::isSafeUrl($value),
                'width', 'height' => ctype_digit($value) && strlen($value) <= 5,
                default => true,
            };

            if ($valid) {
                $kept[] = $name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
            }
        }

        return implode(' ', $kept);
    }

    private static function isSafeUrl(string $url): bool
    {
        // Absolute http(s), mailto, and site-relative. No protocol-relative,
        // no data:, no javascript:.
        return $url !== ''
            && preg_match('#^(https://|http://|mailto:|/|\#)#i', $url) === 1
            && ! preg_match('#[\x00-\x1F\x7F]#', $url);
    }
}
