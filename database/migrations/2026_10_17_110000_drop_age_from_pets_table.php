<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Age is now derived from date_of_birth. Keep pets that only had an age.
        DB::table('pets')
            ->whereNull('date_of_birth')
            ->whereNotNull('age')
            ->get(['id', 'age'])
            ->each(fn ($pet) => DB::table('pets')->where('id', $pet->id)->update([
                'date_of_birth' => now()->subYears($pet->age)->toDateString(),
            ]));

        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn('age');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->unsignedInteger('age')->nullable()->after('name');
        });
    }
};
