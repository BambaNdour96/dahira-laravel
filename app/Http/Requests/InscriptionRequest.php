<?php

namespace App\Http\Requests;

use App\Enums\Sexe;
use App\Services\TelephoneNormalizer;
use App\Support\MessagesValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** On normalise le téléphone avant de le contrôler (règle 4.5). */
    protected function prepareForValidation(): void
    {
        $brut = $this->input('telephone');

        if (is_string($brut)) {
            $this->merge(['telephone' => TelephoneNormalizer::normaliser($brut) ?? $brut]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'sexe' => ['required', Rule::enum(Sexe::class)],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'telephone' => ['required', 'string', 'regex:/^\+\d{8,15}$/', 'unique:membres,telephone', 'unique:users,telephone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'consentement' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return MessagesValidation::tous();
    }
}
