<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_page_id')->unique()->constrained('store_pages')->cascadeOnDelete();
            $table->boolean('offers_delivery')->default(true);
            $table->boolean('offers_pickup')->default(false);
            $table->boolean('offers_home_service')->default(false);
            // The basket subtotal that earns free delivery, in kobo.
            $table->unsignedInteger('free_over_kobo')->nullable();
            $table->unsignedSmallInteger('radius_km')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_profiles');
    }
};
