<?php

namespace App\Filament\Resources\Parametres;

use App\Filament\Resources\Parametres\Pages\ManageParametres;
use App\Models\Parametre;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ParametreResource extends Resource
{
    protected static ?string $model = Parametre::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'réglage';

    protected static ?string $pluralModelLabel = 'réglages';

    protected static ?string $navigationLabel = 'Réglages';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('libelle')
                ->label('Réglage')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('valeur')
                ->label('Valeur')
                ->maxLength(255)
                ->rules([
                    fn (?Parametre $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (blank($value)) {
                            return;
                        }

                        $estWave = str_starts_with((string) $record?->cle, 'wave');
                        $hote = is_string($value) ? parse_url($value, PHP_URL_HOST) : null;
                        $hote = is_string($hote) ? $hote : '';

                        $valide = is_string($value)
                            && str_starts_with($value, 'https://')
                            && ($estWave
                                ? ($hote === 'wave.com' || str_ends_with($hote, '.wave.com'))
                                : $hote === 'chat.whatsapp.com');

                        if (! $valide) {
                            $fail($estWave
                                ? 'Le lien doit être un lien Wave (https://pay.wave.com/...).'
                                : 'Le lien doit commencer par https://chat.whatsapp.com/');
                        }
                    },
                ])
                ->helperText('Visible uniquement par les membres concernés.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('libelle')->label('Réglage'),
                TextColumn::make('valeur')->label('Valeur')->limit(50)->placeholder('Non renseigné'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageParametres::route('/'),
        ];
    }
}
