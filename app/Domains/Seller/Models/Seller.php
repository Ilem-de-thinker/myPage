<?php

namespace App\Domains\Seller\Models;

use App\Domains\Auth\Notifications\EmailOtpNotification;
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
        'email',
        'phone',
        'password',
        'status',
        'is_admin',
        'last_login_at',
        'email_otp_hash',
        'email_otp_expires_at',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'email_otp_expires_at' => 'datetime',
            'password' => 'hashed',
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

    public function storePages(): HasMany
    {
        return $this->hasMany(StorePage::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
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
