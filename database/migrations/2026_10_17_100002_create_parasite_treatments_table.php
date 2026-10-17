<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parasite_treatments', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['flea', 'tick', 'worm']);
            $table->string('product')->nullable();
            $table->timestamp('given_at')->useCurrent();
            $table->timestamp('next_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parasite_treatments');
    }
};
