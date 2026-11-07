<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masjid_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('city')->nullable();
            $table->string('address_query')->nullable();
            $table->string('geocode_source')->nullable();
            $table->timestampTz('geocode_at', 6)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->enum('schedule_source', ['computed', 'api'])->default('computed');
            $table->string('api_location_ref')->nullable();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->decimal('fajr_angle', 4, 2)->default(20);
            $table->decimal('isha_angle', 4, 2)->default(18);
            $table->enum('asr_method', ['standard', 'hanafi'])->default('standard');
            $table->smallInteger('imsak_minutes')->default(10);
            $table->smallInteger('dhuha_offset_minutes')->default(28);
            $table->smallInteger('hijri_offset_days')->default(0);
            $table->boolean('friday_mode')->default(true);
            $table->date('ramadan_start')->nullable();
            $table->date('ramadan_end')->nullable();
            $table->smallInteger('countdown_minutes')->default(15);
            $table->boolean('auto_attendance')->default(false);
            $table->timestampsTz(6);
        });

        Schema::create('prayer_adjustments', function (Blueprint $table) {
            $table->id();
            $table->enum('prayer', ['imsak', 'fajr', 'sunrise', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha']);
            $table->smallInteger('minutes');
            $table->date('effective_from');
            $table->timestampsTz(6);
            $table->unique(['prayer', 'effective_from']);
        });

        Schema::create('prayer_overrides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('date');
            $table->enum('prayer', ['imsak', 'fajr', 'sunrise', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha']);
            $table->time('time');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(6);
            $table->unique(['date', 'prayer']);
        });

        Schema::create('iqamah_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('prayer', ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'])->unique();
            $table->smallInteger('minutes_after_adhan');
            $table->smallInteger('prayer_duration_minutes')->default(15);
            $table->smallInteger('friday_minutes')->nullable();
            $table->smallInteger('ramadan_minutes')->nullable();
            $table->timestampsTz(6);
        });

        foreach (['fajr' => 15, 'dhuhr' => 10, 'asr' => 10, 'maghrib' => 8, 'isha' => 10] as $prayer => $minutes) {
            DB::table('iqamah_settings')->insert(['prayer' => $prayer, 'minutes_after_adhan' => $minutes, 'prayer_duration_minutes' => $prayer === 'fajr' ? 15 : 12, 'friday_minutes' => $prayer === 'dhuhr' ? 30 : null, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::create('prayer_times', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            foreach (['imsak', 'fajr', 'sunrise', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'] as $prayer) {
                $table->string($prayer, 5);
            }
            $table->enum('source', ['computed', 'override', 'imported'])->default('computed');
            $table->string('params_hash')->nullable();
            $table->timestampTz('computed_at', 6);
        });

        Schema::create('prayer_reference_times', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('city_ref');
            foreach (['imsak', 'fajr', 'sunrise', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'] as $prayer) {
                $table->string($prayer, 5)->nullable();
            }
            $table->string('source_label');
            $table->timestampsTz(6);
            $table->unique(['date', 'city_ref']);
        });

        Schema::create('hijri_months', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('hijri_year');
            $table->unsignedTinyInteger('hijri_month');
            $table->date('starts_on');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestampsTz(6);
            $table->unique(['hijri_year', 'hijri_month']);
        });

        DB::statement('ALTER TABLE devices DROP CONSTRAINT devices_type_check');
        DB::statement("ALTER TABLE devices ADD CONSTRAINT devices_type_check CHECK (type IN ('reader_usb', 'camera_qr', 'tv_display'))");

        Schema::create('display_screens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->foreignId('device_id')->unique()->constrained()->restrictOnDelete();
            $table->enum('profile', ['masjid', 'canteen', 'lobby', 'announcement_only'])->default('masjid');
            $table->enum('orientation', ['landscape', 'portrait'])->default('landscape');
            $table->enum('content_pattern', ['rotate', 'list'])->default('rotate');
            $table->jsonb('brightness_schedule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_seen_at', 6)->nullable();
            $table->string('last_manifest_version')->nullable();
            $table->string('app_version')->nullable();
            $table->integer('clock_offset_ms')->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('display_pairings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6);
            $table->string('pairing_token_hash');
            $table->timestampTz('expires_at', 6);
            $table->foreignId('claimed_screen_id')->nullable()->constrained('display_screens')->cascadeOnDelete();
            $table->text('issued_token')->nullable();
            $table->timestampsTz(6);
            $table->index('code');
        });

        Schema::create('display_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->constrained('display_screens')->cascadeOnDelete();
            $table->enum('command', ['reload', 'test', 'dim', 'undim']);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('acked_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->index(['screen_id', 'acked_at']);
        });

        Schema::create('display_contents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('type', ['text', 'poster', 'video', 'agenda', 'qr_donation', 'quote', 'obituary', 'emergency']);
            $table->string('title');
            $table->jsonb('body')->default('{}');
            $table->string('media_path')->nullable();
            $table->timestampTz('starts_at', 6)->nullable();
            $table->timestampTz('ends_at', 6)->nullable();
            $table->jsonb('schedule_rule')->nullable();
            $table->smallInteger('priority')->default(0);
            $table->smallInteger('duration_seconds')->default(12);
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'archived'])->default('draft');
            $table->text('rejected_reason')->nullable();
            $table->foreignId('announcement_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_ticker')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at', 6)->nullable();
            $table->softDeletesTz(precision: 6);
            $table->timestampsTz(6);
            $table->index(['status', 'starts_at', 'ends_at']);
        });
        DB::statement('ALTER TABLE display_contents ADD CONSTRAINT display_contents_duration CHECK (duration_seconds BETWEEN 6 AND 60)');

        Schema::create('display_content_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('display_contents')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('display_screens')->cascadeOnDelete();
            $table->unique(['content_id', 'screen_id']);
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->text('text_ar');
            $table->text('text_id');
            $table->string('source_ref');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at', 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('display_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('scope', ['name', 'photo']);
            $table->timestampTz('granted_at', 6);
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->enum('granted_via', ['portal', 'paper']);
            $table->timestampsTz(6);
        });
        DB::statement('CREATE UNIQUE INDEX display_consents_one_active ON display_consents (student_id, scope) WHERE revoked_at IS NULL');

        Schema::create('khutbah_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('date')->unique();
            $table->string('imam_name')->nullable();
            $table->string('khatib_name')->nullable();
            $table->string('theme')->nullable();
            $table->foreignId('imam_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestampsTz(6);
        });

        Schema::create('kajian_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('speaker')->nullable();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('display_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->constrained('display_screens')->cascadeOnDelete();
            $table->enum('type', ['heartbeat', 'offline', 'online', 'error', 'command_ack']);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('occurred_at', 6);
            $table->index(['screen_id', 'occurred_at']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('anchor_prayer', ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'])->nullable();
            $table->smallInteger('anchor_offset_minutes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['anchor_prayer', 'anchor_offset_minutes']);
        });

        foreach (['display_events', 'kajian_schedules', 'khutbah_schedules', 'display_consents', 'quotes', 'display_content_targets', 'display_contents', 'display_commands', 'display_pairings', 'display_screens', 'hijri_months', 'prayer_reference_times', 'prayer_times', 'iqamah_settings', 'prayer_overrides', 'prayer_adjustments', 'masjid_profile'] as $table) {
            Schema::dropIfExists($table);
        }

        DB::statement("DELETE FROM devices WHERE type = 'tv_display'");
        DB::statement('ALTER TABLE devices DROP CONSTRAINT devices_type_check');
        DB::statement("ALTER TABLE devices ADD CONSTRAINT devices_type_check CHECK (type IN ('reader_usb', 'camera_qr'))");
    }
};
