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
        Schema::create('carousel_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();
            $table->string('headline', 160)->nullable();
            $table->string('subtext', 300)->nullable();
            $table->string('button_label', 80)->nullable();
            $table->string('button_url', 500)->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->index(['store_page_id', 'is_active', 'position']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carousel_slides');
    }
};
