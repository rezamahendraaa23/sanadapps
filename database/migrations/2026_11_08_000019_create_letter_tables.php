<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Surat keluar (permintaan pemilik 8 November 2026): kop dan footer bisa diunggah, template dengan
 * isian otomatis, nomor surat berurutan per tahun yang tersimpan di arsip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letterheads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('header_path')->nullable();
            $table->string('footer_path')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestampsTz(6);
        });
        DB::statement('CREATE UNIQUE INDEX letterheads_one_default ON letterheads (is_default) WHERE is_default');

        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code', 20);
            $table->string('subject');
            $table->text('body');
            $table->boolean('needs_student')->default(false);
            // Isian tambahan per template, misalnya tanggal acara atau tempat: [{key, label}].
            $table->jsonb('fields')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('letter_no')->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->foreignId('template_id')->nullable()->constrained('letter_templates')->nullOnDelete();
            $table->foreignId('letterhead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->jsonb('data')->default('{}');
            $table->date('issued_on');
            $table->string('signer_name');
            $table->string('signer_title');
            $table->enum('status', ['issued', 'void'])->default('issued');
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz(6);
            $table->unique(['year', 'sequence']);
            $table->index(['student_id', 'issued_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_templates');
        Schema::dropIfExists('letterheads');
    }
};
