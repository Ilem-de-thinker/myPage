<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Enums\PayoutStatus;
use App\Domains\Payments\Jobs\ProcessPayout;
use App\Domains\Payments\Models\Payout;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Payments\Services\LedgerService;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RequestPayout
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    public function minimumKobo(): int
    {
        return (int) config('mypage.payout_minimum_kobo', 500_000);
    }

    /**
     * Move the balance into the payouts pool and queue the bank transfer.
     */
    public function handle(Seller $seller, int $amountKobo, string $bankCode, string $accountNumber): Payout
    {
        if ($amountKobo < $this->minimumKobo()) {
            throw new RuntimeException('That payout is below the minimum.');
        }

        $account = $this->gateway->resolveAccount($bankCode, $accountNumber);

        $payout = DB::transaction(function () use ($seller, $amountKobo, $bankCode, $accountNumber, $account) {
            $wallet = Wallet::forSeller($seller);

            $payout = Payout::create([
                'seller_id' => $seller->getKey(),
                'reference' => $this->generateReference(),
                'amount_kobo' => $amountKobo,
                'status' => PayoutStatus::Pending,
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'account_name' => $account['account_name'],
                'gateway' => (string) config('mypage.gateway'),
            ]);

            $this->ledger->holdForPayout($wallet, $payout);

            $wallet->increment('pending_kobo', $amountKobo);

            return $payout;
        });

        ProcessPayout::dispatch($payout->getKey());

        return $payout;
    }

    private function generateReference(): string
    {
        do {
            $reference = 'PO'.Str::upper(Str::random(10));
        } while (Payout::where('reference', $reference)->exists());

        return $reference;
    }
}
