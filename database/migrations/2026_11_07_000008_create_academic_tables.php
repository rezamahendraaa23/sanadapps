<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['diniyah', 'umum', 'tahfidz'])->default('umum');
            $table->unsignedSmallInteger('kkm')->default(70);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->timestampsTz(6);
            $table->unique(['subject_id', 'classroom_id', 'academic_year_id']);
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room')->nullable();
            $table->enum('activity_type', ['kbm', 'sholat', 'halaqah', 'makan', 'tidur', 'kegiatan'])->default('kbm');
            $table->string('title')->nullable();
            $table->timestampsTz(6);
            $table->index(['term_id', 'day_of_week']);
        });
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_values CHECK (day_of_week BETWEEN 1 AND 7 AND end_time > start_time)');

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->date('session_date');
            $table->enum('kind', ['kelas', 'asrama', 'sholat', 'kegiatan'])->default('kelas');
            $table->string('title');
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dormitory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('source', ['manual', 'card', 'qr'])->default('manual');
            $table->jsonb('allowed_modes')->nullable();
            $table->time('start_time');
            $table->timestampTz('closed_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->index(['session_date', 'kind']);
        });
        DB::statement('CREATE UNIQUE INDEX attendance_sessions_schedule_once ON attendance_sessions (schedule_id, session_date) WHERE schedule_id IS NOT NULL');

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa', 'terlambat'])->default('hadir');
            $table->enum('method', ['manual', 'card', 'qr', 'selfie', 'face'])->default('manual');
            $table->timestampTz('scanned_at', 6)->nullable();
            $table->string('evidence_path')->nullable();
            $table->decimal('match_score', 4, 3)->nullable();
            $table->decimal('geo_lat', 10, 7)->nullable();
            $table->decimal('geo_lng', 10, 7)->nullable();
            $table->enum('review_status', ['none', 'flagged', 'pending', 'approved', 'rejected'])->default('none');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at', 6)->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz(6);
            $table->unique(['session_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('grade_components', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('weight');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('component_id')->constrained('grade_components')->restrictOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->date('assessed_on')->nullable();
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->timestampsTz(6);
            $table->index(['subject_id', 'classroom_id', 'term_id']);
        });
        DB::statement('ALTER TABLE assessments ADD CONSTRAINT assessments_max CHECK (max_score > 0)');

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->decimal('score', 6, 2);
            $table->text('note')->nullable();
            $table->timestampsTz(6);
            $table->unique(['assessment_id', 'student_id']);
        });
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_score CHECK (score >= 0)');

        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->text('homeroom_note')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestampTz('published_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->unique(['student_id', 'term_id']);
        });

        Schema::create('report_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->decimal('final_score', 5, 2);
            $table->string('predicate', 2);
            $table->string('remark')->nullable();
            $table->unique(['report_card_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['report_card_items', 'report_cards', 'grades', 'assessments', 'grade_components', 'attendance_records', 'attendance_sessions', 'schedules', 'teaching_assignments', 'subjects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
