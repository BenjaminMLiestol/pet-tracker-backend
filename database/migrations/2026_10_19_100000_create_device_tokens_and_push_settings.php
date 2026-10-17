<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per phone that can receive pushes. A token belongs to one person at a time.
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform', 16)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // Tell me when a co-owner feeds or walks the dog.
            $table->boolean('notify_co_owners')->default(true)->after('walk_reminder_time');
            // Language of pushes sent by the server ("nb" or "en").
            $table->string('locale', 5)->default('nb')->after('notify_co_owners');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_co_owners', 'locale']);
        });

        Schema::dropIfExists('device_tokens');
    }
};
