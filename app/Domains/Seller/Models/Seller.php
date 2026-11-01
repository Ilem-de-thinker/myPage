<?php

namespace App\Domains\Seller\Models;

use App\Domains\Admin\Enums\AdminRole;
use App\Domains\Auth\Notifications\EmailOtpNotification;
use App\Domains\Billing\Models\Subscription;
use Database\Factories\Domains\Seller\Models\SellerFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

/** @use HasFactory<SellerFactory> */
class Seller extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'legal_name',
        'legal_registration_type',
        'legal_registration_number',
        'email',
        'phone',
        'password',
        'status',
        'is_admin',
        'admin_role',
        'last_login_at',
        'email_otp_hash',
        'email_otp_expires_at',
        'google_id',
        'terms_accepted_at',
        'terms_version',
        'privacy_accepted_at',
        'privacy_version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'admin_role' => AdminRole::class,
            'email_verified_at' => 'datetime',
            'onboarding_skipped_at' => 'datetime',
            'last_login_at' => 'datetime',
            'email_otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'kyc_document_expires_at' => 'date',
        ];
    }

    /**
     * Whether this seller can sign in without a password.
     *
     * True when they registered with Google. The `password` column is NOT NULL,
     * so a Google-only seller is issued a random unguessable one at signup: the
     * column has to be satisfied, and a password nobody knows is safer than a
     * guessable one somebody might try.
     */
    public function usesGoogle(): bool
    {
        return filled($this->google_id);
    }

    /**
     * Legacy rows only carry the boolean flag. Treat them as core admins so a
     * half-migrated database never hands somebody a weaker role than they had.
     */
    public function adminRole(): ?AdminRole
    {
        if ($this->admin_role !== null) {
            return $this->admin_role;
        }

        return $this->is_admin ? AdminRole::CoreAdmin : null;
    }

    public function isAdmin(): bool
    {
        return $this->adminRole() !== null;
    }

    public function isCoreAdmin(): bool
    {
        return $this->adminRole() === AdminRole::CoreAdmin;
    }

    public function isStaffAdmin(): bool
    {
        return $this->adminRole() === AdminRole::StaffAdmin;
    }

    public function storePages(): HasMany
    {
        return $this->hasMany(StorePage::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected static function booted(): void
    {
        // During the transition the role is the source of truth, and the old
        // boolean has to agree with it for code that has not been updated yet.
        static::saving(function (Seller $seller): void {
            if (! $seller->isDirty('admin_role')) {
                return;
            }

            if ($seller->admin_role !== null) {
                $seller->is_admin = true;
            } elseif ($seller->getOriginal('admin_role') !== null) {
                // A real demotion clears the old access flag. A new row that
                // merely sets the column to null must not erase is_admin=true,
                // which is how legacy admin rows still enter.
                $seller->is_admin = false;
            }
        });
    }

    public function sendEmailVerificationNotification(): void
    {
        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'email_otp_hash' => Hash::make($code),
            'email_otp_expires_at' => now()->addMinutes(15),
        ])->save();

        $this->notify(new EmailOtpNotification($code));
    }

    public function verifyEmailOtp(string $code): bool
    {
        if (! $this->email_otp_hash || ! $this->email_otp_expires_at || $this->email_otp_expires_at->isPast()) {
            return false;
        }

        if (! Hash::check($code, $this->email_otp_hash)) {
            return false;
        }

        $this->forceFill([
            'email_verified_at' => now(),
            'email_otp_hash' => null,
            'email_otp_expires_at' => null,
        ])->save();

        return true;
    }
}
