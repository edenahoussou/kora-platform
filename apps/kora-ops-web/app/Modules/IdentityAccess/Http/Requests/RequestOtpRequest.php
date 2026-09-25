<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Services\AuthIdentifierResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestOtpRequest extends FormRequest
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
        return [
            'identifier' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:identifier'],
            'purpose' => ['required', Rule::in(OtpPurpose::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $identifier = $this->input('identifier', $this->input('email'));

            if ($this->filled('identifier') && $this->filled('email')) {
                $validator->errors()->add('identifier', 'Utilisez identifier ou email, mais pas les deux.');
            }

            if (is_string($identifier) && ! resolve(AuthIdentifierResolver::class)->isValidIdentifier($identifier)) {
                $validator->errors()->add('identifier', "L'email ou le numéro de téléphone n'est pas valide.");
            }

            if ((string) $this->input('purpose') === OtpPurpose::Registration->value && is_string($identifier)) {
                if (filter_var(trim($identifier), FILTER_VALIDATE_EMAIL) === false) {
                    $validator->errors()->add('identifier', "L'inscription nécessite une adresse email.");
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifier.required_without' => "L'email ou le numéro de téléphone est obligatoire.",
            'identifier.max' => "L'identifiant ne peut pas dépasser 255 caractères.",
            'email.required_without' => "L'email ou le numéro de téléphone est obligatoire.",
            'email.email' => 'Veuillez saisir une adresse email valide.',
            'email.max' => "L'adresse email ne peut pas dépasser 255 caractères.",
            'purpose.required' => 'La raison de la demande est obligatoire.',
            'purpose.in' => "La raison de la demande n'est pas valide.",
        ];
    }

    /**
     * @return array{identifier: string, purpose: string}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'identifier' => (string) ($validated['identifier'] ?? $validated['email']),
            'purpose' => (string) $validated['purpose'],
        ];
    }
}
