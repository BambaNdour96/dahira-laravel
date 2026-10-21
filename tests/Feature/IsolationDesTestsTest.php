<?php

use Illuminate\Support\Facades\Storage;

it('donne à chaque processus de tests son propre disque factice', function () {
    Storage::fake('local');

    // Deux passages lancés en même temps (SQLite et PostgreSQL) ne s'effacent plus leurs fichiers.
    expect(Storage::disk('local')->path(''))->toContain('_test_p'.getmypid());
});
