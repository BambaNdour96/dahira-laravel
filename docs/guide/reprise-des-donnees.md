# Reprise des listes existantes

## Préparer le fichier

Registre → **Reprise des données** → « Télécharger le modèle ». Le remplir dans Excel, puis « Enregistrer sous » →
**CSV UTF-8**. Une ligne par membre :

| Colonne | Règle |
| --- | --- |
| Matricule | Facultatif ; de la forme NDK-AAAA-NNNN. Sans matricule, la plateforme en attribue un (année d'adhésion) |
| Prénom, Nom | Obligatoires |
| Sexe | H ou F (Goor Yalla, Sokhna, Homme, Femme sont aussi compris) |
| Téléphone | Facultatif ; celui du membre lui-même ; jamais pour un enfant de moins de 12 ans (l'âge minimum d'inscription) |
| Cellule | Son nom tel qu'il figure dans Registre → Cellules (les majuscules et les accents n'importent pas). Une cellule inconnue est refusée : créez-la d'abord |
| Dates | Naissance et adhésion : 15/03/1990 (ou 1990-03-15) |
| Savoir-faire | Les noms de la liste, séparés par « ; » ; un nom inconnu est ignoré, et signalé |
| Responsable, Lien | Pour un proche : le matricule ou le téléphone du membre qui gère sa fiche (déjà dans la Daara, ou plus haut dans le même fichier), et le lien (enfant, pupille, conjoint, parent, frère ou sœur, autre). Un enfant de moins de 12 ans, ou un jeune de moins de 18 ans sans son propre téléphone, a toujours un responsable ; il prend sa cellule |
| Cotisations à jour jusqu'à | Facultatif : le dernier mois déjà réglé avant la plateforme (12/2026) |

## Analyser, puis importer

1. « **Analyser un fichier** » : la plateforme lit le fichier **sans rien enregistrer** et montre un rapport, ligne par
   ligne : **prête**, **erreur** (avec la raison), ou **doublon possible** (même téléphone qu'un membre ou un compte,
   même matricule, ou mêmes prénom, nom et date de naissance). Les doublons ne sont jamais importés.
2. Corrigez le fichier et analysez-le de nouveau, ou « **Importer les lignes prêtes** » tout de suite. Le fichier est
   revérifié au moment de l'import ; le rapport final (téléchargeable) donne le matricule de chaque membre importé.

Le super administrateur, le Jewrine national et le Secrétaire général importent pour toute la Daara ; le **Jewrine de
cellule** seulement pour sa cellule (les autres lignes sont refusées au rapport).

**Les membres importés** sont validés, avec leur carte de l'exercice en cours (renouvelés d'office si une campagne est
ouverte) et l'origine « Reprise des données ». Leurs cotisations commencent le mois qui suit l'import ; les mois indiqués
dans « Cotisations à jour jusqu'à » sont notés **« Reprise des données »** : sans montant, sans reçu, hors trésorerie.

## Activer son compte (le membre, sur le téléphone)

Un membre importé n'a pas encore de compte. Page de connexion → « **Activer mon compte** » : son téléphone, sa date de
naissance, son **matricule s'il le connaît** (il figure sur sa carte), et le mot de passe qu'il choisit.

- Avec le bon matricule, le compte s'ouvre aussitôt.
- Sans matricule, la demande part au **Jewrine de cellule** (Registre → **Activations de compte**) : il vérifie que
  c'est bien le membre (un appel suffit), puis valide ; le membre se connecte avec le mot de passe qu'il avait choisi.
- Si une information ne correspond pas, le message est toujours le même : la plateforme ne dit pas quelles fiches
  existent.
- Un proche dont la fiche est gérée par un autre membre ne s'active pas ainsi : voir « Ma famille ».
