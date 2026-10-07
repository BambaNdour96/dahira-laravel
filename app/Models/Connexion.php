<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une tentative de connexion, réussie ou non.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $telephone
 * @property bool $reussie
 * @property string|null $ip
 * @property string|null $navigateur
 * @property Carbon $created_at
 */
class Connexion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'telephone', 'reussie', 'ip', 'navigateur'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reussie' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
