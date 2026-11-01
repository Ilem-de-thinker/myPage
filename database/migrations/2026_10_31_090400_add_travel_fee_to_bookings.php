<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The travel fee for a home service, snapshot at booking time.
     *
     * It is kept on its own column rather than folded into price_kobo: the
     * deposit logic reads that column, and folding the fee in would charge it
     * twice. amountDueKobo() adds it back on top.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedInteger('travel_fee_kobo')->default(0)->after('deposit_kobo');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('travel_fee_kobo');
        });
    }
};
