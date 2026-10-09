<?php

use App\Domains\Payments\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable');
            $table->string('reference')->unique();
            $table->string('gateway', 32);
            $table->integer('amount_kobo');
            $table->integer('fee_kobo')->default(0);
            $table->integer('commission_kobo')->default(0);
            $table->enum('status', array_column(TransactionStatus::cases(), 'value'))->default(TransactionStatus::Pending->value);
            $table->timestamp('paid_at')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
