<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Le téléphone est normalisé (+229…) avant contrôle d'unicité et
     * comparaison avec la valeur stockée.
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
     * L'email officiel est verrouillé (gouvernance DSI) : il n'est
     * volontairement pas validé ici et ne peut pas être modifié.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[\d\s\-.()]{6,20}$/', Rule::unique('users', 'phone')->ignore($userId)],
            'country_code' => ['nullable', 'string', 'max:4', 'regex:/^\+?[\d\s\-.()]{1,7}$/'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],
            'timezone' => ['nullable', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];

        if ($this->phoneChanged()) {
            $rules['password'] = $this->currentPasswordRules();
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'La confirmation par mot de passe est obligatoire pour modifier votre numéro de téléphone.',
            'password.current_password' => 'Le mot de passe saisi est incorrect.',
        ];
    }

    /**
     * @return array{name: string, phone?: string|null, country_code?: string|null, job_title?: string|null, department?: string|null, location?: string|null, timezone?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $payload = ['name' => (string) $validated['name']];

        foreach (['phone', 'country_code', 'job_title', 'department', 'location', 'timezone'] as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        return $payload;
    }

    /**
     * Vrai si un numéro est soumis (même vidé) et diffère du numéro
     * stocké — seul cas exigeant la confirmation par mot de passe.
     */
    private function phoneChanged(): bool
    {
        $user = $this->user();

        if ($user === null || ! $this->has('phone')) {
            return false;
        }

        $submitted = $this->input('phone');

        if (! is_string($submitted) || trim($submitted) === '') {
            return $user->phone !== null;
        }

        return $submitted !== $user->phone;
    }
}
