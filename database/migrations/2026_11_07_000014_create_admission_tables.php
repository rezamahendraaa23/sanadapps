<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('open_at', 6);
            $table->timestampTz('close_at', 6);
            $table->unsignedInteger('quota');
            $table->bigInteger('registration_fee')->default(0);
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $table->timestampTz('announce_at', 6)->nullable();
            $table->timestampTz('announced_at', 6)->nullable();
            $table->timestampsTz(6);
        });
        DB::statement('ALTER TABLE admission_periods ADD CONSTRAINT admission_periods_window CHECK (close_at > open_at)');
        DB::statement("CREATE UNIQUE INDEX admission_periods_one_open ON admission_periods (status) WHERE status = 'open'");

        Schema::create('admission_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('period_id')->constrained('admission_periods')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['document', 'exam', 'interview']);
            $table->unsignedSmallInteger('weight');
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz(6);
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('period_id')->constrained('admission_periods')->restrictOnDelete();
            $table->string('reg_no')->unique();
            $table->string('name')->nullable();
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('previous_school')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone');
            $table->string('guardian_email')->nullable();
            $table->enum('guardian_relation', ['ayah', 'ibu', 'wali'])->default('ayah');
            $table->enum('status', ['draft', 'submitted', 'paid', 'verified', 'in_selection', 'accepted', 'rejected', 'waitlist', 'enrolled'])->default('draft');
            $table->decimal('total_score', 6, 2)->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('extra')->default('{}');
            $table->timestampTz('consent_at', 6)->nullable();
            $table->timestampTz('submitted_at', 6)->nullable();
            $table->timestampTz('announced_at', 6)->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz(6);
            $table->index(['period_id', 'status']);
            $table->index('guardian_phone');
        });

        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['kk', 'akta', 'foto', 'ijazah']);
            $table->string('file_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('note')->nullable();
            $table->timestampsTz(6);
            $table->unique(['applicant_id', 'type']);
        });

        Schema::create('admission_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('admission_steps')->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
            $table->unique(['applicant_id', 'step_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('applicant_id')->references('id')->on('applicants')->nullOnDelete();
        });
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->foreign('applicant_id')->references('id')->on('applicants')->nullOnDelete();
        });
        DB::statement("CREATE UNIQUE INDEX exam_attempts_applicant_one_active ON exam_attempts (exam_id, applicant_id) WHERE status = 'in_progress'");
        DB::statement('CREATE UNIQUE INDEX exam_attempts_applicant_number ON exam_attempts (exam_id, applicant_id, attempt_no) WHERE applicant_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS exam_attempts_applicant_number');
        DB::statement('DROP INDEX IF EXISTS exam_attempts_applicant_one_active');
        Schema::table('exam_attempts', fn (Blueprint $table) => $table->dropForeign(['applicant_id']));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropForeign(['applicant_id']));

        foreach (['admission_results', 'applicant_documents', 'applicants', 'admission_steps', 'admission_periods'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
