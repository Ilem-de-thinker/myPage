<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();

            // How long the appointment runs, and the gap the seller needs either
            // side of it to reset the room before the next one.
            $table->unsignedSmallInteger('duration_min')->default(60);
            $table->unsignedSmallInteger('buffer_min')->default(0);

            $table->unsignedInteger('price_kobo')->default(0);

            // none | fixed | percent. deposit_value means kobo for fixed and
            // whole percent for percent.
            $table->string('deposit_type', 12)->default('none');
            $table->unsignedInteger('deposit_value')->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['store_page_id', 'slug']);
            $table->index(['store_page_id', 'is_active', 'position']);
        });

        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['store_page_id', 'is_active']);
        });

        Schema::create('service_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();

            $table->unique(['service_id', 'staff_member_id']);
        });

        // Times are wall clock in the store's timezone; weekday is ISO 1 (Monday)
        // to 7 (Sunday). A null staff_member_id means the whole business is open.
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');

            $table->index(['store_page_id', 'weekday']);
            $table->index(['staff_member_id', 'weekday']);
        });

        Schema::create('time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['store_page_id', 'starts_at']);
            $table->index(['staff_member_id', 'starts_at']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Payment lives in the polymorphic transactions table, which already
            // points back at this row through payable_type/payable_id.
            $table->string('reference', 16)->unique();

            // Stored in UTC; every store page carries its own timezone in settings.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('status', 20)->default('pending_payment');
            $table->unsignedInteger('price_kobo')->default(0);
            $table->unsignedInteger('deposit_kobo')->default(0);

            // A pending booking holds its slot until this moment.
            $table->timestamp('expires_at')->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 16)->default('web');
            $table->timestamps();

            $table->index(['store_page_id', 'starts_at']);
            $table->index(['staff_member_id', 'starts_at']);
            $table->index(['seller_id', 'status']);
        });

        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('by_seller_id')->nullable()->constrained('sellers')->nullOnDelete();

            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('time_offs');
        Schema::dropIfExists('working_hours');
        Schema::dropIfExists('service_staff');
        Schema::dropIfExists('staff_members');
        Schema::dropIfExists('services');
    }
};
