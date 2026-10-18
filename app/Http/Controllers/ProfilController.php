<?php

namespace App\Http\Controllers;

use App\Enums\NiveauArabe;
use App\Enums\NiveauEtudes;
use App\Enums\Sexe;
use App\Enums\SituationProfessionnelle;
use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\User;
use App\Rules\EmailFiable;
use App\Services\CompetencesService;
use App\Services\StatutMembreService;
use App\Support\Langues;
use App\Support\MessagesValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Le membre complète et met à jour son profil. Si sa fiche est « à corriger »,
 * il peut aussi corriger son identité, et sa demande repart chez le Jewrine.
 */
class ProfilController extends Controller
{
    public function edit(Request $request): View
    {
        $membre = $this->membre($request);

        return view('espace.profil', [
            'membre' => $membre,
            'correction' => $membre->statut === StatutMembre::ACorriger,
            'cellules' => Cellule::query()->enActivite()->orderBy('nom')->get(['id', 'nom']),
            'competences' => CompetencesService::liste(),
            'mesCompetences' => $membre->competences()->pluck('competences.id')->map(fn (mixed $id): int => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, StatutMembreService $statuts, CompetencesService $competences): RedirectResponse
    {
        $membre = $this->membre($request);
        $correction = $membre->statut === StatutMembre::ACorriger;

        $regles = [
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($membre->user_id), new EmailFiable($membre->user_id)],
            'adresse' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:100'],
            'pays_residence' => ['required', 'string', 'max:100'],
            'niveau_etudes' => ['nullable', Rule::enum(NiveauEtudes::class)],
            'niveau_etudes_autre' => ['nullable', 'required_if:niveau_etudes,autre', 'string', 'max:100'],
            'diplome' => ['nullable', 'string', 'max:150'],
            'domaine_etudes' => ['nullable', 'string', 'max:150'],
            'langues' => ['nullable', 'array'],
            'langues.*' => ['string', Rule::in(array_keys(Langues::liste()))],
            'niveau_arabe' => ['nullable', Rule::enum(NiveauArabe::class)],
            'situation_professionnelle' => ['nullable', Rule::enum(SituationProfessionnelle::class)],
            'profession' => ['nullable', 'string', 'max:150'],
            'secteur_activite' => ['nullable', 'string', 'max:150'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'competences' => ['nullable', 'array'],
            'competences.*' => ['integer'],
        ];

        if ($correction) {
            $regles += [
                'prenom' => ['required', 'string', 'max:100'],
                'nom' => ['required', 'string', 'max:100'],
                'sexe' => ['required', Rule::enum(Sexe::class)],
                'date_naissance' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'after:1900-01-01', 'before:today'],
                'lieu_naissance' => ['nullable', 'string', 'max:100'],
                'cellule_id' => ['required', 'integer', Rule::exists('cellules', 'id')->whereNull('archive_le')],
            ];
        }

        $donnees = $request->validate($regles, MessagesValidation::tous());

        $champs = Arr::only($donnees, [
            'email', 'adresse', 'ville', 'pays_residence', 'niveau_etudes', 'niveau_etudes_autre', 'diplome', 'domaine_etudes',
            'niveau_arabe', 'situation_professionnelle', 'profession', 'secteur_activite',
            'prenom', 'nom', 'sexe', 'date_naissance', 'lieu_naissance', 'cellule_id',
        ]);
        $champs['langues'] = $donnees['langues'] ?? [];

        $photo = $request->file('photo');
        if ($photo instanceof UploadedFile) {
            $champs['photo_path'] = $photo->store('photos', 'local');
        }

        $membre->update($champs);
        $membre->user?->update(['email' => $donnees['email'] ?? null]);

        if (! $correction && ($auteur = $request->user()) instanceof User) {
            $competences->definir($membre, array_values(array_map('intval', (array) ($donnees['competences'] ?? []))), $auteur);
        }

        if ($correction) {
            $auteur = $request->user();
            $statuts->changer($membre, StatutMembre::EnAttente, $auteur instanceof User ? $auteur : null);

            return redirect()->route('espace')
                ->with('succes', 'Votre fiche corrigée a été renvoyée à votre Jewrine de cellule.');
        }

        return redirect()->route('espace')->with('succes', 'Votre profil a été mis à jour.');
    }

    private function membre(Request $request): Membre
    {
        $user = $request->user();
        $membre = $user instanceof User ? $user->membre : null;

        abort_if($membre === null, 404);

        // Avant la validation, seule la correction d'une fiche « à corriger » est possible.
        abort_unless(in_array($membre->statut, [StatutMembre::Valide, StatutMembre::ACorriger], true), 403);

        return $membre;
    }
}
