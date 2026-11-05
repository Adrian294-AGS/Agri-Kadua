<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->increments('branch_id');
            $table->string('branch_code', 20)->unique();
            $table->string('branch_name', 100);
            $table->string('region', 60)->nullable();
            $table->string('address', 255)->nullable();
            $table->char('currency_code', 3)->default('PHP');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('terminals', function (Blueprint $table) {
            $table->increments('terminal_id');
            $table->unsignedInteger('branch_id');
            $table->string('device_code', 20)->unique();
            $table->string('description', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('branch_id')->references('branch_id')->on('branches');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('role_id');
            $table->string('role_code', 30)->unique();
            $table->string('role_name', 60);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('crops', function (Blueprint $table) {
            $table->increments('crop_id');
            $table->string('crop_name', 60)->unique();
            $table->timestamps();
        });

        Schema::create('crop_varieties', function (Blueprint $table) {
            $table->increments('variety_id');
            $table->unsignedInteger('crop_id');
            $table->string('variety_name', 100);
            $table->decimal('moisture_limit_pct', 5, 2)->default(12.00);
            $table->decimal('impurity_limit_pct', 5, 2)->default(1.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['crop_id', 'variety_name']);
            $table->foreign('crop_id')->references('crop_id')->on('crops');
        });

        Schema::create('crop_rates', function (Blueprint $table) {
            $table->increments('rate_id');
            $table->unsignedInteger('variety_id');
            $table->decimal('rate_per_kg', 10, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->index(['variety_id', 'effective_from']);
            $table->foreign('variety_id')->references('variety_id')->on('crop_varieties');
        });

        Schema::create('storage_locations', function (Blueprint $table) {
            $table->increments('location_id');
            $table->unsignedInteger('branch_id');
            $table->string('location_name', 80);
            $table->enum('location_type', ['silo', 'dry_bay', 'cold_storage', 'receiving_bay', 'other'])->default('dry_bay');
            $table->decimal('capacity_kg', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'location_name']);
            $table->foreign('branch_id')->references('branch_id')->on('branches');
        });

        Schema::create('input_categories', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('category_name', 60)->unique();
            $table->timestamps();
        });

        Schema::create('input_products', function (Blueprint $table) {
            $table->increments('product_id');
            $table->unsignedInteger('category_id');
            $table->string('product_name', 100);
            $table->string('unit_label', 30);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subsidy_rate_pct', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('category_id')->references('category_id')->on('input_categories');
        });

        Schema::create('payment_channels', function (Blueprint $table) {
            $table->increments('channel_id');
            $table->string('channel_name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('report_templates', function (Blueprint $table) {
            $table->increments('template_id');
            $table->string('template_name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('sync_module_status', function (Blueprint $table) {
            $table->increments('module_id');
            $table->string('module_name', 60)->unique();
            $table->enum('status', ['online', 'degraded', 'offline'])->default('online');
            $table->timestamp('last_checked_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_module_status');
        Schema::dropIfExists('report_templates');
        Schema::dropIfExists('payment_channels');
        Schema::dropIfExists('input_products');
        Schema::dropIfExists('input_categories');
        Schema::dropIfExists('storage_locations');
        Schema::dropIfExists('crop_rates');
        Schema::dropIfExists('crop_varieties');
        Schema::dropIfExists('crops');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('branches');
    }
};