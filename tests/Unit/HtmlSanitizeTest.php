<?php

use App\Shared\Support\Html;

it('keeps allowed tags', function () {
    expect(Html::sanitize('<p>Hello <strong>world</strong></p>'))
        ->toBe('<p>Hello <strong>world</strong></p>');
});

it('strips event handler attributes', function () {
    expect(Html::sanitize('<p onclick="steal()">hi</p>'))
        ->toBe('<p>hi</p>');

    expect(Html::sanitize('<img src="https://example.com/x.png" onerror="steal()" alt="x">'))
        ->toBe('<img src="https://example.com/x.png" alt="x">');
});

it('drops javascript: URLs but keeps the attribute off the tag', function () {
    expect(Html::sanitize('<a href="javascript:steal()">click</a>'))
        ->toBe('<a>click</a>');
});

it('drops data: URLs from images', function () {
    expect(Html::sanitize('<img src="data:text/html;base64,AAAA">'))
        ->toBe('<img>');
});

it('keeps safe links and images', function () {
    expect(Html::sanitize('<a href="https://example.com" title="site">x</a>'))
        ->toBe('<a href="https://example.com" title="site">x</a>');

    expect(Html::sanitize('<a href="mailto:a@b.ng">mail</a>'))
        ->toBe('<a href="mailto:a@b.ng">mail</a>');

    expect(Html::sanitize('<a href="/local/path">local</a>'))
        ->toBe('<a href="/local/path">local</a>');
});

it('unwraps disallowed tags but keeps their text', function () {
    expect(Html::sanitize('<div><span>text</span></div>'))
        ->toBe('text');
});

it('drops script and style subtrees entirely', function () {
    expect(Html::sanitize('<p>ok</p><script>alert(1)</script>'))
        ->toBe('<p>ok</p>');

    expect(Html::sanitize('<style>body{display:none}</style><p>ok</p>'))
        ->toBe('<p>ok</p>');
});

it('escapes raw ampersands and quotes in text', function () {
    expect(Html::sanitize('<p>a & b "quoted"</p>'))
        ->toBe('<p>a &amp; b &quot;quoted&quot;</p>');
});

it('handles empty and null input', function () {
    expect(Html::sanitize(null))->toBe('');
    expect(Html::sanitize(''))->toBe('');
});
