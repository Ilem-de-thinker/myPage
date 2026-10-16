<?php

namespace App\Domains\Seller\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Works out which blocks a page shows, and in what order.
 *
 * The order comes from the theme registry, never from the database, and a block
 * with no content is dropped. That replaces the old is_enabled/position columns
 * and the seller-facing checkboxes and drag handles that went with them: a seller
 * edits words and pictures, and the theme decides the shape of the page.
 */
class BlockRenderer
{
    /**
     * Blocks for one page, in theme order, with the empty ones removed.
     *
     * @return BaseCollection<int, PageBlock>
     */
    public function forPage(StorePage $storePage, string $page, ?string $theme = null): BaseCollection
    {
        $theme ??= $storePage->theme->value;

        // Built from the editable set so a block the theme declares but the
        // seller has never saved still gets a chance to render. That matters
        // for the blocks showing real rows: "no heading typed" is not the same
        // as "no products".
        return $this->editableForPage($storePage, $page, $theme)
            ->filter(fn (PageBlock $block) => $this->hasSomethingToShow($block))
            ->values();
    }

    /**
     * Should this block appear on the page?
     *
     * Usually: only if the seller wrote something. A block whose heading is
     * blank renders an empty section above nothing.
     *
     * But a block marked `always` in the registry is showing real rows, not
     * stored words — the seller's products, their categories, their services.
     * Its emptiness is decided by whether they have any, which the component
     * already handles, so gating it on content would hide a catalogue from a
     * seller who simply did not type a heading.
     */
    private function hasSomethingToShow(PageBlock $block): bool
    {
        return ($block->schema()['always'] ?? false) || ! $block->isEmpty();
    }

    /**
     * Every block a seller can fill in for a page, empty ones included.
     *
     * The settings screen wants these: a block with nothing in it is exactly the
     * one that needs an input, and hiding it there would leave no way to fill it.
     *
     * @return BaseCollection<int, PageBlock>
     */
    public function editableForPage(StorePage $storePage, string $page, ?string $theme = null): BaseCollection
    {
        $theme ??= $storePage->theme->value;

        $order = config("themes.{$theme}.pages.{$page}.blocks", []);

        $saved = $this->savedBlocks($storePage, $page)->keyBy('key');

        return collect($order)
            ->map(function (string $key) use ($saved, $storePage, $page): PageBlock {
                return $saved->get($key) ?? tap(new PageBlock([
                    'page' => $page,
                    'key' => $key,
                    'content' => [],
                ]), function (PageBlock $block) use ($storePage): void {
                    $block->store_page_id = $storePage->getKey();
                    // Attached rather than saved, so rendering the settings form
                    // does not write rows for every block a seller has never
                    // touched.
                    $block->setRelation('storePage', $storePage);
                });
            })
            ->values();
    }

    /**
     * @return Collection<int, PageBlock>
     */
    private function savedBlocks(StorePage $storePage, string $page): Collection
    {
        $blocks = $storePage->blocks()->where('page', $page)->get();

        // Every saved block already belongs to this in-memory page. Reuse it
        // instead of letting each block lazily reload the same parent row.
        $blocks->each->setRelation('storePage', $storePage);

        return $blocks;
    }
}
