<?php

use App\Domains\Orders\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('by_seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->enum('from_status', array_column(OrderStatus::cases(), 'value'))->nullable();
            $table->enum('to_status', array_column(OrderStatus::cases(), 'value'));
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
