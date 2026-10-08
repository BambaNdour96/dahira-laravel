# Daara Nouroud Daareyni Kaolack — Plateforme des membres

Plateforme officielle de gestion des membres de la Daara Nouroud Daareyni Kaolack (NDK) :
inscription en ligne, carte de membre virtuelle avec QR code, cotisations par Wave, reçus,
relances et tableau de bord du bureau.

![Tests](https://img.shields.io/badge/tests-Pest-green) ![Couverture](https://img.shields.io/badge/couverture-%E2%89%A5%2090%20%25-green) ![Analyse statique](https://img.shields.io/badge/Larastan-niveau%208-blue) ![Accessibilit%C3%A9](https://img.shields.io/badge/Lighthouse-100%2F100-brightgreen)

---

## Sommaire

1. [Fonctionnalités](#fonctionnalités)
2. [Technologies](#technologies)
3. [Installation en local](#installation-en-local)
4. [Comptes de démonstration](#comptes-de-démonstration)
5. [Qualité et tests](#qualité-et-tests)
6. [Architecture](#architecture)
7. [Rôles et droits](#rôles-et-droits)
8. [Sécurité](#sécurité)
9. [Données personnelles](#données-personnelles)
10. [Tâches planifiées](#tâches-planifiées)
11. [Contact](#contact)

---

## Fonctionnalités

### Pour les membres

- **Inscription** en deux pages, adaptée au téléphone : identité, photo, cellule et commission.
- **Parcours d'adhésion** : demande examinée par le Jewrine de la cellule, puis frais de carte (1 000 FCFA),
  puis validation avec matricule `NDK-AAAA-NNNN`.
- **Carte de membre virtuelle** avec QR code, consultable même **hors connexion**.
- **Cotisations mensuelles** (1 000 FCFA) payées par **Wave** (1, 2 ou 3 mois), avec calendrier coloré.
- **Reçus PDF** numérotés par nature (`INS-…` pour l'inscription, `COT-…` pour les cotisations).
- **Données personnelles** : téléchargement de ses données et demande de suppression.
- **Application installable** sur l'écran d'accueil du téléphone (PWA).

### Pour le bureau

- **Panneau d'administration** (Filament) : registre des membres, cellules, commissions, postes, tarifs.
- **Validation des paiements** Wave et enregistrement des paiements en espèces.
- **Relances** : message de groupe WhatsApp, bandeau dans le compte, email mensuel, relance individuelle suivie.
- **Tableau de bord** : statistiques des membres, encaissements, taux de membres à jour, graphiques.
- **Exports Excel** des membres et des paiements, **import CSV** des membres existants.

### Pour le super administrateur

- Nomination des membres du bureau, réglages (liens Wave et WhatsApp), mode maintenance.
- Journal d'activité, historique des connexions, consultation « en tant que » un autre compte.
- Dispenses de cotisation, traitement des demandes de suppression de données.

---

## Technologies

| Domaine | Choix |
| --- | --- |
| Langage | PHP 8.3 (8.4 en production) |
| Cadre applicatif | Laravel 13 |
| Panneau d'administration | Filament 5 |
| Base de données | PostgreSQL 16 |
| Interface | Blade, Tailwind CSS, Vite |
| Droits | spatie/laravel-permission |
| Images, QR codes, PDF | Intervention Image, endroid/qr-code, Dompdf |
| Tests | Pest, couverture PCOV |
| Qualité | Larastan (niveau 8), Laravel Pint |
| Intégration continue | GitHub Actions, Dependabot |

---

## Installation en local

### Prérequis

- PHP 8.3 avec les extensions `pdo_pgsql`, `intl`, `gd`, `mbstring`, `bcmath`, `zip`
- Composer 2
- Node.js 22
- Docker (pour PostgreSQL)

### Étapes

```bash
git clone git@github.com:BambaNdour96/dahira-laravel.git
cd dahira-laravel

composer install
npm ci

cp .env.example .env
php artisan key:generate

# Base de données PostgreSQL dans Docker
docker compose -f compose.dev.yml up -d

# Tables et données de démonstration
php artisan migrate --seed

# Fichiers front
npm run build
```

### Lancer l'application

Dans trois terminaux :

```bash
php artisan serve          # le site : http://127.0.0.1:8000
php artisan queue:work     # les exports et les emails
php artisan schedule:work  # les tâches planifiées
```

- Site des membres : http://127.0.0.1:8000
- Panneau du bureau : http://127.0.0.1:8000/admin

En développement, les emails ne partent pas : ils sont écrits dans `storage/logs/laravel.log`.

---

## Comptes de démonstration

Créés uniquement en environnement local par `php artisan migrate --seed`.

| Rôle | Téléphone | Mot de passe |
| --- | --- | --- |
| Jewrine de cellule (Kaolack) | `77 000 00 01` | `password` |
| Jewrine finance | `77 000 00 02` | `password` |
| Jewrine de cellule (Dakar) | `77 000 00 03` | `password` |

Le compte du super administrateur est défini dans le fichier `.env`.

---

## Qualité et tests

```bash
./vendor/bin/pint                       # style du code
./vendor/bin/phpstan analyse            # analyse statique, niveau 8
./vendor/bin/pest                       # tests
./vendor/bin/pest --coverage --min=90   # tests et couverture minimale
composer audit && npm audit             # failles connues des dépendances
```

À chaque envoi sur GitHub, l'intégration continue vérifie le style, l'analyse statique,
les failles connues, puis lance tous les tests avec une **couverture minimale de 90 %**.
Les dépendances sont aussi auditées chaque lundi, et Dependabot propose les mises à jour.

Les tests couvrent notamment :

- les règles métier (statuts, matricules, cotisations, reçus, dispenses) ;
- la **matrice complète des droits** : chaque règle d'accès est vérifiée pour chaque rôle ;
- les boutons du panneau, simulés comme de vrais clics ;
- les mécanismes anti-fraude, la sécurité de connexion et les en-têtes de sécurité ;
- les droits liés aux données personnelles.

---

## Architecture

```
app/
├── Enums/               Statuts et listes de valeurs (membre, paiement, carte…)
├── Services/            Règles métier : statuts, paiements, cotisations, cartes, reçus…
├── Support/             Outils transverses : anti-fraude, liens Wave, messages de relance…
├── Models/              Modèles Eloquent, avec journalisation automatique
├── Policies/            Règles d'accès par rôle et par cellule
├── Http/
│   ├── Controllers/     Site des membres
│   └── Middleware/      Maintenance, en-têtes de sécurité, mot de passe provisoire
├── Filament/            Panneau du bureau : écrans, actions, widgets, exports
├── Listeners/           Historique des connexions, blocage après échecs
├── Notifications/       Emails (rappels de cotisation)
└── Console/Commands/    Tâches planifiées
tests/
├── Feature/             Tests de bout en bout et matrice des droits
└── Unit/                Tests unitaires
```

**Principes retenus :**

- toute règle métier vit dans un **service**, jamais dans un écran ;
- chaque droit est vérifié **deux fois** : pour afficher un bouton, puis au moment de l'action ;
- toute action sensible est **journalisée** (auteur, date, changements).

---

## Rôles et droits

| Rôle | Périmètre |
| --- | --- |
| Super administrateur | Tout, plus les comptes du bureau, les réglages, le journal et les données personnelles |
| Administrateur | Registre complet, données de référence, révocation des cartes |
| Jewrine finance | Paiements, cotisations, relances, rapport financier ; consultation des membres |
| Jewrine de cellule | Membres de **sa seule cellule** : acceptation, correction, relances |
| Membre | Son propre compte uniquement |

Aucun paiement ne peut être supprimé, et personne ne peut confirmer **son propre** paiement.

---

## Sécurité

- **En-têtes de sécurité** : politique de sécurité du contenu stricte (nonce et `strict-dynamic`),
  protection contre l'intégration dans une autre page, HSTS en production.
- **Mots de passe** : 8 caractères minimum avec lettres et chiffres, refus des mots de passe
  ayant fuité (en production), mot de passe provisoire à changer obligatoirement.
- **Connexion** : blocage de 15 minutes après 5 échecs, avec alerte au super administrateur ;
  double authentification du bureau (obligatoire en production).
- **Anti-fraude des paiements** : liens Wave à montant fixe, référence unique et contrôlée,
  numéro Wave déclaré, interdiction de valider son propre paiement, alerte après refus répétés.
- **Photos** stockées hors du dossier public et servies après contrôle des droits.

---

## Données personnelles

La plateforme applique la **loi sénégalaise n° 2008-12 du 25 janvier 2008** sur la protection
des données à caractère personnel :

- page « Données personnelles » détaillant finalités, destinataires et durées de conservation ;
- **droit d'accès** : téléchargement des données au format JSON ;
- **droit à l'effacement** : demande de suppression traitée par anonymisation,
  la comptabilité restant intacte ;
- **durées de conservation** appliquées automatiquement chaque nuit.

---

## Tâches planifiées

| Tâche | Fréquence | Rôle |
| --- | --- | --- |
| `cotisations:rappeler` | Le 5 de chaque mois | Email de rappel aux membres en retard |
| `donnees:purger` | Chaque nuit | Application des durées de conservation |

En production, une seule entrée `cron` suffit : `* * * * * php artisan schedule:run`.

---

## Contact

Responsable technique : **Cheikh Ahmadou Bamba Ndour**

Projet développé pour la Daara Nouroud Daareyni Kaolack. Tous droits réservés.