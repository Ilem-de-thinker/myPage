<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->foreignId('store_page_id')->nullable()->constrained('store_pages')->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('completed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('ai_operations', function (Blueprint $table): void {
            $table->foreignId('ai_task_id')->nullable()->after('store_page_id')->constrained('ai_tasks')->nullOnDelete();
            $table->index('ai_task_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_operations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ai_task_id');
        });
        Schema::dropIfExists('ai_tasks');
    }
};
