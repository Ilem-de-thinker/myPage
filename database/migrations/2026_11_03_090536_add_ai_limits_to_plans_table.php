<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Mercho allowances to plans and give every existing plan a concrete
     * starting point. A null monthly allowance means unlimited by design, so
     * unknown legacy plans get the conservative fallback rather than being
     * silently upgraded to unlimited.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('ai_monthly_credits')->nullable()->after('max_staff');
            $table->unsignedInteger('ai_max_images_per_request')->default(4)->after('ai_monthly_credits');
        });

        $defaults = [
            'trial' => ['credits' => 50, 'images' => 4],
            'starter' => ['credits' => 250, 'images' => 8],
            'growth' => ['credits' => 1000, 'images' => 12],
            'pro' => ['credits' => 5000, 'images' => 20],
        ];

        foreach ($defaults as $slug => $values) {
            DB::table('plans')->where('slug', $slug)->update([
                'ai_monthly_credits' => $values['credits'],
                'ai_max_images_per_request' => $values['images'],
            ]);
        }

        DB::table('plans')
            ->whereNull('ai_monthly_credits')
            ->update(['ai_monthly_credits' => 25]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['ai_monthly_credits', 'ai_max_images_per_request']);
        });
    }
};
