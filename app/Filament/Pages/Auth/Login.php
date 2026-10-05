<?php

namespace App\Filament\Pages\Auth;

use App\Services\TelephoneNormalizer;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

/**
 * Connexion au panneau par numéro de téléphone (règle 4.5).
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('telephone')
            ->label('Téléphone')
            ->tel()
            ->placeholder('77 123 45 67')
            ->required()
            ->autocomplete('tel')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $telephone = is_string($data['telephone'] ?? null) ? $data['telephone'] : '';

        return [
            'telephone' => TelephoneNormalizer::normaliser($telephone) ?? $telephone,
            'password' => $data['password'] ?? '',
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.telephone' => 'Téléphone ou mot de passe incorrect.',
        ]);
    }
}
