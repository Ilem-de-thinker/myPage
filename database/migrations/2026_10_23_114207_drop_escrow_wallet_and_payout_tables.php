<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The platform is no longer in the money flow.
 *
 * A buyer pays the seller's own gateway account, so there is no balance to hold,
 * split, escrow or pay out. These tables described money that never touched us.
 *
 * Destructive on purpose. In an existing deployment, ship and verify the new
 * payment path first, then run this in a later deploy once no old code can write
 * to these tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Children before parents, so an environment with foreign keys enforces
        // them cleanly.
        Schema::dropIfExists('wallet_ledger');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('escrows');
        Schema::dropIfExists('payouts');

        if (Schema::hasColumn('transactions', 'commission_kobo')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->dropColumn('commission_kobo');
            });
        }

        foreach (['payout_bank_code', 'payout_account_number', 'payout_account_name'] as $column) {
            if (Schema::hasColumn('sellers', $column)) {
                Schema::table('sellers', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        // Deliberately not restored. Recreating a ledger that nothing writes to
        // would only make the next reader believe the feature still exists.
    }
};
