<?php

namespace App\Services;

use App\Enums\MoyenPaiement;
use App\Enums\Sexe;
use App\Models\Collecte;
use App\Models\Evenement;
use App\Models\ParticipationSympathisant;
use App\Models\Sympathisant;
use App\Models\User;
use App\Models\VisiteSympathisant;
use App\Support\Numerotation;
use DomainException;

/**
 * Les sympathisants : enregistrement au pointage (avec leur accord), visites, visiteurs anonymes,
 * invitation à l'adhésion, participations volontaires et effacement après 2 ans d'absence.
 */
final class SympathisantService
{
    public function __construct(private readonly PointageService $pointage) {}

    /**
     * Enregistre un nouveau sympathisant pendant le pointage, et note sa venue.
     *
     * @param  array{prenom: string, nom: string, sexe: string, telephone?: string|null, invite_par_id?: int|null, accord?: bool}  $donnees
     */
    public function accueillir(Evenement $evenement, array $donnees, User $pointeur): Sympathisant
    {
        if (! ($donnees['accord'] ?? false)) {
            throw new DomainException('Demandez-lui son accord pour être recensé, puis cochez la case.');
        }

        $sexe = Sexe::from($donnees['sexe']);
        $this->verifier($evenement, $sexe, $pointeur);

        if ($evenement->cellule_id === null) {
            throw new DomainException('Les sympathisants se rattachent à une cellule : pointez-les lors d\'une dahira.');
        }

        $sympathisant = Sympathisant::query()->create([
            'prenom' => trim($donnees['prenom']),
            'nom' => mb_strtoupper(trim($donnees['nom'])),
            'sexe' => $sexe,
            'cellule_id' => $evenement->cellule_id,
            'telephone' => TelephoneNormalizer::normaliser($donnees['telephone'] ?? null),
            'invite_par_id' => $donnees['invite_par_id'] ?? null,
            'consentement_le' => now(),
            'cree_par' => $pointeur->id,
        ]);

        $this->noterVisite($evenement, $sympathisant, $pointeur);

        return $sympathisant;
    }

    public function noterVisite(Evenement $evenement, Sympathisant $sympathisant, User $pointeur): void
    {
        $this->verifier($evenement, $sympathisant->sexe, $pointeur);

        VisiteSympathisant::query()->firstOrCreate(
            ['evenement_id' => $evenement->id, 'sympathisant_id' => $sympathisant->id],
            ['enregistree_par' => $pointeur->id],
        );

        $sympathisant->update(['derniere_visite_le' => now()]);
    }

    public function compterVisiteur(Evenement $evenement, int $ecart, User $pointeur): int
    {
        if ($this->pointage->perimetre($pointeur, $evenement) === null || (! $evenement->scanOuvert() && ! $this->pointage->peutCorriger($pointeur))) {
            throw new DomainException('Vous ne pouvez pas compter les visiteurs de ce rendez-vous.');
        }

        $evenement->update(['visiteurs' => max(0, $evenement->visiteurs + $ecart)]);

        return $evenement->visiteurs;
    }

    /** Enregistre la participation volontaire d'un sympathisant, avec son numéro de reçu. */
    public function participer(Collecte $collecte, Sympathisant $sympathisant, int $montant, MoyenPaiement $moyen, User $tresorier): ParticipationSympathisant
    {
        if (! $tresorier->can('sympathisants.participations')) {
            throw new DomainException('Seul le Jewrine finance enregistre les participations.');
        }

        if ($montant < 1) {
            throw new DomainException('Indiquez le montant reçu.');
        }

        return Numerotation::creer(
            ParticipationSympathisant::class,
            'numero_recu',
            'SYM-'.now()->format('Y').'-',
            fn (string $numero): ParticipationSympathisant => ParticipationSympathisant::query()->create([
                'collecte_id' => $collecte->id,
                'sympathisant_id' => $sympathisant->id,
                'montant' => $montant,
                'moyen' => $moyen,
                'numero_recu' => $numero,
                'enregistre_par' => $tresorier->id,
            ]),
        );
    }

    public function lienInvitation(Sympathisant $sympathisant): ?string
    {
        if ($sympathisant->telephone === null || $sympathisant->membre_id !== null) {
            return null;
        }

        $message = 'Assalamou aleykoum '.$sympathisant->prenom.'. Merci pour votre présence fidèle à nos dahiras. '
            .'La Daara Nouroud Daareyni Kaolack serait heureuse de vous compter parmi ses membres. '
            .'Inscription : '.route('inscription');

        return 'https://wa.me/'.ltrim($sympathisant->telephone, '+').'?text='.rawurlencode($message);
    }

    /**
     * Anonymise les sympathisants qui ne sont pas revenus depuis 2 ans : leurs données personnelles
     * sont effacées, leurs visites et leurs participations restent.
     */
    public function purger(): int
    {
        $aAnonymiser = Sympathisant::query()
            ->auRegistre()
            ->whereNull('membre_id')
            ->where(fn ($query) => $query
                ->where('derniere_visite_le', '<', now()->subYears(2))
                ->orWhere(fn ($sansVisite) => $sansVisite->whereNull('derniere_visite_le')->where('created_at', '<', now()->subYears(2))))
            ->get();

        foreach ($aAnonymiser as $sympathisant) {
            app(ConservationService::class)->anonymiserSympathisant($sympathisant);
        }

        return $aAnonymiser->count();
    }

    private function verifier(Evenement $evenement, Sexe $sexe, User $pointeur): void
    {
        $perimetre = $this->pointage->perimetre($pointeur, $evenement);

        if ($perimetre === null) {
            throw new DomainException('Vous ne pouvez pas pointer ce rendez-vous.');
        }

        if (! $evenement->scanOuvert() && ! $this->pointage->peutCorriger($pointeur)) {
            throw new DomainException('La liste de présence est close.');
        }

        if (($perimetre === 'hommes' && $sexe !== Sexe::Homme) || ($perimetre === 'femmes' && $sexe !== Sexe::Femme)) {
            throw new DomainException('Ce sympathisant n\'est pas dans votre périmètre de pointage.');
        }
    }
}
