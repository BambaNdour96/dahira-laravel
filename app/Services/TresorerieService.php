<?php

namespace App\Services;

use App\Enums\MoyenPaiement;
use App\Enums\StatutPaiement;
use App\Enums\TypeCaisse;
use App\Models\Caisse;
use App\Models\MouvementCaisse;
use App\Models\Paiement;
use App\Models\ParticipationSympathisant;
use App\Models\RemiseFonds;
use App\Models\RepresentantFinance;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * La trésorerie : chaque franc validé entre dans une caisse (Wave, Orange Money, caisse générale, caisse du représentant
 * qui l'a reçu en espèces) ; la remise d'un représentant fait passer ses espèces dans la caisse générale. Le solde d'une
 * caisse est la somme de ses mouvements, qui ne s'effacent jamais.
 */
final class TresorerieService
{
    /** La caisse en activité d'un type (Wave, Orange Money, caisse générale). */
    public function caisse(TypeCaisse $type): Caisse
    {
        return Caisse::query()->enActivite()->where('type', $type->value)->orderBy('ordre')->orderBy('id')->first()
            ?? throw new DomainException('Aucune caisse « '.$type->getLabel().' » en activité : créez-la dans Trésorerie → Caisses.');
    }

    /** La caisse d'un représentant finance, créée à sa première désignation. */
    public function caisseDuRepresentant(User $representant): Caisse
    {
        $existante = Caisse::query()->where('user_id', $representant->id)->first();

        if ($existante !== null) {
            return $existante;
        }

        $cellule = RepresentantFinance::query()->withoutGlobalScope(RepresentantFinance::EN_COURS)->where('user_id', $representant->id)->with('cellule')->latest('id')->first()?->cellule;

        $nom = 'Caisse de '.$representant->name.($cellule !== null ? ' ('.$cellule->nom.')' : '');

        return Caisse::query()->create([
            'nom' => Caisse::query()->where('nom', $nom)->exists() ? $nom.' · '.$representant->id : $nom,
            'type' => TypeCaisse::Representant,
            'user_id' => $representant->id,
            'ordre' => 10,
        ]);
    }

    /** La caisse où entre un paiement : celle de son moyen, ou celle du représentant qui a reçu les espèces. */
    public function caissePour(Paiement $paiement): Caisse
    {
        return match ($paiement->moyen) {
            MoyenPaiement::Wave => $this->caisse(TypeCaisse::Wave),
            MoyenPaiement::OrangeMoney => $this->caisse(TypeCaisse::OrangeMoney),
            MoyenPaiement::Especes => $paiement->traite_par !== null && RepresentantFinance::query()->withoutGlobalScope(RepresentantFinance::EN_COURS)->where('user_id', $paiement->traite_par)->exists()
                ? $this->caisseDuRepresentant($paiement->traitePar ?? throw new DomainException('Représentant introuvable.'))
                : $this->caisse(TypeCaisse::Especes),
        };
    }

    /** Un paiement validé entre dans sa caisse (une seule fois). */
    public function encaisser(Paiement $paiement): void
    {
        if ($paiement->statut !== StatutPaiement::Confirme || MouvementCaisse::query()->where('paiement_id', $paiement->id)->where('nature', 'encaissement')->exists()) {
            return;
        }

        DB::transaction(function () use ($paiement): void {
            $caisse = $this->caissePour($paiement);
            $paiement->forceFill(['caisse_id' => $caisse->id])->saveQuietly();

            MouvementCaisse::query()->create([
                'caisse_id' => $caisse->id, 'sens' => MouvementCaisse::ENTREE, 'montant' => $paiement->montant, 'nature' => 'encaissement',
                'libelle' => $paiement->libelleObjet().' · '.$paiement->membre->nom_complet.($paiement->numero_recu !== null ? ' · '.$paiement->numero_recu : ''),
                'paiement_id' => $paiement->id, 'auteur_id' => $paiement->traite_par, 'date_operation' => $paiement->traite_le ?? now(),
            ]);
        });
    }

    /** La participation d'un sympathisant, enregistrée par la Finance. */
    public function encaisserParticipation(ParticipationSympathisant $participation): void
    {
        $caisse = match ($participation->moyen) {
            MoyenPaiement::Wave => $this->caisse(TypeCaisse::Wave),
            MoyenPaiement::OrangeMoney => $this->caisse(TypeCaisse::OrangeMoney),
            MoyenPaiement::Especes => $this->caisse(TypeCaisse::Especes),
        };

        MouvementCaisse::query()->create([
            'caisse_id' => $caisse->id, 'sens' => MouvementCaisse::ENTREE, 'montant' => $participation->montant, 'nature' => 'encaissement',
            'libelle' => 'Participation d\'un sympathisant · '.$participation->numero_recu, 'participation_id' => $participation->id,
            'auteur_id' => $participation->enregistre_par, 'date_operation' => now(),
        ]);
    }

    /** La remise d'un représentant : ses espèces sortent de sa caisse et entrent dans la caisse générale. */
    public function transfererRemise(RemiseFonds $remise): void
    {
        $depuis = $this->caisseDuRepresentant($remise->representant);
        $vers = $this->caisse(TypeCaisse::Especes);

        foreach ([[$depuis, MouvementCaisse::SORTIE], [$vers, MouvementCaisse::ENTREE]] as [$caisse, $sens]) {
            MouvementCaisse::query()->create([
                'caisse_id' => $caisse->id, 'sens' => $sens, 'montant' => $remise->montant, 'nature' => 'remise',
                'libelle' => 'Remise '.$remise->numero.' · '.$remise->representant->name, 'remise_id' => $remise->id,
                'auteur_id' => $remise->recue_par, 'date_operation' => now(),
            ]);
        }
    }

    /**
     * Les caisses en activité et leur solde, pour le tableau de trésorerie.
     *
     * @return list<array{caisse: Caisse, solde: int}>
     */
    public function soldes(): array
    {
        return array_values(Caisse::query()->enActivite()->orderBy('ordre')->orderBy('nom')->get()
            ->map(fn (Caisse $caisse): array => ['caisse' => $caisse, 'solde' => $caisse->solde()])
            ->all());
    }
}
