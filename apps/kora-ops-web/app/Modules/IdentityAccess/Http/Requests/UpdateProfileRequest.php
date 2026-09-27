<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Le téléphone est stocké normalisé (+229…): l'unicité doit être
     * contrôlée sur la forme stockée, pas sur la saisie brute.
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone) && trim($phone) !== '') {
            $this->merge([
                'phone' => resolve(PassengerIdentityResolver::class)
                    ->normalizePhone($phone, $this->input('country_code')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[\d\s\-.()]{6,20}$/', Rule::unique('users', 'phone')->ignore($userId)],
            'country_code' => ['nullable', 'string', 'max:4', 'regex:/^\+?[\d\s\-.()]{1,7}$/'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 120 caractères.',
            'email.required' => "L'adresse email est obligatoire.",
            'email.email' => 'Veuillez saisir une adresse email valide.',
            'email.max' => "L'adresse email ne peut pas dépasser 255 caractères.",
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères.',
            'phone.regex' => 'Le numéro de téléphone doit être au format international (ex: +2290191003606) ou local (ex: 0191003606).',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'country_code.max' => "L'indicatif pays ne peut pas dépasser 4 caractères.",
            'country_code.regex' => "L'indicatif pays doit contenir uniquement des chiffres (ex: +229).",
            'birth_date.date' => "La date de naissance n'est pas valide.",
            'birth_date.before' => "La date de naissance doit être antérieure à aujourd'hui.",
        ];
    }

    /**
     * @return array{name: string, email: string, phone?: string|null, country_code?: string|null, birth_date?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'phone' => isset($validated['phone']) ? (string) $validated['phone'] : null,
            'country_code' => isset($validated['country_code']) ? (string) $validated['country_code'] : null,
            'birth_date' => isset($validated['birth_date']) ? (string) $validated['birth_date'] : null,
        ];
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Nom affiché ou nom de l’appareil.'],
            'email' => ['description' => 'Adresse email.'],
            'phone' => ['description' => 'Numéro de téléphone.'],
            'country_code' => ['description' => 'Indicatif téléphonique du pays.'],
            'birth_date' => ['description' => 'Date de naissance.'],
        ];
    }
}
