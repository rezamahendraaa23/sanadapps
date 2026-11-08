<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiap fase bisa memilih desain sertifikat dan judul berbahasa Arab sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_phases', function (Blueprint $table) {
            $table->string('certificate_design', 20)->nullable();
            $table->string('certificate_title_ar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_phases', function (Blueprint $table) {
            $table->dropColumn(['certificate_design', 'certificate_title_ar']);
        });
    }
};
