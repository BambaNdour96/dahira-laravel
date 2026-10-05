<?php

namespace App\Http\Controllers;

use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\Section;
use App\Models\User;
use App\Services\StatutMembreService;
use App\Support\MessagesValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Le membre modifie son email et sa photo. Si sa fiche est « à corriger »,
 * il peut tout corriger, et sa demande repart chez le responsable.
 */
class ProfilController extends Controller
{
    public function edit(Request $request): View
    {
        $membre = $this->membre($request);

        return view('espace.profil', [
            'membre' => $membre,
            'correction' => $membre->statut === StatutMembre::ACorriger,
            'sections' => Section::query()->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function update(Request $request, StatutMembreService $statuts): RedirectResponse
    {
        $membre = $this->membre($request);
        $correction = $membre->statut === StatutMembre::ACorriger;

        $regles = [
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($membre->user_id)],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];

        if ($correction) {
            $regles += [
                'prenom' => ['required', 'string', 'max:100'],
                'nom' => ['required', 'string', 'max:100'],
                'sexe' => ['required', Rule::enum(Sexe::class)],
                'date_naissance' => ['nullable', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'after:1900-01-01', 'before:today'],
                'lieu_naissance' => ['nullable', 'string', 'max:100'],
                'section_id' => ['required', 'integer', 'exists:sections,id'],
            ];
        }

        $donnees = $request->validate($regles, MessagesValidation::tous());
        $champs = Arr::only($donnees, ['email', 'prenom', 'nom', 'sexe', 'date_naissance', 'lieu_naissance', 'section_id']);

        $photo = $request->file('photo');
        if ($photo instanceof UploadedFile) {
            $champs['photo_path'] = $photo->store('photos', 'local');
        }

        $membre->update($champs);
        $membre->user?->update(['email' => $donnees['email'] ?? null]);

        if ($correction) {
            $auteur = $request->user();
            $statuts->changer($membre, StatutMembre::EnAttente, $auteur instanceof User ? $auteur : null);

            return redirect()->route('espace')
                ->with('succes', 'Votre fiche corrigée a été renvoyée à votre responsable de section.');
        }

        return redirect()->route('espace')->with('succes', 'Vos informations ont été mises à jour.');
    }

    private function membre(Request $request): Membre
    {
        $user = $request->user();
        $membre = $user instanceof User ? $user->membre : null;

        abort_if($membre === null, 404);

        return $membre;
    }
}
