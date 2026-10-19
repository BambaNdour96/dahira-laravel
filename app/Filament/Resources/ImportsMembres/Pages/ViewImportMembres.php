<?php

namespace App\Filament\Resources\ImportsMembres\Pages;

use App\Filament\Resources\ImportsMembres\ImportMembresResource;
use App\Models\ImportMembres;
use App\Services\ImportMembresService;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Le rapport d'un fichier : chaque ligne et ses messages ; puis l'import des lignes prêtes. */
class ViewImportMembres extends ViewRecord
{
    protected static string $resource = ImportMembresResource::class;

    public function getTitle(): string
    {
        $import = $this->getRecord();

        return $import instanceof ImportMembres ? 'Reprise : '.$import->fichier_nom : 'Reprise des données';
    }

    public function getSubheading(): ?string
    {
        $import = $this->getRecord();

        if (! $import instanceof ImportMembres) {
            return null;
        }

        return $import->statut === ImportMembres::IMPORTE
            ? $import->lignes_importees.' membre(s) importé(s) le '.$import->importe_le?->format('d/m/Y à H:i').' sur '.$import->lignes_total.' ligne(s).'
            : $import->lignes_pretes.' ligne(s) prête(s) sur '.$import->lignes_total.'. Corrigez les autres dans le fichier, puis analysez-le de nouveau, ou importez déjà les lignes prêtes.';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('importer')
                ->label(fn (ImportMembres $record): string => 'Importer les '.$record->lignes_pretes.' ligne(s) prête(s)')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->color('success')
                ->visible(fn (ImportMembres $record): bool => $record->statut === ImportMembres::ANALYSE && $record->lignes_pretes > 0)
                ->requiresConfirmation()
                ->modalDescription('Les membres seront validés, avec leur carte, et pourront activer leur compte depuis le téléphone. Le fichier est vérifié de nouveau au moment de l\'import.')
                ->action(function (ImportMembres $record): void {
                    $auteur = ImportMembresResource::utilisateur();
                    abort_unless($auteur !== null, 403);

                    try {
                        $nombre = app(ImportMembresService::class)->importer($record, $auteur);
                    } catch (DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title($nombre.' membre(s) importé(s).')->success()->send();
                    $this->refreshFormData([]);
                }),
            Action::make('rapport')
                ->label('Télécharger le rapport')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (ImportMembres $record): string => route('reprise.rapport', $record)),
        ];
    }
}
