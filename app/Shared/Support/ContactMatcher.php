<?php

namespace App\Shared\Support;

use App\Domains\Booking\Models\Booking;
use App\Domains\Orders\Models\Order;
use Illuminate\Support\Str;

/**
 * Does this contact belong to the order or booking?
 *
 * Phone numbers are compared normalised, because the same buyer types
 * "08031234567" on the form and "+2348031234567" on the receipt. Emails are
 * compared case-insensitively. A null contact never matches: an empty
 * credential is not a credential.
 */
final class ContactMatcher
{
    public static function matches(Order|Booking $record, string $contact): bool
    {
        $contact = trim($contact);

        if ($contact === '') {
            return false;
        }

        $email = trim((string) ($record->customer_email ?? ''));

        if ($email !== '' && Str::lower($email) === Str::lower($contact)) {
            return true;
        }

        $given = PhoneNumber::normalize($contact);

        return $given !== null && $given === PhoneNumber::normalize($record->customer_phone);
    }
}
