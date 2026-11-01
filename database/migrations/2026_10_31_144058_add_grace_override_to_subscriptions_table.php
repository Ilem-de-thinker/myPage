<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            /*
             * An operator can extend one merchant's grace without touching the
             * platform default. It only ever adds time; the resolver combines it
             * with grace_ends_at and never shortens access.
             */
            $table->timestamp('grace_override_until')->nullable()->after('grace_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('grace_override_until');
        });
    }
};
