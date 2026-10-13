<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un rappel envoyé à un membre pour un programme (fenêtre ou notification).
 *
 * @property int $id
 * @property int $collecte_id
 * @property int $membre_id
 * @property string $canal
 * @property string $type
 * @property Carbon $envoye_le
 */
class Rappel extends Model
{
    public $timestamps = false;

    protected $fillable = ['collecte_id', 'membre_id', 'canal', 'type', 'envoye_le'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['envoye_le' => 'datetime'];
    }
}
