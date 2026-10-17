<?php

/*
 * Users created by `php artisan db:seed`, read from the environment so no
 * credentials live in the repository. Define SEED_USER_1_*, SEED_USER_2_*, ...
 * (see .env.example). A user without a password gets a random one, printed once.
 */
$users = [];
for ($i = 1; $i <= 10; $i++) {
    $email = env("SEED_USER_{$i}_EMAIL");
    if (! $email) {
        continue;
    }
    $users[] = [
        'email' => $email,
        'password' => env("SEED_USER_{$i}_PASSWORD"),
        'first_name' => env("SEED_USER_{$i}_FIRST_NAME", 'Seed'),
        'last_name' => env("SEED_USER_{$i}_LAST_NAME", 'User'),
        'date_of_birth' => env("SEED_USER_{$i}_DATE_OF_BIRTH"),
    ];
}

return ['users' => $users];
