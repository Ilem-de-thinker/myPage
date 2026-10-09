<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_kobo');
            $table->decimal('price', 10, 2)->nullable()->after('price_kobo');
            $table->unsignedInteger('compare_at_kobo')->nullable();
            $table->string('sku')->nullable();
            $table->boolean('track_stock')->default(false);
            $table->integer('stock_qty')->default(0);
            $table->string('status', 16)->default('draft'); // draft | active | archived
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->unique(['store_page_id', 'slug']);
            $table->index(['store_page_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
