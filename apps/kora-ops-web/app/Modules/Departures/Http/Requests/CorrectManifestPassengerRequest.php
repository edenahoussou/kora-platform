<?php

namespace App\Modules\Departures\Http\Requests;

use App\Modules\Booking\Data\PassengerDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correction par le convoyeur des informations d'un passager (PATCH
 * partiel : seuls les champs envoyés sont modifiés ; `null` efface un
 * champ facultatif). L'autorisation (affectation au départ + permission
 * d'embarquement) est portée par le contrôleur via la gate `scanBoarding`.
 */
class CorrectManifestPassengerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'document_type' => ['sometimes', 'nullable', Rule::enum(PassengerDocumentType::class)],
            'document_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'nationality' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'yellow_fever_vaccinated' => ['sometimes', 'nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Champs de correction réellement envoyés (la clé présente, même à
     * `null`, signifie « modifier »).
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return $this->safe()->except('reason');
    }

    public function reason(): ?string
    {
        $reason = trim((string) $this->input('reason', ''));

        return $reason === '' ? null : $reason;
    }

    /** @return array<string, array{description: string, example?: string}> */
    public function bodyParameters(): array
    {
        return [
            'first_name' => ['description' => 'Prénom corrigé.'],
            'last_name' => ['description' => 'Nom de famille corrigé.'],
            'phone' => ['description' => 'Téléphone (normalisé en E.164 par le serveur).'],
            'email' => ['description' => 'Adresse email.'],
            'document_type' => ['description' => 'Type de pièce : national_id, biometric_card, passport, consular_card, cip.'],
            'document_number' => ['description' => 'Numéro de la pièce (numéro de passeport si le type est passport).'],
            'nationality' => ['description' => 'Nationalité, code ISO 3166-1 alpha-2.'],
            'birth_date' => ['description' => 'Date de naissance (Y-m-d).', 'example' => '1990-05-15'],
            'yellow_fever_vaccinated' => ['description' => 'Vacciné contre la fièvre jaune ? null = non renseigné.'],
            'reason' => ['description' => 'Motif de la correction (facultatif, pour l’audit).'],
        ];
    }
}
