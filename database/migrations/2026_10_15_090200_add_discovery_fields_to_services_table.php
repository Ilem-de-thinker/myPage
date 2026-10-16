<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a service card needs to be worth a click.
     *
     * `duration_min` already answers "how long is the appointment". What it does
     * not answer is "how long until I have it in my hands", which for anything
     * that is not an appointment is the question a buyer actually has. Hence the
     * free-text turnaround label alongside it, and the billing unit that turns a
     * bare price into a price per something.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedInteger('view_count')->default(0)->after('position');

            // Badge overlay on the tier card. The seller's own choice to promote
            // one of their own listings, so it is not a platform ranking.
            $table->boolean('is_featured')->default(false)->after('view_count');

            // per_project | per_hour | per_month. A price with no unit is a
            // number a buyer has to email about.
            $table->string('billing_unit', 16)->default('per_project')->after('is_featured');

            // Delivery estimate in the seller's own words: "Same day", "5-7 days",
            // "2 weeks". Deliberately not an integer — real turnarounds are not.
            $table->string('turnaround_label', 60)->nullable()->after('billing_unit');

            // The tick-list on the card. A json list of strings rather than rows:
            // it is read as a list, never queried across services, and a row per
            // deliverable would make editing one service a four-table operation.
            $table->json('deliverables')->nullable()->after('turnaround_label');

            $table->index(
                ['store_page_id', 'is_active', 'price_kobo'],
                'services_catalog_price_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_catalog_price_index');

            $table->dropColumn([
                'view_count',
                'is_featured',
                'billing_unit',
                'turnaround_label',
                'deliverables',
            ]);
        });
    }
};
