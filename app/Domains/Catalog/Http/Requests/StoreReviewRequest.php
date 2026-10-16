<?php

namespace App\Domains\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a review someone is leaving about a business, product or service.
 *
 * Buyers have no account, so there is no identity to check and no way to prevent
 * a determined person from submitting twice. Everything here is therefore either
 * a length bound or a range bound — the fields that exist to make the row
 * renderable, not to police intent. The endpoint's throttle does the latter, and
 * the moderation queue does the rest.
 */
class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'author_name' => ['required', 'string', 'min:2', 'max:80'],
            'author_location' => ['nullable', 'string', 'max:80'],
            // A minimum length, not just a maximum. "Great" is a rating, not a
            // review, and a wall of one-word rows makes the list worthless.
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            'is_anonymous' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'author_name' => 'your name',
            'author_location' => 'your location',
            'body' => 'review',
        ];
    }

    /**
     * Trimmed and shaped for the action.
     *
     * @return array{author_name: string, author_location: ?string, rating: int, body: string, is_anonymous: bool}
     */
    public function reviewData(): array
    {
        return [
            'author_name' => trim((string) $this->input('author_name')),
            'author_location' => ($location = trim((string) $this->input('author_location'))) !== '' ? $location : null,
            'rating' => (int) $this->input('rating'),
            'body' => trim((string) $this->input('body')),
            'is_anonymous' => $this->boolean('is_anonymous'),
        ];
    }
}
