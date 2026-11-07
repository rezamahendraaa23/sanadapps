<?php

use App\Modules\Core\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan pemilik: absen selfie dihapus, wajah didaftarkan admin lalu dikenali otomatis.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendance_records_review');
        DB::statement("UPDATE attendance_sessions SET source = 'manual', allowed_modes = '[\"manual\"]'::jsonb WHERE source = 'selfie'");
        DB::statement("UPDATE attendance_records SET method = 'manual' WHERE method = 'selfie'");
        DB::statement("UPDATE attendance_records SET review_status = 'approved' WHERE review_status = 'flagged'");

        DB::statement('ALTER TABLE attendance_sessions DROP CONSTRAINT attendance_sessions_source_check');
        DB::statement("ALTER TABLE attendance_sessions ADD CONSTRAINT attendance_sessions_source_check CHECK (source IN ('manual', 'card', 'qr', 'face'))");
        DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT attendance_records_method_check');
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_method_check CHECK (method IN ('manual', 'card', 'qr', 'face'))");
        DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT attendance_records_review_status_check');
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_review_status_check CHECK (review_status IN ('none', 'pending', 'approved', 'rejected'))");

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['challenge_code', 'sampled', 'geo_lat', 'geo_lng']);
        });
        DB::statement("CREATE INDEX attendance_records_review ON attendance_records (review_status) WHERE review_status = 'pending'");

        $module = Module::query()->where('module_key', 'attendance')->first();

        if ($module !== null && is_array($module->settings)) {
            $settings = array_diff_key($module->settings, array_flip(['geofence_enabled', 'geofence_lat', 'geofence_lng', 'geofence_radius', 'selfie_retention']));

            if (isset($settings['verification_modes'])) {
                $settings['verification_modes'] = array_values(array_diff($settings['verification_modes'], ['selfie']));
            }

            $module->update(['settings' => $settings]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendance_records_review');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('challenge_code', 8)->nullable();
            $table->boolean('sampled')->default(false);
            $table->decimal('geo_lat', 10, 7)->nullable();
            $table->decimal('geo_lng', 10, 7)->nullable();
        });

        DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT attendance_records_review_status_check');
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_review_status_check CHECK (review_status IN ('none', 'flagged', 'pending', 'approved', 'rejected'))");
        DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT attendance_records_method_check');
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_method_check CHECK (method IN ('manual', 'card', 'qr', 'selfie', 'face'))");
        DB::statement('ALTER TABLE attendance_sessions DROP CONSTRAINT attendance_sessions_source_check');
        DB::statement("ALTER TABLE attendance_sessions ADD CONSTRAINT attendance_sessions_source_check CHECK (source IN ('manual', 'card', 'qr', 'selfie', 'face'))");
        DB::statement("CREATE INDEX attendance_records_review ON attendance_records (review_status) WHERE review_status IN ('flagged', 'pending') OR sampled");
    }
};
