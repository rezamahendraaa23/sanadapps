<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['pulang', 'keluar', 'sakit', 'kegiatan']);
            $table->text('reason');
            $table->timestampTz('start_at', 6);
            $table->timestampTz('end_at', 6);
            $table->enum('status', ['pending', 'approved', 'rejected', 'out', 'returned', 'late', 'cancelled'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('first_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at', 6)->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestampTz('out_at', 6)->nullable();
            $table->timestampTz('returned_at', 6)->nullable();
            $table->timestampTz('late_notified_at', 6)->nullable();
            $table->string('qr_token')->unique();
            $table->timestampsTz(6);
            $table->index(['status', 'end_at']);
            $table->index(['student_id', 'status']);
        });
        DB::statement('ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_dates CHECK (end_at > start_at)');
        // Satu santri tidak bisa sedang di luar lewat dua izin sekaligus.
        DB::statement("CREATE UNIQUE INDEX leave_requests_one_outside ON leave_requests (student_id) WHERE returned_at IS NULL AND status IN ('out', 'late')");

        Schema::create('gate_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['out', 'in']);
            $table->enum('method', ['card', 'qr', 'face', 'manual']);
            $table->enum('result', ['ok', 'denied', 'override']);
            $table->string('deny_reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('occurred_at', 6);
            $table->string('pickup_name')->nullable();
            $table->string('pickup_relation')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('note')->nullable();
            $table->index(['occurred_at', 'result']);
            $table->index('student_id');
        });
        DB::unprepared('CREATE TRIGGER gate_logs_append_only BEFORE UPDATE OR DELETE ON gate_logs FOR EACH ROW EXECUTE FUNCTION ledger_block_changes()');

        // Tinjauan terpisah karena gate_logs tidak boleh diubah (A66).
        Schema::create('gate_log_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_log_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
        });

        Schema::create('gate_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestampTz('started_at', 6);
            $table->timestampTz('ended_at', 6)->nullable();
            $table->text('handover_note')->nullable();
            $table->unsignedInteger('outside_count_at_end')->nullable();
            $table->timestampsTz(6);
        });
        DB::statement('CREATE UNIQUE INDEX gate_shifts_one_open ON gate_shifts (user_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        foreach (['gate_shifts', 'gate_log_reviews', 'gate_logs', 'leave_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
