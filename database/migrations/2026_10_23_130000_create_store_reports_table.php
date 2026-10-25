<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->text('body');
            $table->string('reporter_name')->nullable();
            $table->string('reporter_contact')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('resolved_by_seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['store_page_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_reports');
    }
};
