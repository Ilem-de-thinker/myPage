<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table): void {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('legal_registration_type', 32)->nullable()->after('legal_name');
            $table->string('legal_registration_number', 120)->nullable()->after('legal_registration_type');
            $table->timestamp('kyc_verified_at')->nullable()->after('legal_registration_number');
            $table->date('kyc_document_expires_at')->nullable()->after('kyc_verified_at');
        });

        Schema::table('badge_applications', function (Blueprint $table): void {
            $table->string('registered_legal_name')->nullable()->after('business_name');
            $table->string('registration_number', 120)->nullable()->after('document_type');
            $table->date('document_expires_at')->nullable()->after('document_path');
            $table->timestamp('retention_until')->nullable()->after('submitted_at');
            $table->timestamp('document_deleted_at')->nullable()->after('retention_until');
        });
    }

    public function down(): void
    {
        Schema::table('badge_applications', function (Blueprint $table): void {
            $table->dropColumn([
                'registered_legal_name',
                'registration_number',
                'document_expires_at',
                'retention_until',
                'document_deleted_at',
            ]);
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_name',
                'legal_registration_type',
                'legal_registration_number',
                'kyc_verified_at',
                'kyc_document_expires_at',
            ]);
        });
    }
};
