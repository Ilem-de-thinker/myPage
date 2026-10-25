<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('support_requests')
            ->orderBy('id')
            ->eachById(function (object $supportRequest): void {
                if (DB::table('support_messages')->where('support_request_id', $supportRequest->id)->exists()) {
                    return;
                }

                DB::table('support_messages')->insert([
                    'support_request_id' => $supportRequest->id,
                    'sender_type' => $supportRequest->source === 'seller' ? 'seller' : 'buyer',
                    'sender_id' => $supportRequest->source === 'seller' ? $supportRequest->seller_id : null,
                    'body' => $supportRequest->body,
                    'is_internal' => false,
                    'created_at' => $supportRequest->created_at,
                    'updated_at' => $supportRequest->created_at,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
