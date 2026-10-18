# Nominations, casquettes et droits

**Une personne, plusieurs casquettes** : chacun a un seul compte, et ses droits sont l'addition de ses casquettes
en cours. Quand une casquette prend fin, seuls ses droits à elle disparaissent. Il n'existe plus de rôle
« administrateur » qui donnerait tout d'un coup.

## Les casquettes

| Casquette | D'où elle vient |
| --- | --- |
| **Super administrateur** (deux au plus) | Désigné sur le serveur, jamais depuis un écran (voir plus bas) |
| **Jewrine national** | Nomination par le super administrateur seulement |
| **Secrétaire général** | Nomination par le super administrateur ou le Jewrine national |
| **Jewrine finance national** | Nomination par le super administrateur ou le Jewrine national |
| **Jewrine de cellule** | Nomination par le super administrateur ou le Jewrine national, pour une cellule ; responsable de tous ses membres, Goor Yalla et Sokhnas |
| **Jewrine de commission** | Nomination par le super administrateur seulement, pour une commission permanente (voir [Commissions permanentes](commissions.md)) |
| **Convocateur du Comité directeur** | Nomination par le super administrateur seulement (voir [Réunions](reunions.md)) |
| **Membre de la commission Suivi et Évaluation** | Ajouté par le Jewrine de la commission Suivi et Évaluation |
| **Représentant finance** | Désigné par le Jewrine finance (Trésorerie → Représentants finance) |
| **Membre du Comité directeur** | Liste tenue par le super administrateur |
| **Pointeur, pointeuse** | Désigné par le Jewrine de cellule (sans accès au panneau) ; les anciennes Jewrine des Sokhnas sont devenues pointeuses des Sokhnas |

## Nommer quelqu'un

**Paramètres → Nominations**, bouton **Nommer** (super administrateur ou Jewrine national).

1. Cherchez la personne par son nom, son matricule ou son téléphone : seuls les membres **validés, avec un compte**,
   peuvent être nommés.
2. Choisissez le poste ; pour un Jewrine de cellule, choisissez aussi la cellule ; pour un Jewrine de commission,
   la commission.
3. Indiquez la date de début.

Les droits du poste sont donnés **aussitôt** ; la personne se connecte avec son numéro et son mot de passe habituels.

- Le Jewrine national, les Jewrine de commission et les convocateurs du Comité directeur ne sont nommés que par le
  super administrateur.
- Sur un poste à **titulaire unique** (Jewrine national, Secrétaire général, Jewrine finance national, Jewrine de
  cellule pour une même cellule, Jewrine de commission pour une même commission), nommer quelqu'un **met fin
  automatiquement** à la nomination précédente,
  avec le motif « Remplacé(e) par … ».
- Une même personne peut cumuler plusieurs postes : par exemple Jewrine de cellule et Jewrine finance national.

## Mettre fin à une nomination

Bouton **Mettre fin**, motif obligatoire (fin de mandat, démission…). La personne perd les droits de ce poste,
pas ceux de ses autres casquettes. Rien n'est effacé : le filtre **Nominations** (en cours, terminées, toutes)
montre tout l'historique, avec qui a nommé et qui a mis fin.

Les nominations prennent aussi fin d'elles-mêmes quand un membre est **radié** ou que son compte est anonymisé.

## Où voir les casquettes

- **Fiche d'un membre** (panneau) : tableau « Casquettes », en cours et passées.
- **Paramètres → Comptes** (super administrateur) : les comptes des responsables, leurs casquettes, et les actions
  désactiver, nouveau mot de passe, « voir en tant que ».
- **Paramètres → Matrice des droits** (super administrateur) : le tableau complet ci-dessous.
- **Sur le téléphone** : « Mes responsabilités » dans Mon espace ; la carte du téléphone affiche le **poste
  principal** (le premier dans l'ordre des postes). La carte imprimée n'affiche aucun poste : elle dure un an.

## Validation croisée des paiements

Personne ne valide son propre paiement. Pour un membre qui occupe un poste concerné par une règle
(Paramètres → Règles de validation), le validateur doit être autorisé par **toutes** les règles de ses postes.
Exemple : un Jewrine finance national qui est aussi Secrétaire général ne peut être validé que par le Jewrine
national, seul autorisé par les deux règles. Le super administrateur valide en secours, jamais son propre paiement.

## Les super administrateurs

Deux au plus, jamais aucun. Ils se désignent sur le serveur, par la personne qui l'administre :

```bash
php artisan ndk:super-admin                      # la liste
php artisan ndk:super-admin 771234567           # attribuer le rôle
php artisan ndk:super-admin 771234567 --retirer
```

Chaque changement est noté au journal d'activité.

## La matrice des droits

Une capacité vaut pour **toute la Daara** (✔), ou seulement pour **la cellule** que la personne dirige (« cellule »).
Ce tableau est produit à partir du code (`app/Support/Capacites.php`) ; après une modification de la matrice :
`php artisan droits:synchroniser`.

Légende : **SA** super administrateur · **JN** Jewrine national · **SG** Secrétaire général ·
**JF** Jewrine finance national · **JC** Jewrine de cellule · **JCo** Jewrine de commission ·
**SE** membre de la commission Suivi et Évaluation · **CCD** Convocateur du Comité directeur ·
**RF** Représentant finance · **CD** Membre du Comité directeur · **M** Membre.

S'ajoutent les droits liés à la personne, vérifiés au cas par cas : le président d'un comité d'organisation le gère ;
le Jewrine d'une commission permanente gère ses membres et son espace ; le secrétaire de séance rédige et publie
le PV ; le chef d'une commission d'un comité dirige sa commission.

### Plateforme

| Capacité | SA | JN | SG | JF | JC | JCo | SE | CCD | RF | CD | M |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| Réglages, journaux, sessions, comptes, règles de validation, « voir en tant que » <br>`plateforme.administrer` | ✔ |  |  |  |  |  |  |  |  |  |  |
| Accéder au panneau du bureau (/admin) <br>`panneau.acceder` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |  | ✔ |  |
| Arriver sur le panneau du bureau après la connexion <br>`panneau.accueil` | ✔ | ✔ | ✔ | ✔ | ✔ |  |  |  |  |  |  |
| Charte de confidentialité et déconnexion rapide des responsables <br>`espace.responsable` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |  |
| Voir les nominations <br>`nominations.voir` | ✔ | ✔ |  |  |  |  |  |  |  |  |  |
| Nommer et mettre fin à une nomination (Jewrine national, Jewrine de commission, convocateur : seul le super administrateur) <br>`nominations.gerer` | ✔ | ✔ |  |  |  |  |  |  |  |  |  |
| Cellules, commissions permanentes, exercices, postes, tarifs <br>`referentiel.gerer` | ✔ |  |  |  |  |  |  |  |  |  |  |
| Gérer les membres de toutes les commissions permanentes <br>`commissions.gerer` | ✔ |  |  |  |  |  |  |  |  |  |  |

### Membres et cartes

| Capacité | SA | JN | SG | JF | JC | JCo | SE | CCD | RF | CD | M |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| Voir les fiches des membres <br>`membres.voir` | ✔ | ✔ | ✔ | ✔ | cellule |  |  |  |  |  |  |
| Inscrire un membre <br>`membres.inscrire` | ✔ | ✔ | ✔ |  | cellule |  |  |  |  |  |  |
| Modifier une fiche <br>`membres.modifier` | ✔ | ✔ | ✔ |  | cellule |  |  |  |  |  |  |
| Accepter, faire corriger ou rejeter une inscription (toute la Daara : tout changement de statut hors suspension) <br>`membres.valider` | ✔ | ✔ | ✔ |  | cellule |  |  |  |  |  |  |
| Suspendre un membre, ou le réactiver <br>`membres.suspendre` | ✔ | ✔ |  |  |  |  |  |  |  |  |  |
| Changer le statut d'un responsable <br>`membres.statut_responsables` | ✔ |  |  |  |  |  |  |  |  |  |  |
| Révoquer une carte <br>`cartes.revoquer` | ✔ | ✔ | ✔ |  |  |  |  |  |  |  |  |
| Importer des membres <br>`membres.importer` | ✔ | ✔ | ✔ |  |  |  |  |  |  |  |  |
| Exporter les membres <br>`membres.exporter` | ✔ | ✔ | ✔ | ✔ |  |  |  |  |  |  |  |
| Valider ou refuser un changement de cellule (seul le super administrateur) <br>`membres.changer_cellule` | ✔ |  |  |  |  |  |  |  |  |  |  |

### Paiements et cotisations

| Capacité | SA | JN | SG | JF | JC | JCo | SE | CCD | RF | CD | M |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| Voir les paiements <br>`paiements.voir` | ✔ | ✔ | ✔ | ✔ |  |  |  |  |  |  |  |
| Enregistrer un paiement en espèces <br>`paiements.encaisser` | ✔ |  |  | ✔ |  |  |  |  |  |  |  |
| Confirmer ou refuser un paiement déclaré <br>`paiements.valider` | ✔ |  |  | ✔ |  |  |  |  |  |  |  |
| Valider le paiement d'un responsable, selon les règles de validation croisée <br>`paiements.valider_bureau` | ✔ | ✔ | ✔ |  |  |  |  |  |  |  |  |
| Valider en secours, hors règles de validation croisée (jamais son propre paiement) <br>`paiements.secours` | ✔ |  |  |  |  |  |  |  |  |  |  |
| Dispenser un membre de cotisation <br>`cotisations.dispenser` | ✔ | ✔ |  |  |  |  |  |  |  |  |  |
| Suivre les cotisations (tableaux de bord) <br>`cotisations.suivre` | ✔ | ✔ | ✔ | ✔ | cellule |  | ✔ |  |  |  |  |
| Voir les rapports financiers <br>`finances.voir` | ✔ | ✔ | ✔ | ✔ |  |  | ✔ |  |  |  |  |
| Relancer les membres en retard <br>`relances.envoyer` | ✔ |  |  | ✔ | cellule |  |  |  |  |  |  |
| Voir les représentants finance <br>`representants.voir` | ✔ | ✔ |  | ✔ |  |  |  |  |  |  |  |
| Désigner les représentants finance <br>`representants.designer` | ✔ |  |  | ✔ |  |  |  |  |  |  |  |
| Recevoir la caisse d'un représentant <br>`caisses.recevoir` | ✔ |  |  | ✔ |  |  |  |  |  |  |  |
| Tenir sa caisse de représentant <br>`caisse.tenir` |  |  |  |  |  |  |  |  | ✔ |  |  |

### Programmes

| Capacité | SA | JN | SG | JF | JC | JCo | SE | CCD | RF | CD | M |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| Voir les programmes et les engagements <br>`programmes.voir` | ✔ | ✔ | ✔ | ✔ | cellule |  | ✔ |  |  |  |  |
| Créer, paramétrer et annuler un programme <br>`programmes.parametrer` | ✔ |  |  |  |  |  |  |  |  |  |  |
| Clôturer et rouvrir un programme <br>`programmes.cloturer` | ✔ | ✔ |  |  |  |  |  |  |  |  |  |
| Attribuer un montant à un membre <br>`programmes.attribuer` | ✔ | ✔ | ✔ |  |  |  |  |  |  |  |  |
| Modifier un engagement, fiche de paramétrage <br>`programmes.piloter` | ✔ | ✔ | ✔ | ✔ |  |  |  |  |  |  |  |
| Exporter un programme et son rapport <br>`programmes.exporter` | ✔ | ✔ | ✔ | ✔ |  |  | ✔ |  |  |  |  |

### Dahiras et événements

| Capacité | SA | JN | SG | JF | JC | JCo | SE | CCD | RF | CD | M |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| Voir les événements <br>`evenements.voir` | ✔ | ✔ | ✔ |  |  |  | ✔ |  |  |  |  |
| Créer, modifier et annuler un événement <br>`evenements.gerer` | ✔ | ✔ | ✔ |  |  |  |  |  |  |  |  |
| Voir les dahiras et l'assiduité <br>`dahiras.voir` | ✔ | ✔ | ✔ |  | cellule |  | ✔ |  |  |  |  |
| Créer, modifier et annuler une dahira <br>`dahiras.gerer` | ✔ |  |  |  | cellule |  |  |  |  |  |  |
| Pointer partout, et corriger un pointage après coup <br>`pointage.partout` | ✔ | ✔ |  |  |  |  |  |  |  |  |  |
| Désigner les pointeurs <br>`pointeurs.designer` | ✔ |  |  |  | cellule |  |  |  |  |  |  |
| Voir les sympathisants <br>`sympathisants.voir` | ✔ | ✔ | ✔ | ✔ | cellule |  | ✔ |  |  |  |  |
| Modifier et retirer un sympathisant du registre <br>`sympathisants.gerer` | ✔ |  |  |  | cellule |  |  |  |  |  |  |
| Enregistrer la participation d'un sympathisant <br>`sympathisants.participations` | ✔ |  |  | ✔ |  |  |  |  |  |  |  |
| Statistiques des événements et des sympathisants <br>`statistiques.voir` | ✔ | ✔ | ✔ |  |  |  | ✔ |  |  |  |  |

### Comités

| Capacité | SA | JN | SG | JF | JC | JCo | SE | CCD | RF | CD | M |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| Créer un comité d'organisation, en désigner le président, le gérer <br>`comites.diriger` | ✔ | ✔ |  |  |  |  |  |  |  |  |  |
| Convoquer le Comité directeur et préparer ses réunions <br>`comite_directeur.convoquer` | ✔ | ✔ | ✔ |  |  |  |  | ✔ |  |  |  |
| Consulter les comités, réunions, décisions et actions <br>`comites.consulter` | ✔ | ✔ | ✔ |  |  |  |  | ✔ |  | ✔ |  |
| Voir les besoins et les dépenses des commissions <br>`comites.budget_voir` | ✔ | ✔ | ✔ | ✔ |  |  |  |  |  | ✔ |  |
| Valider les dépenses des commissions <br>`comites.depenses_valider` | ✔ |  |  | ✔ |  |  |  |  |  |  |  |
| Tenir la liste du Comité directeur <br>`comite_directeur.composer` | ✔ |  |  |  |  |  |  |  |  |  |  |

### Projets et Suivi-Évaluation

| Capacité | SA | JN | SG | JF | JC | JCo | SE | CCD | RF | CD | M |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| Voir les projets <br>`projets.voir` | ✔ | ✔ | ✔ | ✔ |  |  | ✔ |  |  |  |  |
| Créer et piloter les projets <br>`projets.gerer` | ✔ | ✔ |  |  |  |  | ✔ |  |  |  |  |
| Rédiger des rapports de suivi <br>`rapports.rediger` | ✔ | ✔ | ✔ |  |  |  | ✔ |  |  |  |  |
| Lire les rapports en brouillon <br>`rapports.tout_lire` | ✔ |  |  |  |  |  | ✔ |  |  |  |  |
| Modifier le brouillon d'un autre auteur <br>`rapports.corriger` | ✔ |  |  |  |  |  |  |  |  |  |  |
