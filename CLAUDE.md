# Plateforme NDK – Daara Nouroud Daareyni Kaolack

Plateforme de gestion de la Daara : membres et cartes, dahiras, cotisations, programmes (Magal, Ziar…),
CDGMT, projets, comités, finance. **La source de vérité fonctionnelle est `docs/conception.md`** :
la lire avant tout travail, et ne rien construire qui la contredise sans le signaler.

## Langue et vocabulaire

- Tout le code métier, les textes, les commits et les messages sont **en français**, soigné.
- Vocabulaire de la Daara : **Goor Yalla** (hommes), **Sokhna / Sokhnas** (femmes), **Jàyante** (montant que le
  membre se fixe), **Sass** (minimum imposé par le Jewrine), **Dahira** (séance de cellule), **CDGMT**.
- Pas de « Jewrine des Sokhnas » : le Jewrine de cellule est responsable de tous ses membres.

## Pile technique

Laravel 13, Filament 5 (panneau `/admin`), Livewire, Blade + Tailwind, PostgreSQL (Docker, `compose.dev.yml`),
Pest (tests Unit, Feature, Browser), Larastan niveau 8, Pint. Application web progressive (installable, notifications
Web Push, empreinte WebAuthn). Pas d'API ni d'application native pour l'instant.

## Vérification obligatoire avant chaque commit

```bash
./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest --testsuite=Unit,Feature --coverage --min=90 | tail -6 && ./vendor/bin/pest --testsuite=Browser
```

Ne jamais committer ni pousser si une étape échoue. Couverture minimale : 90 % (objectif : au-dessus de 92 %).
Après une migration : `php artisan migrate`. Après une vue : `php artisan view:clear`. Après le `.env` : `php artisan config:clear`.

## Architecture

- **La logique métier vit dans `app/Services`** (jamais dans les contrôleurs ni les ressources Filament),
  pour pouvoir être réutilisée par une future API.
- Les droits se vérifient dans les services (exceptions `DomainException` avec un message clair en français)
  et dans les `can*()` des ressources Filament.
- Principe de la conception : **une seule vérité** (une donnée saisie une fois, visible partout),
  **la validation de la Finance fait foi**, **rien ne se supprime** (on corrige par une opération nouvelle, tracée).

## Pièges déjà rencontrés : à ne jamais reproduire

1. **Larastan, commentaires de type** : jamais de `@return` ou `@param` sur la même ligne qu'une phrase.
   Écrire un bloc sur plusieurs lignes :
   ```php
   /**
    * Les membres couverts par ce représentant.
    *
    * @return Collection<int, Membre>
    */
   ```
2. **Larastan, listes** : une méthode qui attend `list<int>` reçoit `array_values(...)`.
3. **Larastan, `find()`** : `Model::query()->find($valeur)` peut renvoyer une collection ; pour un seul
   enregistrement, utiliser `->whereKey($valeur)->first()`.
4. **Larastan, `Auth::user()`** : l'assigner à une variable avant de la passer à une méthode typée
   (`($utilisateur = Auth::user()) instanceof User && Service::peut($utilisateur, ...)`).
5. **Filament, noms des paramètres des closures** : Filament injecte par nom. Utiliser exactement
   `$query`, `$record`, `$data`, `$get`, `$set`, `$state`, `$livewire` (jamais `$requete`, `$enregistrement`…).
6. **Filament, Repeater** : `->defaultItems(0)` pour une liste facultative, sinon un élément vide obligatoire apparaît.
7. **Filament, tests** : une action d'en-tête ou de ligne de tableau se teste avec
   `TestAction::make('nom')->table()` ou `->table($record)`.
8. **PostgreSQL** : les tests tournent sur une autre base, donc certains défauts n'y apparaissent pas.
   Une requête `DISTINCT` sur une table qui a des colonnes JSON échoue sous PostgreSQL : dans un
   `Select::relationship()`, restreindre les colonnes (`modifyQueryUsing` avec `->select([...])`).
   Toujours ouvrir l'écran modifié sous PostgreSQL avant de déclarer le travail fini.
9. **Blade** : jamais de `@if … @endif` sur une seule ligne dans une vue qui utilise `@php(...)` :
   écrire un ternaire `{{ $a ? 'x' : 'y' }}` ou un bloc sur plusieurs lignes.
10. **Tests** : les membres créés par la fabrique n'ont pas de matricule ; utiliser le téléphone pour les retrouver.
    Le Jewrine finance reçoit aussi une tâche « paiement à vérifier » à chaque déclaration : ne pas compter
    ses notifications au total, chercher la notification attendue par son contenu.

## Commandes utiles

- Démarrer : `docker compose -f compose.dev.yml up -d`, puis le site, la file d'envois, le tunnel ngrok et le planificateur
  (lanceur `~/demarrer-ndk.sh`).
- Dernière erreur : `grep "local.ERROR" storage/logs/laravel.log | tail -1 | cut -c1-400`
- Remise à zéro (recette uniquement) : `APP_ENV=production php artisan migrate:fresh --seed --force`

## Méthode de travail

- Travailler **par lot**, dans l'ordre de `docs/conception.md` et du plan ci-dessous ; un commit par lot, message en français.
- Avant de coder un lot : résumer en quelques lignes ce qui va changer (écrans, droits, données) et attendre l'accord.
- Écrire les tests du lot (Feature, et Browser pour les écrans du téléphone) avant de déclarer le lot terminé.
- Mettre à jour la documentation utilisateur si un écran change.

## Plan de reconstruction

| Lot | Contenu |
| --- | --- |
| R1 | Acteurs et droits : deux super administrateurs, nominations par le super administrateur ou le Jewrine national, suppression de la Jewrine des Sokhnas, « Dahira » partout, commissions permanentes (Jewrine qui ajoute ses membres), Comité directeur, secrétaire de séance, décisions sans vote |
| R2 | Membres : comptes famille et enfant, inscription par le Jewrine, compétences, volontariat, changement de cellule, renouvellement annuel, cartes par lots, import des listes existantes |
| R3 | Finance : caisses paramétrables (Wave, Orange Money, espèces, banque plus tard), ordres de dépense, corrections (réaffectation, avoir, contre-passation, remboursement), réaffectations, exercices et bilan annuel, libellés de dépenses |
| R4 | Programmes : Jàyante et Sass (minimum) seulement, objectifs et budgets, organisation par comité dédié ou par commissions choisies |
| R5 | Espace des grands événements (CDGMT, Grand Ziar) : onglets, comité de suivi des dépenses, avances et remboursements, clôture et bilans, itinéraire et transport |
| R6 | Pointage sans réseau (dahira et jour J) |
| R7 | Projets, Suivi et Évaluation (questionnaires), tableau de bord du Comité directeur, assemblée générale |
| R8 | Vie de la Daara : annonces, solidarité, calendrier hégirien, matériel, documents |
| R9 | Mise en ligne : sécurité, sauvegardes, surveillance, déclaration CDP |
