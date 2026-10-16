<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // The PLN_... code Paystack bills against. Nullable because a plan can
            // exist here before anyone has created it on the gateway, and the
            // billing screen has to be able to say so rather than crash.
            $table->string('gateway_plan_code', 64)->nullable()->after('slug');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('gateway_subscription_code', 64)->nullable();
            $table->string('gateway_customer_code', 64)->nullable();
            $table->string('email_token', 64)->nullable();

            // Paystack's own next debit date, which is the truth about when the
            // next charge lands. Kept beside, not instead of, the local period:
            // one is the gateway's promise, the other is what we have delivered.
            $table->timestamp('next_payment_date')->nullable();

            // Webhook lookups arrive by code on every cycle, so this is indexed
            // rather than scanned.
            $table->index('gateway_subscription_code');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['gateway_subscription_code']);
            $table->dropColumn([
                'gateway_subscription_code',
                'gateway_customer_code',
                'email_token',
                'next_payment_date',
            ]);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('gateway_plan_code');
        });
    }
};
