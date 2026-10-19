<?php

namespace App\Services;

use App\Enums\StatutCarte;
use App\Enums\StatutMembre;
use App\Models\Carte;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Émission, révocation et vérification des cartes (section 10 du cahier des charges).
 */
final class CarteService
{
    /** Émet une carte active, de l'exercice indiqué (par défaut l'exercice en cours), ou renvoie celle qui existe déjà. */
    public function emettre(Membre $membre, ?Exercice $exercice = null): Carte
    {
        $active = $membre->carteActive()->first();

        if ($active !== null) {
            return $active;
        }

        return $membre->cartes()->create([
            'exercice_id' => ($exercice ?? Exercice::enCours())?->id,
            'jeton' => $this->nouveauJeton(),
            'statut' => StatutCarte::Active,
            'emise_le' => now(),
        ]);
    }

    /** Révoque une carte et en émet aussitôt une nouvelle, avec un nouveau jeton (de l'exercice indiqué, ou du même). */
    public function revoquer(Carte $carte, ?User $auteur, string $motif, ?Exercice $exercice = null): Carte
    {
        return DB::transaction(function () use ($carte, $auteur, $motif, $exercice): Carte {
            $this->desactiver($carte, $auteur, $motif);

            return $this->emettre($carte->membre, $exercice ?? $carte->exercice);
        });
    }

    /** Révoque une carte sans la remplacer (adhésion non renouvelée à la date limite). */
    public function desactiver(Carte $carte, ?User $auteur, string $motif): void
    {
        $carte->update([
            'statut' => StatutCarte::Revoquee,
            'revoquee_le' => now(),
            'revoquee_par' => $auteur?->id,
            'motif_revocation' => $motif,
        ]);
    }

    /** Renvoie la carte si elle est valide au moment du scan, sinon null. */
    public function verifier(string $jeton): ?Carte
    {
        if (! preg_match('/^[A-Za-z0-9]{22}$/', $jeton)) {
            return null;
        }

        $carte = Carte::query()
            ->with('membre.cellule')
            ->where('jeton', $jeton)
            ->first();

        if ($carte === null
            || $carte->statut !== StatutCarte::Active
            || $carte->membre->statut !== StatutMembre::Valide) {
            return null;
        }

        return $carte;
    }

    /** Image SVG du QR code : il ne contient que l'adresse de vérification. */
    public function qrSvg(Carte $carte): string
    {
        return (new SvgWriter)->write(new QrCode(route('verification', $carte->jeton)))->getString();
    }

    private function nouveauJeton(): string
    {
        do {
            $jeton = Str::random(22);
        } while (Carte::query()->where('jeton', $jeton)->exists());

        return $jeton;
    }
}
