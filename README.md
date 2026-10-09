# Plateforme des membres – Daara Nouroud Daareyni Kaolack (NDK)

Plateforme web de gestion des membres de la Daara : inscription en ligne, carte de membre virtuelle vérifiable par QR code, cotisations par Wave, panneau du bureau et présence aux événements par scan.

![Laravel 13](https://img.shields.io/badge/Laravel-13-red) ![Filament 5](https://img.shields.io/badge/Filament-5-orange) ![PHP 8.4](https://img.shields.io/badge/PHP-8.4-blue) ![Tests](https://img.shields.io/badge/tests-320%2B-green) ![Couverture](https://img.shields.io/badge/couverture-93%25-green)

## Fonctionnalités

### Pour les membres

- **Inscription en deux pages** : identité et photo (prise directement avec l'appareil ou choisie dans la galerie), puis contact, cellule et commission.
- **Connexion par numéro de téléphone**, avec choix du pays : Sénégal par défaut, et indicatifs de la diaspora.
- **Carte de membre virtuelle** avec QR code, consultable **hors connexion**.
- **Cotisations** de 1, 2 ou 3 mois par Wave (liens à montant fixe), calendrier de l'année, reçus PDF numérotés (`INS-AAAA-NNNN`, `COT-AAAA-NNNN`).
- **Mes notifications** : demande acceptée ou à corriger, paiement confirmé ou refusé, carte remplacée, connexion depuis un nouvel appareil ; copie par email.
- **Mes participations** aux événements de la Daara.
- **Données personnelles** : téléchargement de ses données et demande de suppression du compte.
- **Application installable** sur le téléphone (PWA).

### Pour le bureau (panneau `/admin`)

- **Registre des membres** par cellule et commission, parcours d'adhésion à 8 statuts, matricule `NDK-AAAA-NNNN`.
- **Trésorerie** : vérification des déclarations Wave (onglet « À vérifier »), paiements en espèces, rapport financier, exports Excel.
- **Validation croisée** des paiements du bureau : Jewrine finance ↔ Secrétaire général ↔ Jewrine national, règles modifiables.
- **Tâches et notifications** : cloche ciblée, compteurs dans le menu, bloc « Mes tâches », alerte quotidienne des tâches en retard.
- **Relances** des retardataires par WhatsApp et par email.
- **Événements** : contrôleurs désignés, présence enregistrée en scannant la carte, taux de participation, export Excel.
- **Super administrateur** : comptes du bureau, réglages, journal d'activité, historique des connexions, personnes connectées, « voir en tant que », mode maintenance, demandes de suppression.

## Technologies

| Composant | Choix |
| --- | --- |
| Langage et cadre | PHP 8.3 (8.4 en production), Laravel 13 |
| Panneau du bureau | Filament 5 |
| Base de données | PostgreSQL 16 |
| Interface | Blade, Tailwind CSS, Vite, service worker (PWA) |
| Bibliothèques | spatie/laravel-permission, Intervention Image, endroid/qr-code, Dompdf |
| Tests | Pest 4, dont des tests dans un vrai navigateur (Playwright) |
| Qualité | Larastan niveau 8, Laravel Pint, couverture minimale de 90 % |
| Intégration continue | GitHub Actions, Dependabot |

## Installation (développement)

Prérequis : PHP 8.3 ou plus, Composer, Node.js, PostgreSQL 16 (ou Docker).

```bash
git clone git@github.com:BambaNdour96/dahira-laravel.git
cd dahira-laravel
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Renseigner dans `.env` la connexion PostgreSQL et le compte du super administrateur, puis lancer, dans trois terminaux :

```bash
php -d upload_max_filesize=10M -d post_max_size=12M artisan serve
php artisan queue:work
php artisan schedule:work
```

En local, `php artisan migrate:fresh --seed` crée aussi des comptes de démonstration. Pour repartir avec le seul super administrateur :

```bash
APP_ENV=production php artisan migrate:fresh --seed --force
```

## Tests et qualité

```bash
./vendor/bin/pint                                   # style du code
./vendor/bin/phpstan analyse                        # analyse statique (niveau 8)
./vendor/bin/pest --testsuite=Unit,Feature          # tests habituels
./vendor/bin/pest --testsuite=Unit,Feature --coverage --min=90
./vendor/bin/pest --testsuite=Browser               # tests dans un vrai navigateur
```

Les tests navigateur demandent une installation unique : `npx playwright install --with-deps chromium`.

La suite comprend plus de 320 tests : règles métier, matrice des droits de chaque rôle, boutons du panneau, anti-fraude, sécurité, trois **grands parcours de bout en bout** (vie d'un membre, vie du bureau, sécurité d'un compte) et des tests dans Chrome (aucune erreur dans la console, affichage sur téléphone).

## Architecture

```
app/
├── Enums/          Statuts, sexe, niveaux…
├── Filament/       Panneau du bureau (ressources, actions, widgets)
├── Http/           Contrôleurs du site des membres, middlewares de sécurité
├── Listeners/      Connexions, blocage, session unique, nouvel appareil
├── Models/         Membre, Carte, Paiement, Cotisation, Evenement…
├── Notifications/  Notifications des membres (compte et email)
├── Observers/      Tâches du bureau et avis aux membres
├── Policies/       Règles d'accès de chaque rôle
├── Services/       Règles métier : statuts, paiements, cotisations, présences…
└── Support/        Anti-fraude, indicatifs, tâches, liens Wave
```

Toute règle métier vit dans `app/Services` et `app/Support`, jamais dans un écran : le site, le panneau et les tests passent par les mêmes règles.

## Rôles

| Rôle | Périmètre |
| --- | --- |
| `membre` | Son propre compte ; contrôleur d'un événement s'il est désigné |
| `responsable_section` | Jewrine de cellule : les membres de sa seule cellule |
| `tresorier` | Jewrine finance : paiements, cotisations, relances, rapport financier |
| `administrateur` | Registre complet, données de référence, cartes |
| `super_administrateur` | Tout, plus le bureau, les réglages, le journal et les données personnelles |

## Sécurité

- Mots de passe de 8 caractères minimum, lettres et chiffres ; refus des mots de passe ayant fuité (production).
- Blocage après 5 échecs, limite de tentatives, double authentification du bureau.
- Déconnexion après inactivité (6 min membre, 10 min bureau, 25 min super administrateur) et une seule session par compte.
- Politique de sécurité du contenu stricte (nonce), HTTPS et HSTS en production.
- Photos stockées hors du dossier public, servies après contrôle des droits.
- Anti-fraude des paiements : références Wave uniques au format imposé, interdiction de valider son propre paiement, validation croisée du bureau.
- Journal des actions sensibles et historique des connexions.

## Données personnelles

Conforme à la loi sénégalaise n° 2008-12 (CDP) : page d'information, consentement à l'inscription, téléchargement des données, effacement par anonymisation (la comptabilité est conservée), durées de conservation appliquées chaque nuit.

## Tâches planifiées

| Tâche | Fréquence |
| --- | --- |
| Expiration des demandes non payées | Chaque jour |
| Rappel email des cotisations en retard | Le 5 de chaque mois |
| Alerte des tâches du bureau en retard (plus de 3 jours) | Chaque jour à 8 h |
| Application des durées de conservation (`donnees:purger`) | Chaque nuit à 3 h |

## Contact

Daara Nouroud Daareyni Kaolack · Cheikh Ahmadou Bamba Ndour