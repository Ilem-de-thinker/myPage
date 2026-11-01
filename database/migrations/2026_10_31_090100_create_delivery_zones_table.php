<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_profile_id')->constrained('delivery_profiles')->cascadeOnDelete();
            $table->string('name', 80);
            // Optional grouping so a seller with twenty areas can still present
            // them by state instead of one long list.
            $table->string('state', 80)->nullable();
            // Zero means free delivery in this area; there is no separate flag
            // that could drift out of sync with the fee.
            $table->unsignedInteger('fee_kobo')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['delivery_profile_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
