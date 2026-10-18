<?php

namespace App\Filament\Resources\Reunions\Pages;

use App\Filament\Resources\Reunions\ReunionResource;
use App\Models\PointOrdreDuJour;
use App\Models\Reunion;
use App\Models\User;
use App\Services\ComiteService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/** Une réunion : convoquer, noter les présences, rédiger, puis publier et partager le PV. */
class ViewReunion extends ViewRecord
{
    protected static string $resource = ReunionResource::class;

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public function getSubheading(): ?string
    {
        $reunion = $this->getRecord();

        if (! $reunion instanceof Reunion) {
            return null;
        }

        $quorum = app(ComiteService::class)->quorum($reunion);

        return ReunionResource::ETATS[$reunion->statut].' · '.$quorum['presents'].' présent(s) sur '.$quorum['membres'].' membre(s)';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('convoquer')
                ->label('Convoquer')
                ->icon('heroicon-o-megaphone')
                ->visible(fn (Reunion $record): bool => ! $record->estPubliee() && ! $record->estAnnule() && ($utilisateur = self::utilisateur()) !== null && ComiteService::peutOrganiser($utilisateur, $record->instance))
                ->modalHeading('Convoquer les membres')
                ->modalDescription('Chaque membre du comité reçoit une notification. Copiez aussi ce message dans le groupe WhatsApp du comité.')
                ->fillForm(fn (Reunion $record): array => ['message' => app(ComiteService::class)->messageConvocation($record)])
                ->schema([Textarea::make('message')->label('Message pour le groupe')->rows(10)->readOnly()])
                ->modalSubmitActionLabel('Envoyer les notifications')
                ->action(function (Reunion $record): void {
                    app(ComiteService::class)->convoquer($record);
                    Notification::make()->title('Convocation envoyée aux membres du comité.')->success()->send();
                }),
            Action::make('modifierConvocation')
                ->label('Modifier la convocation')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (Reunion $record): bool => ($utilisateur = self::utilisateur()) !== null && ComiteService::peutModifierConvocation($utilisateur, $record))
                ->modalHeading('Modifier la convocation')
                ->modalDescription('Jusqu\'au début de la réunion. Un changement de date, de lieu ou d\'ordre du jour envoie une « convocation modifiée » à chaque membre du comité. Les présences, le compte rendu et le PV restent au secrétaire de séance.')
                ->fillForm(fn (Reunion $record): array => [
                    'debut' => $record->debut,
                    'lieu' => $record->lieu,
                    'lien' => $record->lien,
                    'secretaire_id' => $record->secretaire_id,
                    'points' => $record->points->map(fn (PointOrdreDuJour $point): array => ['id' => $point->id, 'titre' => $point->titre])->all(),
                ])
                ->schema([
                    DateTimePicker::make('debut')->label('Date et heure')->required()->native(false)->seconds(false)->after('now'),
                    TextInput::make('lieu')->label('Lieu')->required()->maxLength(150),
                    TextInput::make('lien')->label('Lien de visioconférence (facultatif)')->url()->maxLength(255),
                    Select::make('secretaire_id')
                        ->label('Secrétaire de séance')
                        ->helperText('S\'il change, l\'ancien et le nouveau secrétaire sont prévenus.')
                        ->options(fn (Reunion $record): array => ComiteService::secretairesPossibles($record->instance))
                        ->searchable()
                        ->required(),
                    Repeater::make('points')
                        ->label('Ordre du jour')
                        ->addActionLabel('Ajouter un point')
                        ->minItems(1)
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('titre')->label('Point')->required()->maxLength(200),
                        ]),
                ])
                ->modalSubmitActionLabel('Envoyer la convocation modifiée')
                ->action(function (Reunion $record, array $data): void {
                    $auteur = self::utilisateur();
                    abort_unless($auteur !== null, 403);

                    try {
                        $message = app(ComiteService::class)->modifierConvocation($record, [
                            'debut' => (string) $data['debut'],
                            'lieu' => (string) $data['lieu'],
                            'lien' => isset($data['lien']) ? (string) $data['lien'] : null,
                            'secretaire_id' => (int) $data['secretaire_id'],
                            'points' => array_values(array_map(
                                fn (array $point): array => ['id' => isset($point['id']) && $point['id'] !== '' ? (int) $point['id'] : null, 'titre' => (string) $point['titre']],
                                (array) $data['points'],
                            )),
                        ], $auteur);
                    } catch (DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    if ($message === null) {
                        Notification::make()->title('Secrétaire de séance changé : l\'ancien et le nouveau sont prévenus.')->success()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Convocation modifiée envoyée aux membres du comité.')
                        ->body('Prévenez aussi le groupe WhatsApp du comité.')
                        ->actions([Action::make('whatsapp')->label('Partager sur WhatsApp')->url('https://wa.me/?text='.rawurlencode($message), true)])
                        ->persistent()
                        ->success()
                        ->send();
                }),
            Action::make('presences')
                ->label('Présences')
                ->icon('heroicon-o-users')
                ->color('gray')
                ->visible(fn (Reunion $record): bool => ($utilisateur = self::utilisateur()) !== null && ComiteService::peutRediger($utilisateur, $record))
                ->fillForm(fn (Reunion $record): array => [
                    'presents' => $record->presences()->where('statut', 'present')->pluck('membre_id')->all(),
                    'excuses' => $record->presences()->where('statut', 'excuse')->pluck('membre_id')->all(),
                ])
                ->schema([
                    CheckboxList::make('presents')->label('Présents')->options(fn (Reunion $record): array => ComiteService::choixMembres($record->instance))->columns(2)->searchable(),
                    CheckboxList::make('excuses')->label('Excusés')->options(fn (Reunion $record): array => ComiteService::choixMembres($record->instance))->columns(2)->searchable(),
                ])
                ->action(function (Reunion $record, array $data): void {
                    app(ComiteService::class)->enregistrerPresences(
                        $record,
                        array_values(array_map('intval', (array) ($data['presents'] ?? []))),
                        array_values(array_map('intval', (array) ($data['excuses'] ?? []))),
                    );
                    Notification::make()->title('Présences enregistrées.')->success()->send();
                }),
            EditAction::make()->label('Rédiger'),
            ReunionResource::annuler(),
            Action::make('pv')
                ->label('PV (PDF)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (Reunion $record): string => route('reunions.pv', $record)),
            Action::make('publier')
                ->label('Publier le PV')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (Reunion $record): bool => ($utilisateur = self::utilisateur()) !== null && ComiteService::peutRediger($utilisateur, $record))
                ->requiresConfirmation()
                ->modalDescription('Le PV sera figé, et les membres du comité seront prévenus. Vous pourrez ensuite le partager sur WhatsApp.')
                ->action(function (Reunion $record): void {
                    $auteur = self::utilisateur();
                    abort_unless($auteur !== null, 403);

                    try {
                        app(ComiteService::class)->publier($record, $auteur);
                    } catch (DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('PV publié. Partagez-le maintenant sur le groupe WhatsApp.')->success()->send();
                }),
            Action::make('partager')
                ->label('Partager sur WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->visible(fn (Reunion $record): bool => $record->estPubliee())
                ->url(fn (Reunion $record): string => app(ComiteService::class)->lienWhatsapp($record))
                ->openUrlInNewTab(),
        ];
    }
}
