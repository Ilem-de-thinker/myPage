<?php

namespace App\Domains\Domains\Models;

use App\Domains\Domains\Enums\DomainStatus;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Hostname;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Domains\Models\DomainBindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller's own domain, pointed at their storefront.
 *
 * @property DomainStatus $status
 */
class DomainBinding extends Model
{
    /** @use HasFactory<DomainBindingFactory> */
    use BelongsToSeller;

    use HasFactory;

    protected $fillable = [
        'seller_id',
        'store_page_id',
        'host',
        'verification_token',
        'status',
        'verified_at',
        'ssl_active_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'verified_at' => 'datetime',
            'ssl_active_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The host is canonicalised on the way in, not on the way out, so the
        // unique index and the request-time lookup agree on what a host is.
        static::saving(function (self $binding): void {
            if ($host = Hostname::withoutWww((string) Hostname::normalise($binding->host))) {
                $binding->host = $host;
            }
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    /**
     * The CNAME the seller has to create at their registrar.
     */
    public function cnameTarget(): string
    {
        return (string) config('mypage.custom_domain.cname_target');
    }

    public function isServed(): bool
    {
        return $this->status->isServed();
    }

    /**
     * The label to show them while they wait, including the specific record
     * their DNS provider is probably missing.
     */
    public function nextStep(): ?string
    {
        if ($this->status === DomainStatus::Active) {
            return null;
        }

        if ($this->status === DomainStatus::Suspended) {
            return 'Your domain has stopped pointing at us. Check the CNAME record is still there.';
        }

        return $this->last_error
            ?? "Add a CNAME record for {$this->host} pointing to {$this->cnameTarget()}, then check again.";
    }
}
