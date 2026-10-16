<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a seller's money goes when they withdraw it.
     *
     * Stored on the seller rather than retyped per payout, because a wrong digit
     * means the money goes to someone else and the only recovery is a bank
     * dispute. Saved once, reused, and re-saved deliberately.
     */
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('payout_bank_code', 16)->nullable()->after('phone');
            $table->string('payout_account_number', 20)->nullable()->after('payout_bank_code');
            $table->string('payout_account_name')->nullable()->after('payout_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['payout_bank_code', 'payout_account_number', 'payout_account_name']);
        });
    }
};
