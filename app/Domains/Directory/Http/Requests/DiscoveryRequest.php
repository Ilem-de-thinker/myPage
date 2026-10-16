<?php

namespace App\Domains\Directory\Http\Requests;

use App\Domains\Seller\Enums\PriceTier;
use App\Domains\Seller\Enums\Theme;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The discovery hub's filter state.
 *
 * The hub is public and unranked by search engines into everything it serves, so
 * the only rules it enforces are the ones that keep a URL from becoming a way to
 * hammer the database: a length cap on the term, a whitelist on the type, and a
 * real tier or nothing. Everything else is dropped, not rejected.
 */
class DiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:80'],
            'tier' => ['nullable', 'integer', 'min:1', 'max:4'],
            'sort' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function term(): ?string
    {
        $term = trim((string) $this->input('q', ''));

        return $term === '' ? null : $term;
    }

    /**
     * Null means "both types", which is the hub's default state.
     */
    public function theme(): ?Theme
    {
        $type = $this->input('type');

        return is_string($type) ? Theme::tryFrom($type) : null;
    }

    public function city(): ?string
    {
        $city = trim((string) $this->input('city', ''));

        return $city === '' ? null : $city;
    }

    public function tier(): ?PriceTier
    {
        return PriceTier::tryFromRequest($this->input('tier'));
    }

    /**
     * Whether the visitor has narrowed anything at all.
     *
     * This is what decides between the marquee (the "here is everything" state)
     * and the result grid (the "here is what you asked for" state).
     */
    public function hasFilters(): bool
    {
        return $this->term() !== null
            || $this->theme() !== null
            || $this->city() !== null
            || $this->tier() !== null
            || $this->filled('sort');
    }

    /**
     * @return array<string, mixed>
     */
    public function activeFilters(): array
    {
        return array_filter([
            'q' => $this->term(),
            'type' => $this->theme()?->value,
            'city' => $this->city(),
            'tier' => $this->tier()?->value,
            'sort' => $this->input('sort'),
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * Every query parameter this request understands, with the rest dropped.
     *
     * Used to build the "clear one filter" links: carrying an unknown parameter
     * across would leave it stuck in the URL forever, and nobody notices a chip
     * for a filter that does nothing.
     *
     * @return array<string, mixed>
     */
    public function carriedFilters(): array
    {
        return $this->activeFilters();
    }
}
