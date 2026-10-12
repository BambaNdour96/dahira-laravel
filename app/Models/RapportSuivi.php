<?php

namespace App\Models;

use App\Enums\TypeRapport;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un rapport rédigé : sur un projet, une collecte, ou la Daara en général.
 *
 * @property int $id
 * @property string $titre
 * @property TypeRapport $type
 * @property int|null $projet_id
 * @property int|null $collecte_id
 * @property Carbon|null $periode_debut
 * @property Carbon|null $periode_fin
 * @property string $contenu
 * @property string|null $recommandations
 * @property string $statut
 * @property Carbon|null $publie_le
 * @property int|null $auteur_id
 * @property-read Projet|null $projet
 * @property-read Collecte|null $collecte
 * @property-read User|null $auteur
 */
class RapportSuivi extends Model
{
    use Journalise;

    protected $table = 'rapports_suivi';

    protected $attributes = ['statut' => 'brouillon'];

    protected $fillable = [
        'titre', 'type', 'projet_id', 'collecte_id', 'periode_debut', 'periode_fin',
        'contenu', 'recommandations', 'statut', 'publie_le', 'auteur_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => TypeRapport::class,
            'periode_debut' => 'date',
            'periode_fin' => 'date',
            'publie_le' => 'datetime',
        ];
    }

    public function estPublie(): bool
    {
        return $this->statut === 'publie';
    }

    public function sujet(): string
    {
        return match (true) {
            $this->projet !== null => 'Projet : '.$this->projet->titre,
            $this->collecte !== null => 'Collecte : '.$this->collecte->titre,
            default => 'La Daara en général',
        };
    }

    /** Texte mis en forme, débarrassé de tout ce qui n'est pas de la mise en forme simple. */
    public static function propre(?string $html): string
    {
        return strip_tags((string) $html, '<p><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><blockquote><a><hr>');
    }

    /** @return BelongsTo<Projet, $this> */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }

    /** @return BelongsTo<Collecte, $this> */
    public function collecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
