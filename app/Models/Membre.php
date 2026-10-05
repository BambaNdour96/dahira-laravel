<?php

namespace App\Models;

use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Services\TelephoneNormalizer;
use Database\Factories\MembreFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $matricule
 * @property string $prenom
 * @property string $nom
 * @property Sexe $sexe
 * @property Carbon|null $date_naissance
 * @property string $telephone
 * @property string|null $email
 * @property int $section_id
 * @property string|null $photo_path
 * @property string|null $fonction
 * @property StatutMembre $statut
 * @property string|null $motif_statut
 * @property Carbon|null $date_adhesion
 * @property Carbon|null $valide_le
 * @property int|null $valide_par
 * @property Carbon|null $consentement_le
 * @property string|null $consentement_version
 */
class Membre extends Model
{
    /** @use HasFactory<MembreFactory> */
    use HasFactory;

    /**
     * Valeurs par défaut connues dès la création de l'objet,
     * avant même l'enregistrement en base.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'en_attente',
    ];

    protected $fillable = [
        'user_id', 'prenom', 'nom', 'sexe', 'date_naissance', 'telephone', 'email',
        'section_id', 'photo_path', 'fonction', 'date_adhesion',
        'consentement_le', 'consentement_version',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sexe' => Sexe::class,
            'statut' => StatutMembre::class,
            'date_naissance' => 'date',
            'date_adhesion' => 'date',
            'valide_le' => 'datetime',
            'consentement_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /** @return Attribute<string, never> */
    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->prenom.' '.$this->nom));
    }

    /** @return Attribute<never, string> */
    protected function telephone(): Attribute
    {
        return Attribute::set(fn (string $valeur): string => TelephoneNormalizer::normaliser($valeur) ?? $valeur);
    }
}
