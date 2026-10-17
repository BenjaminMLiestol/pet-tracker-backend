<?php

namespace App\Services;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Carbon;

/** "Benjamin fed Toya": tells the other owners when someone logs a feeding or a walk. */
class CoOwnerActivity
{
    // Entries logged for earlier than this are corrections or undos, not news.
    private const FRESH_MINUTES = 10;

    private const TEXT = [
        'nb' => ['fed' => '{who} matet {pet}', 'walked' => '{who} gikk tur med {pet}'],
        'en' => ['fed' => '{who} fed {pet}', 'walked' => '{who} walked {pet}'],
    ];

    public function __construct(private ExpoPush $push) {}

    /** @param  'fed'|'walked'  $kind */
    public function announce(string $kind, Pet $pet, User $actor, Carbon $at): void
    {
        if ($at->lt(now()->subMinutes(self::FRESH_MINUTES))) {
            return;
        }

        $others = $pet->users()->where('users.id', '!=', $actor->id)->where('notify_co_owners', true)->get();
        if ($others->isEmpty()) {
            return;
        }

        $this->push->send(
            $others,
            fn (User $to) => [
                'title' => $pet->name,
                'body' => strtr(
                    (self::TEXT[$to->locale] ?? self::TEXT['nb'])[$kind],
                    ['{who}' => $actor->first_name, '{pet}' => $pet->name],
                ),
            ],
            ['type' => 'activity', 'kind' => $kind, 'pet_id' => $pet->id],
        );
    }
}
