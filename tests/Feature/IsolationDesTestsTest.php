<?php

use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;

it('donne à chaque processus de tests son propre disque factice', function () {
    Storage::fake('local');

    // Deux passages lancés en même temps (SQLite et PostgreSQL) ne s'effacent plus leurs fichiers.
    // En mode --parallel, Laravel donne déjà un jeton à chaque processus ; sinon, c'est le numéro du processus.
    expect(Storage::disk('local')->path(''))->toContain('_test_'.ParallelTesting::token())
        ->and(ParallelTesting::token())->not->toBeFalse();
});
