<?php

namespace App\Filament\Resources\Membres\Schemas;

use App\Enums\Sexe;
use App\Models\Membre;
use App\Services\TelephoneNormalizer;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                    TextInput::make('prenom')
                        ->label('Prénom')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('nom')
                        ->label('Nom')
                        ->required()
                        ->maxLength(100),
                    Select::make('sexe')
                        ->label('Sexe')
                        ->options(Sexe::class)
                        ->required(),
                    DatePicker::make('date_naissance')
                        ->label('Date de naissance')
                        ->maxDate(now()->subDay()),
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

            Section::make('Contact')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('telephone')
                        ->label('Téléphone')
                        ->tel()
                        ->required()
                        ->placeholder('77 123 45 67')
                        ->rules([
                            fn (?Membre $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                $telephone = TelephoneNormalizer::normaliser(is_string($value) ? $value : null);

                                if ($telephone === null) {
                                    $fail('Numéro invalide. Exemple : 77 123 45 67, ou +33 6 12 34 56 78 depuis l\'étranger.');

                                    return;
                                }

                                $dejaPris = Membre::query()
                                    ->where('telephone', $telephone)
                                    ->when($record, fn ($requete) => $requete->whereKeyNot($record?->getKey()))
                                    ->exists();

                                if ($dejaPris) {
                                    $fail('Ce numéro est déjà utilisé par un autre membre.');
                                }
                            },
                        ]),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),
                ]),

            Section::make('Dahira')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('section_id')
                        ->label('Section')
                        ->relationship('section', 'nom')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('fonction')
                        ->label('Fonction')
                        ->maxLength(100)
                        ->placeholder('Exemple : trésorier, commissaire'),
                    DatePicker::make('date_adhesion')
                        ->label('Date d\'adhésion')
                        ->helperText('Remplie automatiquement à la validation si elle est vide.'),
                ]),
        ]);
    }
}
