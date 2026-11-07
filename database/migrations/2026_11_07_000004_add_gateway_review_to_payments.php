<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false);
            $table->text('review_note')->nullable();
            $table->timestampTz('last_synced_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['needs_review', 'review_note', 'last_synced_at']);
        });
    }
};
