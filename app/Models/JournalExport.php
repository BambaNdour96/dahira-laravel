<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un document contenant des données personnelles, téléchargé depuis la plateforme.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $document
 * @property string|null $details
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class JournalExport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'document', 'details'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
