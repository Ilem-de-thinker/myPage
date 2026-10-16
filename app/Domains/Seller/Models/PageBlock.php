<?php

namespace App\Domains\Seller\Models;

use Database\Factories\Domains\Seller\Models\PageBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of a seller's content on one page of their storefront.
 *
 * The old StoreSection let a seller enable, disable and reorder these. All three
 * are gone: each theme is a fixed website, so blocks render in the order the
 * theme defines and a block with nothing in it does not appear at all. What is
 * left is the words and pictures the seller wrote.
 *
 * @property array<string, mixed> $content
 */
class PageBlock extends Model
{
    /** @use HasFactory<PageBlockFactory> */
    use HasFactory;

    protected $fillable = [
        'store_page_id',
        'page',
        'key',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    /**
     * "page.key", the identifier the theme registry and the views both use.
     */
    public function blockKey(): string
    {
        return $this->page.'.'.$this->key;
    }

    /**
     * Read one field, falling back to the theme's default.
     *
     * Defaults live in config rather than in a row so that changing a heading
     * does not mean writing to the database for every page that uses it.
     */
    public function field(string $name): mixed
    {
        $value = $this->content[$name] ?? null;

        if ($value !== null && $value !== '' && $value !== []) {
            return $value;
        }

        return $this->schema()['fields'][$name]['default'] ?? null;
    }

    /**
     * Does this block have anything to say?
     *
     * The rule that replaces the enable/disable checkbox. A block whose fields
     * are all blank renders as nothing, so a seller never sees an empty heading
     * above an empty section and never has to think about switching it off.
     *
     * Defaults do not count as content: a block showing only the theme's
     * default heading and nothing else is still empty.
     */
    public function isEmpty(): bool
    {
        foreach ($this->schema()['fields'] ?? [] as $name => $field) {
            $value = $this->content[$name] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($field['type'] === 'repeater' && $value === []) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * This block's definition from the theme registry.
     *
     * @return array{label?: string, help?: string, fields?: array<string, array{type: string, label: string}>}
     */
    public function schema(): array
    {
        // config() treats a dot in a key as nesting, so the registry stores
        // block keys flattened (home_hero) and the dotted name is rebuilt here.
        $theme = $this->storePage->theme->value;

        return config("themes.{$theme}.blocks.".str_replace('.', '_', $this->blockKey()), []);
    }

    public function label(): string
    {
        return $this->schema()['label'] ?? ucfirst($this->key);
    }
}
