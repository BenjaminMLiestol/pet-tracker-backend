<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;

class PetUserSeeder extends Seeder
{
    public function run(): void
    {
        $pet = Pet::where('name', 'Toya')->first();
        if (! $pet) {
            $this->command->warn('Pet "Toya" not found. Run PetSeeder first.');

            return;
        }

        $ben = User::where('email', 'bennyliestol@gmail.com')->first();
        $camilla = User::where('email', 'camillaab@icloud.com')->first();

        $userIds = collect([$ben?->id, $camilla?->id])->filter()->all();
        if (empty($userIds)) {
            $this->command->warn('No users found to attach to Toya. Run UserSeeder first.');

            return;
        }

        // Attach without removing existing links; ignore duplicates
        $pet->users()->syncWithoutDetaching($userIds);
    }
}
