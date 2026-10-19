<?php

namespace App\Services;

use App\Enums\LienFamille;
use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Competence;
use App\Models\Cotisation;
use App\Models\ImportMembres;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\User;
use DateTime;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La reprise des listes existantes, en deux temps : le fichier s'analyse d'abord, sans rien enregistrer (rapport ligne
 * par ligne : prête, erreur, doublon possible), puis l'import se confirme et ne reprend que les lignes prêtes. Les
 * membres importés sont validés, gardent leur origine « Reprise des données », et activent eux-mêmes leur compte.
 */
final class ImportMembresService
{
    /** Les colonnes reconnues : nom de colonne simplifié => champ. */
    private const COLONNES = [
        'matricule' => 'matricule', 'prenom' => 'prenom', 'prenoms' => 'prenom', 'nom' => 'nom', 'sexe' => 'sexe',
        'telephone' => 'telephone', 'tel' => 'telephone', 'email' => 'email', 'mail' => 'email', 'cellule' => 'cellule',
        'datedenaissance' => 'date_naissance', 'datenaissance' => 'date_naissance', 'lieudenaissance' => 'lieu_naissance',
        'lieunaissance' => 'lieu_naissance', 'datedadhesion' => 'date_adhesion', 'dateadhesion' => 'date_adhesion',
        'adresse' => 'adresse', 'quartier' => 'adresse', 'adresseouquartier' => 'adresse', 'ville' => 'ville',
        'savoirfaire' => 'savoir_faire', 'competences' => 'savoir_faire', 'responsable' => 'responsable',
        'matriculeoutelephoneduresponsable' => 'responsable', 'lien' => 'lien', 'liendefamille' => 'lien',
        'cotisationsajourjusqua' => 'cotisations', 'ajourjusqua' => 'cotisations',
    ];

    /** L'en-tête du modèle, dans l'ordre. */
    public const MODELE = [
        'Matricule', 'Prénom', 'Nom', 'Sexe', 'Téléphone', 'Email', 'Cellule', 'Date de naissance', 'Lieu de naissance',
        'Date d\'adhésion', 'Adresse ou quartier', 'Ville', 'Savoir-faire', 'Responsable', 'Lien', 'Cotisations à jour jusqu\'à',
    ];

    public function __construct(
        private readonly CasquettesService $casquettes,
        private readonly MatriculeService $matricules,
        private readonly CarteService $cartes,
        private readonly CotisationService $cotisations,
    ) {}

    /** Importer : toute la Daara (« membres.importer »), ou les cellules qu'il dirige pour le Jewrine. */
    public function peutImporter(User $utilisateur): bool
    {
        return $this->casquettes->peutQuelquePart($utilisateur, 'membres.importer');
    }

    /** Analyse le fichier déposé (CSV), sans rien enregistrer d'autre que le rapport. */
    public function analyser(string $chemin, string $nomFichier, User $auteur): ImportMembres
    {
        if (! $this->peutImporter($auteur)) {
            throw new DomainException('Vous n\'avez pas le droit d\'importer des membres.');
        }

        $lignes = $this->analyserLignes($this->lire($chemin), $auteur);
        $import = ImportMembres::query()->create([
            'auteur_id' => $auteur->id,
            'cellule_id' => $auteur->can('membres.importer') ? null : ($this->casquettes->cellulesDirigees($auteur)[0] ?? null),
            'fichier_nom' => self::nomDeFichier($nomFichier, $chemin),
            'chemin' => $chemin,
            'lignes_total' => count($lignes),
            'lignes_pretes' => count(array_filter($lignes, fn (array $ligne): bool => $ligne['etat'] === ImportMembres::PRETE)),
            'rapport' => array_map(fn (array $ligne): array => array_diff_key($ligne, ['donnees' => true]), $lignes),
        ]);

        JournalActivite::enregistrer($import, 'cree', ['apres' => ['fichier' => $import->fichier_nom, 'lignes' => $import->lignes_total, 'pretes' => $import->lignes_pretes]]);

        return $import;
    }

    /** Importe les lignes prêtes ; le fichier est relu et revérifié au moment de l'import. */
    public function importer(ImportMembres $import, User $auteur): int
    {
        if (! $this->peutImporter($auteur) || ($import->auteur_id !== $auteur->id && ! $auteur->can('membres.importer'))) {
            throw new DomainException('Vous n\'avez pas le droit d\'importer ce fichier.');
        }

        if ($import->statut !== ImportMembres::ANALYSE) {
            throw new DomainException('Ce fichier a déjà été importé.');
        }

        $lignes = $this->analyserLignes($this->lire($import->chemin), $auteur);

        $importees = DB::transaction(function () use ($lignes, $import, $auteur): int {
            $crees = [];
            $nombre = 0;

            foreach ($lignes as $index => $ligne) {
                if ($ligne['etat'] !== ImportMembres::PRETE) {
                    continue;
                }

                $membre = $this->creer($ligne['donnees'], $crees, $import, $auteur);
                $crees[$ligne['numero']] = $membre->id;
                $lignes[$index]['etat'] = ImportMembres::IMPORTEE;
                $lignes[$index]['messages'][] = 'Matricule '.$membre->matricule.'.';
                $nombre++;
            }

            $import->update([
                'statut' => ImportMembres::IMPORTE,
                'importe_le' => now(),
                'lignes_importees' => $nombre,
                'rapport' => array_map(fn (array $ligne): array => array_diff_key($ligne, ['donnees' => true]), $lignes),
            ]);

            return $nombre;
        });

        JournalActivite::enregistrer($import, 'modifie', ['apres' => ['importees' => $importees]]);

        return $importees;
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @param  array<int, int>  $crees  numéro de ligne => membre créé
     */
    private function creer(array $donnees, array $crees, ImportMembres $import, User $auteur): Membre
    {
        $responsableId = $donnees['responsable_id'] ?? (isset($donnees['responsable_ligne']) ? ($crees[$donnees['responsable_ligne']] ?? null) : null);
        $celluleId = $responsableId !== null ? (int) Membre::query()->whereKey($responsableId)->value('cellule_id') : (int) $donnees['cellule_id'];

        $membre = new Membre([
            'prenom' => $donnees['prenom'], 'nom' => $donnees['nom'], 'sexe' => $donnees['sexe'],
            'telephone' => $donnees['telephone'], 'email' => $donnees['email'], 'date_naissance' => $donnees['date_naissance'],
            'lieu_naissance' => $donnees['lieu_naissance'], 'adresse' => $donnees['adresse'], 'ville' => $donnees['ville'],
            'cellule_id' => $celluleId, 'date_adhesion' => $donnees['date_adhesion'] ?? now()->toDateString(),
            'responsable_id' => $responsableId, 'lien_famille' => $responsableId !== null ? $donnees['lien'] : null,
            'inscrit_par' => $auteur->id, 'origine' => Membre::ORIGINE_REPRISE, 'import_id' => $import->id,
        ]);
        $membre->statut = StatutMembre::Valide;
        $membre->valide_le = now();

        if ($donnees['matricule'] !== null) {
            $membre->matricule = $donnees['matricule'];
        }

        $membre->save();

        $this->matricules->attribuer($membre, $membre->date_adhesion?->year);
        $this->cartes->emettre($membre);
        app(RenouvellementService::class)->apresValidation($membre);
        $membre->competences()->sync($donnees['competences']);

        // Les mois déjà réglés avant la plateforme : notés « Reprise des données », sans montant ni reçu.
        if ($donnees['cotisations'] !== null && ($debut = $this->cotisations->debut($membre)) !== null) {
            for ($mois = $debut->copy(); $mois->lte($donnees['cotisations']); $mois->addMonth()) {
                Cotisation::query()->create([
                    'membre_id' => $membre->id, 'mois' => $mois->toDateString(), 'montant' => 0, 'reprise' => true, 'motif' => 'Reprise des données',
                ]);
            }
        }

        return $membre;
    }

    /**
     * Les lignes du fichier, avec leurs champs reconnus.
     *
     * @return list<array<string, string>>
     */
    private function lire(string $chemin): array
    {
        $contenu = Storage::disk('local')->get($chemin);

        if (! is_string($contenu) || trim($contenu) === '') {
            throw new DomainException('Le fichier est vide ou illisible.');
        }

        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu) ?? $contenu;

        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        $lignes = preg_split('/\r\n|\n|\r/', $contenu) ?: [];
        $entete = (string) array_shift($lignes);
        $separateur = substr_count($entete, ';') >= substr_count($entete, ',') ? ';' : ',';
        $champs = array_map(fn (?string $colonne): ?string => self::COLONNES[self::simplifier((string) $colonne)] ?? null, str_getcsv($entete, $separateur, '"', ''));

        if (! in_array('prenom', $champs, true) || ! in_array('nom', $champs, true)) {
            throw new DomainException('Colonnes « Prénom » et « Nom » introuvables : partez du modèle à télécharger.');
        }

        $resultat = [];

        foreach ($lignes as $ligne) {
            if (trim($ligne) === '' || trim(str_replace([$separateur, '"'], '', $ligne)) === '') {
                continue;
            }

            $valeurs = str_getcsv($ligne, $separateur, '"', '');
            $donnees = [];

            foreach ($champs as $position => $champ) {
                if ($champ !== null) {
                    $donnees[$champ] = trim((string) ($valeurs[$position] ?? ''));
                }
            }

            $resultat[] = $donnees;
        }

        return $resultat;
    }

    /**
     * L'analyse de chaque ligne : prête, erreur ou doublon possible, avec ses messages et ses données normalisées.
     *
     * @param  list<array<string, string>>  $lignes
     * @return list<array{numero: int, etat: string, nom: string, messages: list<string>, donnees: array<string, mixed>}>
     */
    private function analyserLignes(array $lignes, User $auteur): array
    {
        $cellules = Cellule::query()->enActivite()->get()->mapWithKeys(fn (Cellule $cellule): array => [self::simplifier($cellule->nom) => $cellule]);
        $competences = Competence::query()->enActivite()->get()->mapWithKeys(fn (Competence $competence): array => [self::simplifier($competence->nom) => $competence->id]);
        $limite = $auteur->can('membres.importer') ? null : $this->casquettes->cellulesDirigees($auteur);
        $vus = ['telephone' => [], 'matricule' => []];
        $resultat = [];

        foreach ($lignes as $index => $brut) {
            $numero = $index + 2;
            $erreurs = [];
            $notes = [];
            $doublon = null;
            $prenom = $brut['prenom'] ?? '';
            $nom = $brut['nom'] ?? '';

            if ($prenom === '' || $nom === '') {
                $erreurs[] = 'Prénom et nom obligatoires.';
            }

            $sexe = self::sexe($brut['sexe'] ?? '');
            if ($sexe === null) {
                $erreurs[] = 'Sexe à indiquer : H (Goor Yalla) ou F (Sokhna).';
            }

            $naissance = self::date($brut['date_naissance'] ?? '', $erreurs, 'Date de naissance');
            $adhesion = self::date($brut['date_adhesion'] ?? '', $erreurs, 'Date d\'adhésion');
            $enfant = $naissance !== null && Carbon::parse($naissance)->addYears(Membre::AGE_MAJORITE)->isFuture();

            $telephone = null;
            if (($brut['telephone'] ?? '') !== '') {
                $telephone = TelephoneNormalizer::normaliser($brut['telephone']);

                $existant = $telephone !== null ? Membre::query()->where('telephone', $telephone)->first() : null;

                if ($telephone === null) {
                    $erreurs[] = 'Téléphone invalide : « '.$brut['telephone'].' ».';
                } elseif ($enfant) {
                    $erreurs[] = 'Un enfant de moins de 18 ans n\'a pas de téléphone : laissez la colonne vide.';
                } elseif (isset($vus['telephone'][$telephone])) {
                    $erreurs[] = 'Téléphone déjà présent à la ligne '.$vus['telephone'][$telephone].'.';
                } elseif ($existant !== null) {
                    $doublon = 'Ce téléphone est déjà celui de '.$existant->nom_complet.'.';
                } elseif (User::query()->where('telephone', $telephone)->exists()) {
                    $doublon = 'Ce téléphone est déjà celui d\'un compte.';
                }

                if ($telephone !== null) {
                    $vus['telephone'][$telephone] ??= $numero;
                }
            }

            $matricule = ($brut['matricule'] ?? '') !== '' ? mb_strtoupper($brut['matricule']) : null;
            if ($matricule !== null) {
                if (preg_match('/^NDK-\d{4}-\d{4}$/', $matricule) !== 1) {
                    $erreurs[] = 'Matricule de la forme NDK-AAAA-NNNN attendu.';
                } elseif (isset($vus['matricule'][$matricule])) {
                    $erreurs[] = 'Matricule déjà présent à la ligne '.$vus['matricule'][$matricule].'.';
                } elseif (Membre::query()->where('matricule', $matricule)->exists()) {
                    $doublon ??= 'Ce matricule existe déjà.';
                }

                $vus['matricule'][$matricule] ??= $numero;
            }

            if ($doublon === null && $prenom !== '' && $nom !== '' && $naissance !== null
                && Membre::query()->where('prenom', Str::title(mb_strtolower($prenom)))->where('nom', mb_strtoupper($nom))->whereDate('date_naissance', $naissance)->exists()) {
                $doublon = 'Un membre de mêmes prénom, nom et date de naissance existe déjà.';
            }

            // Le responsable : un membre de la Daara, ou une ligne prête plus haut dans le fichier.
            $responsableId = null;
            $responsableLigne = null;
            if (($brut['responsable'] ?? '') !== '') {
                $saisie = $brut['responsable'];
                $parTelephone = TelephoneNormalizer::normaliser($saisie);
                $responsable = Membre::query()->whereNull('responsable_id')->whereIn('statut', [StatutMembre::Valide->value])
                    ->where(fn ($query) => $query->where('matricule', mb_strtoupper($saisie))->when($parTelephone !== null, fn ($tel) => $tel->orWhere('telephone', $parTelephone)))
                    ->first();

                if ($responsable !== null) {
                    $responsableId = $responsable->id;
                } else {
                    $ligneResponsable = $vus['matricule'][mb_strtoupper($saisie)] ?? ($parTelephone !== null ? ($vus['telephone'][$parTelephone] ?? null) : null);
                    $etatResponsable = $ligneResponsable !== null && $ligneResponsable < $numero
                        ? (collect($resultat)->firstWhere('numero', $ligneResponsable)['etat'] ?? null)
                        : null;

                    if ($etatResponsable === ImportMembres::PRETE) {
                        $responsableLigne = $ligneResponsable;
                    } else {
                        $erreurs[] = 'Responsable introuvable : « '.$saisie.' » (un membre validé, ou une ligne prête plus haut dans le fichier).';
                    }
                }
            } elseif ($enfant) {
                $erreurs[] = 'Un enfant de moins de 18 ans est rattaché à un parent ou tuteur : colonne « Responsable ».';
            }

            $celluleId = null;
            if ($responsableId === null && $responsableLigne === null) {
                $cellule = $cellules[self::simplifier($brut['cellule'] ?? '')] ?? null;

                if (($brut['cellule'] ?? '') === '') {
                    $erreurs[] = 'Cellule obligatoire.';
                } elseif ($cellule === null) {
                    $erreurs[] = 'Cellule inconnue : « '.$brut['cellule'].' ». Créez-la d\'abord dans Registre → Cellules.';
                } else {
                    $celluleId = $cellule->id;
                }
            } elseif ($responsableId !== null) {
                $celluleId = (int) Membre::query()->whereKey($responsableId)->value('cellule_id');
            } else {
                $celluleId = (int) (collect($resultat)->firstWhere('numero', $responsableLigne)['donnees']['cellule_id'] ?? 0);
            }

            if ($limite !== null && $celluleId !== null && ! in_array($celluleId, $limite, true)) {
                $erreurs[] = 'Cette ligne n\'est pas dans votre cellule.';
            }

            $lien = LienFamille::Enfant->value;
            if (($brut['lien'] ?? '') !== '') {
                $lien = self::lien($brut['lien']);

                if ($lien === null) {
                    $erreurs[] = 'Lien inconnu : « '.$brut['lien'].' » (enfant, pupille, conjoint, parent, frère ou sœur, autre).';
                }
            }

            $email = ($brut['email'] ?? '') !== '' ? $brut['email'] : null;
            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $erreurs[] = 'Email invalide.';
            }

            $savoirFaire = [];
            foreach (array_filter(array_map('trim', explode(';', $brut['savoir_faire'] ?? ''))) as $nomCompetence) {
                $id = $competences[self::simplifier($nomCompetence)] ?? null;

                if ($id === null) {
                    $notes[] = 'Savoir-faire inconnu, ignoré : « '.$nomCompetence.' ».';
                } else {
                    $savoirFaire[] = (int) $id;
                }
            }

            $cotisations = null;
            if (($brut['cotisations'] ?? '') !== '') {
                $cotisations = self::mois($brut['cotisations']);

                if ($cotisations === null) {
                    $erreurs[] = 'Cotisations à jour jusqu\'à : mois attendu, par exemple 12/2026.';
                }
            }

            $resultat[] = [
                'numero' => $numero,
                'etat' => $erreurs !== [] ? ImportMembres::ERREUR : ($doublon !== null ? ImportMembres::DOUBLON : ImportMembres::PRETE),
                'nom' => trim($prenom.' '.$nom),
                'messages' => [...$erreurs, ...($doublon !== null ? [$doublon.' Ligne non importée.'] : []), ...$notes],
                'donnees' => [
                    'matricule' => $matricule, 'prenom' => $prenom, 'nom' => $nom, 'sexe' => $sexe, 'telephone' => $telephone, 'email' => $email,
                    'date_naissance' => $naissance, 'lieu_naissance' => ($brut['lieu_naissance'] ?? '') ?: null, 'date_adhesion' => $adhesion,
                    'adresse' => ($brut['adresse'] ?? '') ?: null, 'ville' => ($brut['ville'] ?? '') ?: null, 'cellule_id' => $celluleId,
                    'responsable_id' => $responsableId, 'responsable_ligne' => $responsableLigne, 'lien' => $lien,
                    'competences' => $savoirFaire, 'cotisations' => $cotisations,
                ],
            ];
        }

        return $resultat;
    }

    /** Le rapport en CSV, à télécharger pour corriger le fichier. */
    public function rapportCsv(ImportMembres $import): string
    {
        $lignes = ['"Ligne";"Nom";"État";"Messages"'];

        foreach ($import->rapport ?? [] as $ligne) {
            $lignes[] = implode(';', array_map(fn (string $valeur): string => '"'.str_replace('"', '""', $valeur).'"', [
                (string) $ligne['numero'], $ligne['nom'], ImportMembres::ETATS[$ligne['etat']] ?? $ligne['etat'], implode(' ', $ligne['messages']),
            ]));
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lignes)."\r\n";
    }

    /** Le modèle à remplir, avec deux exemples (un adulte et son enfant). */
    public static function modeleCsv(): string
    {
        $lignes = [
            self::MODELE,
            ['NDK-2019-0001', 'Cheikh', 'Ndour', 'H', '77 123 45 67', '', 'Médina', '15/03/1980', 'Kaolack', '01/01/2015', 'Médina Baye', 'Kaolack', 'Cuisine; Chauffeur', '', '', '12/2026'],
            ['', 'Fallou', 'Ndour', 'H', '', '', '', '02/05/2016', 'Kaolack', '', '', '', '', 'NDK-2019-0001', 'enfant', ''],
        ];

        return "\xEF\xBB\xBF".implode("\r\n", array_map(fn (array $ligne): string => implode(';', array_map(fn (string $valeur): string => '"'.str_replace('"', '""', $valeur).'"', $ligne)), $lignes))."\r\n";
    }

    /** Le nom d'origine du fichier, lisible ; à défaut, celui du fichier enregistré. */
    private static function nomDeFichier(string $nom, string $chemin): string
    {
        $nom = trim(mb_scrub($nom, 'UTF-8'));

        return mb_substr(mb_check_encoding($nom, 'UTF-8') && preg_match('/^[\p{L}\p{N} ._()\-]+$/u', $nom) === 1 ? $nom : basename($chemin), 0, 255);
    }

    /** Un nom de colonne ou de cellule simplifié : sans accents, sans majuscules, sans espaces ni ponctuation. */
    public static function simplifier(string $texte): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii(trim($texte))));
    }

    private static function sexe(string $valeur): ?string
    {
        return match (self::simplifier($valeur)) {
            'h', 'homme', 'm', 'masculin', 'gooryalla' => Sexe::Homme->value,
            'f', 'femme', 'feminin', 'sokhna' => Sexe::Femme->value,
            default => null,
        };
    }

    private static function lien(string $valeur): ?string
    {
        return match (self::simplifier($valeur)) {
            'enfant', 'fils', 'fille' => LienFamille::Enfant->value,
            'pupille', 'tuteur' => LienFamille::Pupille->value,
            'conjoint', 'epoux', 'epouse', 'mari', 'femme' => LienFamille::Conjoint->value,
            'parent', 'pere', 'mere' => LienFamille::Parent->value,
            'frere', 'soeur', 'frereousoeur' => LienFamille::FrereSoeur->value,
            'autre' => LienFamille::Autre->value,
            default => null,
        };
    }

    /**
     * Une date du fichier (15/03/1990, 1990-03-15 ou 15-03-1990), au format de la base.
     *
     * @param  list<string>  $erreurs
     */
    private static function date(string $valeur, array &$erreurs, string $libelle): ?string
    {
        if ($valeur === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $date = DateTime::createFromFormat('!'.$format, $valeur);

            if ($date !== false && $date->format($format) === $valeur && $date < new DateTime && $date->format('Y') >= '1900') {
                return $date->format('Y-m-d');
            }
        }

        $erreurs[] = $libelle.' invalide : « '.$valeur.' » (par exemple 15/03/1990).';

        return null;
    }

    /** Un mois (12/2026, 2026-12 ou 12-2026), au premier jour du mois. */
    private static function mois(string $valeur): ?Carbon
    {
        if (preg_match('#^(\d{1,2})[/-](\d{4})$#', $valeur, $morceaux) === 1 && (int) $morceaux[1] >= 1 && (int) $morceaux[1] <= 12) {
            return Carbon::create((int) $morceaux[2], (int) $morceaux[1], 1)?->startOfMonth();
        }

        if (preg_match('#^(\d{4})-(\d{1,2})$#', $valeur, $morceaux) === 1 && (int) $morceaux[2] >= 1 && (int) $morceaux[2] <= 12) {
            return Carbon::create((int) $morceaux[1], (int) $morceaux[2], 1)?->startOfMonth();
        }

        return null;
    }
}
