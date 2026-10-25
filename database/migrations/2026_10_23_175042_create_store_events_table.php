<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_page_id')->constrained('store_pages')->cascadeOnDelete();
            $table->string('event', 40);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('visitor_hash', 64)->nullable();
            $table->string('source', 80)->nullable();
            $table->string('device', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['store_page_id', 'event', 'created_at']);
            $table->index(['store_page_id', 'product_id', 'created_at']);
            $table->index(['store_page_id', 'service_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_events');
    }
};
