<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konten layar bisa tampil di bagian pengumuman atau memenuhi layar, dan bisa berupa video YouTube.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('display_contents', function (Blueprint $table) {
            $table->string('layout', 10)->default('panel');
        });
        DB::statement("ALTER TABLE display_contents ADD CONSTRAINT display_contents_layout_check CHECK (layout IN ('panel', 'full'))");
        DB::statement('ALTER TABLE display_contents DROP CONSTRAINT display_contents_type_check');
        DB::statement("ALTER TABLE display_contents ADD CONSTRAINT display_contents_type_check CHECK (type IN ('text', 'poster', 'video', 'youtube', 'agenda', 'qr_donation', 'quote', 'obituary', 'emergency'))");
        // Video dan siaran boleh tayang sampai satu jam; konten lain tetap singkat.
        DB::statement('ALTER TABLE display_contents DROP CONSTRAINT display_contents_duration');
        DB::statement("ALTER TABLE display_contents ADD CONSTRAINT display_contents_duration CHECK (duration_seconds BETWEEN 6 AND CASE WHEN type IN ('video', 'youtube') THEN 3600 ELSE 120 END)");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM display_contents WHERE type = 'youtube'");
        DB::statement('ALTER TABLE display_contents DROP CONSTRAINT display_contents_duration');
        DB::statement('UPDATE display_contents SET duration_seconds = LEAST(duration_seconds, 60)');
        DB::statement('ALTER TABLE display_contents ADD CONSTRAINT display_contents_duration CHECK (duration_seconds BETWEEN 6 AND 60)');
        DB::statement('ALTER TABLE display_contents DROP CONSTRAINT display_contents_type_check');
        DB::statement("ALTER TABLE display_contents ADD CONSTRAINT display_contents_type_check CHECK (type IN ('text', 'poster', 'video', 'agenda', 'qr_donation', 'quote', 'obituary', 'emergency'))");
        DB::statement('ALTER TABLE display_contents DROP CONSTRAINT display_contents_layout_check');
        Schema::table('display_contents', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
