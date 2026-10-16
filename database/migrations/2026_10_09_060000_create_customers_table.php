<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();

            // Buyers are identified per seller, never per page: someone who orders
            // from two of a seller's pages is still one customer.
            $table->string('identity_key');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();

            // Broadcasts are opt-in and default off, so a customer book can never
            // be treated as marketing consent.
            $table->boolean('opted_in')->default(false);

            // Denormalised so the customer list renders without an aggregate per
            // row. lifetime_value_kobo only counts orders and bookings that were
            // actually paid for.
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('booking_count')->default(0);
            $table->unsignedBigInteger('lifetime_value_kobo')->default(0);

            // Set by any purchase, whichever theme it came through, so the list
            // can sort by "last heard from" without caring which inbox it was.
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'identity_key']);
            $table->index(['seller_id', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
