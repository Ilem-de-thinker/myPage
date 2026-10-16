<?php

namespace App\Domains\Booking\Models;

use App\Domains\Seller\Models\StorePage;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Booking\Models\StaffMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<StaffMemberFactory> */
class StaffMember extends Model
{
    use BelongsToSeller, HasFactory;

    protected $table = 'staff_members';

    protected $fillable = ['seller_id', 'store_page_id', 'name', 'photo_path', 'bio', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
