<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\User;
use App\Models\Volontariat;
use App\Notifications\NotificationMembre;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Le volontariat : un membre se propose pour une commission d'un événement (CDGMT, Grand Ziar…), et le chef accepte
 * ou non ; ou le chef invite un membre, qui accepte ou décline. Rien ne s'efface.
 */
final class VolontariatService
{
    public function __construct(private readonly ComiteService $comites) {}

    /**
     * Les commissions des comités d'organisation actifs, ouvertes au volontariat.
     *
     * @return Collection<int, CommissionComite>
     */
    public function commissionsOuvertes(): Collection
    {
        return CommissionComite::query()
            ->with(['instance', 'chef'])
            ->enActivite()
            ->whereNotNull('chef_id')
            ->whereHas('instance', fn ($query) => $query->where('statut', 'active')->whereNotIn('type', [Instance::COMITE_DIRECTEUR, Instance::COMMISSION]))
            ->orderBy('instance_id')
            ->orderBy('ordre')
            ->get();
    }

    public function proposer(CommissionComite $commission, Membre $membre, ?string $message, User $auteur): Volontariat
    {
        if ($membre->user_id !== $auteur->id) {
            throw new DomainException('On se porte volontaire pour soi-même.');
        }

        $this->verifierOuverte($commission, $membre);

        $volontariat = Volontariat::query()->create([
            'commission_comite_id' => $commission->id,
            'membre_id' => $membre->id,
            'statut' => Volontariat::PROPOSE,
            'message' => filled($message) ? mb_substr(trim((string) $message), 0, 255) : null,
        ]);

        $commission->chef?->user?->notify(new NotificationMembre(
            'Volontaire : commission '.$commission->nom,
            $membre->nom_complet.' ('.$membre->cellule->nom.') se propose pour votre commission'.($volontariat->message !== null ? ' : « '.$volontariat->message.' »' : '').'.',
            route('comites.index'),
            'Répondre',
        ));

        return $volontariat;
    }

    public function retirer(Volontariat $volontariat, User $auteur): void
    {
        if ($volontariat->membre->user_id !== $auteur->id || $volontariat->statut !== Volontariat::PROPOSE) {
            throw new DomainException('Seule votre proposition en attente peut être retirée.');
        }

        $volontariat->update(['statut' => Volontariat::RETIRE, 'decide_le' => now(), 'decide_par' => $auteur->id]);
    }

    /** Le chef invite un membre (trouvé par ses savoir-faire, par exemple) à rejoindre sa commission. */
    public function inviter(CommissionComite $commission, Membre $membre, User $chef): Volontariat
    {
        if (! ComiteService::dirige($chef, $commission)) {
            throw new DomainException('Seul le chef de la commission invite des volontaires.');
        }

        if ($membre->user_id === null) {
            throw new DomainException('Ce membre n\'a pas de compte pour répondre : ajoutez-le directement à la commission.');
        }

        $this->verifierOuverte($commission, $membre);

        $volontariat = Volontariat::query()->create([
            'commission_comite_id' => $commission->id,
            'membre_id' => $membre->id,
            'statut' => Volontariat::INVITE,
            'invite_par' => $chef->id,
        ]);

        $membre->user?->notify(new NotificationMembre(
            'Invitation : commission '.$commission->nom,
            $chef->name.' vous invite à rejoindre la commission '.$commission->nom.' ('.$commission->instance->nom.').',
            route('volontariat.index'),
            'Répondre',
        ));

        return $volontariat;
    }

    /** Le chef accepte ou non un volontaire : accepté, il entre dans la commission et le comité. */
    public function decider(Volontariat $volontariat, bool $accepte, ?string $motif, User $chef): void
    {
        if (! ComiteService::dirige($chef, $volontariat->commission)) {
            throw new DomainException('Seul le chef de la commission répond aux volontaires.');
        }

        if ($volontariat->statut !== Volontariat::PROPOSE) {
            throw new DomainException('Cette proposition a déjà reçu une réponse.');
        }

        $this->conclure($volontariat, $accepte ? Volontariat::ACCEPTE : Volontariat::REFUSE, $chef, $motif);

        $volontariat->membre->user?->notify(new NotificationMembre(
            'Volontariat : commission '.$volontariat->commission->nom,
            $accepte
                ? 'Votre proposition est acceptée : vous faites partie de la commission '.$volontariat->commission->nom.'. Barakallahou fikoum.'
                : 'Votre proposition n\'est pas retenue cette fois'.(filled($motif) ? ' : '.$motif : '').'. Merci pour votre engagement.',
            route('volontariat.index'),
            'Voir',
        ));
    }

    /** Le membre invité accepte (il entre dans la commission) ou décline. */
    public function repondre(Volontariat $volontariat, bool $accepte, User $auteur): void
    {
        if ($volontariat->membre->user_id !== $auteur->id || $volontariat->statut !== Volontariat::INVITE) {
            throw new DomainException('Cette invitation ne vous attend pas.');
        }

        $this->conclure($volontariat, $accepte ? Volontariat::ACCEPTE : Volontariat::DECLINE, $auteur, null);

        $volontariat->commission->chef?->user?->notify(new NotificationMembre(
            'Invitation : commission '.$volontariat->commission->nom,
            $volontariat->membre->nom_complet.($accepte ? ' accepte de rejoindre votre commission.' : ' décline votre invitation.'),
            route('comites.index'),
            'Voir ma commission',
        ));
    }

    private function conclure(Volontariat $volontariat, string $statut, User $auteur, ?string $motif): void
    {
        if ($statut === Volontariat::ACCEPTE) {
            $this->comites->accueillir($volontariat->commission, $volontariat->membre);
        }

        $volontariat->update([
            'statut' => $statut,
            'decide_le' => now(),
            'decide_par' => $auteur->id,
            'motif_refus' => filled($motif) ? mb_substr(trim((string) $motif), 0, 255) : null,
        ]);
    }

    private function verifierOuverte(CommissionComite $commission, Membre $membre): void
    {
        if ($membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Seuls les membres validés se portent volontaires.');
        }

        if ($commission->estArchive() || $commission->instance->statut !== 'active' || $commission->instance->estComiteDirecteur() || $commission->instance->estCommission()) {
            throw new DomainException('Cette commission n\'accueille pas de volontaires.');
        }

        if ($commission->membres()->whereKey($membre->id)->exists() || $commission->chef_id === $membre->id) {
            throw new DomainException($membre->prenom.' fait déjà partie de la commission '.$commission->nom.'.');
        }

        if (Volontariat::query()->where('commission_comite_id', $commission->id)->where('membre_id', $membre->id)->whereIn('statut', Volontariat::EN_ATTENTE)->exists()) {
            throw new DomainException('Une proposition ou une invitation attend déjà une réponse pour cette commission.');
        }
    }
}
