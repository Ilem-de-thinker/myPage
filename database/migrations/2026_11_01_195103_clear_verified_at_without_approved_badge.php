<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The badge application is the evidence; verified_at is the public trust
     * flag. Approval is the only thing that should set the flag, so a page
     * stamped without an approved application is inconsistent data. The demo
     * seeder used to create exactly that state for a pending merchant, which
     * made the directory and the storefront disagree about the badge.
     */
    public function up(): void
    {
        DB::table('store_pages')
            ->whereNotNull('verified_at')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('badge_applications')
                    ->whereColumn('badge_applications.store_page_id', 'store_pages.id')
                    ->where('badge_applications.status', 'approved');
            })
            ->update(['verified_at' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
