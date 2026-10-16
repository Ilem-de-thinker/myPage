<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Stamped per lead time, so the 24h and 2h nudges are tracked
            // separately and neither can send twice.
            $table->timestamp('reminded_24h_at')->nullable()->after('expires_at');
            $table->timestamp('reminded_2h_at')->nullable()->after('reminded_24h_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['reminded_24h_at', 'reminded_2h_at']);
        });
    }
};
