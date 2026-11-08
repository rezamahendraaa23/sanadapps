<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat bisa dua sisi: depan sertifikat, belakang hasil ujian (opsional per fase).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_phases', function (Blueprint $table) {
            $table->boolean('certificate_back')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_phases', function (Blueprint $table) {
            $table->dropColumn('certificate_back');
        });
    }
};
