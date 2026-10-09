<?php

use App\Domains\Payments\Enums\WalletKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', array_column(WalletKind::cases(), 'value'))->default(WalletKind::Seller->value);
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
