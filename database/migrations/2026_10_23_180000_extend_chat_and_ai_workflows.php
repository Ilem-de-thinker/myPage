<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->text('ai_summary')->nullable()->after('status');
            $table->timestamp('ai_summary_updated_at')->nullable()->after('ai_summary');
            $table->index(['store_page_id', 'status', 'last_message_at']);
        });

        Schema::table('ai_drafts', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('ai_drafts')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('task');
            $table->foreignId('reviewed_by')->nullable()->after('rejected_at')->constrained('sellers')->nullOnDelete();
            $table->index(['store_page_id', 'task', 'parent_id', 'version']);
        });

        Schema::create('ai_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('embeddable');
            $table->string('provider', 80);
            $table->string('model', 120);
            $table->string('content_hash', 64);
            $table->text('content');
            $table->json('vector');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['embeddable_type', 'embeddable_id', 'content_hash']);
            $table->index(['seller_id', 'provider', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_embeddings');

        Schema::table('ai_drafts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['version']);
        });

        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->dropIndex(['store_page_id', 'status', 'last_message_at']);
            $table->dropColumn(['ai_summary', 'ai_summary_updated_at']);
        });
    }
};
