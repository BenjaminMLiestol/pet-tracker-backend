<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $configured = config('seeding.users', []);
        if (empty($configured)) {
            $this->command?->warn('No SEED_USER_1_EMAIL set in .env; no users seeded.');

            return;
        }

        foreach ($configured as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);

            $user->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'date_of_birth' => $data['date_of_birth'] ?: null,
            ]);

            // Never overwrite an existing password unless one is configured explicitly.
            if ($data['password']) {
                $user->password = Hash::make($data['password']);
            } elseif (! $user->exists) {
                $generated = Str::random(20);
                $user->password = Hash::make($generated);
                $this->command?->warn("Generated password for {$data['email']}: {$generated} (shown once)");
            }

            $user->save();
        }
    }
}
