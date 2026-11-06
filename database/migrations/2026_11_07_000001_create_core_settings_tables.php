<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_profile', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->char('brand_color', 7)->default('#0B5D45');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('nis_pattern')->default('{tahun}{urut:4}');
            $table->timestampTz('setup_dismissed_at', 6)->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('module_key')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz(6);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('provider', ['midtrans', 'fonnte'])->unique();
            $table->string('mode')->nullable();
            $table->text('credentials')->nullable();
            $table->jsonb('settings')->default('{}');
            $table->boolean('is_active')->default(false);
            $table->timestampsTz(6);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });

        // user_id boleh menjadi NULL saat akun dihapus; isi catatan lainnya tidak boleh berubah.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_block_changes() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND NEW.user_id IS NULL AND OLD.user_id IS NOT NULL
                    AND (to_jsonb(NEW) - 'user_id') = (to_jsonb(OLD) - 'user_id') THEN
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'audit_logs bersifat append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_logs_append_only BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_block_changes();
        SQL);

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider');
            $table->string('event_key');
            $table->jsonb('payload');
            $table->boolean('signature_valid')->default(false);
            $table->timestampTz('processed_at', 6)->nullable();
            $table->text('error')->nullable();
            $table->timestampsTz(6);
            $table->unique(['provider', 'event_key']);
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('purpose');
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('consumed_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->index(['phone', 'purpose']);
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->jsonb('errors')->default('[]');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
        });

        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->timestampTz('read_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_block_changes()');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('school_profile');
    }
};
