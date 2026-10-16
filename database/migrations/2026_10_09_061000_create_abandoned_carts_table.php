<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();

            // The recovery link the buyer gets in the reminder email.
            $table->string('reference')->unique();

            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();

            // Snapshot of what the cart held and what it came to, so the seller
            // can chase it even after the products change.
            $table->json('cart');
            $table->unsignedInteger('total_kobo')->default(0);

            $table->string('status', 16)->default('open');

            // Deleting the order must not resurrect a cart the buyer already paid for.
            $table->foreignId('recovered_order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['store_page_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts');
    }
};
