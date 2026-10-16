<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Booking\Models\Service;
use App\Domains\Seller\Enums\ReviewStatus;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Html;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Catalog\Models\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** @use HasFactory<ReviewFactory> */
class Review extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'seller_id',
        'store_page_id',
        'author_name',
        'author_location',
        'rating',
        'body',
        'is_anonymous',
        'status',
        'is_featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_anonymous' => 'boolean',
            'is_featured' => 'boolean',
            'status' => ReviewStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Review $review): void {
            // The body is buyer-authored and lands on a public page, so it is
            // sanitised on the way in rather than escaped on the way out. Every
            // other place it would have to be remembered; here there is one door.
            $review->body = Html::sanitize($review->body);
            $review->status ??= ReviewStatus::Pending;
        });
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    /**
     * The business, product or service this review is about.
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | What this review is about
    |--------------------------------------------------------------------------
    |
    | One table for all three shapes rather than three tables: the inbox, the
    | moderation queue and the JSON-LD all want "every review for this seller",
    | and a union of three tables is a slower answer to the only question anyone
    * actually asks.
    */

    /**
     * The product or service name, or null for a review of the business itself.
     */
    public function subjectLabel(): ?string
    {
        $reviewable = $this->reviewable;

        return $reviewable instanceof Product || $reviewable instanceof Service
            ? $reviewable->name
            : null;
    }

    /**
     * What to call the author on screen.
     *
     * An anonymous review still needs a byline or it reads as unattributed
     * content; "A customer" is honest and is not a name anyone can dox.
     */
    public function authorLabel(): string
    {
        if ($this->is_anonymous) {
            return 'A customer';
        }

        return $this->author_location
            ? $this->author_name.', '.$this->author_location
            : $this->author_name;
    }

    public function isPending(): bool
    {
        return $this->status === ReviewStatus::Pending;
    }

    public function isPublished(): bool
    {
        return $this->status->isPublished();
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Approved->value)
            ->whereNotNull('published_at');
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeAwaitingModeration(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Pending->value);
    }
}
