<?php

namespace App\Http\Requests;

use App\Enums\Sexe;
use App\Models\Commission;
use App\Rules\EmailFiable;
use App\Services\TelephoneNormalizer;
use App\Support\MessagesValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class InscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** On normalise le téléphone et on ignore le choix « Aucune commission ». */
    protected function prepareForValidation(): void
    {
        $brut = $this->input('telephone');

        if (is_string($brut)) {
            $this->merge(['telephone' => TelephoneNormalizer::normaliser($brut) ?? $brut]);
        }

        $this->merge([
            'commissions' => array_values(array_filter((array) $this->input('commissions', []), 'filled')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Page 1 : identité
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'sexe' => ['required', Rule::enum(Sexe::class)],
            'date_naissance' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'after:1900-01-01', 'before:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:100'],
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],

            // Page 2 : contact, résidence et Daara
            'telephone' => ['required', 'string', 'regex:/^\+\d{8,15}$/', 'unique:membres,telephone', 'unique:users,telephone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email', new EmailFiable],
            'adresse' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:100'],
            'pays_residence' => ['required', 'string', 'max:100'],
            'cellule_id' => ['required', 'integer', 'exists:cellules,id'],
            'commissions' => ['required', 'array', 'min:1', 'max:'.Commission::MAX_PAR_MEMBRE],
            'commissions.*' => ['integer', 'distinct', Rule::exists('commissions', 'id')->where('actif', true)],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'consentement' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return MessagesValidation::tous();
    }
}
