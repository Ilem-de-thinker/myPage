<?php

use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // The pre-launch table stored one line item per order on the order itself.
        // Orders now own order_items, so the table is rebuilt to match.
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->enum('source', array_column(OrderSource::cases(), 'value'));
            $table->enum('status', array_column(OrderStatus::cases(), 'value'))->default(OrderStatus::Pending->value);
            $table->integer('total_kobo')->default(0);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'source', 'status']);
            $table->index(['store_page_id', 'created_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
