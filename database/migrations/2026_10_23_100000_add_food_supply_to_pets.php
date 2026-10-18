<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The current bag of food: its size, how much the dog gets a day, and the day it was
        // opened. All optional: a dog without them simply has no food tracker. Shared by owners.
        Schema::table('pets', function (Blueprint $table) {
            $table->unsignedInteger('food_bag_grams')->nullable()->after('walks_per_day');
            $table->unsignedSmallInteger('food_daily_grams')->nullable()->after('food_bag_grams');
            $table->date('food_started_on')->nullable()->after('food_daily_grams');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['food_bag_grams', 'food_daily_grams', 'food_started_on']);
        });
    }
};
