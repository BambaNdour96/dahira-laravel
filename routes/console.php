<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Chaque nuit : expiration des demandes acceptées et non payées.
Schedule::command('membres:expirer')->dailyAt('03:00');

// Le 5 de chaque mois : rappel par email aux membres en retard de cotisation.
Schedule::command('cotisations:rappeler')->monthlyOn(5, '09:00');

// Chaque nuit à 3 h : application des durées de conservation des données personnelles.
Schedule::command('donnees:purger')->dailyAt('03:00');

// Chaque matin à 8 h : paiements en attente depuis 48 h et caisses des représentants à remettre.
Schedule::command('finance:routine')->dailyAt('08:00');

// Chaque matin à 8 h 15 : actions des comités à échéance, et retards signalés aux présidents.
Schedule::command('comites:routine')->dailyAt('08:15');

// Chaque matin à 8 h 30 : rappel des dahiras du jour, alerte après 3 absences de suite.
Schedule::command('dahiras:routine')->dailyAt('08:30');

// Chaque matin à 9 h : rappels des programmes (jamais de nuit, jamais en double).
Schedule::command('programmes:rappeler')->dailyAt('09:00');

// Chaque matin à 8 h : alerte sur les tâches en attente depuis plus de 3 jours.
Schedule::command('taches:surveiller')->dailyAt('08:00');

// Chaque matin à 9 h 15 : un proche qui atteint 18 ans peut recevoir son propre compte (proposé, jamais imposé).
Schedule::command('familles:routine')->dailyAt('09:15');

// Chaque matin à 9 h 30 : relances du renouvellement annuel, puis clôture à la date limite.
Schedule::command('renouvellement:routine')->dailyAt('09:30');
