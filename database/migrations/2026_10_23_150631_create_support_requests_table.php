<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->string('category', 40);
            $table->string('subject', 180);
            $table->text('body');
            $table->string('requester_name', 120)->nullable();
            $table->string('requester_contact', 190)->nullable();
            $table->string('status', 20)->default('open');
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'status']);
            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
