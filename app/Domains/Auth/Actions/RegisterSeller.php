<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Events\SellerRegistered;
use App\Domains\Seller\Models\Seller;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

class RegisterSeller
{
    /**
     * @param  array{name: string, email: string, phone: string, password: string, terms_accepted: true, privacy_accepted: true}  $data
     */
    public function handle(array $data): Seller
    {
        return DB::transaction(function () use ($data) {
            $seller = Seller::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => PhoneNumber::normalize($data['phone']),
                'password' => $data['password'],
                'status' => 'active',
                'terms_accepted_at' => now(),
                'terms_version' => config('legal.terms_version'),
                'privacy_accepted_at' => now(),
                'privacy_version' => config('legal.privacy_version'),
            ]);

            event(new SellerRegistered($seller));

            $seller->sendEmailVerificationNotification();

            return $seller;
        });
    }
}
