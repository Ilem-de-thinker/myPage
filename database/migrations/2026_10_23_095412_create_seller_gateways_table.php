<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gateway credentials a seller uses to take their own money.
     *
     * Buyer payments settle in the seller's gateway account, so the platform
     * holds no buyer money. That makes these keys the most sensitive rows in the
     * application: one per seller and gateway, encrypted at rest, and never
     * mass-assignable.
     */
    public function up(): void
    {
        Schema::create('seller_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32);
            // 'test' or 'live'. Derived from the secret key's own prefix, never
            // chosen by the request, so the two cannot disagree.
            $table->string('environment', 16);
            $table->string('public_key')->nullable();
            // text, not string: ciphertext is longer than the key it protects.
            $table->text('secret_key');
            $table->string('secret_key_last4', 4);
            // Flutterwave signs webhooks with its own hash; Paystack reuses the
            // secret key. Nullable because most sellers only need the one.
            $table->text('webhook_secret')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_gateways');
    }
};
