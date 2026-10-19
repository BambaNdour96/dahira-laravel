<?php

namespace App\Filament\Resources\CampagnesRenouvellement\Pages;

use App\Filament\Resources\CampagnesRenouvellement\CampagneRenouvellementResource;
use App\Filament\Resources\CampagnesRenouvellement\Widgets\AvancementRenouvellement;
use App\Filament\Resources\CampagnesRenouvellement\Widgets\RenouvellementsDeLaCampagne;
use App\Models\CampagneRenouvellement;
use Filament\Resources\Pages\ViewRecord;

/** Le suivi d'une campagne : l'avancement par cellule, puis chaque membre, avec la revalidation par le Jewrine. */
class ViewCampagneRenouvellement extends ViewRecord
{
    protected static string $resource = CampagneRenouvellementResource::class;

    public function getTitle(): string
    {
        $campagne = $this->getRecord();

        return $campagne instanceof CampagneRenouvellement ? 'Renouvellement '.$campagne->exercice->libelle : 'Renouvellement';
    }

    public function getSubheading(): ?string
    {
        $campagne = $this->getRecord();

        if (! $campagne instanceof CampagneRenouvellement) {
            return null;
        }

        return 'Date limite : '.$campagne->date_limite->translatedFormat('j F Y').($campagne->estClose() ? ' · close le '.$campagne->cloturee_le?->format('d/m/Y') : ' · en cours');
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [AvancementRenouvellement::class, RenouvellementsDeLaCampagne::class];
    }
}
