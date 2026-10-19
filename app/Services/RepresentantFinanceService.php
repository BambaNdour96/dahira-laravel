<?php

namespace App\Services;

use App\Enums\MoyenPaiement;
use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\RemiseFonds;
use App\Models\RepresentantFinance;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Support\Numerotation;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Les représentants du Jewrine finance dans les cellules, sous sa supervision :
 * désignation, validations, encaissements, caisse d'espèces, remises de fonds et alertes.
 */
final class RepresentantFinanceService
{
    /** Ce qu'un représentant peut valider : les cotisations, les Sass et les Jàyante. */
    public const OBJETS = ['cotisation', 'collecte'];

    public function designer(Cellule $cellule, string $perimetre, User $designe, User $auteur): RepresentantFinance
    {
        if (! $auteur->can('representants.designer')) {
            throw new DomainException('Seul le Jewrine finance désigne ses représentants.');
        }

        $membre = $designe->membre;
        $sexe = $perimetre === 'femmes' ? Sexe::Femme : Sexe::Homme;

        if ($membre === null || $membre->statut !== StatutMembre::Valide || $membre->cellule_id !== $cellule->id) {
            throw new DomainException('Le représentant doit être un membre validé de cette cellule.');
        }

        if ($membre->sexe !== $sexe) {
            throw new DomainException($perimetre === 'femmes'
                ? 'Les Sokhnas sont représentées par une Sokhna.'
                : 'Les Goor Yalla sont représentés par un Goor Yalla.');
        }

        $ancien = RepresentantFinance::query()->where('cellule_id', $cellule->id)->where('perimetre', $perimetre)->first();

        if ($ancien !== null && $ancien->user_id !== $designe->id) {
            $this->retirer($ancien, $auteur);
        }

        $representant = RepresentantFinance::query()->updateOrCreate(
            ['cellule_id' => $cellule->id, 'perimetre' => $perimetre],
            ['user_id' => $designe->id, 'designe_par' => $auteur->id],
        );

        app(CasquettesService::class)->synchroniser($designe);

        return $representant;
    }

    /** Met fin à la désignation (elle reste dans l'historique), une fois la caisse remise. */
    public function retirer(RepresentantFinance $representant, User $auteur): void
    {
        if ($this->enCaisse($representant->user) > 0) {
            throw new DomainException('Ce représentant a encore des espèces en caisse : enregistrez d\'abord leur remise.');
        }

        app(ConservationService::class)->mettreFin($representant, $auteur);
    }

    /** @return Collection<int, RepresentantFinance> */
    public function representationsDe(User $utilisateur): Collection
    {
        return RepresentantFinance::query()->with('cellule')->where('user_id', $utilisateur->id)->get();
    }

    /**
     * Les membres que ce représentant couvre.
     *
     * @return Collection<int, Membre>
     */
    public function membresCouverts(User $utilisateur): Collection
    {
        $representations = $this->representationsDe($utilisateur);

        // Les membres « non renouvelés » restent couverts : leur renouvellement s'encaisse aussi en espèces.
        return Membre::query()
            ->whereIn('statut', [StatutMembre::Valide->value, StatutMembre::NonRenouvele->value])
            ->where(function ($query) use ($representations): void {
                foreach ($representations as $representation) {
                    $query->orWhere(fn ($couvert) => $couvert
                        ->where('cellule_id', $representation->cellule_id)
                        ->where('sexe', $representation->sexe()->value));
                }

                if ($representations->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    /**
     * Les paiements déclarés qui attendent la validation de ce représentant.
     *
     * @return Collection<int, Paiement>
     */
    public function aValider(User $utilisateur): Collection
    {
        return Paiement::query()
            ->with(['membre', 'collecte'])
            ->where('statut', StatutPaiement::Declare->value)
            ->whereIn('objet', self::OBJETS)
            ->whereIn('membre_id', $this->membresCouverts($utilisateur)->modelKeys())
            ->where('membre_id', '!=', $utilisateur->membre->id ?? 0)
            ->orderBy('declare_le')
            ->get();
    }

    public function verifier(User $utilisateur, Paiement $paiement): void
    {
        if (! in_array($paiement->objet, self::OBJETS, true) || ! RepresentantFinance::represente($utilisateur, $paiement->membre)) {
            throw new DomainException('Ce paiement ne relève pas de votre périmètre.');
        }
    }

    public function valider(User $utilisateur, Paiement $paiement): void
    {
        $this->verifier($utilisateur, $paiement);
        app(PaiementService::class)->confirmer($paiement, $utilisateur);
    }

    public function refuser(User $utilisateur, Paiement $paiement, string $motif): void
    {
        $this->verifier($utilisateur, $paiement);
        app(PaiementService::class)->refuser($paiement, $utilisateur, $motif);
    }

    /**
     * Les espèces encaissées et pas encore remises au Jewrine finance national.
     *
     * @return Collection<int, Paiement>
     */
    public function operationsEnCaisse(User $representant): Collection
    {
        return Paiement::query()
            ->with(['membre', 'collecte'])
            ->where('traite_par', $representant->id)
            ->where('moyen', MoyenPaiement::Especes->value)
            ->where('statut', StatutPaiement::Confirme->value)
            ->whereNull('remise_id')
            ->orderBy('traite_le')
            ->get();
    }

    public function enCaisse(User $representant): int
    {
        return (int) $this->operationsEnCaisse($representant)->sum('montant');
    }

    /** Le Jewrine finance national reçoit les espèces d'un représentant : la caisse revient à zéro. */
    public function recevoirRemise(User $representant, User $receveur): RemiseFonds
    {
        if (! $receveur->can('caisses.recevoir') || $receveur->id === $representant->id) {
            throw new DomainException('La remise est reçue par le Jewrine finance national.');
        }

        $operations = $this->operationsEnCaisse($representant);

        if ($operations->isEmpty()) {
            throw new DomainException('La caisse de ce représentant est vide.');
        }

        return DB::transaction(function () use ($representant, $receveur, $operations): RemiseFonds {
            $celluleId = RepresentantFinance::query()->where('user_id', $representant->id)->value('cellule_id')
                ?? $operations->first()->membre->cellule_id;

            $remise = Numerotation::creer(
                RemiseFonds::class,
                'numero',
                'REM-'.now()->format('Y').'-',
                fn (string $numero): RemiseFonds => RemiseFonds::query()->create([
                    'numero' => $numero,
                    'cellule_id' => $celluleId,
                    'representant_id' => $representant->id,
                    'recue_par' => $receveur->id,
                    'montant' => (int) $operations->sum('montant'),
                    'nombre_operations' => $operations->count(),
                ]),
            );

            Paiement::query()->whereIn('id', $operations->modelKeys())->update(['remise_id' => $remise->id]);

            return $remise;
        });
    }

    /** Un membre déclare un paiement : son représentant en est prévenu. */
    public static function prevenir(Paiement $paiement): void
    {
        $representant = RepresentantFinance::pour($paiement->membre);

        if ($representant === null || $representant->user_id === $paiement->membre->user_id) {
            return;
        }

        $representant->user->notify(new NotificationMembre(
            'Paiement à valider',
            $paiement->membre->prenom.' '.$paiement->membre->nom.' a déclaré '.CollecteService::fcfa($paiement->montant).' par Wave.',
            route('caisse.index'),
            'Ouvrir ma caisse',
        ));
    }

    /**
     * Chaque matin : alerte le national sur les paiements en attente depuis 48 heures et les caisses à remettre.
     *
     * @return array{en_attente: int, caisses: int}
     */
    public function routineDuMatin(): array
    {
        $enAttente = Paiement::query()
            ->where('statut', StatutPaiement::Declare->value)
            ->whereIn('objet', self::OBJETS)
            ->where('declare_le', '<', now()->subHours(48))
            ->count();

        $seuil = (int) config('dahira.seuil_caisse', 100000);
        $caisses = 0;

        foreach (RepresentantFinance::query()->with(['user', 'cellule'])->get()->unique('user_id') as $representation) {
            $operations = $this->operationsEnCaisse($representation->user);
            $ancienne = $operations->first()?->traite_le;

            if ($operations->sum('montant') >= $seuil || ($ancienne !== null && $ancienne->lt(now()->subDays(15)))) {
                $caisses++;
            }
        }

        $nationaux = User::role(['jewrine_finance', 'super_administrateur'])->get();

        if ($nationaux->isNotEmpty() && ($enAttente > 0 || $caisses > 0)) {
            Notification::make()
                ->title('Trésorerie des cellules')
                ->body(trim(($enAttente > 0 ? $enAttente.' paiement(s) attendent depuis plus de 48 heures. ' : '')
                    .($caisses > 0 ? $caisses.' caisse(s) de représentant à remettre.' : '')))
                ->warning()
                ->sendToDatabase($nationaux);
        }

        return ['en_attente' => $enAttente, 'caisses' => $caisses];
    }
}
