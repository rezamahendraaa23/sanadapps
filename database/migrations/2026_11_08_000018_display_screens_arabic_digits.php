<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan per layar: jam dan jadwal memakai angka Arab (٠١٢٣). Bawaannya mati agar mudah dibaca jamaah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('display_screens', function (Blueprint $table): void {
            $table->boolean('arabic_digits')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('display_screens', function (Blueprint $table): void {
            $table->dropColumn('arabic_digits');
        });
    }
};
