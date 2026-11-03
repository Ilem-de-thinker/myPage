<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NDPA requests are answered within a month, so the deadline is stored, not
     * recalculated from whatever a screen happens to display. seller_id records
     * the account the email belongs to when the request came from a signed-in
     * seller, and resolution_note holds the manual handling an admin writes.
     */
    public function up(): void
    {
        Schema::table('data_subject_requests', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('id')->constrained('sellers')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('status');
            $table->timestamp('due_at')->nullable()->after('requested_at');
            $table->text('resolution_note')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('data_subject_requests', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn(['seller_id', 'verified_at', 'due_at', 'resolution_note']);
        });
    }
};
