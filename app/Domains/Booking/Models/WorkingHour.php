<?php

namespace App\Domains\Booking\Models;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring weekly window the store is open. Times are wall clock in the
 * store's own timezone; weekday is ISO, 1 (Monday) to 7 (Sunday).
 */
class WorkingHour extends Model
{
    // Recurring weekly rows have no place in time, so the table has no
    // timestamps at all rather than two columns that are always null.
    public $timestamps = false;

    protected $fillable = ['store_page_id', 'staff_member_id', 'weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
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
