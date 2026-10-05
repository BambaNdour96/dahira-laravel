<?php

namespace App\Http\Controllers;

use App\Http\Requests\InscriptionRequest;
use App\Models\Section;
use App\Services\InscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InscriptionController extends Controller
{
    public function create(): View
    {
        return view('public.inscription', [
            'sections' => Section::query()->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function store(InscriptionRequest $request, InscriptionService $inscriptions): RedirectResponse
    {
        $photo = $request->file('photo');
        abort_unless($photo instanceof UploadedFile, 422);

        $user = $inscriptions->inscrire($request->validated(), $photo);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('inscription.merci');
    }
}
