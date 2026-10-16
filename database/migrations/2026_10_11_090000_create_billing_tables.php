<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedInteger('price_kobo')->default(0);
            $table->string('interval', 16)->default('monthly');

            // null on an infinite trial, so "14 days" is not a magic zero.
            $table->unsignedSmallInteger('trial_days')->nullable();

            // null means unlimited rather than zero.
            $table->unsignedInteger('max_products')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();

            // trialing | active | past_due | cancelled | expired
            $table->string('status', 16)->default('trialing');

            $table->unsignedInteger('price_kobo')->default(0);

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();

            // How it is paid for: gateway | manual | trial
            $table->string('payment_method', 16)->default('trial');

            $table->string('gateway_reference')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // One live subscription per seller, enforced rather than assumed.
            $table->unique('seller_id');
            $table->index(['status', 'current_period_ends_at']);
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 40);
            $table->boolean('enabled')->default(false);

            $table->unique(['seller_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
