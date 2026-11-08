<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jeda fase: masa ketika pelajaran reguler berhenti, baik program khusus (misalnya Program Ramadan) maupun libur.
 * Fase yang melewati jeda diperpanjang sepanjang jedanya, dan nilai selama jeda tidak masuk nilai fase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_breaks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('kind', 10)->default('special');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestampsTz(6);
            $table->index(['starts_on', 'ends_on']);
        });
        DB::statement('ALTER TABLE academic_breaks ADD CONSTRAINT academic_breaks_range CHECK (ends_on >= starts_on)');
        DB::statement("ALTER TABLE academic_breaks ADD CONSTRAINT academic_breaks_kind CHECK (kind IN ('special', 'holiday'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_breaks');
    }
};
