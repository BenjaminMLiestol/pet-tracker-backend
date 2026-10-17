<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The pet's home timezone decides where a "day" starts for feeding/walk counts,
        // so co-owners in different timezones see the same "today".
        Schema::table('pets', function (Blueprint $table) {
            $table->string('timezone', 64)->default('Europe/Oslo')->after('breed');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
