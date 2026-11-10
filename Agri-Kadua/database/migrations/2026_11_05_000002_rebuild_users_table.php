<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Replaces Laravel's default users table with the Co-opSync users table
 * (Employee ID login, role and branch). It runs after the reference tables
 * so that the foreign keys to roles and branches can be created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('employee_code', 20)->unique();
            $table->string('full_name', 120);
            $table->string('job_title', 80)->nullable();
            $table->string('email', 150)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('password');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('branch_id');
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role_id');
            $table->foreign('role_id')->references('role_id')->on('roles');
            $table->foreign('branch_id')->references('branch_id')->on('branches');
        });

        Schema::create('login_events', function (Blueprint $table) {
            $table->bigIncrements('event_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('employee_code_entered', 20);
            $table->unsignedInteger('selected_role_id')->nullable();
            $table->unsignedInteger('terminal_id')->nullable();
            $table->boolean('guidelines_acknowledged')->default(false);
            $table->boolean('success');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('logged_in_at')->useCurrent();

            $table->index('logged_in_at');
            $table->foreign('user_id')->references('user_id')->on('users');
            $table->foreign('selected_role_id')->references('role_id')->on('roles');
            $table->foreign('terminal_id')->references('terminal_id')->on('terminals');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('users');
    }
};