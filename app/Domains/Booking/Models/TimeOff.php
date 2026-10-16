<?php

namespace App\Domains\Booking\Models;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off break in the schedule: a holiday, a training day, staff sickness.
 * Stored in UTC, like everything else that is not a wall clock.
 */
class TimeOff extends Model
{
    protected $fillable = ['store_page_id', 'staff_member_id', 'starts_at', 'ends_at', 'reason'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Narrow a time off query to whoever it applies to. With nobody named this is
     * the shop as a whole, so one person's holiday never closes the business.
     */
    public function scopeScopedTo(Builder $query, ?StaffMember $staff): Builder
    {
        return $query->when(
            $staff,
            fn (Builder $query) => $query->where('staff_member_id', $staff->getKey()),
            fn (Builder $query) => $query->whereNull('staff_member_id'),
        );
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
