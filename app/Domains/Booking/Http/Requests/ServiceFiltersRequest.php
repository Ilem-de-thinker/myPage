<?php

namespace App\Domains\Booking\Http\Requests;

use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Enums\ServiceSort;
use App\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The service directory's filter state, validated and normalised.
 *
 * Same contract as the store's ProductFiltersRequest: every field optional,
 * nothing here able to fail on a value a person typed. A visitor arriving from a
 * shared link with a broken query string must still get to the services.
 */
class ServiceFiltersRequest extends FormRequest
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
            'min' => ['nullable', 'numeric', 'min:0'],
            'max' => ['nullable', 'numeric', 'min:0'],
            // "Under an hour", "a half day", "all day". Named rather than a raw
            // minute count so the filter survives being written by a person.
            'duration' => ['nullable', 'string', 'max:20'],
            'unit' => ['nullable', 'string', 'max:20'],
            // Unknown keys fall back through the enum, as on the store side.
            'sort' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function term(): ?string
    {
        $term = trim((string) $this->input('q', ''));

        return $term === '' ? null : $term;
    }

    public function minKobo(): ?int
    {
        return $this->nairaToKobo('min');
    }

    public function maxKobo(): ?int
    {
        return $this->nairaToKobo('max');
    }

    /**
     * The longest appointment a buyer is willing to sit through, in minutes.
     *
     * @return array<string, int> label => minutes
     */
    public static function durationOptions(): array
    {
        return [
            '30' => 30,
            '60' => 60,
            '120' => 120,
            '180' => 180,
        ];
    }

    public function maxDurationMinutes(): ?int
    {
        $key = (string) $this->input('duration', '');

        return self::durationOptions()[$key] ?? null;
    }

    public function durationLabel(): ?string
    {
        $minutes = $this->maxDurationMinutes();

        if ($minutes === null) {
            return null;
        }

        return match (true) {
            $minutes < 60 => "Under {$minutes} min",
            $minutes === 60 => 'Up to 1 hour',
            $minutes % 60 === 0 => 'Up to '.intdiv($minutes, 60).' hours',
            default => 'Up to '.$minutes.' min',
        };
    }

    public function unit(): ?BillingUnit
    {
        return BillingUnit::tryFromRequest($this->input('unit'));
    }

    public function sort(): ServiceSort
    {
        return ServiceSort::tryFromRequest($this->input('sort'));
    }

    public function featuredOnly(): bool
    {
        return $this->boolean('featured');
    }

    public function hasFilters(): bool
    {
        return $this->term() !== null
            || $this->minKobo() !== null
            || $this->maxKobo() !== null
            || $this->maxDurationMinutes() !== null
            || $this->unit() !== null
            || $this->featuredOnly()
            || $this->filled('sort');
    }

    /**
     * @return array<string, mixed>
     */
    public function activeFilters(): array
    {
        return array_filter([
            'q' => $this->term(),
            'min' => $this->input('min'),
            'max' => $this->input('max'),
            'duration' => $this->input('duration'),
            'unit' => $this->input('unit'),
            'featured' => $this->featuredOnly() ?: null,
            'sort' => $this->input('sort'),
        ], fn ($value): bool => $value !== null && $value !== '');
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
