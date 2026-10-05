<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EspaceController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('espace.index', [
            'membre' => $user instanceof User ? $user->membre()->with('section')->first() : null,
        ]);
    }
}
