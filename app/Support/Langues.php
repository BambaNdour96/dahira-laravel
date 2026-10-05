<?php

namespace App\Support;

final class Langues
{
    /** @return array<string, string> */
    public static function liste(): array
    {
        return [
            'wolof' => 'Wolof',
            'francais' => 'Français',
            'arabe' => 'Arabe',
            'anglais' => 'Anglais',
            'pulaar' => 'Pulaar',
            'serere' => 'Sérère',
            'diola' => 'Diola',
            'mandingue' => 'Mandingue',
            'autre' => 'Autre',
        ];
    }
}
