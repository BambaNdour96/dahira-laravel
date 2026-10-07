<?php

namespace App\Filament\Exports;

use App\Models\Paiement;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Support\Contracts\HasLabel;

class PaiementExporter extends Exporter
{
    protected static ?string $model = Paiement::class;

    /** @return array<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('numero_recu')->label('Reçu'),
            ExportColumn::make('declare_le')->label('Déclaré le')->formatStateUsing(fn ($state) => self::date($state)),
            ExportColumn::make('traite_le')->label('Traité le')->formatStateUsing(fn ($state) => self::date($state)),
            ExportColumn::make('membre.matricule')->label('Matricule'),
            ExportColumn::make('membre.prenom')->label('Prénom'),
            ExportColumn::make('membre.nom')->label('Nom'),
            ExportColumn::make('membre.cellule.nom')->label('Cellule'),
            ExportColumn::make('objet')->label('Objet')->state(fn (Paiement $record): string => $record->libelleObjet()),
            ExportColumn::make('montant')->label('Montant (FCFA)'),
            ExportColumn::make('moyen')->label('Moyen')->formatStateUsing(fn ($state) => $state instanceof HasLabel ? $state->getLabel() : $state),
            ExportColumn::make('reference')->label('Référence Wave'),
            ExportColumn::make('statut')->label('Statut')->formatStateUsing(fn ($state) => $state instanceof HasLabel ? $state->getLabel() : $state),
            ExportColumn::make('traitePar.name')->label('Traité par'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Export terminé : '.$export->successful_rows.' paiement(s). Cliquez sur « Télécharger » pour ouvrir le fichier.';
    }

    private static function date(mixed $state): mixed
    {
        return $state instanceof CarbonInterface ? $state->format('d/m/Y H:i') : $state;
    }
}
