<?php

namespace App\Http\Controllers;

use App\Models\Membre;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert une photo depuis le disque privé, après contrôle des droits (règle 4.6).
 */
class PhotoController extends Controller
{
    public function __invoke(Membre $membre): StreamedResponse
    {
        Gate::authorize('voirPhoto', $membre);

        abort_if($membre->photo_path === null || ! Storage::disk('local')->exists($membre->photo_path), 404);

        return Storage::disk('local')->response($membre->photo_path);
    }
}
