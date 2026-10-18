<?php

namespace App\Filament\Resources\Membres\Schemas;

use App\Enums\LienFamille;
use App\Enums\NiveauArabe;
use App\Enums\NiveauEtudes;
use App\Enums\Sexe;
use App\Enums\SituationProfessionnelle;
use App\Enums\StatutMembre;
use App\Filament\Resources\Membres\MembreResource;
use App\Models\Cellule;
use App\Models\Membre;
use App\Services\TelephoneNormalizer;
use App\Support\Langues;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MembreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('matricule')
                        ->label('Matricule')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Attribué à la validation')
                        ->visibleOn('edit'),
                    TextInput::make('prenom')->label('Prénom')->required()->maxLength(100),
                    TextInput::make('nom')->label('Nom')->required()->maxLength(100),
                    Select::make('sexe')->label('Sexe')->options(Sexe::class)->required(),
                    DatePicker::make('date_naissance')
                        ->label('Date de naissance')
                        ->minDate('1900-01-01')
                        ->maxDate(now()->subDay()),
                    TextInput::make('lieu_naissance')->label('Lieu de naissance')->maxLength(100),
                    FileUpload::make('photo_path')
                        ->label('Photo')
                        ->image()
                        ->disk('local')
                        ->directory('photos')
                        ->visibility('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120)
                        ->imageEditor()
                        ->helperText('Photo de face, visage bien visible. 5 Mo maximum.')
                        ->columnSpanFull(),
                ]),

            Section::make('Contact et résidence')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('telephone')
                        ->label('Téléphone')
                        ->tel()
                        ->placeholder('77 123 45 67')
                        ->helperText('Facultatif : un membre sans téléphone (ou un enfant) reste joignable par sa famille ou son Jewrine.')
                        ->rules([
                            fn (?Membre $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (blank($value)) {
                                    return;
                                }

                                $telephone = TelephoneNormalizer::normaliser(is_string($value) ? $value : null);

                                if ($telephone === null) {
                                    $fail('Numéro invalide. Exemple : 77 123 45 67, ou +33 6 12 34 56 78 depuis l\'étranger.');

                                    return;
                                }

                                $dejaPris = Membre::query()
                                    ->where('telephone', $telephone)
                                    ->when($record, fn ($query) => $query->whereKeyNot($record?->getKey()))
                                    ->exists();

                                if ($dejaPris) {
                                    $fail('Ce numéro est déjà utilisé par un autre membre.');
                                }
                            },
                        ]),
                    TextInput::make('email')->label('Email')->email()->maxLength(255),
                    TextInput::make('adresse')->label('Adresse ou quartier')->maxLength(255),
                    TextInput::make('ville')->label('Ville')->maxLength(100),
                    TextInput::make('pays_residence')->label('Pays de résidence')->required()->default('Sénégal')->maxLength(100),
                ]),

            Section::make('Formation')
                ->columns(2)
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Select::make('niveau_etudes')->label('Niveau d\'études')->options(NiveauEtudes::class),
                    TextInput::make('diplome')->label('Diplôme le plus élevé')->maxLength(150),
                    TextInput::make('domaine_etudes')->label('Domaine d\'études')->maxLength(150),
                    Select::make('niveau_arabe')->label('Niveau en arabe')->options(NiveauArabe::class),
                    CheckboxList::make('langues')->label('Langues parlées')->options(Langues::liste())->columns(3)->columnSpanFull(),
                ]),

            Section::make('Situation professionnelle')
                ->columns(2)
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Select::make('situation_professionnelle')->label('Situation')->options(SituationProfessionnelle::class),
                    TextInput::make('profession')->label('Profession')->maxLength(150),
                    TextInput::make('secteur_activite')->label('Secteur d\'activité')->maxLength(150),
                ]),

            Section::make('Famille')
                ->description('Un enfant de moins de 18 ans est rattaché à un parent ou tuteur, membre de la Daara, qui gère sa fiche depuis son téléphone. Le proche prend sa cellule.')
                ->columns(2)
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Select::make('responsable_id')
                        ->label('Fiche gérée par (parent, tuteur ou proche)')
                        ->options(fn (?Membre $record): array => self::responsablesPossibles($record))
                        ->searchable()
                        ->live(),
                    Select::make('lien_famille')
                        ->label('Ce membre est, pour lui')
                        ->options(LienFamille::class)
                        ->visible(fn (Get $get): bool => filled($get('responsable_id'))),
                ]),

            Section::make('Affectations dans la Daara')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('cellule_id')
                        ->label('Cellule')
                        ->helperText(fn (Get $get): ?string => filled($get('responsable_id')) ? 'Le proche prend la cellule du membre qui gère sa fiche.' : null)
                        ->relationship('cellule', 'nom', modifyQueryUsing: fn ($query) => MembreResource::limiterCellules($query))
                        ->searchable()
                        ->preload()
                        ->required(),
                    DatePicker::make('date_adhesion')
                        ->label('Date d\'adhésion')
                        ->helperText('Remplie automatiquement à la validation si elle est vide.'),
                ]),
        ]);
    }

    /**
     * Les membres validés qui peuvent gérer la fiche d'un proche, dans les cellules que l'on voit ;
     * jamais un membre dont la fiche est elle-même gérée par un proche.
     *
     * @return array<int, string>
     */
    public static function responsablesPossibles(?Membre $record): array
    {
        return Membre::query()
            ->where('statut', StatutMembre::Valide->value)
            ->whereNull('responsable_id')
            ->whereNotNull('user_id')
            ->whereIn('cellule_id', MembreResource::limiterCellules(Cellule::query())->select('cellules.id'))
            ->when($record !== null, fn ($query) => $query->whereKeyNot($record?->id))
            ->orderBy('nom')
            ->get()
            ->mapWithKeys(fn (Membre $membre): array => [$membre->id => $membre->prenom.' '.$membre->nom.' — '.($membre->matricule ?? $membre->telephone)])
            ->all();
    }
}
