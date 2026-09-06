<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('recipient')->nullable();
            $table->text('template')->nullable();
            $table->string('schedule')->default('on_event');
            $table->string('api_url')->nullable();
            $table->string('api_token')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('wa_notification_settings');
        Schema::dropIfExists('audit_logs');
    }
};
