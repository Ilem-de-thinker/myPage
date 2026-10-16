<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | The platform half of a feature flag.
         |
         | The per-seller rows in feature_flags answer "is this on for that
         | seller". They cannot answer "is this on for anyone", because a platform
         | decision is not a seller decision: today that answer lives in config,
         | so turning a feature off for the whole platform means a deploy, and
         | the switch nobody can reach in an incident is not a switch.
         |
         | One row per feature, and no row at all means "follow config". An
         | absent row is therefore the normal state, not a missing one.
         */
        Schema::create('platform_feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('feature', 40)->unique();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_feature_flags');
    }
};
