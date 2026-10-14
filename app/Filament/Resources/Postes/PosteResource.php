<?php

namespace App\Filament\Resources\Postes;

use App\Filament\Resources\Postes\Pages\ManagePostes;
use App\Models\Poste;
use App\Support\Capacites;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class PosteResource extends Resource
{
    protected static ?string $model = Poste::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'poste';

    protected static ?string $pluralModelLabel = 'postes';

    protected static ?string $recordTitleAttribute = 'nom';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom du poste')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('ordre')->label('Ordre dans l\'organigramme')->integer()->minValue(0)->default(0)
                ->helperText('Le premier poste en cours dans cet ordre s\'affiche sur la carte du téléphone.'),
            Select::make('perimetre')->label('Portée')->options(Poste::PERIMETRES)->default('national')->required()
                ->disabled(fn (?Poste $record): bool => $record?->code !== null),
            Toggle::make('titulaire_unique')->label('Un seul titulaire à la fois')->default(true)
                ->helperText('Nommer un nouveau titulaire met fin à la nomination précédente (par cellule pour un poste de cellule).'),
            Toggle::make('actif')->label('Actif')->default(true)
                ->helperText('Un poste archivé ne peut plus recevoir de nomination.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ordre')->label('Ordre')->sortable(),
                TextColumn::make('nom')->label('Poste')->searchable(),
                TextColumn::make('code')->label('Casquette')->badge()->placeholder('Sans droits')
                    ->formatStateUsing(fn (?string $state): string => Capacites::CASQUETTES[$state ?? ''] ?? (string) $state),
                TextColumn::make('perimetre')->label('Portée')->formatStateUsing(fn (string $state): string => Poste::PERIMETRES[$state] ?? $state),
                IconColumn::make('titulaire_unique')->label('Titulaire unique')->boolean(),
                TextColumn::make('nominations_count')->label('En poste')->counts('nominations'),
                IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('actif')->label('Postes')->placeholder('Tous')->trueLabel('Actifs')->falseLabel('Archivés')->default(true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('ordre');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManagePostes::route('/'),
        ];
    }
}
