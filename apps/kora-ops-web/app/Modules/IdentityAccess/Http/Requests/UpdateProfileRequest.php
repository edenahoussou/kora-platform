<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'phone' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'max:4'],
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
            'country_code.max' => "L'indicatif pays ne peut pas dépasser 4 caractères.",
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
}
