<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->foreignId('owner_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestampsTz(6);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('bank_id')->constrained('question_banks')->cascadeOnDelete();
            $table->enum('type', ['mcq', 'multi', 'true_false', 'short', 'essay']);
            $table->text('body');
            $table->enum('direction', ['ltr', 'rtl'])->default('ltr');
            $table->string('media_path')->nullable();
            $table->jsonb('options')->nullable();
            $table->jsonb('answer_key')->nullable();
            $table->decimal('points', 6, 2)->default(1);
            $table->unsignedSmallInteger('difficulty')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->softDeletesTz(precision: 6);
            $table->timestampsTz(6);
        });
        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_points CHECK (points > 0)');

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('audience', ['student', 'applicant'])->default('student');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->timestampTz('start_at', 6)->nullable();
            $table->timestampTz('end_at', 6)->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->decimal('pass_score', 5, 2)->nullable();
            $table->string('access_code')->nullable();
            $table->enum('show_result_mode', ['immediately', 'after_close', 'never'])->default('after_close');
            $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
            $table->index(['status', 'start_at']);
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('points_override', 6, 2)->nullable();
            $table->unique(['exam_id', 'question_id']);
        });

        Schema::create('exam_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('applicant_id')->nullable();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->timestampTz('started_at', 6);
            $table->timestampTz('deadline_at', 6);
            $table->timestampTz('submitted_at', 6)->nullable();
            $table->enum('status', ['in_progress', 'submitted', 'auto_submitted', 'graded'])->default('in_progress');
            $table->decimal('score', 6, 2)->nullable();
            $table->jsonb('question_order');
            $table->jsonb('option_order');
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('blur_count')->default(0);
            $table->timestampTz('last_seen_at', 6)->nullable();
            // Tanda sesi perangkat: membuka dari perangkat lain mengambil alih (PRD Sistem 8.8).
            $table->string('session_token', 64);
            $table->timestampsTz(6);
            $table->index(['status', 'deadline_at']);
        });
        DB::statement("CREATE UNIQUE INDEX exam_attempts_one_active ON exam_attempts (exam_id, student_id) WHERE status = 'in_progress'");
        DB::statement('CREATE UNIQUE INDEX exam_attempts_number ON exam_attempts (exam_id, student_id, attempt_no) WHERE student_id IS NOT NULL');

        Schema::create('exam_answers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->jsonb('answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('client_seq')->default(0);
            $table->timestampTz('answered_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('exam_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->enum('type', ['blur', 'focus', 'offline', 'online', 'reconnect']);
            $table->timestampTz('occurred_at', 6);
            $table->jsonb('meta')->nullable();
            $table->index(['attempt_id', 'type']);
        });
    }

    public function down(): void
    {
        foreach (['exam_events', 'exam_answers', 'exam_attempts', 'exam_targets', 'exam_questions', 'exams', 'questions', 'question_banks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
