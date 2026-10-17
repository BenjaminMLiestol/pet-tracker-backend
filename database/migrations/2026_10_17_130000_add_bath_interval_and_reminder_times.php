<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared by everyone who owns the pet.
        Schema::table('pets', function (Blueprint $table) {
            $table->unsignedSmallInteger('bath_interval_days')->default(30)->after('timezone');
        });

        // Personal: each person picks when they want to be reminded ("HH:MM", 24h).
        Schema::table('users', function (Blueprint $table) {
            $table->string('feeding_reminder_time', 5)->default('18:00')->after('date_of_birth');
            $table->string('walk_reminder_time', 5)->default('19:00')->after('feeding_reminder_time');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn('bath_interval_days');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['feeding_reminder_time', 'walk_reminder_time']);
        });
    }
};
