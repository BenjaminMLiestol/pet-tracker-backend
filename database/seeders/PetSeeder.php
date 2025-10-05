<?php

namespace Database\Seeders;

use App\Models\Pet;
use Illuminate\Database\Seeder;

class PetSeeder extends Seeder
{
    public function run(): void
    {
        // One entry: Toya
        Pet::updateOrCreate(
            ['name' => 'Toya'],
            [
                'age' => 4,
                'breed' => 'Finnish lappdog',
                'date_of_birth' => '2021-10-30',
            ]
        );
    }
}
