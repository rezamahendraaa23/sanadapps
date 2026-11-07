<?php

use App\Modules\Tahfidz\Support\QuranData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_surahs', function (Blueprint $table) {
            $table->unsignedSmallInteger('number')->primary();
            $table->string('name_ar');
            $table->string('name_latin');
            $table->unsignedSmallInteger('ayah_count');
            $table->unsignedTinyInteger('juz_start');
        });

        foreach (QuranData::SURAHS as $index => [$arabic, $latin, $ayahs, $juz]) {
            DB::table('quran_surahs')->insert(['number' => $index + 1, 'name_ar' => $arabic, 'name_latin' => $latin, 'ayah_count' => $ayahs, 'juz_start' => $juz]);
        }

        Schema::create('tahfidz_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('record_date');
            $table->enum('type', ['ziyadah', 'murojaah', 'tahsin']);
            $table->unsignedSmallInteger('surah_from');
            $table->unsignedSmallInteger('ayah_from');
            $table->unsignedSmallInteger('surah_to');
            $table->unsignedSmallInteger('ayah_to');
            $table->decimal('pages', 5, 2)->default(0);
            $table->enum('grade', ['mumtaz', 'jayyid_jiddan', 'jayyid', 'maqbul', 'rasib']);
            $table->text('notes')->nullable();
            $table->timestampsTz(6);
            $table->foreign('surah_from')->references('number')->on('quran_surahs');
            $table->foreign('surah_to')->references('number')->on('quran_surahs');
            $table->index(['student_id', 'record_date']);
        });

        Schema::create('tahfidz_targets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->decimal('target_pages', 6, 2)->default(0);
            $table->decimal('target_juz', 4, 1)->default(0);
            $table->timestampsTz(6);
            $table->unique(['student_id', 'term_id']);
        });

        Schema::create('mutabaah_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(6);
        });

        Schema::create('mutabaah_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('mutabaah_items')->cascadeOnDelete();
            $table->date('log_date');
            $table->enum('status', ['done', 'skipped']);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->unique(['student_id', 'item_id', 'log_date']);
            $table->index(['log_date', 'verified_by']);
        });
    }

    public function down(): void
    {
        foreach (['mutabaah_logs', 'mutabaah_items', 'tahfidz_targets', 'tahfidz_records', 'quran_surahs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
