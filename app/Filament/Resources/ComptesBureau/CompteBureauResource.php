<?php

namespace App\Filament\Resources\ComptesBureau;

use App\Filament\Actions\EnTantQueAction;
use App\Filament\Resources\ComptesBureau\Pages\ManageComptesBureau;
use App\Models\Cellule;
use App\Models\User;
use App\Services\CompteBureauService;
use App\Services\TelephoneNormalizer;
use BackedEnum;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CompteBureauResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'comptes-bureau';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'compte du bureau';

    protected static ?string $pluralModelLabel = 'comptes du bureau';

    protected static ?string $navigationLabel = 'Comptes du bureau';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nom complet')
                ->required()
                ->maxLength(150),
            TextInput::make('telephone')
                ->label('Téléphone')
                ->tel()
                ->required()
                ->disabledOn('edit')
                ->placeholder('77 123 45 67')
                ->helperText('Si ce numéro appartient déjà à un membre inscrit, il reçoit simplement le rôle.')
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (TelephoneNormalizer::normaliser(is_string($value) ? $value : null) === null) {
                            $fail('Numéro invalide. Exemple : 77 123 45 67.');
                        }
                    },
                ]),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255)
                ->unique(table: 'users', column: 'email', ignoreRecord: true),
            Select::make('role')
                ->label('Rôle')
                ->options(CompteBureauService::ROLES)
                ->required()
                ->live()
                ->hidden(fn (?User $record): bool => (bool) $record?->hasRole('super_administrateur')),
            Select::make('cellule_id')
                ->label('Cellule de ce Jewrine')
                ->options(fn (): array => Cellule::query()->orderBy('nom')->pluck('nom', 'id')->all())
                ->visible(fn ($get): bool => $get('role') === 'responsable_section')
                ->required(fn ($get): bool => $get('role') === 'responsable_section')
                ->helperText('Si cette cellule avait déjà un Jewrine, il sera remplacé.'),
            TextInput::make('password')
                ->label('Mot de passe provisoire')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required()
                ->visibleOn('create')
                ->helperText('À transmettre à la personne. Ignoré si le numéro a déjà un compte.'),
            Toggle::make('actif')
                ->label('Compte actif')
                ->helperText('Un compte désactivé ne peut plus se connecter.')
                ->visibleOn('edit')
                ->hidden(fn (?User $record): bool => (bool) $record?->hasRole('super_administrateur')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', [
                    'super_administrateur', 'administrateur', 'tresorier', 'responsable_section',
                ]))
                ->with('roles'))
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('telephone')->label('Téléphone')->searchable(),
                TextColumn::make('roles.name')
                    ->label('Rôles')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CompteBureauService::LIBELLES[$state] ?? $state),
                TextColumn::make('cellule')
                    ->label('Cellule')
                    ->state(fn (User $record): string => (string) (Cellule::query()->where('responsable_id', $record->id)->value('nom') ?? '—')),
                IconColumn::make('actif')->label('Actif')->boolean(),
                TextColumn::make('derniere_connexion_at')->label('Dernière connexion')->since()->placeholder('Jamais'),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, User $record): array => [
                        ...$data,
                        'role' => $record->getRoleNames()->intersect(array_keys(CompteBureauService::ROLES))->first(),
                        'cellule_id' => Cellule::query()->where('responsable_id', $record->id)->value('id'),
                    ])
                    ->using(fn (User $record, array $data): User => app(CompteBureauService::class)->mettreAJour($record, $data)),

                EnTantQueAction::pourCompte(),

                Action::make('reinitialiserMotDePasse')
                    ->label('Nouveau mot de passe')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Un nouveau mot de passe provisoire sera généré. L\'ancien ne fonctionnera plus.')
                    ->action(function (User $record): void {
                        $motDePasse = app(CompteBureauService::class)->reinitialiserMotDePasse($record);

                        Notification::make()
                            ->title('Nouveau mot de passe de '.$record->name)
                            ->body('Mot de passe provisoire : '.$motDePasse.' — Transmettez-le à la personne. Il ne sera plus affiché.')
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                Action::make('retirerDuBureau')
                    ->label('Retirer du bureau')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->hidden(fn (User $record): bool => $record->hasRole('super_administrateur'))
                    ->requiresConfirmation()
                    ->modalDescription('La personne perd ses droits dans le panneau, mais garde son compte de membre si elle en a un.')
                    ->action(function (User $record): void {
                        try {
                            app(CompteBureauService::class)->retirerDuBureau($record);
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title($record->name.' a été retiré du bureau.')->success()->send();
                    }),
            ])
            ->defaultSort('name');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageComptesBureau::route('/'),
        ];
    }
}
