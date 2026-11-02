<?php

namespace App\Domains\Admin\Models;

use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Database\Factories\Domains\Admin\Models\BadgeApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** @use HasFactory<BadgeApplicationFactory> */
class BadgeApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'store_page_id',
        'status',
        'business_name',
        'registered_legal_name',
        'document_type',
        'registration_number',
        'document_path',
        'document_expires_at',
        'note',
        'reviewed_by_seller_id',
        'rejection_reason',
        'reviewed_at',
        'submitted_at',
        'retention_until',
        'document_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BadgeStatus::class,
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'document_expires_at' => 'date',
            'retention_until' => 'datetime',
            'document_deleted_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function isPending(): bool
    {
        return $this->status === BadgeStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->document_expires_at !== null && $this->document_expires_at->isPast();
    }

    public function canResubmit(): bool
    {
        return in_array($this->status, [
            BadgeStatus::Rejected,
            BadgeStatus::NeedsMoreInfo,
            BadgeStatus::Expired,
            BadgeStatus::Revoked,
        ], true);
    }

    public function maskedRegistrationNumber(): string
    {
        $value = (string) $this->registration_number;

        if (strlen($value) <= 6) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 3).str_repeat('•', strlen($value) - 6).substr($value, -3);
    }

    /**
     * Uploads are never trusted to be images of documents. The name is forced and
     * the extension is taken from the stored path, so a seller cannot get a
     * .php file onto disk by calling it a CAC certificate.
     */
    public function documentUrl(): string
    {
        return URL::temporarySignedRoute('admin.badges.document', now()->addMinutes(10), $this);
    }

    public function hasDocument(): bool
    {
        return $this->document_deleted_at === null
            && filled($this->document_path)
            && Storage::disk('local')->exists($this->document_path);
    }
}
