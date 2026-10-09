<?php

namespace App\Domains\Payments\Events;

use App\Domains\Payments\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Transaction $transaction) {}
}
