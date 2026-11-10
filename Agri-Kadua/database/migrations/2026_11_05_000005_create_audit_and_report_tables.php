<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('audit_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('actor_label', 80)->nullable();
            $table->string('module', 40);
            $table->string('record_ref', 40);
            $table->string('action', 40);
            $table->string('reason', 255)->nullable();
            $table->text('before_value')->nullable();
            $table->text('after_value')->nullable();
            $table->unsignedInteger('terminal_id')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->char('row_hash', 64)->nullable();

            $table->index('changed_at');
            $table->index('record_ref');
            $table->index(['module', 'changed_at']);
            $table->index(['user_id', 'changed_at']);
            $table->foreign('user_id')->references('user_id')->on('users');
            $table->foreign('terminal_id')->references('terminal_id')->on('terminals');
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->bigIncrements('export_id');
            $table->unsignedInteger('template_id');
            $table->enum('export_format', ['csv_excel', 'pdf'])->default('csv_excel');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('generated_by');
            $table->unsignedInteger('terminal_id')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->char('signature_hash', 64)->nullable();
            $table->timestamp('generated_at')->useCurrent();

            $table->foreign('template_id')->references('template_id')->on('report_templates');
            $table->foreign('generated_by')->references('user_id')->on('users');
            $table->foreign('terminal_id')->references('terminal_id')->on('terminals');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('audit_logs');
    }
};