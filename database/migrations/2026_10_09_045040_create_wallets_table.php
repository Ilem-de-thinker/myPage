<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['seller', 'gateway_clearing', 'commission', 'payouts', 'escrow'])->default('seller');
            $table->foreignId('seller_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('balance_kobo')->default(0);
            $table->integer('pending_kobo')->default(0);
            $table->timestamps();

            // One seller wallet per seller; platform accounts have a null seller_id
            // and are singletons enforced by uniqueWalletAccount().
            $table->unique(['kind', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
