@extends('layouts.public')

@section('titre', 'Données personnelles')

@section('contenu')
    @php
        $titre = 'mt-8 text-lg font-semibold text-emerald-900';
        $texte = 'mt-2 text-sm leading-relaxed text-stone-700';
        $liste = 'mt-2 list-disc space-y-1 pl-5 text-sm leading-relaxed text-stone-700';
    @endphp

    <h1 class="text-2xl font-semibold text-emerald-900">Protection de vos données personnelles</h1>
    <p class="{{ $texte }}">
        La Daara Nouroud Daareyni Kaolack attache une grande importance à la confidentialité de ses membres.
        Cette page explique quelles données la plateforme collecte, pourquoi, qui peut les voir, combien de temps
        elles sont conservées, et comment exercer vos droits, conformément à la loi sénégalaise n° 2008-12
        du 25 janvier 2008 sur la protection des données à caractère personnel.
    </p>

    @auth
        <div class="mt-6 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
            <p class="text-sm font-semibold text-emerald-900">Vos données, à tout moment</p>
            <a href="{{ route('donnees.export') }}"
               class="mt-3 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-900">
                Télécharger mes données
            </a>
        </div>
    @endauth

    <h2 class="{{ $titre }}">1. Qui est responsable de vos données ?</h2>
    <p class="{{ $texte }}">
        Le responsable du traitement est la <strong>Daara Nouroud Daareyni Kaolack</strong>, représentée par son bureau.
        Pour toute question sur vos données, contactez {{ config('dahira.contact_donnees') }}.
    </p>

    <h2 class="{{ $titre }}">2. Pourquoi collectons-nous vos données ?</h2>
    <ul class="{{ $liste }}">
        <li>Gérer votre adhésion : examen de votre demande, validation, matricule et carte de membre.</li>
        <li>Vérifier votre carte lors des activités de la Daara, grâce à son code QR.</li>
        <li>Suivre vos paiements (frais de carte, cotisations) et vous délivrer des reçus.</li>
        <li>Organiser la vie de la Daara : cellules, commissions, communication avec les membres.</li>
        <li>Assurer la sécurité de la plateforme : historique des connexions et journal des actions du bureau.</li>
    </ul>
    <p class="{{ $texte }}">
        Ces traitements reposent sur votre <strong>consentement</strong>, donné lors de l'inscription,
        et sur la gestion de votre adhésion. Vos données ne sont <strong>jamais vendues</strong>,
        ni utilisées à des fins publicitaires.
    </p>

    <h2 class="{{ $titre }}">3. Quelles données ?</h2>
    <ul class="{{ $liste }}">
        <li><strong>Identité</strong> : prénom, nom, sexe, date et lieu de naissance, photo.</li>
        <li><strong>Contact</strong> : téléphone, adresse email (facultative), lieu de résidence.</li>
        <li><strong>Vie de la Daara</strong> : cellule, commission, poste, statut de membre, matricule, carte.</li>
        <li><strong>Profil</strong> (facultatif) : niveau d'études, niveau en arabe, langues, situation professionnelle.</li>
        <li><strong>Paiements</strong> : montants, dates, moyen de paiement, référence et numéro Wave, reçus.</li>
        <li><strong>Sécurité</strong> : dates et adresses IP des connexions, actions réalisées dans le panneau du bureau.</li>
    </ul>

    <h2 class="{{ $titre }}">4. Qui peut voir vos données ?</h2>
    <ul class="{{ $liste }}">
        <li>Le <strong>Jewrine de votre cellule</strong> : les membres de sa seule cellule.</li>
        <li>Le <strong>Jewrine finance</strong> : les membres et leurs paiements, en lecture.</li>
        <li>Les <strong>administrateurs</strong> du bureau : l'ensemble du registre.</li>
        <li>Lors d'un <strong>scan de votre carte</strong>, la personne qui scanne voit seulement votre photo,
            votre nom, votre cellule et la validité de la carte.</li>
    </ul>
    <p class="{{ $texte }}">
        Chaque accès est limité par des droits vérifiés automatiquement, et les actions du bureau sont enregistrées.
        Wave ne reçoit de la plateforme aucune donnée : vous payez directement dans votre application Wave.
    </p>

    <h2 class="{{ $titre }}">5. Où sont-elles hébergées ?</h2>
    <p class="{{ $texte }}">
        Hébergeur : {{ config('dahira.hebergeur') }}. Les échanges sont chiffrés (HTTPS),
        les mots de passe ne sont jamais conservés en clair, et les photos ne sont accessibles qu'aux personnes autorisées.
    </p>

    <h2 class="{{ $titre }}">6. Combien de temps les conservons-nous ?</h2>
    <ul class="{{ $liste }}">
        <li>Fiche d'un membre actif : pendant toute la durée de l'adhésion.</li>
        <li>Après une radiation ou une demande de suppression : anonymisation sous 30 jours.</li>
        <li>Demandes d'adhésion non abouties (rejetées ou expirées) : supprimées après 6 mois.</li>
        <li>Paiements et reçus : 10 ans, pour la transparence financière de la Daara, sous forme anonyme après votre départ.</li>
        <li>Historique des connexions : 12 mois.</li>
        <li>Journal des actions du bureau : 3 ans.</li>
    </ul>

    <h2 class="{{ $titre }}">7. Vos droits</h2>
    <ul class="{{ $liste }}">
        <li><strong>Accès</strong> : télécharger à tout moment une copie de vos données, depuis « Mon compte ».</li>
        <li><strong>Rectification</strong> : modifier votre profil vous-même, ou demander une correction à votre Jewrine.</li>
        <li><strong>Opposition et suppression</strong> : demander la suppression de votre compte au bureau.</li>
        <li><strong>Réclamation</strong> : si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir
            la <strong>Commission de Protection des Données Personnelles (CDP)</strong> du Sénégal.</li>
    </ul>

    <h2 class="{{ $titre }}">8. Cookies</h2>
    <p class="{{ $texte }}">
        La plateforme n'utilise que les cookies <strong>strictement nécessaires</strong> à son fonctionnement :
        votre session de connexion et la protection des formulaires. Aucun cookie publicitaire ni de mesure d'audience.
    </p>

    <p class="mt-8 text-xs text-stone-500">Dernière mise à jour : {{ now()->translatedFormat('F Y') }}.</p>
@endsection