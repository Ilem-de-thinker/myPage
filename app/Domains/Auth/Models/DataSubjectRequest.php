<?php

namespace App\Domains\Auth\Models;

use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSubjectRequest extends Model
{
    protected $fillable = [
        'seller_id',
        'name',
        'email',
        'request_type',
        'details',
        'status',
        'verified_at',
        'requested_at',
        'due_at',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'due_at' => 'datetime',
            'verified_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Open requests past their NDPA answer date.
     */
    public function scopeOverdue($query)
    {
        return $query->whereNull('resolved_at')->whereNotNull('due_at')->where('due_at', '<', now());
    }
}
