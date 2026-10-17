<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Logins issued before tokens expired lived forever. Give them one more week; an app
        // that is opened in that time swaps its token for a fresh one.
        DB::table('personal_access_tokens')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addDays(7)]);
    }

    public function down(): void {}
};
