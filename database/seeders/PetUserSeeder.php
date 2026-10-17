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
            $this->command?->warn('Pet "Toya" not found. Run PetSeeder first.');

            return;
        }

        $emails = collect(config('seeding.users', []))->pluck('email')->all();
        $userIds = User::whereIn('email', $emails)->pluck('id')->all();
        if (empty($userIds)) {
            $this->command?->warn('No users found to attach to Toya. Run UserSeeder first.');

            return;
        }

        // Attach without removing existing links; ignore duplicates
        $pet->users()->syncWithoutDetaching($userIds);
    }
}
