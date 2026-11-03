<?php

namespace App\Domains\Billing\Models;

use App\Shared\Support\Money;
use Database\Factories\Domains\Billing\Models\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<PlanFactory> */
class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'price_kobo',
        'interval',
        'trial_days',
        'max_products',
        'max_staff',
        'ai_monthly_credits',
        'ai_max_images_per_request',
        'is_default',
        'is_featured',
        'is_active',
        'gateway_plan_code',
    ];

    protected function casts(): array
    {
        return [
            'price_kobo' => 'integer',
            'trial_days' => 'integer',
            'max_products' => 'integer',
            'max_staff' => 'integer',
            'ai_monthly_credits' => 'integer',
            'ai_max_images_per_request' => 'integer',
            'is_default' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function priceLabel(): string
    {
        return Money::format($this->price_kobo).' / '.rtrim($this->interval, 'ly');
    }

    /**
     * null means unlimited, which is why the column is nullable rather than zero.
     */
    public function allowsProducts(int $count): bool
    {
        return $this->max_products === null || $count <= $this->max_products;
    }

    public function allowsStaff(int $count): bool
    {
        return $this->max_staff === null || $count <= $this->max_staff;
    }
}
