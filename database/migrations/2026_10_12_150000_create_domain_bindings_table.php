<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A seller's own domain, pointed at their storefront.
 *
 * A binding is unique on `host` rather than on the pair, because a domain can
 * only ever resolve to one seller. That is also the constraint that stops two
 * sellers claiming the same hostname: the second insert fails at the database
 * rather than at some later point when traffic is already being misrouted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->nullable()->constrained()->nullOnDelete();

            // Normalised: lowercase, no scheme, no trailing dot, no port. This is
            // what the request host is matched against, so it has to be canonical
            // at rest or two spellings of one domain would be two rows.
            $table->string('host')->unique();
            $table->string('verification_token', 32)->nullable();

            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ssl_active_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // The verification job and the "check now" button both scan for
            // unverified rows that are due another attempt.
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_bindings');
    }
};
