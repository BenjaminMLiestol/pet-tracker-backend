<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Use updateOrCreate so seeding is idempotent by unique email
        User::updateOrCreate(
            ['email' => 'bennyliestol@gmail.com'],
            [
                'password' => Hash::make('olemann2k'),
                'first_name' => 'Benjamin',
                'last_name' => 'Barth-Liestøl',
                'date_of_birth' => '1995-10-20',
            ]
        );

        User::updateOrCreate(
            ['email' => 'camillaab@icloud.com'],
            [
                'password' => Hash::make('camilla211097'),
                'first_name' => 'Camilla',
                'last_name' => 'Barth-Liestøl',
                'date_of_birth' => '1997-10-21',
            ]
        );
    }
}
