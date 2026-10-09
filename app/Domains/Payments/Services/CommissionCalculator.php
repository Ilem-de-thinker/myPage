<?php

namespace App\Domains\Payments\Services;

class CommissionCalculator
{
    public function basisPoints(): int
    {
        return (int) config('mypage.commission_bps', 150);
    }

    /**
     * Commission in kobo, rounded down so rounding always favours the seller.
     */
    public function for(int $amountKobo): int
    {
        return intdiv($amountKobo * $this->basisPoints(), 10_000);
    }
}
