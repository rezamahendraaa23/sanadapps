<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attendance_sessions DROP CONSTRAINT attendance_sessions_source_check');
        DB::statement("ALTER TABLE attendance_sessions ADD CONSTRAINT attendance_sessions_source_check CHECK (source IN ('manual', 'card', 'qr', 'selfie', 'face'))");

        Schema::table('attendance_records', function (Blueprint $table) {
            // Kode acak yang tercetak di foto selfie, pembanding bagi peninjau (PRD Sistem R12).
            $table->string('challenge_code', 8)->nullable();
            // Sampel acak 5%: statusnya tetap, hanya ikut antrean tinjauan (PRD Sistem 8.9b).
            $table->boolean('sampled')->default(false);
        });
        DB::statement("CREATE INDEX attendance_records_review ON attendance_records (review_status) WHERE review_status IN ('flagged', 'pending') OR sampled");

        Schema::create('biometric_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope')->default('face_attendance');
            $table->enum('method', ['digital', 'paper']);
            $table->string('document_path')->nullable();
            $table->timestampTz('granted_at', 6);
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->string('ip')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });
        DB::statement('CREATE UNIQUE INDEX biometric_consents_one_active ON biometric_consents (student_id, scope) WHERE revoked_at IS NULL');

        Schema::create('face_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consent_id')->constrained('biometric_consents')->restrictOnDelete();
            // Terenkripsi, jadi disimpan sebagai teks. Dikosongkan saat dicabut.
            $table->text('embedding')->nullable();
            $table->string('model_version');
            $table->decimal('quality_score', 4, 3)->nullable();
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('enrolled_at', 6);
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->timestampsTz(6);
        });
        DB::statement("CREATE UNIQUE INDEX face_enrollments_one_active ON face_enrollments (student_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('face_enrollments');
        Schema::dropIfExists('biometric_consents');
        DB::statement('DROP INDEX IF EXISTS attendance_records_review');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['challenge_code', 'sampled']);
        });

        DB::statement('ALTER TABLE attendance_sessions DROP CONSTRAINT attendance_sessions_source_check');
        DB::statement("ALTER TABLE attendance_sessions ADD CONSTRAINT attendance_sessions_source_check CHECK (source IN ('manual', 'card', 'qr'))");
    }
};
