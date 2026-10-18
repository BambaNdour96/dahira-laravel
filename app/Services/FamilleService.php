<?php

namespace App\Services;

use App\Enums\LienFamille;
use App\Enums\StatutMembre;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Policies\MembrePolicy;
use App\Support\AffichageMotDePasse;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Les familles : un membre (le responsable) gère depuis son téléphone les fiches de ses proches, enfants sans compte
 * de connexion ou adultes sans téléphone ; il voit leurs cartes et paie pour eux. Le proche est validé par le Jewrine
 * de cellule comme tout membre, cotise comme tout membre, et peut recevoir plus tard son propre compte, avec tout
 * son historique.
 */
final class FamilleService
{
    public function __construct(private readonly CasquettesService $casquettes) {}

    /**
     * Le responsable ajoute un proche. La fiche part au Jewrine de cellule, comme une inscription ; le proche est
     * dans la cellule du responsable.
     *
     * @param  array{prenom: string, nom: string, sexe: string, date_naissance: string, lien_famille: string, telephone?: string|null, lieu_naissance?: string|null}  $donnees
     */
    public function ajouterProche(Membre $responsable, array $donnees, UploadedFile $photo): Membre
    {
        if ($responsable->statut !== StatutMembre::Valide) {
            throw new DomainException('Votre adhésion doit être validée avant d\'ajouter un proche.');
        }

        if ($responsable->estGereParUnProche()) {
            throw new DomainException('Votre fiche est gérée par un proche : c\'est lui qui ajoute les membres de la famille.');
        }

        $chemin = $photo->store('photos', 'local');

        if ($chemin === false) {
            throw new RuntimeException('Impossible d\'enregistrer la photo.');
        }

        try {
            return DB::transaction(fn (): Membre => $this->creerProche($responsable, $donnees, $chemin));
        } catch (Throwable $e) {
            Storage::disk('local')->delete($chemin);

            throw $e;
        }
    }

    /**
     * @param  array{prenom: string, nom: string, sexe: string, date_naissance: string, lien_famille: string, telephone?: string|null, lieu_naissance?: string|null}  $donnees
     */
    private function creerProche(Membre $responsable, array $donnees, string $photo): Membre
    {
        $telephone = $this->telephoneLibre($donnees['telephone'] ?? null);

        $proche = new Membre([
            'prenom' => $donnees['prenom'],
            'nom' => $donnees['nom'],
            'sexe' => $donnees['sexe'],
            'date_naissance' => $donnees['date_naissance'],
            'lieu_naissance' => $donnees['lieu_naissance'] ?? null,
            'telephone' => $telephone,
            'adresse' => $responsable->adresse,
            'ville' => $responsable->ville,
            'pays_residence' => $responsable->pays_residence,
            'cellule_id' => $responsable->cellule_id,
            'photo_path' => $photo,
            'responsable_id' => $responsable->id,
            'lien_famille' => LienFamille::from($donnees['lien_famille']),
            'inscrit_par' => $responsable->user_id,
            'consentement_le' => now(),
            'consentement_version' => config('dahira.consentement_version'),
        ]);

        if ($proche->estEnfant() && $telephone !== null) {
            throw new DomainException('Un enfant de moins de 18 ans n\'a pas de compte : laissez son téléphone vide.');
        }

        $proche->save();

        return $proche;
    }

    /**
     * Le Jewrine de cellule (ou le secrétariat) inscrit lui-même un membre, par exemple sans téléphone. S'il peut
     * valider dans cette cellule, la fiche est aussitôt acceptée : il ne reste que les frais de carte. Un enfant est
     * rattaché à un parent ou tuteur, membre de la Daara, dont il prend la cellule.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function inscrireParLeJewrine(array $donnees, User $auteur): Membre
    {
        $donnees = $this->rattacher($donnees, null);

        if (! $this->casquettes->peut($auteur, 'membres.inscrire', (int) $donnees['cellule_id'])) {
            throw new DomainException('Vous inscrivez seulement des membres de votre cellule.');
        }

        $donnees['telephone'] = $this->telephoneLibre(isset($donnees['telephone']) ? (string) $donnees['telephone'] : null);

        return DB::transaction(function () use ($donnees, $auteur): Membre {
            $membre = new Membre($donnees);
            $membre->inscrit_par = $auteur->id;

            if ($this->casquettes->peut($auteur, 'membres.valider', $membre->cellule_id)) {
                $membre->statut = StatutMembre::Accepte;
                $membre->accepte_le = now();
            }

            $membre->save();

            return $membre;
        });
    }

    /**
     * Les règles de la famille, à la création comme à la modification d'une fiche dans le panneau : le proche prend
     * la cellule du membre qui gère sa fiche ; un enfant de moins de 18 ans est toujours rattaché à un parent ou
     * tuteur ; un membre qui a son propre compte ne se rattache plus à personne.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    public function rattacher(array $donnees, ?Membre $membre): array
    {
        $responsable = filled($donnees['responsable_id'] ?? null)
            ? Membre::query()->whereKey($donnees['responsable_id'])->first()
            : null;

        if ($responsable === null) {
            $donnees['responsable_id'] = null;
            $donnees['lien_famille'] = null;
            $naissance = filled($donnees['date_naissance'] ?? null) ? (string) $donnees['date_naissance'] : null;

            if ($naissance !== null && (new Membre(['date_naissance' => $naissance]))->estEnfant()) {
                throw new DomainException('Un enfant de moins de 18 ans est rattaché à un parent ou tuteur, membre de la Daara.');
            }

            return $donnees;
        }

        if ($membre !== null && ($membre->user_id !== null || $membre->id === $responsable->id)) {
            throw new DomainException('Un membre qui a son propre compte ne se rattache pas à un proche.');
        }

        if ($responsable->statut !== StatutMembre::Valide || $responsable->estGereParUnProche()) {
            throw new DomainException('Le parent ou tuteur doit être un membre validé, qui gère lui-même sa fiche.');
        }

        $donnees['cellule_id'] = $responsable->cellule_id;
        $donnees['lien_famille'] = filled($donnees['lien_famille'] ?? null) ? $donnees['lien_famille'] : LienFamille::Enfant->value;

        return $donnees;
    }

    /** Peut-il donner son propre compte à ce proche ? Le membre qui gère sa fiche, ou le Jewrine de sa cellule. */
    public function peutDonnerSonCompte(User $utilisateur, Membre $proche): bool
    {
        return $proche->user_id === null
            && $proche->estGereParUnProche()
            && (MembrePolicy::gereLaFiche($utilisateur, $proche) || $this->casquettes->peut($utilisateur, 'membres.modifier', $proche->cellule_id));
    }

    /**
     * Le proche reçoit son propre compte de connexion, sur son numéro : il garde sa fiche, son matricule, sa carte et
     * tout son historique, et sa fiche n'est plus gérée par son responsable. Renvoie le mot de passe provisoire, à lui
     * transmettre ; il le change à la première connexion.
     */
    public function donnerSonCompte(Membre $proche, string $telephone, User $auteur): string
    {
        if (! $this->peutDonnerSonCompte($auteur, $proche)) {
            throw new DomainException('Seuls le membre qui gère cette fiche et le Jewrine de la cellule donnent son compte à un proche.');
        }

        if ($proche->estEnfant()) {
            throw new DomainException('Un enfant de moins de 18 ans n\'a pas de compte de connexion.');
        }

        $numero = $this->telephoneLibre($telephone, $proche);

        if ($numero === null) {
            throw new DomainException('Indiquez le numéro de téléphone du proche : il servira à se connecter.');
        }

        $motDePasse = AffichageMotDePasse::generer();
        $ancienResponsable = $proche->responsable;

        DB::transaction(function () use ($proche, $numero, $motDePasse): void {
            $compte = User::query()->create([
                'name' => $proche->nom_complet,
                'telephone' => $numero,
                'email' => $proche->email,
                'password' => $motDePasse,
                'actif' => true,
            ]);
            $compte->forceFill(['doit_changer_mot_de_passe' => true])->save();
            $compte->assignRole('membre');

            $proche->update(['user_id' => $compte->id, 'telephone' => $numero, 'responsable_id' => null, 'lien_famille' => null]);
            JournalActivite::enregistrer($proche, 'modifie', ['apres' => ['compte' => 'propre compte de connexion']]);
        });

        $compteResponsable = $ancienResponsable?->user;

        if ($compteResponsable !== null && $compteResponsable->id !== $auteur->id) {
            $compteResponsable->notify(new NotificationMembre(
                'Famille : '.$proche->prenom.' a son compte',
                $proche->nom_complet.' se connecte désormais avec son propre numéro ; vous ne gérez plus sa fiche.',
                route('famille.index'),
                'Ma famille',
            ));
        }

        return $motDePasse;
    }

    /**
     * Chaque jour : un proche qui atteint 18 ans sans compte. La plateforme propose, une seule fois, au membre qui gère
     * sa fiche et au Jewrine de sa cellule de lui donner son propre compte, sans l'imposer.
     */
    public function proposerComptesAuxMajeurs(): int
    {
        $proposes = 0;

        $majeurs = Membre::query()
            ->with(['responsable.user', 'cellule'])
            ->whereNull('user_id')
            ->whereNotNull('responsable_id')
            ->whereNull('compte_propose_le')
            ->whereNotNull('date_naissance')
            ->whereDate('date_naissance', '<=', now()->subYears(Membre::AGE_MAJORITE)->toDateString())
            ->whereNotIn('statut', [StatutMembre::Rejete->value, StatutMembre::Radie->value])
            ->get();

        foreach ($majeurs as $proche) {
            $message = $proche->nom_complet.' a 18 ans : vous pouvez lui donner son propre compte, sur son numéro, avec tout son historique. Ce n\'est pas obligatoire.';

            $proche->responsable?->user?->notify(new NotificationMembre(
                'Famille : '.$proche->prenom.' a 18 ans',
                $message,
                route('famille.show', $proche),
                'Voir sa fiche',
            ));

            $jewrine = $proche->cellule->jewrine();

            if ($jewrine !== null && $jewrine->id !== $proche->responsable?->user_id) {
                $jewrine->notify(new NotificationMembre(
                    'Cellule : '.$proche->prenom.' a 18 ans',
                    $message,
                    route('filament.admin.resources.membres.edit', $proche),
                    'Ouvrir la fiche',
                ));
            }

            $proche->forceFill(['compte_propose_le' => now()])->saveQuietly();
            $proposes++;
        }

        return $proposes;
    }

    /**
     * Un numéro facultatif, mais jamais déjà pris par un autre membre ou un autre compte.
     */
    private function telephoneLibre(?string $saisie, ?Membre $sauf = null): ?string
    {
        if (blank($saisie)) {
            return null;
        }

        $numero = TelephoneNormalizer::normaliser($saisie);

        if ($numero === null) {
            throw new DomainException('Numéro invalide. Exemple : 77 123 45 67.');
        }

        $pris = Membre::query()->where('telephone', $numero)->when($sauf !== null, fn ($query) => $query->whereKeyNot($sauf?->id))->exists()
            || User::query()->where('telephone', $numero)->exists();

        if ($pris) {
            throw new DomainException('Ce numéro est déjà utilisé par un autre membre.');
        }

        return $numero;
    }
}
