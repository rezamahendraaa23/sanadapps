<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestampsTz(6);
        });
        DB::statement('CREATE UNIQUE INDEX academic_years_one_active ON academic_years (is_active) WHERE is_active');
        DB::statement('ALTER TABLE academic_years ADD CONSTRAINT academic_years_dates CHECK (end_date > start_date)');

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestampsTz(6);
        });
        DB::statement('CREATE UNIQUE INDEX terms_one_active ON terms (is_active) WHERE is_active');
        DB::statement('ALTER TABLE terms ADD CONSTRAINT terms_dates CHECK (end_date > start_date)');

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nip')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('specialization')->nullable();
            $table->softDeletesTz(precision: 6);
            $table->timestampsTz(6);
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('level')->nullable();
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('dormitories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->enum('gender', ['L', 'P']);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->foreignId('musyrif_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nis')->unique();
            $table->string('nisn')->nullable();
            $table->string('name');
            $table->enum('gender', ['L', 'P']);
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('status', ['active', 'graduated', 'dropped', 'inactive'])->default('active');
            $table->date('entry_date')->nullable();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dormitory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('extra')->default('{}');
            $table->softDeletesTz(precision: 6);
            $table->timestampsTz(6);
            $table->index(['status', 'classroom_id']);
        });
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX students_name_trgm ON students USING gin (name gin_trgm_ops)');

        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('entity')->default('student');
            $table->string('key');
            $table->string('label');
            $table->enum('type', ['text', 'number', 'select', 'date']);
            $table->jsonb('options')->default('[]');
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
            $table->unique(['entity', 'key']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone_wa')->unique();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('occupation')->nullable();
            $table->softDeletesTz(precision: 6);
            $table->timestampsTz(6);
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('relation', ['ayah', 'ibu', 'wali']);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_payer')->default(false);
            $table->boolean('can_receive_wa')->default(true);
            $table->timestampsTz(6);
            $table->unique(['guardian_id', 'student_id']);
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('started_at', 6);
            $table->timestampTz('ended_at', 6)->nullable();
            $table->enum('status', ['active', 'moved', 'promoted', 'graduated', 'reverted'])->default('active');
            $table->uuid('batch_id')->nullable()->index();
            $table->timestampsTz(6);
            $table->index(['student_id', 'ended_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('body');
            $table->enum('audience', ['all', 'classroom', 'dormitory'])->default('all');
            $table->unsignedBigInteger('audience_id')->nullable();
            $table->timestampTz('publish_at', 6)->nullable();
            $table->boolean('send_wa')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });
    }

    public function down(): void
    {
        foreach (['announcements', 'student_enrollments', 'guardian_student', 'guardians', 'custom_field_definitions', 'students', 'dormitories', 'classrooms', 'teachers', 'terms', 'academic_years'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
