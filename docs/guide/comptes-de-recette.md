# Comptes de recette

Données fictives pour vérifier tous les écrans. **Jamais en production** : la commande refuse de s'y exécuter.

```bash
php artisan recette:charger            # sur une base qui n'a pas encore les données de recette
php artisan recette:charger --fraiche  # vide la base, la recrée, puis charge (sauvegardez-la d'abord)
```

**Mot de passe de tous les comptes : `Recette2026`.** On se connecte avec le numéro de téléphone (sur le téléphone :
/connexion ; le panneau : /admin).

Pour voir des **cotisations en retard**, le mois de démarrage des cotisations (`COTISATIONS_DEBUT` dans le `.env`) doit
être passé : la commande le signale si ce n'est pas le cas.

## Le bureau et les responsables

| Téléphone | Nom | Casquettes | Cellule |
| --- | --- | --- | --- |
| 77 010 00 01 | Super Administrateur | Super administrateur | Kaolack |
| 77 010 00 02 | Serigne Ndiaye | Jewrine national, Comité directeur | Kaolack |
| 77 010 00 03 | Astou Diop | Secrétaire général, Comité directeur | Dakar |
| 77 010 00 04 | Fatou Ndiaye | Jewrine finance national, Comité directeur | Kaolack |
| 77 010 00 31 | El Hadji Mbaye | Comité directeur (sans poste) ; secrétaire de séance de la réunion du CDGMT | Dakar |
| 77 010 00 11 | Modou Fall | Jewrine de cellule | Kaolack |
| 77 010 00 12 | Ibrahima Sarr | Jewrine de cellule | Dakar |
| 77 010 00 13 | Cheikh Niasse | Jewrine de cellule | Médina Baye |
| 77 010 00 21 | Abdou Sow | Représentant finance (Goor Yalla) | Kaolack |
| 77 010 00 22 | Khady Ba | Représentante finance (Sokhnas) | Kaolack |
| 77 010 00 23 | Pape Gueye | Représentant finance (Goor Yalla) | Dakar |
| 77 010 00 24 | Mariama Sy | Représentante finance (Sokhnas) | Dakar |
| 77 010 00 25 | Ousmane Cissé | Représentant finance (Goor Yalla) | Médina Baye |
| 77 010 00 26 | Ndèye Kane | Représentante finance (Sokhnas) | Médina Baye |
| 77 010 00 27 | Aminata Diallo | Pointeuse (Sokhnas) | Kaolack |

## Les commissions permanentes

| Téléphone | Nom | Casquettes | Cellule |
| --- | --- | --- | --- |
| 77 010 00 41 | Mouhamadou Kebe | Jewrine de la commission Culturelle | Kaolack |
| 77 010 00 42 | Adama Wade | Jewrine de la commission Suivi et Évaluation | Dakar |
| 77 010 00 43 | Oumar Samb | Membre de la commission Culturelle | Médina Baye |
| 77 010 00 44 | Seynabou Mané | Membre de la commission Culturelle | Kaolack |
| 77 010 00 45 | Aliou Badji | Membre de Suivi et Évaluation | Dakar |
| 77 010 00 46 | Ndeye Fatou Gning | Membre de la commission Communication | Dakar |

## Les simples membres

| Téléphone | Nom | Situation | Cellule |
| --- | --- | --- | --- |
| 77 010 01 01 | Awa Ndour | Cotisations à jour ; Jàyante Magal avec versement ; cheffe de la commission Cuisine du CDGMT | Kaolack |
| 77 010 01 02 | Moussa Thiam | À jour ; membre de la commission Cuisine du CDGMT ; souhaite apprendre l'arabe | Kaolack |
| 77 010 01 03 | Binta Faye | À jour ; Jàyante avec versement | Kaolack |
| 77 010 01 04 | Lamine Seck | À jour ; Jàyante avec versement Wave à valider ; chef de la commission Hygiène ; hafiz | Dakar |
| 77 010 01 05 | Coumba Diouf | En retard ; cotisation Wave à valider (2 mois) | Dakar |
| 77 010 01 06 | Babacar Mbengue | En retard ; Jàyante sans versement | Dakar |
| 77 010 01 07 | Sokhna Ka | En retard ; cotisation Wave à valider (3 mois) ; cheffe de la commission Transport | Médina Baye |
| 77 010 01 08 | Mamadou Ly | En retard | Médina Baye |
| 77 010 01 09 | Rokhaya Gaye | Validée il y a un mois : renouvelée d'office | Médina Baye |
| 77 010 01 10 | Saliou Touré | Validé il y a deux mois : renouvelé d'office | Kaolack |
| 77 010 01 20 | Daouda Sène | Inscription en attente du Jewrine | Kaolack |
| 77 010 01 21 | Mame Diagne | Inscription acceptée, frais de carte Wave à valider | Kaolack |

## La famille

| Téléphone | Nom | Situation | Cellule |
| --- | --- | --- | --- |
| 77 010 00 51 | Ibrahima Ndour | Gère la fiche de son fils **Fallou** (9 ans, sans compte) dans « Ma famille » | Kaolack |
| 77 010 00 52 | Aïssatou Ndour | Son épouse, avec son propre compte (pour essayer « Payer pour un membre ») | Kaolack |

## Ce que contient aussi le jeu

- **Programme en cours** : Grand Magal de Touba, Jàyante libre, sept engagements, trois versements en espèces et un
  versement Wave à valider.
- **CDGMT** : présidé par le premier membre du Comité directeur (Serigne Ndiaye), trois commissions (Cuisine,
  Hygiène et environnement, Transport) avec leurs chefs, une réunion de lancement convoquée, tenue (présences,
  décisions, une action) et son **PV publié** par le secrétaire de séance (El Hadji Mbaye).
- **Renouvellement** : la campagne de l'exercice suivant est ouverte (date limite dans deux mois) ; les membres validés
  depuis moins de trois mois sont renouvelés d'office.
- **Arabe et Coran** : sept rubriques remplies (du hafiz à celui qui ne sait pas lire), dont celle de Fallou par son
  père ; plusieurs membres souhaitent apprendre.
- **Notifications** : la plupart des opérations envoient leurs avis ; lancez la file d'envois (`php artisan queue:work`)
  pour les voir arriver.
