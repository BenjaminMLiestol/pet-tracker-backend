<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetInvitation extends Model
{
    /** No 0/O or 1/I/L: codes get read aloud and typed by hand. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const CODE_LENGTH = 8;

    public const TTL_DAYS = 7;

    /** Active invitations a single pet can have at once. */
    public const MAX_ACTIVE = 5;

    protected $fillable = [
        'pet_id', 'invited_by', 'code', 'expires_at',
        'accepted_by', 'accepted_at', 'revoked_at', // set when an invitation is used or cancelled
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function generateCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /** What a person typed ("abcd-efgh ") to what is stored ("ABCDEFGH"). */
    public static function normalize(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
    }

    /** Not used, not revoked and not expired. */
    public function scopeActive($query)
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
