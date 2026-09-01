<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('system_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('browser_enabled')->default(false);
            $table->json('modules')->nullable();
            $table->string('custom_sound_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
