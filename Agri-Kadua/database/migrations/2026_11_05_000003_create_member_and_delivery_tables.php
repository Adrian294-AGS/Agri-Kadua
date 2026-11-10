<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_records', function (Blueprint $table) {
            $table->bigIncrements('sync_id');
            $table->string('record_code', 20)->unique();
            $table->enum('source_module', [
                'farmer_members', 'warehouse_receiving', 'field_coordinator',
                'inventory', 'payments', 'milling_outbound', 'receipts',
            ]);
            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->decimal('size_kb', 8, 2)->default(0);
            $table->enum('status', ['saved', 'syncing', 'retry', 'failed'])->default('syncing');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->unsignedInteger('terminal_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('synced_at')->nullable();

            $table->index(['status', 'created_at']);
            $table->foreign('terminal_id')->references('terminal_id')->on('terminals');
            $table->foreign('created_by')->references('user_id')->on('users');
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->bigIncrements('run_id');
            $table->unsignedInteger('terminal_id')->nullable();
            $table->enum('trigger_type', ['auto', 'manual'])->default('auto');
            $table->unsignedInteger('triggered_by')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('records_sent')->default(0);
            $table->unsignedInteger('records_failed')->default(0);

            $table->foreign('terminal_id')->references('terminal_id')->on('terminals');
            $table->foreign('triggered_by')->references('user_id')->on('users');
        });

        Schema::create('farmer_members', function (Blueprint $table) {
            $table->increments('member_id');
            $table->string('member_code', 20)->unique();
            $table->string('full_name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('land_location', 255)->nullable();
            $table->decimal('farm_size_ha', 8, 2)->default(0);
            $table->string('soil_classification', 80)->nullable();
            $table->unsignedInteger('main_crop_id')->nullable();
            $table->unsignedInteger('primary_variety_id')->nullable();
            $table->decimal('expected_yield_kg', 12, 2)->nullable();
            $table->string('expected_yield_season', 30)->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->date('registered_on');
            $table->unsignedInteger('branch_id');
            $table->unsignedInteger('coordinator_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('full_name');
            $table->index('status');
            $table->foreign('main_crop_id')->references('crop_id')->on('crops');
            $table->foreign('primary_variety_id')->references('variety_id')->on('crop_varieties');
            $table->foreign('branch_id')->references('branch_id')->on('branches');
            $table->foreign('coordinator_id')->references('user_id')->on('users');
            $table->foreign('created_by')->references('user_id')->on('users');
        });

        Schema::create('scheduled_deliveries', function (Blueprint $table) {
            $table->increments('schedule_id');
            $table->unsignedInteger('member_id');
            $table->unsignedInteger('variety_id');
            $table->date('scheduled_date');
            $table->decimal('estimated_kg', 12, 2);
            $table->enum('status', ['scheduled', 'on_time', 'silo_pending', 'delayed', 'received', 'cancelled'])->default('scheduled');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['scheduled_date', 'status']);
            $table->foreign('member_id')->references('member_id')->on('farmer_members');
            $table->foreign('variety_id')->references('variety_id')->on('crop_varieties');
            $table->foreign('created_by')->references('user_id')->on('users');
        });

        Schema::create('schedule_changes', function (Blueprint $table) {
            $table->increments('change_id');
            $table->unsignedInteger('schedule_id');
            $table->date('old_date');
            $table->date('revised_date');
            $table->string('reason', 255);
            $table->unsignedInteger('changed_by');
            $table->dateTime('transmitted_at')->nullable();
            $table->unsignedBigInteger('sync_id')->nullable();
            $table->timestamps();

            $table->foreign('schedule_id')->references('schedule_id')->on('scheduled_deliveries');
            $table->foreign('changed_by')->references('user_id')->on('users');
            $table->foreign('sync_id')->references('sync_id')->on('sync_records');
        });

        Schema::create('delivery_receipts', function (Blueprint $table) {
            $table->bigIncrements('receipt_id');
            $table->string('receipt_code', 20)->unique();
            $table->unsignedInteger('schedule_id')->nullable();
            $table->unsignedInteger('member_id');
            $table->unsignedInteger('variety_id');
            $table->unsignedInteger('branch_id');
            $table->unsignedInteger('terminal_id')->nullable();
            $table->date('delivery_date');
            $table->decimal('gross_weight_kg', 12, 2);
            $table->decimal('moisture_pct', 5, 2)->default(0);
            $table->decimal('impurities_pct', 5, 2)->default(0);
            $table->decimal('moisture_limit_pct', 5, 2)->default(12.00);
            $table->decimal('impurity_limit_pct', 5, 2)->default(1.00);
            $table->decimal('deduction_pct', 5, 2)->default(0);
            $table->decimal('deduction_kg', 12, 2)->default(0);
            $table->decimal('rejected_kg', 12, 2)->default(0);
            $table->decimal('net_weight_kg', 12, 2)->default(0);
            $table->enum('status', ['draft', 'completed', 'stored', 'voided'])->default('draft');
            $table->enum('verification_status', ['unverified', 'verified', 're_weighed'])->default('unverified');
            $table->unsignedInteger('location_id')->nullable();
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('received_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'delivery_date']);
            $table->index(['status', 'verification_status']);
            $table->index('delivery_date');
            $table->foreign('schedule_id')->references('schedule_id')->on('scheduled_deliveries');
            $table->foreign('member_id')->references('member_id')->on('farmer_members');
            $table->foreign('variety_id')->references('variety_id')->on('crop_varieties');
            $table->foreign('branch_id')->references('branch_id')->on('branches');
            $table->foreign('terminal_id')->references('terminal_id')->on('terminals');
            $table->foreign('location_id')->references('location_id')->on('storage_locations');
            $table->foreign('received_by')->references('user_id')->on('users');
            $table->foreign('approved_by')->references('user_id')->on('users');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('payment_id');
            $table->string('payment_code', 20)->unique();
            $table->unsignedInteger('member_id');
            $table->unsignedBigInteger('receipt_id')->unique();
            $table->unsignedInteger('variety_id');
            $table->decimal('net_weight_kg', 12, 2);
            $table->decimal('base_rate_per_kg', 10, 2);
            $table->decimal('gross_value', 14, 2)->default(0);
            $table->decimal('input_advance', 14, 2)->default(0);
            $table->decimal('service_fee', 14, 2)->default(0);
            $table->decimal('standard_deductions', 14, 2)->default(0);
            $table->decimal('final_amount', 14, 2)->default(0);
            $table->unsignedInteger('channel_id')->nullable();
            $table->string('bank_reference', 60)->nullable();
            $table->enum('status', ['pending', 'ready_to_pay', 'paid', 'failed', 'cancelled'])->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index('status');
            $table->index('bank_reference');
            $table->foreign('member_id')->references('member_id')->on('farmer_members');
            $table->foreign('receipt_id')->references('receipt_id')->on('delivery_receipts');
            $table->foreign('variety_id')->references('variety_id')->on('crop_varieties');
            $table->foreign('channel_id')->references('channel_id')->on('payment_channels');
            $table->foreign('recorded_by')->references('user_id')->on('users');
        });

        Schema::create('receipt_slips', function (Blueprint $table) {
            $table->bigIncrements('slip_id');
            $table->string('slip_code', 30)->unique();
            $table->unsignedBigInteger('receipt_id');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('barcode_value', 40);
            $table->unsignedInteger('issued_by');
            $table->timestamp('issued_at')->useCurrent();
            $table->boolean('member_signed')->default(false);
            $table->unsignedInteger('print_count')->default(0);
            $table->dateTime('last_printed_at')->nullable();
            $table->string('pdf_path', 255)->nullable();

            $table->index('receipt_id');
            $table->foreign('receipt_id')->references('receipt_id')->on('delivery_receipts');
            $table->foreign('payment_id')->references('payment_id')->on('payments');
            $table->foreign('issued_by')->references('user_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_slips');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('delivery_receipts');
        Schema::dropIfExists('schedule_changes');
        Schema::dropIfExists('scheduled_deliveries');
        Schema::dropIfExists('farmer_members');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('sync_records');
    }
};