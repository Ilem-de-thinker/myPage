<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a customer reaches a business, and where else to find it.
 *
 * `address` already existed but nothing wrote to it, so a seller could not say
 * where they were. `social_links` is JSON keyed by platform rather than a column
 * per network: new platforms should not mean a migration, and a seller's
 * Instagram is not the same shape as their YouTube channel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_pages', function (Blueprint $table): void {
            $table->string('public_email')->nullable()->after('whatsapp_number');
            $table->json('social_links')->nullable()->after('public_email');
        });
    }

    public function down(): void
    {
        Schema::table('store_pages', function (Blueprint $table): void {
            $table->dropColumn(['public_email', 'social_links']);
        });
    }
};
