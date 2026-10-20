<?php

namespace App\Filament\Exports;

use App\Models\Membre;
use App\Support\Langues;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Support\Contracts\HasLabel;

/**
 * Export des membres pour les réunions du bureau (Excel ou CSV).
 */
class MembreExporter extends Exporter
{
    protected static ?string $model = Membre::class;

    /** @return array<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('matricule')->label('Matricule'),
            ExportColumn::make('prenom')->label('Prénom'),
            ExportColumn::make('nom')->label('Nom'),
            ExportColumn::make('sexe')->label('Sexe')->formatStateUsing(fn ($state) => self::libelle($state)),
            ExportColumn::make('date_naissance')->label('Date de naissance')->formatStateUsing(fn ($state) => self::date($state)),
            ExportColumn::make('lieu_naissance')->label('Lieu de naissance'),
            ExportColumn::make('telephone')->label('Téléphone'),
            ExportColumn::make('email')->label('Email'),
            ExportColumn::make('adresse')->label('Adresse'),
            ExportColumn::make('ville')->label('Ville'),
            ExportColumn::make('pays_residence')->label('Pays de résidence'),
            ExportColumn::make('cellule.nom')->label('Cellule'),
            ExportColumn::make('poste')->label('Poste principal')->state(fn (Membre $record): ?string => $record->postePrincipal()?->nom),
            ExportColumn::make('commissions.nom')->label('Commissions'),
            ExportColumn::make('niveau_etudes')->label('Niveau d\'études')->formatStateUsing(fn ($state) => self::libelle($state)),
            ExportColumn::make('diplome')->label('Diplôme'),
            ExportColumn::make('domaine_etudes')->label('Domaine d\'études'),
            ExportColumn::make('langues')->label('Langues')->formatStateUsing(fn ($state) => is_string($state) ? (Langues::liste()[$state] ?? $state) : $state),
            ExportColumn::make('situation_professionnelle')->label('Situation professionnelle')->formatStateUsing(fn ($state) => self::libelle($state)),
            ExportColumn::make('profession')->label('Profession'),
            ExportColumn::make('secteur_activite')->label('Secteur d\'activité'),
            ExportColumn::make('statut')->label('Statut')->formatStateUsing(fn ($state) => self::libelle($state)),
            ExportColumn::make('date_adhesion')->label('Date d\'adhésion')->formatStateUsing(fn ($state) => self::date($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Export terminé : '.$export->successful_rows.' membre(s). Cliquez sur « Télécharger » pour ouvrir le fichier.';
    }

    private static function libelle(mixed $state): mixed
    {
        return $state instanceof HasLabel ? $state->getLabel() : $state;
    }

    private static function date(mixed $state): mixed
    {
        return $state instanceof CarbonInterface ? $state->format('d/m/Y') : $state;
    }
}
