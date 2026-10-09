<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ConfirmerEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConfirmationEmailController extends Controller
{
    /** Le lien reçu par email confirme l'adresse, si elle n'a pas changé depuis l'envoi. */
    public function confirmer(Request $request, User $user, string $hash): RedirectResponse
    {
        abort_unless(filled($user->email) && hash_equals(sha1((string) $user->email), $hash), 403);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->saveQuietly();
        }

        return redirect()->route($request->user() !== null ? 'espace' : 'login')
            ->with('succes', 'Merci, votre adresse email est confirmée.');
    }

    public function renvoyer(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (filled($user->email) && $user->email_verified_at === null) {
            $user->notify(new ConfirmerEmail);
        }

        return back()->with('succes', 'Un nouveau lien de confirmation vient d\'être envoyé à '.$user->email.'.');
    }
}
