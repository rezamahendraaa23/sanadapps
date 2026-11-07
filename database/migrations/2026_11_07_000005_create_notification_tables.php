<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('in_app_notifications', function (Blueprint $table) {
            $table->string('dedupe_key')->nullable()->unique();
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_key');
            $table->enum('channel', ['whatsapp', 'inapp', 'email'])->default('whatsapp');
            $table->text('body');
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz(6);
            $table->unique(['event_key', 'channel']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('channel')->default('whatsapp');
            $table->string('recipient');
            $table->string('event_key');
            $table->jsonb('payload')->default('{}');
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued');
            $table->string('provider_message_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('sent_at', 6)->nullable();
            $table->string('dedupe_key')->nullable()->unique();
            $table->timestampsTz(6);
            $table->index(['status', 'created_at']);
        });

        Schema::create('guardian_notification_prefs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz(6);
            $table->unique(['guardian_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_notification_prefs');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');

        Schema::table('in_app_notifications', function (Blueprint $table) {
            $table->dropColumn('dedupe_key');
        });
    }
};
