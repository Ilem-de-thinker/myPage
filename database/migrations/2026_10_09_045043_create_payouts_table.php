<?php

use App\Domains\Payments\Enums\PayoutStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->integer('amount_kobo');
            $table->enum('status', array_column(PayoutStatus::cases(), 'value'))->default(PayoutStatus::Pending->value);
            $table->string('bank_code', 16);
            $table->string('account_number', 32);
            $table->string('account_name');
            $table->string('gateway', 32);
            $table->string('gateway_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
