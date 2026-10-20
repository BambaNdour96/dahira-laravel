# Caisses et trésorerie

## Les caisses

Trésorerie → **Caisses** : chaque caisse et son **solde** ; le total de la trésorerie s'affiche en tête.

| Caisse | Ce qui y entre |
| --- | --- |
| **Wave** | Les paiements Wave validés |
| **Orange Money** | Les paiements Orange Money validés |
| **Caisse générale** | Les espèces reçues par la Finance, et les remises des représentants |
| **Caisse d'un représentant** | Les espèces qu'il encaisse, jusqu'à leur remise à la Finance (elle se crée à sa désignation) |
| **Banque** | Plus tard : le super administrateur la crée quand le compte est ouvert, sans toucher au code |

Le super administrateur crée, renomme et **archive** les caisses (une caisse archivée sort des choix, ne s'efface pas, et
seulement si son solde est nul). Le Jewrine finance et ceux qui voient les finances consultent les soldes et, pour
chaque caisse, tous ses **mouvements** (date, nature, libellé, entrée, sortie, auteur).

Un paiement compte dès qu'il est **validé** : il entre aussitôt dans sa caisse. À la **remise** d'un représentant, ses
espèces sortent de sa caisse et entrent dans la caisse générale. Un mouvement ne s'efface jamais : une erreur se
corrige par un mouvement nouveau (voir [Corriger un paiement validé](corrections.md)).

## Payer avec Orange Money (sur le téléphone)

Chaque formulaire de paiement propose « Payé avec Wave » ou « Payé avec **Orange Money** ». Pour Orange Money, le membre
recopie la **référence du SMS** de confirmation : des lettres, des chiffres et des points ; une référence ne sert
qu'une fois. Le numéro Orange Money de la Daara s'affiche s'il est renseigné (Paramètres → « Numéro Orange Money de la
Daara »). La Finance ou le représentant valide la déclaration comme pour Wave.
