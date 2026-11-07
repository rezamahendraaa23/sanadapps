<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedSmallInteger('points');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('discipline_sanctions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->unsignedInteger('min_points');
            $table->text('action_text')->nullable();
            $table->boolean('notify_guardian')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('discipline_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('rule_id')->constrained('discipline_rules')->restrictOnDelete();
            $table->date('occurred_on');
            // Poin disalin agar perubahan aturan tidak mengubah catatan lama.
            $table->unsignedSmallInteger('points');
            $table->text('notes')->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
            $table->index(['student_id', 'occurred_on']);
        });
        DB::statement('CREATE UNIQUE INDEX discipline_records_leave_once ON discipline_records (leave_request_id) WHERE leave_request_id IS NOT NULL');

        Schema::create('discipline_sanction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sanction_id')->constrained('discipline_sanctions')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('points_at');
            $table->timestampTz('issued_at', 6);
            $table->unique(['student_id', 'sanction_id', 'academic_year_id']);
        });

        Schema::create('health_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('visit_date');
            // Kolom sensitif dienkripsi (PRD Sistem 6.8).
            $table->text('complaint');
            $table->text('action')->nullable();
            $table->text('medication')->nullable();
            $table->enum('outcome', ['ditangani', 'rujuk', 'dipulangkan'])->default('ditangani');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
            $table->index(['student_id', 'visit_date']);
        });

        Schema::create('student_risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('computed_on');
            $table->unsignedSmallInteger('score');
            $table->enum('level', ['low', 'medium', 'high']);
            $table->jsonb('reasons');
            $table->timestampsTz(6);
            $table->unique(['student_id', 'computed_on']);
            $table->index(['computed_on', 'level']);
        });

        Schema::create('alumni_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('graduated_on')->nullable();
            $table->string('current_activity')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dormitory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('condition')->default('baik');
            $table->string('location')->nullable();
            $table->timestampsTz(6);
        });
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_quantity CHECK (quantity >= 0)');

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->enum('type', ['in', 'out', 'damage', 'repair', 'adjust']);
            $table->integer('quantity');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at', 6)->useCurrent();
        });
        DB::unprepared('CREATE TRIGGER inventory_movements_append_only BEFORE UPDATE OR DELETE ON inventory_movements FOR EACH ROW EXECUTE FUNCTION ledger_block_changes()');

        Schema::create('duty_rosters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location')->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('duty_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('roster_id')->constrained('duty_rosters')->cascadeOnDelete();
            $table->enum('assignee_type', ['student', 'teacher']);
            $table->unsignedBigInteger('assignee_id');
            $table->date('duty_date');
            $table->enum('status', ['scheduled', 'done', 'missed'])->default('scheduled');
            $table->timestampsTz(6);
            $table->unique(['roster_id', 'assignee_type', 'assignee_id', 'duty_date']);
            $table->index(['assignee_type', 'assignee_id', 'duty_date']);
        });
    }

    public function down(): void
    {
        foreach (['duty_assignments', 'duty_rosters', 'inventory_movements', 'inventory_items', 'alumni_profiles', 'student_risk_scores', 'health_records', 'discipline_sanction_events', 'discipline_records', 'discipline_sanctions', 'discipline_rules'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
