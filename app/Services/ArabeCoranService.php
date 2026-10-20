<?php

namespace App\Services;

use App\Enums\NiveauComprehension;
use App\Enums\NiveauCoran;
use App\Enums\NiveauKhassaides;
use App\Enums\NiveauLecture;
use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Membre;
use App\Models\ProfilArabeCoran;
use App\Models\User;
use App\Policies\MembrePolicy;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/**
 * La rubrique « Arabe et Coran » : lecture, écriture, compréhension, Coran mémorisé, khassaides, souhait d'apprendre.
 * Confidentielle : le membre (ou celui qui gère sa fiche) la remplit ; la commission Culturelle et le super administrateur
 * voient le recensement ; le Jewrine de cellule voit seulement qui, dans sa cellule, souhaite apprendre (nom et téléphone).
 */
final class ArabeCoranService
{
    /** Les critères du recensement : clé => libellé. */
    public const CRITERES = [
        'lit_couramment' => 'Lit couramment ou mieux',
        'comprend' => 'Comprend l\'essentiel ou mieux',
        'hafiz' => 'Hafiz',
        'kourel' => 'Peut animer un kourel',
        'souhaite_apprendre' => 'Souhaite apprendre',
        'non_rempli' => 'Rubrique non remplie',
    ];

    public function __construct(private readonly CasquettesService $casquettes) {}

    /**
     * Les règles du formulaire de la rubrique.
     *
     * @return array<string, list<mixed>>
     */
    public static function regles(): array
    {
        return [
            'lecture' => ['nullable', Rule::enum(NiveauLecture::class)],
            'ecriture' => ['nullable', Rule::enum(NiveauLecture::class)],
            'comprehension' => ['nullable', Rule::enum(NiveauComprehension::class)],
            'coran' => ['nullable', Rule::enum(NiveauCoran::class)],
            'nombre_hizb' => ['nullable', 'required_if:coran,'.NiveauCoran::Hizb->value, 'integer', 'min:1', 'max:'.NiveauCoran::HIZB_MAX],
            'khassaides' => ['nullable', Rule::enum(NiveauKhassaides::class)],
            'souhaite_apprendre' => ['nullable', 'in:0,1'],
        ];
    }

    /**
     * Les valeurs du formulaire, prêtes à enregistrer.
     *
     * @param  array<string, mixed>  $saisie
     * @return array{lecture: ?string, ecriture: ?string, comprehension: ?string, coran: ?string, nombre_hizb: ?int, khassaides: ?string, souhaite_apprendre: ?bool}
     */
    public static function donnees(array $saisie): array
    {
        $texte = fn (string $cle): ?string => filled($saisie[$cle] ?? null) ? (string) $saisie[$cle] : null;

        return [
            'lecture' => $texte('lecture'), 'ecriture' => $texte('ecriture'), 'comprehension' => $texte('comprehension'),
            'coran' => $texte('coran'), 'nombre_hizb' => filled($saisie['nombre_hizb'] ?? null) ? (int) $saisie['nombre_hizb'] : null,
            'khassaides' => $texte('khassaides'),
            'souhaite_apprendre' => filled($saisie['souhaite_apprendre'] ?? null) ? $saisie['souhaite_apprendre'] === '1' : null,
        ];
    }

    /**
     * Rien n'est saisi : on n'ouvre pas de fiche vide.
     *
     * @param  array<string, mixed>  $donnees
     */
    public static function estVide(array $donnees): bool
    {
        return array_filter($donnees, fn (mixed $valeur): bool => $valeur !== null) === [];
    }

    /** La remplir : le membre lui-même, ou celui qui gère la fiche de son proche. */
    public static function peutRemplir(User $utilisateur, Membre $membre): bool
    {
        return $membre->user_id === $utilisateur->id || MembrePolicy::gereLaFiche($utilisateur, $membre);
    }

    /** Le recensement : les membres et le Jewrine de la commission Culturelle, et le super administrateur. */
    public function peutVoirRecensement(User $utilisateur): bool
    {
        if ($utilisateur->can('arabe_coran.voir')) {
            return true;
        }

        $culturelle = Commission::query()->where('code', Commission::CULTURELLE)->first();

        if ($culturelle === null) {
            return false;
        }

        return ($utilisateur->membre !== null && $culturelle->membres()->whereKey($utilisateur->membre->id)->exists())
            || Commission::query()->dirigeesPar($utilisateur)->whereKey($culturelle->id)->exists();
    }

    /** La liste des membres qui souhaitent apprendre : le recensement, ou un Jewrine de cellule pour la sienne. */
    public function peutVoirApprenants(User $utilisateur): bool
    {
        return $this->peutVoirRecensement($utilisateur) || $this->casquettes->peutQuelquePart($utilisateur, 'arabe_coran.apprenants');
    }

    /**
     * Enregistre la rubrique.
     *
     * @param  array{lecture: ?string, ecriture: ?string, comprehension: ?string, coran: ?string, nombre_hizb: ?int, khassaides: ?string, souhaite_apprendre: ?bool}  $donnees
     */
    public function enregistrer(Membre $membre, array $donnees, User $auteur): ProfilArabeCoran
    {
        if (! self::peutRemplir($auteur, $membre)) {
            throw new DomainException('Seuls le membre et celui qui gère sa fiche remplissent la rubrique Arabe et Coran.');
        }

        $coran = $donnees['coran'] !== null ? NiveauCoran::from($donnees['coran']) : null;
        $hizb = $coran === NiveauCoran::Hizb ? $donnees['nombre_hizb'] : null;

        if ($coran === NiveauCoran::Hizb && ($hizb === null || $hizb < 1 || $hizb > NiveauCoran::HIZB_MAX)) {
            throw new DomainException('Indiquez le nombre de hizb mémorisés, de 1 à '.NiveauCoran::HIZB_MAX.' (60 hizb : hafiz).');
        }

        return ProfilArabeCoran::query()->updateOrCreate(['membre_id' => $membre->id], [
            'lecture' => $donnees['lecture'] !== null ? NiveauLecture::from($donnees['lecture']) : null,
            'ecriture' => $donnees['ecriture'] !== null ? NiveauLecture::from($donnees['ecriture']) : null,
            'comprehension' => $donnees['comprehension'] !== null ? NiveauComprehension::from($donnees['comprehension']) : null,
            'coran' => $coran,
            'nombre_hizb' => $hizb,
            'khassaides' => $donnees['khassaides'] !== null ? NiveauKhassaides::from($donnees['khassaides']) : null,
            'souhaite_apprendre' => $donnees['souhaite_apprendre'],
            'renseigne_par' => $auteur->id,
        ]);
    }

    /**
     * Les membres validés, filtrés par cellule et par critère, avec leur rubrique.
     *
     * @return Builder<Membre>
     */
    public function requete(?int $celluleId, ?string $critere): Builder
    {
        return Membre::query()
            ->with(['cellule', 'profilArabeCoran', 'responsable'])
            ->where('statut', StatutMembre::Valide->value)
            ->when($celluleId !== null, fn (Builder $query) => $query->where('cellule_id', $celluleId))
            ->when($critere !== null && $critere !== 'non_rempli', fn (Builder $query) => $query->whereHas('profilArabeCoran', fn (Builder $profil) => match ($critere) {
                'lit_couramment' => $profil->whereIn('lecture', [NiveauLecture::Couramment->value, NiveauLecture::Excellent->value]),
                'comprend' => $profil->whereIn('comprehension', [NiveauComprehension::Essentiel->value, NiveauComprehension::Maitrise->value]),
                'hafiz' => $profil->where('coran', NiveauCoran::Hafiz->value),
                'kourel' => $profil->where('khassaides', NiveauKhassaides::Kourel->value),
                'souhaite_apprendre' => $profil->where('souhaite_apprendre', true),
                default => $profil,
            }))
            ->when($critere === 'non_rempli', fn (Builder $query) => $query->whereDoesntHave('profilArabeCoran', fn (Builder $profil) => $profil->whereNotNull('lecture')))
            ->orderBy('nom')
            ->orderBy('prenom');
    }

    /**
     * Les chiffres du recensement, cellule par cellule.
     *
     * @return list<array{cellule: string, membres: int, remplies: int, lisent: int, hafiz: int, hizb: int, kourel: int, apprendre: int}>
     */
    public function chiffresParCellule(): array
    {
        $resultat = [];

        foreach (Cellule::query()->enActivite()->orderBy('nom')->get() as $cellule) {
            $profils = ProfilArabeCoran::query()
                ->whereHas('membre', fn (Builder $membre) => $membre->where('cellule_id', $cellule->id)->where('statut', StatutMembre::Valide->value))
                ->get();

            $resultat[] = [
                'cellule' => $cellule->nom,
                'membres' => $cellule->membres()->where('statut', StatutMembre::Valide->value)->count(),
                'remplies' => $profils->filter(fn (ProfilArabeCoran $profil): bool => $profil->estRemplie())->count(),
                'lisent' => $profils->filter(fn (ProfilArabeCoran $profil): bool => in_array($profil->lecture, [NiveauLecture::Couramment, NiveauLecture::Excellent], true))->count(),
                'hafiz' => $profils->where('coran', NiveauCoran::Hafiz)->count(),
                'hizb' => (int) $profils->sum(fn (ProfilArabeCoran $profil): int => $profil->coran === NiveauCoran::Hafiz ? 60 : (int) $profil->nombre_hizb),
                'kourel' => $profils->where('khassaides', NiveauKhassaides::Kourel)->count(),
                'apprendre' => $profils->where('souhaite_apprendre', true)->count(),
            ];
        }

        return $resultat;
    }

    /**
     * Les membres qui souhaitent apprendre, dans les cellules que l'on voit : nom et téléphone, rien d'autre.
     *
     * @return Collection<int, Membre>
     */
    public function apprenants(User $utilisateur): Collection
    {
        $cellules = $this->peutVoirRecensement($utilisateur) || $utilisateur->can('arabe_coran.apprenants')
            ? null
            : $this->casquettes->cellulesDirigees($utilisateur);

        return Membre::query()
            ->with(['cellule', 'responsable'])
            ->where('statut', StatutMembre::Valide->value)
            ->whereHas('profilArabeCoran', fn (Builder $profil) => $profil->where('souhaite_apprendre', true))
            ->when($cellules !== null, fn (Builder $query) => $query->whereIn('cellule_id', $cellules ?? []))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }
}
