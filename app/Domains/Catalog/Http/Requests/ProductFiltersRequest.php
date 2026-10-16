<?php

namespace App\Domains\Catalog\Http\Requests;

use App\Domains\Catalog\Enums\ProductSort;
use App\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The store catalog's filter state, validated and normalised.
 *
 * Filtering happens on a GET, which means the input arrives from a URL a person
 * can edit, share in a WhatsApp message, or arrive on from a stale bookmark. So
 * every field is optional, nothing here ever fails, and an unusable value is
 * dropped rather than redirected with an error — the visitor is looking at a shop,
 * not filling in a form, and a 422 on a filter click loses the page they were on.
 *
 * Naira goes in on the wire and kobo comes out, because that is the unit prices
 * are stored in and the unit the two price boxes are labelled in.
 */
class ProductFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Fold a scalar category into a list, and trim the term.
     *
     * An older shared link carries `?category=3` while the multi-select control
     * posts `?category[]=3`. Normalising here means every caller downstream deals
     * with one shape, and the first shape still works.
     */
    protected function prepareForValidation(): void
    {
        $category = $this->input('category');

        if ($category !== null && ! is_array($category)) {
            $this->merge(['category' => [$category]]);
        }

        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            // Accepts a bare id and an array of ids from the same control.
            'category' => ['nullable', 'array', 'max:50'],
            'category.*' => ['integer', 'min:1'],
            // A bare `category=3` from an older shared link still works.
            'min' => ['nullable', 'numeric', 'min:0'],
            'max' => ['nullable', 'numeric', 'min:0'],
            /*
             * Deliberately not `in:`. An unrecognised sort key is dropped by the
             * enum and the page renders on its default ordering; failing
             * validation would bounce a visitor off a shop because somebody
             * hand-edited a URL, which is the opposite of what a shop wants.
             */
            'sort' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * The term, already trimmed. Empty is genuinely empty, not a space that
     * matches every description in the catalogue.
     */
    public function term(): ?string
    {
        $term = trim((string) $this->input('q', ''));

        return $term === '' ? null : $term;
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        $raw = $this->input('category');

        return collect(is_array($raw) ? $raw : [$raw])
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The lower bound in kobo, or null when unbounded.
     */
    public function minKobo(): ?int
    {
        return $this->nairaToKobo('min');
    }

    public function maxKobo(): ?int
    {
        return $this->nairaToKobo('max');
    }

    public function sort(): ProductSort
    {
        return ProductSort::tryFromRequest($this->input('sort'));
    }

    /**
     * Whether anything at all is being filtered.
     *
     * Drives the empty state's advice: "no products matched" and "this shop has no
     * products" are different sentences and only one of them is the shopper's fault.
     */
    public function hasFilters(): bool
    {
        return $this->term() !== null
            || $this->categoryIds() !== []
            || $this->minKobo() !== null
            || $this->maxKobo() !== null
            || $this->filled('sort');
    }

    /**
     * Only the keys a caller should carry between requests.
     *
     * @return array<string, mixed>
     */
    public function activeFilters(): array
    {
        return array_filter([
            'q' => $this->term(),
            'category' => $this->categoryIds(),
            'min' => $this->input('min'),
            'max' => $this->input('max'),
            'sort' => $this->input('sort'),
        ], fn ($value): bool => $value !== null && $value !== []);
    }

    private function nairaToKobo(string $key): ?int
    {
        $value = $this->input($key);

        if (! is_numeric($value)) {
            return null;
        }

        return Money::toKobo((string) $value);
    }
}
