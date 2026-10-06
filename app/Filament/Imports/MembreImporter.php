<?php

namespace App\Filament\Imports;

use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Services\CarteService;
use App\Services\MatriculeService;
use App\Services\TelephoneNormalizer;
use DateTime;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

/**
 * Import des membres existants de la daara (cellule 9.4 du cahier des charges).
 * Les membres importés sont créés au statut « validé ».
 */
class MembreImporter extends Importer
{
    protected static ?string $model = Membre::class;

    /** @return array<ImportColumn> */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('matricule')
                ->label('Matricule')
                ->rules(['nullable', 'regex:/^NDK-\d{4}-\d{4}$/', 'unique:membres,matricule'])
                ->example('NDK-2019-0001'),
            ImportColumn::make('prenom')
                ->label('Prénom')
                ->requiredMapping()
                ->rules(['required', 'max:100'])
                ->example('Cheikh'),
            ImportColumn::make('nom')
                ->label('Nom')
                ->requiredMapping()
                ->rules(['required', 'max:100'])
                ->example('Ndour'),
            ImportColumn::make('sexe')
                ->label('Sexe')
                ->requiredMapping()
                ->castStateUsing(fn (?string $state): ?string => self::sexe($state))
                ->rules(['required', 'in:H,F'])
                ->example('H'),
            ImportColumn::make('telephone')
                ->label('Téléphone')
                ->requiredMapping()
                ->castStateUsing(fn (?string $state): ?string => TelephoneNormalizer::normaliser($state))
                ->rules(['required', 'unique:membres,telephone'])
                ->example('77 123 45 67'),
            ImportColumn::make('email')
                ->label('Email')
                ->rules(['nullable', 'email'])
                ->example(''),
            ImportColumn::make('cellule')
                ->label('Cellule')
                ->requiredMapping()
                ->relationship(resolveUsing: 'nom')
                ->rules(['required'])
                ->example('Yoff'),
            ImportColumn::make('date_naissance')
                ->label('Date de naissance')
                ->castStateUsing(fn (?string $state): ?string => self::date($state))
                ->rules(['nullable', 'date'])
                ->example('15/03/1990'),
            ImportColumn::make('lieu_naissance')->label('Lieu de naissance')->rules(['nullable', 'max:100'])->example('Dakar'),
            ImportColumn::make('date_adhesion')
                ->label('Date d\'adhésion')
                ->castStateUsing(fn (?string $state): ?string => self::date($state))
                ->rules(['nullable', 'date'])
                ->example('01/01/2015'),
            ImportColumn::make('poste')
                ->label('Poste')
                ->relationship(resolveUsing: 'nom')
                ->example('Commissaire'),
            ImportColumn::make('adresse')
                ->label('Adresse ou quartier')
                ->rules(['nullable', 'max:255'])
                ->example('Médina Baye'),
            ImportColumn::make('ville')
                ->label('Ville')
                ->rules(['nullable', 'max:100'])
                ->example('Kaolack'),
        ];
    }

    /** @return array<string, string> */
    public function getValidationMessages(): array
    {
        return [
            'telephone.required' => 'Téléphone absent ou invalide.',
            'telephone.unique' => 'Ce téléphone est déjà enregistré.',
            'matricule.unique' => 'Ce matricule existe déjà.',
            'matricule.regex' => 'Le matricule doit avoir la forme NDK-AAAA-NNNN.',
            'sexe.in' => 'Le sexe doit être H ou F.',
        ];
    }

    public function resolveRecord(): Membre
    {
        return new Membre;
    }

    protected function beforeSave(): void
    {
        $membre = $this->record;

        if (! $membre instanceof Membre) {
            return;
        }

        if ($membre->getAttribute('cellule_id') === null) {
            throw new RowImportFailedException('Cellule inconnue : vérifiez que son nom est identique à celui du panneau.');
        }

        $membre->statut = StatutMembre::Valide;
        $membre->valide_le = now();

        if ($membre->date_adhesion === null) {
            $membre->date_adhesion = now();
        }
    }

    protected function afterSave(): void
    {
        $membre = $this->record;

        if (! $membre instanceof Membre) {
            return;
        }

        if ($membre->matricule === null) {
            app(MatriculeService::class)->attribuer($membre, $membre->date_adhesion?->year);
        }

        app(CarteService::class)->emettre($membre);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $texte = $import->successful_rows.' membre(s) importé(s).';
        $echecs = $import->getFailedRowsCount();

        if ($echecs > 0) {
            $texte .= ' '.$echecs.' ligne(s) en erreur : téléchargez le rapport pour les corriger.';
        }

        return $texte;
    }

    private static function sexe(?string $valeur): ?string
    {
        return match (mb_strtoupper(trim((string) $valeur))) {
            'H', 'HOMME', 'M' => Sexe::Homme->value,
            'F', 'FEMME' => Sexe::Femme->value,
            default => $valeur,
        };
    }

    /** Accepte 15/03/1990, 1990-03-15 ou 15-03-1990. */
    private static function date(?string $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        if ($valeur === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $date = DateTime::createFromFormat('!'.$format, $valeur);

            if ($date !== false && $date->format($format) === $valeur) {
                return $date->format('Y-m-d');
            }
        }

        return $valeur;
    }
}
