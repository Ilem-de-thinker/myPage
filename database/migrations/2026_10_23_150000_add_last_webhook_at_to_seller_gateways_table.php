<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_gateways', function (Blueprint $table): void {
            $table->timestamp('last_webhook_at')->nullable()->after('last_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('seller_gateways', function (Blueprint $table): void {
            $table->dropColumn('last_webhook_at');
        });
    }
};
