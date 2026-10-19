<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\ActivationCompte;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Policies\MembrePolicy;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Un membre dont la fiche existe sans compte (reprise des listes, inscription par le Jewrine) active son compte depuis
 * le téléphone : son numéro et sa date de naissance, plus son matricule s'il le connaît. Avec le bon matricule, le compte
 * est ouvert aussitôt ; sans matricule, le Jewrine de cellule valide la demande. Le mot de passe choisi reste chiffré.
 */
final class ActivationCompteService
{
    /** Le même message pour toute information qui ne correspond pas : on ne révèle pas quelles fiches existent. */
    public const INTROUVABLE = 'Ces informations ne correspondent à aucune fiche à activer. Vérifiez-les, ou contactez votre Jewrine de cellule.';

    public function __construct(private readonly CasquettesService $casquettes) {}

    /** Renvoie « active » si le compte est ouvert, « en_attente » si le Jewrine doit valider. */
    public function demander(string $telephone, string $dateNaissance, ?string $matricule, string $motDePasse): string
    {
        $numero = TelephoneNormalizer::normaliser($telephone);
        $membre = $numero !== null
            ? Membre::query()->with('cellule')->where('telephone', $numero)->whereNull('user_id')->whereNull('responsable_id')
                ->whereIn('statut', [StatutMembre::Valide->value, StatutMembre::NonRenouvele->value])->first()
            : null;

        if ($membre === null || $membre->date_naissance?->format('Y-m-d') !== $dateNaissance || User::query()->where('telephone', $numero)->exists()) {
            throw new DomainException(self::INTROUVABLE);
        }

        if (filled($matricule)) {
            if (mb_strtoupper(trim((string) $matricule)) !== $membre->matricule) {
                throw new DomainException(self::INTROUVABLE);
            }

            $this->ouvrirCompte($membre, (string) $numero, Hash::make($motDePasse));

            return 'active';
        }

        if (ActivationCompte::query()->where('membre_id', $membre->id)->where('statut', ActivationCompte::EN_ATTENTE)->exists()) {
            throw new DomainException('Votre demande attend déjà la validation de votre Jewrine de cellule.');
        }

        ActivationCompte::query()->create(['membre_id' => $membre->id, 'telephone' => (string) $numero, 'mot_de_passe' => Hash::make($motDePasse)]);

        $membre->cellule->jewrine()?->notify(new NotificationMembre(
            'Activation de compte : '.$membre->nom_complet,
            $membre->nom_complet.' demande à activer son compte sans son matricule. Vérifiez que c\'est bien lui, puis validez.',
            route('filament.admin.resources.activations-compte.index'),
            'Examiner',
        ));

        return 'en_attente';
    }

    /** Le Jewrine de cellule (ou ceux qui valident pour toute la Daara) ; jamais pour soi ni pour un proche géré. */
    public function peutDecider(User $utilisateur, ActivationCompte $activation): bool
    {
        $membre = $activation->membre;

        return $this->casquettes->peut($utilisateur, 'membres.valider', $membre->cellule_id)
            && ! MembrePolicy::gereLaFiche($utilisateur, $membre);
    }

    public function valider(ActivationCompte $activation, User $auteur): void
    {
        $this->verifierDecision($activation, $auteur);
        $membre = $activation->membre;

        if ($membre->user_id !== null || User::query()->where('telephone', $activation->telephone)->exists()) {
            throw new DomainException('Ce membre a déjà un compte, ou ce numéro est déjà utilisé.');
        }

        DB::transaction(function () use ($activation, $auteur, $membre): void {
            $this->ouvrirCompte($membre, $activation->telephone, $activation->mot_de_passe);
            $activation->update(['statut' => ActivationCompte::VALIDEE, 'decide_le' => now(), 'decide_par' => $auteur->id]);
        });
    }

    public function refuser(ActivationCompte $activation, User $auteur): void
    {
        $this->verifierDecision($activation, $auteur);
        $activation->update(['statut' => ActivationCompte::REFUSEE, 'decide_le' => now(), 'decide_par' => $auteur->id]);
    }

    private function verifierDecision(ActivationCompte $activation, User $auteur): void
    {
        if ($activation->statut !== ActivationCompte::EN_ATTENTE) {
            throw new DomainException('Cette demande a déjà été traitée.');
        }

        if (! $this->peutDecider($auteur, $activation)) {
            throw new DomainException('Le Jewrine de la cellule valide cette activation.');
        }
    }

    /** Le compte s'ouvre sur son numéro ; le mot de passe arrive déjà chiffré. */
    private function ouvrirCompte(Membre $membre, string $telephone, string $empreinte): void
    {
        DB::transaction(function () use ($membre, $telephone, $empreinte): void {
            $compte = new User(['name' => $membre->nom_complet, 'telephone' => $telephone, 'email' => $membre->email, 'actif' => true]);
            $compte->forceFill(['password' => $empreinte])->save();
            $compte->assignRole('membre');
            $membre->update(['user_id' => $compte->id]);
            JournalActivite::enregistrer($membre, 'modifie', ['apres' => ['compte' => 'activé par le membre']]);
        });
    }
}
