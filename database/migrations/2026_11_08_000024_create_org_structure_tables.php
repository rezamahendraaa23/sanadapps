<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur pesantren (permintaan pemilik 8 November 2026): bagan yayasan dari pendiri dan bagan
 * kesantrian dari rois. Setiap jabatan menempel pada atasannya, jadi menambah cukup dari kotak atasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_charts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key', 30)->unique();
            $table->string('name');
            $table->boolean('show_on_tv')->default(false);
            $table->timestampsTz(6);
        });

        Schema::create('org_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('chart_id')->constrained('org_charts')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('org_positions')->nullOnDelete();
            $table->string('title');
            $table->string('person_name')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz(6);
            $table->index(['chart_id', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_positions');
        Schema::dropIfExists('org_charts');
    }
};
