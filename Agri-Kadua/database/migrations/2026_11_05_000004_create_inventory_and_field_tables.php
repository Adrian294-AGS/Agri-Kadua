<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->increments('stock_id');
            $table->unsignedInteger('branch_id');
            $table->unsignedInteger('variety_id');
            $table->unsignedInteger('location_id');
            $table->decimal('quantity_kg', 14, 2)->default(0);
            $table->decimal('low_stock_threshold_kg', 14, 2)->default(1000);
            $table->timestamps();

            $table->unique(['variety_id', 'location_id']);
            $table->foreign('branch_id')->references('branch_id')->on('branches');
            $table->foreign('variety_id')->references('variety_id')->on('crop_varieties');
            $table->foreign('location_id')->references('location_id')->on('storage_locations');
        });

        Schema::create('milling_releases', function (Blueprint $table) {
            $table->increments('release_id');
            $table->string('milling_order_code', 20)->unique();
            $table->unsignedInteger('variety_id')->nullable();
            $table->decimal('quantity_kg', 14, 2);
            $table->string('destination', 120)->nullable();
            $table->enum('status', ['pending', 'authorised', 'released', 'cancelled'])->default('pending');
            $table->unsignedInteger('authorised_by')->nullable();
            $table->dateTime('authorised_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->foreign('variety_id')->references('variety_id')->on('crop_varieties');
            $table->foreign('authorised_by')->references('user_id')->on('users');
            $table->foreign('created_by')->references('user_id')->on('users');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->bigIncrements('movement_id');
            $table->unsignedInteger('stock_id');
            $table->enum('movement_type', ['receipt_in', 'release_out', 'adjustment', 'recount']);
            $table->decimal('quantity_kg', 14, 2);
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->unsignedInteger('release_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['stock_id', 'created_at']);
            $table->foreign('stock_id')->references('stock_id')->on('stock_items');
            $table->foreign('receipt_id')->references('receipt_id')->on('delivery_receipts');
            $table->foreign('release_id')->references('release_id')->on('milling_releases');
            $table->foreign('created_by')->references('user_id')->on('users');
        });

        Schema::create('input_stock', function (Blueprint $table) {
            $table->increments('input_stock_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('location_id');
            $table->decimal('qty_on_hand', 12, 2)->default(0);
            $table->decimal('qty_reserved', 12, 2)->default(0);

            $table->unique(['product_id', 'location_id']);
            $table->foreign('product_id')->references('product_id')->on('input_products');
            $table->foreign('location_id')->references('location_id')->on('storage_locations');
        });

        Schema::create('field_input_requests', function (Blueprint $table) {
            $table->increments('request_id');
            $table->string('request_code', 20)->unique();
            $table->unsignedInteger('member_id');
            $table->unsignedInteger('product_id');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subsidy_rate_pct', 5, 2);
            $table->decimal('total_value', 14, 2)->default(0);
            $table->decimal('subsidy_value', 14, 2)->default(0);
            $table->decimal('member_share', 14, 2)->default(0);
            $table->date('pickup_date');
            $table->unsignedInteger('reserved_location_id')->nullable();
            $table->enum('status', ['draft', 'submitted', 'reserved', 'picked_up', 'cancelled'])->default('draft');
            $table->unsignedInteger('submitted_by')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('member_id')->on('farmer_members');
            $table->foreign('product_id')->references('product_id')->on('input_products');
            $table->foreign('reserved_location_id')->references('location_id')->on('storage_locations');
            $table->foreign('submitted_by')->references('user_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_input_requests');
        Schema::dropIfExists('input_stock');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('milling_releases');
        Schema::dropIfExists('stock_items');
    }
};