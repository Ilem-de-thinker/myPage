<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable();
            $table->enum('direction', ['debit', 'credit']);
            $table->integer('amount_kobo');
            $table->integer('balance_after_kobo');
            $table->string('reason', 48);
            $table->string('counterpart_wallet_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger');
    }
};
