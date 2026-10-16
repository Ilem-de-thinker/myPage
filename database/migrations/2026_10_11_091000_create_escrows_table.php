<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Escrow parks a buyer's money in a pool instead of the seller's wallet until
     * the buyer is satisfied. The pool is a ledger wallet, so the same double-entry
     * rules cover it and the books still net to zero.
     */
    public function up(): void
    {
        Schema::create('escrows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();

            // One escrow per order or booking.
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');

            $table->unsignedInteger('amount_kobo');
            $table->unsignedInteger('commission_kobo')->default(0);

            // held | released | refunded | frozen
            $table->string('status', 16)->default('held');

            $table->timestamp('held_at')->nullable();

            // When the buyer has had long enough, it releases without them.
            $table->timestamp('auto_releases_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->foreignId('disputed_by_seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->text('dispute_reason')->nullable();

            $table->timestamps();

            $table->unique(['payable_type', 'payable_id']);
            $table->index(['status', 'auto_releases_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrows');
    }
};
