<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une session ouverte, lue dans la table « sessions » de Laravel.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $last_activity
 * @property-read User|null $user
 */
class SessionActive extends Model
{
    protected $table = 'sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** Téléphone ou ordinateur, et navigateur, d'après la signature de l'appareil. */
    public function appareil(): string
    {
        $signature = (string) $this->user_agent;
        $type = preg_match('/Mobile|Android|iPhone|iPad/i', $signature) === 1 ? 'Téléphone' : 'Ordinateur';
        $navigateur = match (true) {
            str_contains($signature, 'Edg') => 'Edge',
            str_contains($signature, 'Chrome') => 'Chrome',
            str_contains($signature, 'Firefox') => 'Firefox',
            str_contains($signature, 'Safari') => 'Safari',
            default => 'navigateur inconnu',
        };

        return $type.' · '.$navigateur;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
