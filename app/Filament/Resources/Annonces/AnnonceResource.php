<?php

namespace App\Filament\Resources\Annonces;

use App\Filament\Resources\Annonces\Pages\ManageAnnonces;
use App\Models\Annonce;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Les annonces de la Daara, avec affiche. Réservées au super administrateur (voir AnnoncePolicy).
 */
class AnnonceResource extends Resource
{
    protected static ?string $model = Annonce::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Vie de la Daara';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'annonce';

    protected static ?string $pluralModelLabel = 'annonces';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('titre')->label('Titre')->required()->maxLength(150),
            FileUpload::make('affiche_path')
                ->label('Affiche')
                ->image()
                ->disk('public')
                ->directory('affiches')
                ->visibility('public')
                ->maxSize(5120)
                ->helperText('Image JPEG ou PNG, 5 Mo au maximum. Facultative si vous écrivez un message.'),
            Textarea::make('contenu')
                ->label('Message')
                ->rows(5)
                ->maxLength(5000)
                ->helperText('Facultatif si vous ajoutez une affiche.'),
            Toggle::make('importante')->label('Annonce importante (affichée en premier)'),
            DateTimePicker::make('expire_le')
                ->label('Ne plus afficher après le')
                ->native(false)
                ->helperText('Facultatif. Par exemple, le lendemain de l\'événement annoncé.'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('affiche_path')->label('Affiche')->disk('public')->height(48),
                TextColumn::make('titre')->label('Titre')->searchable()->limit(50),
                IconColumn::make('importante')->label('Importante')->boolean(),
                TextColumn::make('created_at')->label('Publiée le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('expire_le')->label('Jusqu\'au')->dateTime('d/m/Y')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageAnnonces::route('/'),
        ];
    }
}
