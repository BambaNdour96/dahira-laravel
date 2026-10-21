<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->isolerLesDisquesFactices();
    }

    /**
     * Storage::fake() vide son dossier au début de chaque test. Sans jeton, ce dossier est le même pour tous les
     * processus : deux passages lancés en même temps (par exemple SQLite et PostgreSQL) effacent alors les fichiers l'un
     * de l'autre, et un test échoue au hasard. Hors du mode --parallel (qui a déjà ses jetons), chaque processus reçoit
     * son propre dossier, supprimé à la fin du test ; ce jeton ne sert qu'aux disques factices.
     */
    private function isolerLesDisquesFactices(): void
    {
        if (! empty($_SERVER['LARAVEL_PARALLEL_TESTING'])) {
            return;
        }

        $jeton = 'p'.getmypid();
        ParallelTesting::resolveTokenUsing(fn (): string => $jeton);

        $this->beforeApplicationDestroyed(function () use ($jeton): void {
            foreach (glob(storage_path('framework/testing/disks/*_test_'.$jeton)) ?: [] as $dossier) {
                (new Filesystem)->deleteDirectory($dossier);
            }
        });
    }
}
