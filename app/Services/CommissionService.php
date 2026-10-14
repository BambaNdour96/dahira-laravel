<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\AppartenanceCommission;
use App\Models\Commission;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Support\Taches;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Les commissions permanentes : leur Jewrine ajoute et retire les membres, accepte ou refuse les souhaits
 * exprimés à l'inscription. Chaque changement reste dans l'historique (AppartenanceCommission) ; l'espace de
 * la commission (un comité lié) suit la liste de ses membres.
 */
final class CommissionService
{
    public function __construct(private readonly CasquettesService $casquettes) {}

    /** Dirige-t-il cette commission ? Son Jewrine, ou « commissions.gerer » (super administrateur). */
    public static function peutDiriger(User $utilisateur, Commission $commission): bool
    {
        return $utilisateur->can('commissions.gerer')
            || Commission::query()->dirigeesPar($utilisateur)->whereKey($commission->id)->exists();
    }

    /** Le souhait d'un membre, exprimé à son inscription : le Jewrine de la commission est prévenu. */
    public function souhaiter(Membre $membre, Commission $commission): AppartenanceCommission
    {
        $existante = AppartenanceCommission::query()
            ->where('commission_id', $commission->id)->where('membre_id', $membre->id)
            ->whereNull('refuse_le')->whereNull('fin_le')
            ->first();

        if ($existante !== null) {
            return $existante;
        }

        $souhait = AppartenanceCommission::query()->create([
            'commission_id' => $commission->id,
            'membre_id' => $membre->id,
            'souhaite_le' => now(),
        ]);

        if (($jewrine = $commission->jewrine()) !== null) {
            Taches::envoyer(collect([$jewrine]), 'Souhait pour la commission '.$commission->nom,
                $membre->prenom.' '.$membre->nom.' souhaite rejoindre la commission.', route('comites.index'));
        }

        return $souhait;
    }

    public function accepter(AppartenanceCommission $souhait, User $auteur): void
    {
        $this->verifierDirection($auteur, $souhait->commission);

        if (! $souhait->estSouhait()) {
            throw new DomainException('Ce souhait a déjà été traité.');
        }

        if ($souhait->membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Seul un membre validé peut rejoindre une commission ; le souhait attend sa validation.');
        }

        $souhait->forceFill(['debut_le' => now()->toDateString(), 'ajoute_par' => $auteur->id])->save();
        $this->apresChangement($souhait, 'Bienvenue dans la commission '.$souhait->commission->nom.'.');
    }

    public function refuser(AppartenanceCommission $souhait, string $motif, User $auteur): void
    {
        $this->verifierDirection($auteur, $souhait->commission);

        if (! $souhait->estSouhait()) {
            throw new DomainException('Ce souhait a déjà été traité.');
        }

        $souhait->forceFill([
            'refuse_le' => now(),
            'refuse_par' => $auteur->id,
            'motif_refus' => mb_substr(trim($motif), 0, 255) ?: null,
        ])->save();

        if (($compte = $souhait->membre->user) !== null) {
            $compte->notify(new NotificationMembre(
                'Commission '.$souhait->commission->nom,
                'Votre souhait de rejoindre la commission n\'a pas été retenu.'.($souhait->motif_refus ? ' Motif : '.$souhait->motif_refus : ''),
            ));
        }
    }

    /** Le Jewrine ajoute directement un membre validé (son souhait éventuel est accepté). */
    public function ajouter(Commission $commission, Membre $membre, User $auteur): AppartenanceCommission
    {
        $this->verifierDirection($auteur, $commission);

        if ($membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Seul un membre validé peut rejoindre une commission.');
        }

        $existante = AppartenanceCommission::query()
            ->where('commission_id', $commission->id)->where('membre_id', $membre->id)
            ->whereNull('refuse_le')->whereNull('fin_le')
            ->first();

        if ($existante?->estActive()) {
            throw new DomainException($membre->prenom.' '.$membre->nom.' fait déjà partie de la commission.');
        }

        if ($existante !== null) {
            $this->accepter($existante, $auteur);

            return $existante;
        }

        $appartenance = AppartenanceCommission::query()->create([
            'commission_id' => $commission->id,
            'membre_id' => $membre->id,
            'debut_le' => now()->toDateString(),
            'ajoute_par' => $auteur->id,
        ]);

        $this->apresChangement($appartenance, 'Vous faites désormais partie de la commission '.$commission->nom.'.');

        return $appartenance;
    }

    /** Le membre quitte la commission : la date, l'auteur et le motif restent. */
    public function retirer(AppartenanceCommission $appartenance, string $motif, User $auteur): void
    {
        $this->verifierDirection($auteur, $appartenance->commission);

        if (! $appartenance->estActive()) {
            throw new DomainException('Ce membre ne fait plus partie de la commission.');
        }

        $appartenance->forceFill([
            'fin_le' => now(),
            'fin_par' => $auteur->id,
            'motif_fin' => mb_substr(trim($motif), 0, 255) ?: null,
        ])->save();

        $this->apresChangement($appartenance);
    }

    /** Toutes ses appartenances et ses souhaits prennent fin (radiation, anonymisation). */
    public function terminerToutes(Membre $membre, string $motif): void
    {
        $encours = AppartenanceCommission::query()->where('membre_id', $membre->id)->whereNull('refuse_le')->whereNull('fin_le')->get();

        foreach ($encours as $appartenance) {
            $appartenance->forceFill(['fin_le' => now(), 'motif_fin' => $motif])->save();
            $this->synchroniserComite($appartenance->commission);
        }

        if ($membre->user !== null) {
            $this->casquettes->synchroniser($membre->user);
        }
    }

    /** L'espace de la commission : créé avec elle, ou à la première demande. */
    public function comite(Commission $commission): Instance
    {
        return $commission->comite ?? DB::transaction(function () use ($commission): Instance {
            $instance = Instance::query()->create([
                'type' => Instance::COMMISSION,
                'nom' => 'Commission '.$commission->nom,
                'code' => mb_substr('C-'.Str::upper(Str::slug($commission->nom, '')), 0, 15),
                'annee' => (int) now()->format('Y'),
                'commission_id' => $commission->id,
                'mise_en_place' => now(),
                'statut' => 'active',
            ]);
            $commission->setRelation('comite', $instance);
            $this->synchroniserComite($commission);

            return $instance;
        });
    }

    /** Les membres de l'espace suivent exactement ceux de la commission (convocations, PV, « Mes comités »). */
    public function synchroniserComite(Commission $commission): void
    {
        $this->comite($commission)->membres()->sync($commission->membres()->pluck('membres.id')->all());
    }

    private function apresChangement(AppartenanceCommission $appartenance, ?string $bienvenue = null): void
    {
        $this->synchroniserComite($appartenance->commission);

        $compte = $appartenance->membre->user;

        if ($compte === null) {
            return;
        }

        $this->casquettes->synchroniser($compte);

        if ($bienvenue !== null) {
            $compte->notify(new NotificationMembre('Commission '.$appartenance->commission->nom, $bienvenue, route('comites.index'), 'Mes comités'));
        }
    }

    private function verifierDirection(User $auteur, Commission $commission): void
    {
        if (! self::peutDiriger($auteur, $commission)) {
            throw new DomainException('Seul le Jewrine de la commission gère ses membres (le super administrateur en secours).');
        }
    }
}
