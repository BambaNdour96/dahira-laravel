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
