<?php

namespace App\Listeners;

use App\Models\Connexion;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Str;

class JournaliserConnexionEchouee
{
    public function handle(Failed $event): void
    {
        $telephone = $event->credentials['telephone'] ?? null;

        Connexion::query()->create([
            'user_id' => $event->user instanceof User ? $event->user->id : null,
            'telephone' => is_string($telephone) ? Str::limit($telephone, 30, '') : null,
            'reussie' => false,
            'ip' => request()->ip(),
            'navigateur' => Str::limit((string) request()->userAgent(), 250, ''),
        ]);
    }
}
