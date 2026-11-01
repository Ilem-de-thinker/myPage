<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            // Null means not an admin. is_admin stays as the access flag while
            // older rows and code are migrated to roles.
            $table->string('admin_role', 32)->nullable()->index()->after('is_admin');
        });

        // Everyone who already had the flag is trusted with the full panel.
        DB::table('sellers')
            ->where('is_admin', true)
            ->update(['admin_role' => 'core_admin']);
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropIndex(['admin_role']);
            $table->dropColumn('admin_role');
        });
    }
};
