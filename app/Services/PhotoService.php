<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Règle 4.6 : photo recadrée au carré, 800 × 800, JPEG qualité 85,
 * métadonnées supprimées, nom aléatoire, disque privé.
 */
final class PhotoService
{
    public const DISQUE = 'local';

    public const TAILLE = 800;

    public function traiter(string $chemin): string
    {
        $disque = Storage::disk(self::DISQUE);
        $contenu = $disque->get($chemin);

        if ($contenu === null) {
            return $chemin;
        }

        $image = (new ImageManager(new Driver))->read($contenu);
        $image->cover(self::TAILLE, self::TAILLE);

        // Le réencodage en JPEG ne conserve aucune métadonnée (EXIF, GPS).
        $nouveauChemin = 'photos/'.Str::uuid()->toString().'.jpg';
        $disque->put($nouveauChemin, (string) $image->toJpeg(85));
        $disque->delete($chemin);

        return $nouveauChemin;
    }

    public function supprimer(?string $chemin): void
    {
        if ($chemin !== null && $chemin !== '') {
            Storage::disk(self::DISQUE)->delete($chemin);
        }
    }
}
