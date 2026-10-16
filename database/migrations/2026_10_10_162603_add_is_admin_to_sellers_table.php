<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin is a seller with a flag, not a separate user table: the people
        // who moderate sellers are sellers themselves, and one identity means
        // one set of credentials to lose.
        Schema::table('sellers', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
