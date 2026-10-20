<?php

namespace App\Filament\Resources\Exercices;

use App\Filament\Resources\Exercices\Pages\ManageExercices;
use App\Models\Exercice;
use App\Models\User;
use App\Services\ExerciceService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ExerciceResource extends Resource
{
    protected static ?string $model = Exercice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'exercice';

    protected static ?string $pluralModelLabel = 'exercices';

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('libelle')
                ->label('Libellé')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true)
                ->placeholder('2026'),
            DatePicker::make('date_debut')
                ->label('Début')
                ->required(),
            DatePicker::make('date_fin')
                ->label('Fin')
                ->required()
                ->after('date_debut'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('libelle')->label('Libellé')->sortable(),
                TextColumn::make('date_debut')->label('Début')->date('d/m/Y')->sortable(),
                TextColumn::make('date_fin')->label('Fin')->date('d/m/Y'),
                IconColumn::make('en_cours')
                    ->label('En cours')
                    ->boolean()
                    ->state(fn (Exercice $record): bool => Exercice::enCours()?->is($record) ?? false),
                TextColumn::make('cloture')
                    ->label('Clôture')
                    ->badge()
                    ->state(fn (Exercice $record): string => match (true) {
                        $record->estClos() => 'Clos le '.$record->cloture_le?->format('d/m/Y'),
                        $record->cloture_preparee_le !== null => 'Préparée, à confirmer',
                        default => 'Ouvert',
                    })
                    ->color(fn (Exercice $record): string => $record->estClos() ? 'success' : ($record->cloture_preparee_le !== null ? 'warning' : 'gray')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('bilan')->label('Bilan')->icon('heroicon-o-document-chart-bar')->color('gray')
                    ->visible(fn (Exercice $record): bool => $record->date_debut->isPast() && self::voitLesFinances())
                    ->url(fn (Exercice $record): string => route('finances.bilan', $record), true),
                Action::make('preparer')->label('Préparer la clôture')->icon('heroicon-o-clipboard-document-check')->color('warning')
                    ->visible(fn (Exercice $record): bool => ($utilisateur = self::utilisateur()) !== null && app(ExerciceService::class)->peutPreparer($utilisateur, $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Exercice $record): string => self::descriptionAlertes(app(ExerciceService::class)->alertes($record), 'Le bilan est calculé et le Jewrine national est prévenu pour confirmer.'))
                    ->action(fn (Exercice $record) => self::executer(function (ExerciceService $service, User $auteur) use ($record): void {
                        $service->preparer($record, $auteur);
                        Notification::make()->title('Clôture préparée : le Jewrine national est prévenu.')->success()->send();
                    })),
                Action::make('confirmer')->label('Confirmer la clôture')->icon('heroicon-o-lock-closed')->color('success')
                    ->visible(fn (Exercice $record): bool => ($utilisateur = self::utilisateur()) !== null && app(ExerciceService::class)->peutConfirmer($utilisateur, $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Exercice $record): string => self::descriptionAlertes(app(ExerciceService::class)->alertes($record), 'Une fois clos, l\'exercice ne se modifie plus et son bilan est figé.'))
                    ->action(fn (Exercice $record) => self::executer(function (ExerciceService $service, User $auteur) use ($record): void {
                        $service->confirmer($record, $auteur);
                        Notification::make()->title('Exercice clos : le bilan est figé.')->success()->send();
                    })),
                Action::make('renvoyer')->label('Renvoyer')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                    ->visible(fn (Exercice $record): bool => ($utilisateur = self::utilisateur()) !== null && app(ExerciceService::class)->peutConfirmer($utilisateur, $record))
                    ->schema([Textarea::make('motif')->label('Ce qui doit être repris')->required()->maxLength(255)])
                    ->action(fn (Exercice $record, array $data) => self::executer(function (ExerciceService $service, User $auteur) use ($record, $data): void {
                        $service->renvoyer($record, (string) $data['motif'], $auteur);
                        Notification::make()->title('Clôture renvoyée au Jewrine finance.')->success()->send();
                    })),
            ])
            ->defaultSort('date_debut', 'desc');
    }

    public static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    private static function voitLesFinances(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && ($utilisateur->can('finances.voir') || $utilisateur->can('exercices.preparer_cloture') || $utilisateur->can('exercices.cloturer'));
    }

    /** @param  list<string>  $alertes */
    private static function descriptionAlertes(array $alertes, string $suite): string
    {
        return ($alertes === [] ? 'Aucun point ouvert. ' : 'Points encore ouverts : '.implode(' ', $alertes).' ').$suite;
    }

    /** @param  callable(ExerciceService, User): void  $operation */
    private static function executer(callable $operation): void
    {
        $auteur = self::utilisateur();
        abort_unless($auteur !== null, 403);

        try {
            $operation(app(ExerciceService::class), $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageExercices::route('/'),
        ];
    }
}
