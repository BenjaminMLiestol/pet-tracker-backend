<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Entries logged while offline are sent later, possibly more than once if the reply is lost.
    // The app tags each with a random id so the server can recognise a repeat and answer with
    // the entry it already saved instead of creating a duplicate.
    private const TABLES = ['feedings', 'walks', 'baths', 'weights'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('client_id', 64)->nullable();
                $t->unique(['user_id', 'client_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique("{$table}_user_id_client_id_unique");
                $t->dropColumn('client_id');
            });
        }
    }
};
