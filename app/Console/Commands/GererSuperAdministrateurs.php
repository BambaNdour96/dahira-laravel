<?php

namespace App\Console\Commands;

use App\Models\JournalActivite;
use App\Models\User;
use App\Services\TelephoneNormalizer;
use Illuminate\Console\Command;

/**
 * Les super administrateurs (deux au plus, jamais aucun) se désignent ici, sur le serveur,
 * et non depuis un écran : personne ne peut s'attribuer ce rôle depuis la plateforme.
 */
class GererSuperAdministrateurs extends Command
{
    public const MAXIMUM = 2;

    protected $signature = 'ndk:super-admin
        {telephone? : Numéro du compte concerné}
        {--retirer : Retirer le rôle au lieu de l\'attribuer}';

    protected $description = 'Liste, attribue ou retire le rôle de super administrateur (deux au plus)';

    public function handle(): int
    {
        $actuels = User::role('super_administrateur')->orderBy('name')->get();

        if ($this->argument('telephone') === null) {
            $this->table(['Nom', 'Téléphone'], $actuels->map(fn (User $user): array => [$user->name, $user->telephone])->all());

            return self::SUCCESS;
        }

        $telephone = TelephoneNormalizer::normaliser((string) $this->argument('telephone'));
        $compte = $telephone !== null ? User::query()->where('telephone', $telephone)->first() : null;

        if ($compte === null) {
            $this->error('Aucun compte avec ce numéro.');

            return self::FAILURE;
        }

        return $this->option('retirer') ? $this->retirer($compte, $actuels->count()) : $this->attribuer($compte, $actuels->count());
    }

    private function attribuer(User $compte, int $nombre): int
    {
        if ($compte->hasRole('super_administrateur')) {
            $this->info($compte->name.' est déjà super administrateur.');

            return self::SUCCESS;
        }

        if ($nombre >= self::MAXIMUM) {
            $this->error('La Daara a déjà '.self::MAXIMUM.' super administrateurs : retirez d\'abord l\'un d\'eux.');

            return self::FAILURE;
        }

        if (! $compte->actif) {
            $this->error('Ce compte est désactivé.');

            return self::FAILURE;
        }

        $compte->assignRole('super_administrateur');
        JournalActivite::enregistrer($compte, 'modifie', ['avant' => ['super_administrateur' => 'non'], 'apres' => ['super_administrateur' => 'oui']]);
        $this->info($compte->name.' est maintenant super administrateur.');

        return self::SUCCESS;
    }

    private function retirer(User $compte, int $nombre): int
    {
        if (! $compte->hasRole('super_administrateur')) {
            $this->info($compte->name.' n\'est pas super administrateur.');

            return self::SUCCESS;
        }

        if ($nombre <= 1) {
            $this->error('C\'est le dernier super administrateur : désignez-en d\'abord un autre.');

            return self::FAILURE;
        }

        $compte->removeRole('super_administrateur');
        JournalActivite::enregistrer($compte, 'modifie', ['avant' => ['super_administrateur' => 'oui'], 'apres' => ['super_administrateur' => 'non']]);
        $this->info($compte->name.' n\'est plus super administrateur.');

        return self::SUCCESS;
    }
}
