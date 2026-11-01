<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // A snapshot, not a reference: editing a zone later must never
            // rewrite the terms an order was placed under.
            $table->string('delivery_mode', 20)->default('delivery')->after('customer_note');
            $table->unsignedInteger('delivery_fee_kobo')->default(0)->after('delivery_mode');
            $table->string('delivery_zone', 80)->nullable()->after('delivery_fee_kobo');
            $table->string('delivery_address', 255)->nullable()->after('delivery_zone');
            $table->string('delivery_city', 80)->nullable()->after('delivery_address');
            $table->string('delivery_state', 80)->nullable()->after('delivery_city');
            $table->decimal('delivery_latitude', 10, 7)->nullable()->after('delivery_state');
            $table->decimal('delivery_longitude', 10, 7)->nullable()->after('delivery_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_mode',
                'delivery_fee_kobo',
                'delivery_zone',
                'delivery_address',
                'delivery_city',
                'delivery_state',
                'delivery_latitude',
                'delivery_longitude',
            ]);
        });
    }
};
