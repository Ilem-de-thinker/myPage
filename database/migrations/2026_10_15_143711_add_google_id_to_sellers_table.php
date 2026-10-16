<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a seller sign in with Google instead of a password.
 *
 * `google_id` rather than the email, deliberately. Google's `sub` is the stable
 * per-account identifier and the email is not: a seller can change the address
 * on their Google account, and one day two accounts will share one address. If
 * the email were the key, either a rename would silently orphan the seller or a
 * recycled address would hand somebody else their shop.
 *
 * Nullable because most sellers have never seen this screen. It is an extra way
 * in, not a replacement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table): void {
            $table->string('google_id')->nullable()->after('email');
        });

        // Unique, so one Google account cannot end up attached to two sellers.
        // A plain unique index rather than unique() so several NULLs are allowed:
        // every seller without a Google account has no google_id at all, and
        // SQLite and MySQL both treat repeated NULLs as distinct.
        Schema::table('sellers', function (Blueprint $table): void {
            $table->unique('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};
