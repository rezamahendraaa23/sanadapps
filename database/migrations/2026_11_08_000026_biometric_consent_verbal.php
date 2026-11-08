<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persetujuan lisan wali untuk absen wajah: tetap tercatat siapa yang menerima, kapan, dan keterangannya,
 * karena data wajah adalah data pribadi spesifik yang persetujuannya harus bisa dibuktikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE biometric_consents DROP CONSTRAINT IF EXISTS biometric_consents_method_check');
        DB::statement("ALTER TABLE biometric_consents ADD CONSTRAINT biometric_consents_method_check CHECK (method IN ('digital', 'paper', 'verbal'))");
        Schema::table('biometric_consents', function (Blueprint $table) {
            $table->string('note', 300)->nullable();
        });
    }

    public function down(): void
    {
        DB::table('biometric_consents')->where('method', 'verbal')->delete();
        Schema::table('biometric_consents', function (Blueprint $table) {
            $table->dropColumn('note');
        });
        DB::statement('ALTER TABLE biometric_consents DROP CONSTRAINT IF EXISTS biometric_consents_method_check');
        DB::statement("ALTER TABLE biometric_consents ADD CONSTRAINT biometric_consents_method_check CHECK (method IN ('digital', 'paper'))");
    }
};
