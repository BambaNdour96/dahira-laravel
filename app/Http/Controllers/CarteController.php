<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use App\Services\CartePhysiqueService;
use App\Services\CarteService;
use DomainException;
use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CarteController extends Controller
{
    /** Sa carte, ou celle d'un proche dont il gère la fiche (« Ma famille »). */
    public function show(Request $request, CarteService $cartes, CartePhysiqueService $plastique, ?Membre $proche = null): View
    {
        $membre = $this->membreAvecCarte($request, $proche);

        return view('espace.carte', [
            'membre' => $membre,
            'proche' => $proche,
            'tarifPlastique' => $plastique->tarif(),
            'commandePlastique' => $plastique->commandeEnCours($membre),
            'paiementPlastique' => $plastique->paiementEnCours($membre),
            'qr' => $cartes->qrSvg($membre->carteActive ?? abort(404)),
        ]);
    }

    /** La carte en PDF, à sa taille réelle, pour l'imprimer ou la garder. */
    public function pdf(Request $request, ?Membre $proche = null): Response
    {
        $membre = $this->membreAvecCarte($request, $proche);
        $carte = $membre->carteActive;
        abort_if($carte === null, 404);

        $html = view('pdf.carte', [
            'membre' => $membre,
            'logo' => $this->enDonnees(public_path('images/logo-ndk.png'), 'image/png'),
            'photo' => $membre->photo_path !== null && Storage::disk('local')->exists($membre->photo_path)
                ? 'data:image/jpeg;base64,'.base64_encode((string) Storage::disk('local')->get($membre->photo_path))
                : null,
            'qr' => (new PngWriter)->write(new QrCode(route('verification', $carte->jeton)))->getDataUri(),
            'annee' => $carte->exercice?->libelle,
        ])->render();

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="carte-'.$membre->matricule.'.pdf"',
        ]);
    }

    /** Commander la carte en plastique (pour soi, ou pour un proche) et déclarer son paiement Wave. */
    public function commander(Request $request, CartePhysiqueService $plastique, ?Membre $proche = null): RedirectResponse
    {
        $membre = $this->membreAvecCarte($request, $proche);
        [$reference, $numeroWave, $moyen] = PaiementMembreController::valider($request);
        $declarant = $request->user();
        abort_unless($declarant instanceof User, 403);

        try {
            $plastique->commander($membre, $reference, $numeroWave, $declarant, $moyen);
        } catch (DomainException $e) {
            return back()->withErrors(['reference' => $e->getMessage()]);
        }

        return redirect()->to($proche !== null ? route('famille.carte', $proche) : route('carte'))
            ->with('succes', 'Merci ! La carte en plastique est commandée : le paiement est en cours de vérification.');
    }

    private function membreAvecCarte(Request $request, ?Membre $proche): Membre
    {
        $user = $request->user();

        if ($proche !== null && $user instanceof User) {
            FamilleController::verifier($user, $proche);
        }

        $membre = $proche?->load(['cellule', 'carteActive.exercice', 'responsable']) ?? ($user instanceof User
            ? $user->membre()->with(['cellule', 'carteActive.exercice'])->first()
            : null);

        abort_unless(
            $membre !== null && $membre->statut === StatutMembre::Valide && $membre->carteActive !== null,
            404,
        );

        return $membre;
    }

    private function enDonnees(string $chemin, string $type): ?string
    {
        return is_file($chemin) ? 'data:'.$type.';base64,'.base64_encode((string) file_get_contents($chemin)) : null;
    }
}
