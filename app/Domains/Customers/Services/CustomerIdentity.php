<?php

namespace App\Domains\Customers\Services;

use App\Domains\Customers\Models\Customer;
use App\Shared\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Decides whether a buyer arriving with an order or a booking is someone the
 * seller has seen before.
 *
 * Both paths go through here on purpose: an order and a booking made with the
 * same phone number are the same person, and two different matching rules would
 * split one customer into two rows.
 */
class CustomerIdentity
{
    /**
     * Find or create the customer for these contact details. Returns null when
     * the buyer gave nothing to be identified by.
     *
     * @param  class-string<Model>  $type
     * @return array{customer: Customer, created: bool}|null
     */
    public function resolve(int $sellerId, ?string $phone, ?string $email, string $type): ?array
    {
        $phoneKey = Customer::identityKey($phone, null);
        $emailKey = Customer::identityKey(null, $email);

        $identityKey = $phoneKey ?? $emailKey;

        // Anonymous buyers cannot be told apart from one another, so they get no
        // record rather than a fresh one on every single order.
        if ($identityKey === null) {
            return null;
        }

        return DB::transaction(function () use ($sellerId, $phone, $email, $type, $identityKey, $phoneKey, $emailKey) {
            // Either key may match: someone who ordered with an email last month
            // and gives a phone this time is the same person, and their record
            // should carry the stronger identifier from now on.
            $customer = $this->lockByKey($sellerId, $phoneKey)
                ?? $this->lockByKey($sellerId, $emailKey);

            $created = $customer === null;

            $customer ??= Customer::withoutGlobalScopes()->create([
                'seller_id' => $sellerId,
                'identity_key' => $identityKey,
                'name' => null,
                'email' => $email,
                'phone' => PhoneNumber::normalize($phone),
                // Never opt a buyer in just because they bought something.
                'opted_in' => false,
            ]);

            // Fill gaps, but never blank out what a previous order taught us.
            $customer->update([
                'identity_key' => $identityKey,
                'email' => $email ?: $customer->email,
                'phone' => PhoneNumber::normalize($phone) ?: $customer->phone,
                "{$type}_count" => $customer->{"{$type}_count"} + 1,
                'last_activity_at' => $this->latestActivity($customer),
            ]);

            return ['customer' => $customer, 'created' => $created];
        });
    }

    private function lockByKey(int $sellerId, ?string $identityKey): ?Customer
    {
        if ($identityKey === null) {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->where('seller_id', $sellerId)
            ->where('identity_key', $identityKey)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Activity only ever moves forwards; a backfilled order must not drag a
     * customer's last seen date into the past.
     */
    private function latestActivity(Customer $customer): \DateTimeInterface
    {
        $now = now();

        return $customer->last_activity_at && $customer->last_activity_at->gt($now)
            ? $customer->last_activity_at
            : $now;
    }
}
