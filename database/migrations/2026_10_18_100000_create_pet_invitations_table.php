<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A one-time code an owner shares so someone else can become a co-owner.
        Schema::create('pet_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->timestamp('expires_at');
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_invitations');
    }
};
