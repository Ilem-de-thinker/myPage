<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();

            // pending | approved | rejected
            $table->string('status', 16)->default('pending');

            // What proves the business is real: a CAC certificate or a national ID.
            $table->string('business_name');
            $table->string('document_type', 32)->default('cac');
            $table->string('document_path');

            $table->text('note')->nullable();

            $table->foreignId('reviewed_by_seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            // One live application per page: a rejected one can be resubmitted,
            // but two pending ones would be nonsense.
            $table->index(['status', 'submitted_at']);
            $table->index(['store_page_id', 'status']);
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('badge_applications');
    }
};
