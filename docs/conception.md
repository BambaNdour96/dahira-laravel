# Conception de la plateforme NDK

**Daara Nouroud Daareyni Kaolack** · Document de conception, version 1.2 · octobre 2026

Ce document fixe ce que la plateforme doit faire, pour qui, et comment chaque partie se relie aux autres. Il a été établi domaine par domaine, en séances de travail, et sert de plan pour reconstruire et compléter la plateforme.

## 1. Les principes fondateurs

| Principe | Ce qu'il signifie |
| --- | --- |
| **Une seule vérité, visible partout** | Chaque information est saisie une seule fois, par la personne responsable, puis apparaît automatiquement partout où elle compte. Personne ne recopie un chiffre d'un écran à l'autre |
| **La validation de la Finance fait foi** | Un franc compte dans les jauges (membre, cellule, programme, Suivi et Évaluation, Comité directeur, trésorerie) dès qu'il est validé par la Finance ou un représentant |
| **Celui qui ordonne n'est pas celui qui paie** | Aucun argent ne sort sans un ordre de dépense ; la Finance exécute l'ordre, et seulement lui |
| **Une personne, plusieurs casquettes** | Un seul compte par personne ; ses droits sont l'addition de ses rôles (poste, comité, commission), et chacun disparaît quand elle le quitte ; il n'existe pas de rôle « administrateur » générique |
| **Chacun gère son niveau** | Chaque niveau gère son équipe ; le niveau au-dessus supervise sans faire à sa place ; les alertes ne remontent qu'en cas de retard |
| **Souplesse sans désordre** | Tout ce qui change d'une année à l'autre se crée dans la plateforme, sans toucher au code, et entre aussitôt dans les liaisons |
| **Rien ne se perd** | On ne supprime jamais une donnée importante : on la corrige par une opération nouvelle, avec motif et trace ; ce qui n'a plus cours est archivé ou clos, jamais effacé (seules exceptions : les données personnelles effacées selon la loi et les durées de conservation) |
| **Personne ne se valide lui-même** | Personne ne valide son propre paiement ; pour chaque poste, la liste des postes autorisés à valider ses paiements est paramétrable |
| **Le super administrateur peut tout** | Il a toujours toutes les capacités du Jewrine national |

**Le vocabulaire de la Daara**

| Terme | Sens |
| --- | --- |
| **Goor Yalla** | Les hommes |
| **Sokhna** (pluriel : Sokhnas) | Les femmes |
| **Jàyante** | Le montant que le membre se fixe lui-même, librement |
| **Sass** | Le minimum imposé par le Jewrine : chacun verse au moins ce montant |
| **Dahira** | La séance de la cellule |
| **CDGMT** | Comité d'organisation du Grand Magal de Touba |

## 2. Les acteurs et leurs droits

| Acteur | Désigné par | Rôle |
| --- | --- | --- |
| **Super administrateur** (deux) | — | La technique, les réglages, la liste du Comité directeur, les nominations ; toutes les capacités du Jewrine national ; le secours |
| **Membre du Comité directeur** | Le super administrateur | Participe à l'instance de décision ; peut être secrétaire de séance |
| **Jewrine national** | Nomination enregistrée par le super administrateur | Dirige ; enregistre les nominations ; désigne le président du CDGMT ; fixe objectifs et enveloppes ; signe les ordres de dépense hors Magal ; a le dernier mot |
| **Secrétaire général** | Super administrateur ou Jewrine national | Registre des membres, données de référence, réunions du Comité directeur |
| **Jewrine finance national** | Super administrateur ou Jewrine national | Toute la trésorerie, en détail ; exécute les ordres de dépense ; désigne ses représentants ; seul à corriger un paiement validé |
| **Représentant finance** | Le Jewrine finance | Un Goor Yalla et une Sokhna par cellule : valident et encaissent dans leur périmètre, remettent leur caisse |
| **Jewrine de cellule** | Super administrateur ou Jewrine national | Toute sa cellule : inscriptions, dahiras, pointage, assiduité, demandes de dispense |
| **Pointeur ou pointeuse** | Le Jewrine de cellule | Pointe les Goor Yalla ou les Sokhnas pendant la dahira |
| **Jewrine d'une commission permanente** | Le super administrateur | Dirige sa commission, ajoute ses membres ; le Jewrine de la commission Suivi et Évaluation dirige le Suivi et l'Évaluation |
| **Membre d'une commission permanente** | Le Jewrine de la commission | Ses tâches et ses réunions |
| **Président d'un comité d'organisation** (CDGMT, Grand Ziar) | Le Jewrine national | Coordonne tout l'événement |
| **Chef de commission d'événement** | Le président du comité | Sa commission : membres, tâches, besoins, demandes de décaissement, justifications, bilan |
| **Jewrine du comité de suivi des dépenses** (CDGMT) | Proposé par le président, validé par le Jewrine national ; ou désigné par le super administrateur | Arbitre les budgets et signe les ordres de dépense du Magal |
| **Responsable de projet** | Le Jewrine national | Pilote un projet de la Daara |
| **Membre** | Validé par son Jewrine de cellule | Cotise, verse ses Jàyante et Sass, participe |
| **Sympathisant** | — (sans compte) | Recensé avec son accord ; peut participer financièrement |

**Les commissions permanentes** : Administration, Suivi et Évaluation, Communication, Sociale, Culturelle, Finance, Organisation, et toute autre que le super administrateur crée. Modèle commun : un Jewrine désigné, qui ajoute ses membres ; un espace avec membres, réunions et PV, tâches, documents ; visibles dans « Mes comités » sur le téléphone. Suivi et Évaluation est une commission comme les autres : ses droits de lecture (section 10) viennent de l'appartenance à la commission, et non d'un rôle à part.

**Les droits** : chaque casquette (poste national, Jewrine de cellule, représentant finance, pointeur, Jewrine ou membre de commission, président ou chef de commission d'un comité, membre du Comité directeur) apporte ses capacités, dans son périmètre ; les droits d'une personne sont la somme de ses casquettes actives. Toute nomination est tracée (qui, par qui, depuis quand) et prend fin sans être effacée.

## 3. Les membres et les cartes

| Élément | Règle |
| --- | --- |
| **Inscription** | Sur le téléphone, ou par le Jewrine de cellule pour un membre sans téléphone ; validation par le Jewrine de cellule ; frais de carte ; matricule ; carte à QR code |
| **Familles** | Un membre gère depuis son téléphone les fiches de sa famille, voit leurs cartes et paie pour eux |
| **Compte enfant** | Peu d'informations : prénom, nom, sexe, date de naissance, photo, parent ou tuteur et lien ; pas de compte de connexion ; cellule du parent ; plus tard, son propre compte avec tout son historique |
| **Cellules** | Créées par le super administrateur ; pas de sous-groupes |
| **Changement de cellule** | Demandé, puis validé par le super administrateur ; l'historique suit |
| **Cartes en plastique** | Fichiers pour l'imprimeur, par lots ; la carte du téléphone reste valable |
| **Renouvellement annuel** | Campagne ouverte par le super administrateur ; le membre vérifie sa fiche et paie les frais de renouvellement (même montant que la carte) ; renouvellement automatique, revalidé par le Jewrine seulement si la fiche a changé ; nouvelle carte de l'année ; à la date limite, anciennes cartes révoquées et statut « non renouvelé », sans perte de données ; relances automatiques |
| **Compétences** | Chaque membre indique son métier et ses savoir-faire, pour être mobilisé |
| **Volontariat** | Un membre se propose pour une commission d'événement ; le chef accepte ou non |
| **Arabe et Coran** | Une rubrique du complément d'inscription et de « Mon profil » (elle remplace le « niveau d'arabe ») : lecture et écriture (ne sait pas lire, lit avec difficulté, lit couramment, excellent), compréhension (ne comprend pas, quelques mots, comprend l'essentiel, maîtrise), Coran mémorisé (aucun, quelques sourates, nombre de hizb, hafiz), khassaides (ne récite pas, récite quelques khassaides, récite bien et peut animer un kourel), souhaite apprendre (oui ou non) ; le parent la remplit pour un proche ; la commission Culturelle a un écran « Recensement Arabe et Coran » (chiffres par cellule, liste filtrable) ; visible seulement par le membre, la commission Culturelle et le super administrateur |
| **Sympathisants** | Recensement avec accord, visites, invitation à adhérer après 3 visites en 3 mois, participations avec reçu, effacement après 2 ans sans visite |
| **Reprise des données** | Possibilité d'importer les listes existantes au lancement |

## 4. La Dahira

| Élément | Règle |
| --- | --- |
| **Création** | À la main, par le super administrateur ou le Jewrine de cellule |
| **Pointage** | Scan de la carte ou à la main ; absents excusés ; sympathisants et visiteurs ; pointeurs et pointeuses désignés par le Jewrine ; **fonctionne sans réseau** |
| **Contenu** | Thème, khassaides, intervenant, annonces : facultatif |
| **Argent** | Aucun versement pendant la dahira |
| **Assiduité** | Taux sur 3 mois, absences de suite ; après 3 absences, alerte au Jewrine de cellule, qui peut faire remonter le cas au Jewrine national |

## 5. Les cotisations

| Élément | Règle |
| --- | --- |
| **Montant** | 1 000 FCFA par mois, le même pour tous, modifiable par exercice ; chaque membre, enfant compris, a sa cotisation |
| **Paiement** | Un ou plusieurs mois, par Wave, Orange Money ou en espèces ; versement petit à petit possible |
| **Retard** | Relance seulement, automatique chaque mois ; jamais de sanction automatique |
| **Dispense** | Demandée par le Jewrine de cellule, validée par le Jewrine national |

## 6. Les programmes et les grands événements

**Les types** : Magal, Ziar, Prokhane, Gamou, Laylatoul Qadr, et toute autre que le super administrateur ajoute.

**Deux façons d'organiser**, choisies à la création :

| Façon | Pour | Contenu |
| --- | --- | --- |
| **Comité d'organisation dédié** | Le Magal (CDGMT) et le Grand Ziar annuel | Président, commissions permanentes choisies et commissions créées pour l'événement, réunions et PV, besoins et budget, circuit des dépenses, tableau de bord, clôture et bilan |
| **Commissions permanentes choisies** | Prokhane, Gamou, Laylatoul Qadr… | Les commissions cochées reçoivent leurs tâches ; l'événement a son objectif, son budget et son bilan |

**Pour tout programme** : objectif de recettes et budget de dépenses fixés par le Jewrine national ; contribution en **Jàyante** ou en **Sass**, au choix du super administrateur ; versements petit à petit ; ordres de dépense ; jour J avec scan des cartes, **sans réseau** si besoin ; solde vers la caisse générale.

### Le CDGMT (et le Grand Ziar annuel)

Un espace dédié, avec ses onglets : Tableau de bord, Commissions, Réunions et PV, Collecte, Budget, Argent, Tâches, Jour J, Documents, Bilan ; plus Itinéraire et Transport pour le Grand Ziar.

| Phase | Contenu |
| --- | --- |
| **Mise en place** | Le Jewrine national désigne le président, fixe l'objectif de la collecte et l'enveloppe générale ; le président crée les commissions (ou les reprend), désigne les chefs ; les améliorations notées l'an dernier s'affichent |
| **Lancement** | Première réunion ; les chefs composent leurs équipes ; la collecte s'ouvre |
| **Préparation** | Besoins exprimés par les chefs (budget de leur commission) ; arbitrage ; réunions et PV ; tâches ; achats ; relances |
| **Jour J** | Scan des cartes, tâches du jour, dépenses de dernière minute |
| **Clôture** | Bilans de commission, bilan général, évaluation, réunion de bilan, clôture quand tout est rendu et justifié |

**Le budget** : le chef fixe le budget de sa commission par ses besoins ; le Jewrine national fixe l'enveloppe générale ; au Magal, le **comité de suivi des dépenses** arbitre (retient, réduit, augmente, répartit) dans l'enveloppe, en regardant l'argent réellement encaissé, et révise en cours de route ; chaque révision est tracée.

**Le circuit de l'argent** : le chef demande un décaissement (**avance**) ou un **remboursement** ; l'ordre de dépense est signé par le comité de suivi des dépenses (Magal) ou par le Jewrine national (Grand Ziar) ; la Finance exécute ; le chef justifie avec les preuves et rend le reste ; l'avance est soldée. Pour chaque commission : budget, décaissé, justifié, reste à justifier.

**Les indicateurs** : promis, encaissé, en vérification, disponible réel (encaissé moins décaissé), courbe de la collecte, recettes et dépenses cumulées, budget par commission, collecte par cellule, avancement des tâches.

**La clôture** : chaque chef rend le bilan de sa commission (chiffres automatiques, objectifs atteints ou non, forces, faiblesses, améliorations) ; le président rend le bilan général ; Suivi et Évaluation rend son évaluation ; réunion de bilan ; clôture possible quand tous les bilans sont rendus et toutes les avances justifiées ; archive en lecture.

**Le transport et l'itinéraire** (Grand Ziar et tout grand déplacement) : étapes du voyage visibles sur le téléphone ; cars, chefs de car, inscriptions, affectations, liste d'attente, embarquement pointé au départ et au retour ; frais de transport compris dans la contribution ou à part, au choix pour chaque événement. Hébergement et repas : gérés par les commissions, sans outil.

## 7. Les projets de la Daara

| Élément | Règle |
| --- | --- |
| **Pilotage** | Un responsable désigné |
| **Financement** | Jàyante facultatifs, sans rappel ni relance ; réaffectations (solde du Magal…) ; dons rattachés |
| **Étapes** | Chacune avec son budget, ses dates, son avancement |
| **Dépenses** | Ordres de dépense signés par le Jewrine national ; factures, contrats, photos du chantier |
| **Visibilité** | Comité directeur ; un interrupteur l'ouvre aux membres (jauge, étapes, photos) |
| **Suivi** | Suivi et Évaluation : indicateurs, feu tricolore, journal, rapports |

## 8. Le Comité directeur et l'assemblée générale

| Élément | Règle |
| --- | --- |
| **Membres** | Liste tenue par le super administrateur, avec ou sans poste |
| **Réunions** | Convoquées par le Jewrine national, le Secrétaire général ou une personne désignée ; ordre du jour défini à l'avance |
| **Secrétaire de séance** | Une seule personne : elle anime, rédige, publie et partage le PV |
| **Présences** | Le quorum est une simple information : présents sur membres, sans seuil « atteint » ou « non atteint » |
| **Décisions** | Pas de vote : unanimité, le dernier mot revient au Jewrine national ; registre numéroté avec état |
| **Actions** | Qui, quoi, pour quand ; rappels et retards |
| **Tableau de bord** | La vue d'ensemble de la Daara, réservée au Comité directeur et à une personne désignée |
| **Assemblée générale** | Convocation de tous les membres, présences par scan, ordre du jour, bilan annuel, PV lisible par tous |

## 9. La Finance de la Daara

| Élément | Règle |
| --- | --- |
| **Caisses** | Paramétrables : Wave, Orange Money, caisse générale, caisses des représentants ; banque et Free Money plus tard, sans toucher au code |
| **Recettes** | Toujours rattachées : frais de carte, cotisations, Jàyante et Sass, participations des sympathisants, dons rattachés, réaffectations reçues |
| **Dépenses** | Toujours rattachées (programme et commission, projet, ou fonctionnement), avec preuve ; libellés de dépenses créés librement |
| **Ordre de dépense** | Aucun argent ne sort sans lui : comité de suivi des dépenses pour le Magal, Jewrine national pour tout le reste ; la Finance exécute |
| **Validation croisée** | Personne ne valide son propre paiement ; des règles paramétrables désignent, pour chaque poste, les postes qui valident ses paiements |
| **Représentants** | Un Goor Yalla et une Sokhna par cellule : validations, encaissements, caisse remise au national avec bordereau ; alertes après 48 heures d'attente et pour les caisses à remettre |
| **Paiement mobile** | Wave et Orange Money : bouton de paiement, déclaration de la référence, contrôle de son format, validation sur relevé, rapprochement mensuel ; confirmation automatique en option plus tard |
| **Erreurs** | Jamais de suppression : refus avant validation ; après validation, réaffectation, avoir sur le compte du membre, annulation par contre-passation, ou remboursement par ordre de dépense ; corrections réservées au Jewrine finance national ; membre prévenu, tout journalisé |
| **Réaffectations** | Décidées par le Jewrine national, enregistrées par la Finance, avec leur origine |
| **Exercice** | Période fixée par la Daara ; bilan annuel présenté en assemblée |
| **Rapports** | Trésorerie par caisse, recettes et dépenses par mois, par source, par programme, par projet, par cellule ; détail par membre pour le Jewrine finance ; courbes ; PDF et Excel |

## 10. Le Suivi et l'Évaluation

| Élément | Règle |
| --- | --- |
| **Qui** | La commission permanente Suivi et Évaluation : son Jewrine, désigné par le super administrateur, et les membres qu'il y ajoute |
| **Vision** | Tout, en lecture, avec les totaux financiers seulement |
| **Projets** | Indicateurs, feu tricolore, journal |
| **Événements** | Fiche d'évaluation après chaque programme ; questionnaire facultatif à des membres choisis |
| **Rapports** | Publiés au Comité directeur, ou envoyés à une personne précise |
| **Alertes** | Dépassements de budget, retards, avances non justifiées |

## 11. La vie de la Daara

| Fonction | Contenu |
| --- | --- |
| **Annonces** (commission Communication) | Annonce aux membres, sur l'accueil et en notification, pour tous ou une cellule |
| **Solidarité** (commission Sociale) | Événements de vie (décès, maladie, naissance, mariage) et collectes de solidarité |
| **Calendrier** | Agenda unique : dahiras, réunions, programmes, avec les dates hégiriennes |
| **Matériel** | Inventaire des biens de la Daara et de leurs prêts aux événements |
| **Documents** | Statuts, règlement intérieur, PV d'assemblée, consultables selon les droits |
| **Questions à la commission Culturelle** | Le membre pose une question, à découvert ou anonyme ; pour une question anonyme, aucun membre de la commission ne voit l'auteur, sur aucun écran, le lien n'étant gardé que pour lui remettre la réponse ; n'importe quel membre de la commission répond ; l'auteur est prévenu et retrouve la réponse dans « Mes questions » ; la commission peut publier des questions-réponses, sans aucun nom, dans une foire aux questions lisible par tous les membres |

## 12. L'administration

| Élément | Contenu |
| --- | --- |
| **Listes paramétrables** | Cellules, commissions, types de programmes, libellés de dépenses, caisses, montants |
| **Exercices** | Dates, campagne de renouvellement |
| **Nominations** | Postes, Comité directeur, Jewrine des commissions permanentes ; historique de toutes les nominations |
| **Sécurité** | Double authentification des responsables, sessions, comptes bloqués, cartes révoquées |
| **Confidentialité** | Charte des responsables, journal des exports, demandes de suppression, déclaration à la CDP |
| **Journaux et sauvegardes** | Qui a fait quoi ; sauvegardes chiffrées chaque nuit, restauration testée |
| **Options à activer plus tard** | SMS aux membres ; messages WhatsApp automatiques ; confirmation automatique des paiements mobiles |
| **Langue** | Français uniquement |

## 13. Décisions complémentaires (version 1.1)

Prises après l'inventaire de l'existant, en octobre 2026.

| Sujet | Décision |
| --- | --- |
| **Rien ne se perd** | Un premier lot (R0) recense toutes les suppressions possibles et les effacements en cascade, et les remplace par une interdiction, un archivage ou une clôture |
| **Validation croisée** | Les règles de validation des paiements par poste sont conservées : elles appliquent « personne ne valide son propre paiement » |
| **Rôles** | Le rôle « administrateur » disparaît : les droits viennent uniquement de l'addition des casquettes |
| **Quorum** | Simple information (présents sur membres), sans seuil |
| **Commissions permanentes** | Administration, Suivi et Évaluation, Communication, Sociale, Culturelle (anciennement « Culturelle et religieuse »), Finance, Organisation |
| **Suivi et Évaluation** | Devient une commission permanente avec son Jewrine, et non plus seulement un rôle |

## 13 bis. Décisions complémentaires (version 1.2)

Prises pendant la reconstruction (lots R1 et R2), en octobre 2026.

| Sujet | Décision |
| --- | --- |
| **Réunions** | Jusqu'au début de la réunion, l'organisateur peut changer la date, le lieu, l'ordre du jour et le secrétaire de séance ; chaque changement envoie une « convocation modifiée » (l'ancien et le nouveau secrétaire sont prévenus) ; le secrétaire de séance garde seul les présences, la rédaction et le PV |
| **Familles** | Un enfant est un membre de moins de 18 ans, sans compte ; il paie les frais de carte et la cotisation ; à 18 ans, la plateforme propose au parent et au Jewrine de lui donner son propre compte, sans l'imposer ; le parent verse aussi ses Sass et Jàyante |
| **Payer pour un autre membre** | Distinct de la gestion d'une fiche : tout membre paie la cotisation ou le Jàyante d'un membre qui a son compte, trouvé par matricule ou téléphone ; le bénéficiaire est prévenu et reçoit son reçu ; personne ne valide un paiement qu'il a déclaré pour un autre |
| **Changement de cellule** | Demandé par le membre ou son Jewrine, validé par le super administrateur ; les proches suivent ; les postes de l'ancienne cellule prennent fin (le Jewrine national est prévenu s'il faut nommer un Jewrine) ; chaque versement garde la cellule du moment où il a été fait |
| **Renouvellement** | Revalidation par le Jewrine seulement si changent le prénom, le nom, la date de naissance, la photo ou le sexe ; renouvellement d'office des membres validés dans les 3 mois avant l'ouverture ou pendant la campagne ; relances à l'ouverture, puis 30, 7 et 1 jour avant la date limite ; un « non renouvelé » qui paie est réactivé et doit les cotisations des mois écoulés ; un nouveau membre cotise à partir du mois qui suit sa validation |
| **Carte en plastique** | Payante, au tarif « Carte physique » ; tant que le tarif n'est pas fixé, « Bientôt disponible » ; seules les cartes payées entrent dans un lot ; les lots sont préparés par le super administrateur et le Secrétaire général |

## 14. Ce qui reste à décider hors conception

Le nom de domaine, le compte Wave Business et le compte marchand Orange Money, l'adresse d'envoi des emails, l'hébergement de la mise en ligne, et la déclaration à la Commission de protection des données personnelles.
