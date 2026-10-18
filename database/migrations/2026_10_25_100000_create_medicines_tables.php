<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A medicine on a schedule: several times of day, repeated every `interval_days` days from
        // `starts_on`, until `ends_on` (null = forever). Shared by everyone who owns the dog.
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('dose', 100)->nullable();
            $table->json('times');
            $table->unsignedSmallInteger('interval_days')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // One row per dose that was given: which medicine, for which due day and time slot, by whom.
        // The unique key makes ticking the same dose twice (or two owners at once) one dose.
        Schema::create('medicine_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('given_for');
            $table->string('slot', 5);
            $table->timestamp('given_at')->useCurrent();
            $table->timestamps();
            $table->unique(['medicine_id', 'given_for', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_doses');
        Schema::dropIfExists('medicines');
    }
};
