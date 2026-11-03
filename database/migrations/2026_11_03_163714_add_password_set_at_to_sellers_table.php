<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Google-only sellers are issued a random password nobody knows so the
     * NOT NULL column is satisfied. This timestamp is what tells "a password
     * they chose" apart from "a placeholder we generated", so the account
     * screen can ask for the current password only when one really exists.
     */
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};
