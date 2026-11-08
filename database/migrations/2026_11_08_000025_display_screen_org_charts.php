<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur pesantren dipilih per layar: bagan mana yang tampil, dan apakah ikut bergiliran atau tampil terus.
 * Pilihan lama "tampil di TV" per bagan dipindahkan ke semua layar yang sudah terpasang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('display_screens', function (Blueprint $table) {
            $table->jsonb('org_charts')->default('[]');
            $table->string('org_mode', 10)->default('rotate');
        });
        DB::statement("ALTER TABLE display_screens ADD CONSTRAINT display_screens_org_mode CHECK (org_mode IN ('rotate', 'always'))");

        $shown = DB::table('org_charts')->where('show_on_tv', true)->pluck('key')->all();
        if ($shown !== []) {
            DB::table('display_screens')->update(['org_charts' => json_encode(array_values($shown))]);
        }

        Schema::table('org_charts', function (Blueprint $table) {
            $table->dropColumn('show_on_tv');
        });
    }

    public function down(): void
    {
        Schema::table('org_charts', function (Blueprint $table) {
            $table->boolean('show_on_tv')->default(false);
        });
        DB::statement('ALTER TABLE display_screens DROP CONSTRAINT display_screens_org_mode');
        Schema::table('display_screens', function (Blueprint $table) {
            $table->dropColumn(['org_charts', 'org_mode']);
        });
    }
};
