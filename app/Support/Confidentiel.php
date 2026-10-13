<?php

namespace App\Support;

use App\Models\JournalExport;
use App\Models\User;

/** La mention portée par chaque document exporté, et la trace de chaque téléchargement. */
final class Confidentiel
{
    public const AVERTISSEMENT = 'Ce document contient des données personnelles des membres. Ne le partagez pas dans les groupes WhatsApp ni en dehors du bureau.';

    public static function mention(User $utilisateur): string
    {
        return 'Document confidentiel – Daara Nouroud Daareyni Kaolack – exporté par '.$utilisateur->name
            .' le '.now()->format('d/m/Y à H:i').'. Ne pas diffuser.';
    }

    public static function noter(User $utilisateur, string $document, ?string $details = null): void
    {
        JournalExport::query()->create([
            'user_id' => $utilisateur->id,
            'document' => mb_substr($document, 0, 150),
            'details' => $details !== null ? mb_substr($details, 0, 255) : null,
        ]);
    }
}
