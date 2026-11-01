<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Null inherits the store's delivery profile; a value overrides it
            // for this item only.
            $table->string('delivery_mode', 20)->nullable()->after('status');
            $table->unsignedInteger('delivery_fee_kobo')->nullable()->after('delivery_mode');
        });

        Schema::table('services', function (Blueprint $table): void {
            // Null inherits the store's profile: at your place, home service,
            // or remote. A service has no delivery fee, only a travel fee.
            $table->string('service_mode', 20)->nullable()->after('is_active');
            $table->unsignedInteger('travel_fee_kobo')->nullable()->after('service_mode');
            $table->unsignedSmallInteger('travel_radius_km')->nullable()->after('travel_fee_kobo');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['delivery_mode', 'delivery_fee_kobo']);
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['service_mode', 'travel_fee_kobo', 'travel_radius_km']);
        });
    }
};
