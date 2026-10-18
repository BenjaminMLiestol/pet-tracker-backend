<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many feedings and walks a day counts as "done". Shared by everyone who owns the dog.
        Schema::table('pets', function (Blueprint $table) {
            $table->unsignedTinyInteger('feedings_per_day')->default(1)->after('bath_interval_days');
            $table->unsignedTinyInteger('walks_per_day')->default(2)->after('feedings_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['feedings_per_day', 'walks_per_day']);
        });
    }
};
