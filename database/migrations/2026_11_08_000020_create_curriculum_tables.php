<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kurikulum berbasis fase (permintaan pemilik 8 November 2026): misalnya tiga bulan masa
 * perkenalan, ujian, sertifikat dan rapor fase, lalu naik ke fase berikutnya. Fase menempel pada
 * kelas (rombongan) dengan tanggal mulai dan selesai sendiri, jadi tidak terikat dua semester.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('curriculum_phases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('curriculum_id')->constrained('curricula')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('name');
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_weeks');
            $table->decimal('min_score', 5, 2)->default(70);
            $table->boolean('has_certificate')->default(true);
            $table->string('certificate_title')->nullable();
            $table->timestampsTz(6);
            $table->unique(['curriculum_id', 'sort_order']);
        });
        DB::statement('ALTER TABLE curriculum_phases ADD CONSTRAINT curriculum_phases_values CHECK (duration_weeks > 0 AND min_score >= 0 AND min_score <= 100)');

        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('phase_id')->nullable()->constrained('curriculum_phases')->nullOnDelete();
            $table->date('phase_started_on')->nullable();
            $table->date('phase_ends_on')->nullable();
        });

        Schema::create('phase_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('phase_id')->constrained('curriculum_phases')->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->date('started_on');
            $table->date('ended_on');
            // Nilai per mapel saat dievaluasi: {subject_id: {name, score}}.
            $table->jsonb('scores')->default('{}');
            $table->decimal('final_score', 5, 2)->nullable();
            $table->enum('status', ['pending', 'passed', 'failed'])->default('pending');
            $table->text('note')->nullable();
            $table->string('certificate_no')->nullable()->unique();
            $table->timestampTz('certified_at', 6)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at', 6)->nullable();
            $table->foreignId('promoted_to_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->timestampTz('promoted_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->unique(['student_id', 'phase_id', 'started_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_results');
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('phase_id');
            $table->dropColumn(['phase_started_on', 'phase_ends_on']);
        });
        Schema::dropIfExists('curriculum_phases');
        Schema::dropIfExists('curricula');
    }
};
