<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title');
            $table->string('summary', 500);
            $table->json('sections');
            $table->string('version', 32);
            $table->date('effective_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_draft')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
