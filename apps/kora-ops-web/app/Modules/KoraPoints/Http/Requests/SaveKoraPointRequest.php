<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKoraPointRequest extends FormRequest
{
    /**
     * Grants access when the actor is active and holds the related permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.manage');
    }

    /**
     * Normalizes wizard aliases (manager name, credit limit, frequency) into canonical fields.
     */
    protected function prepareForValidation(): void
    {
        $calendar = $this->input('settlement_calendar');
        if (is_string($calendar)) {
            $freq = in_array($calendar, ['weekly', 'monthly', 'daily'], true) ? $calendar : 'weekly';
            $calendar = [
                'frequency' => $freq,
                'weekday' => $freq === 'weekly' ? 1 : null,
                'day' => $freq === 'monthly' ? 1 : null,
                'time' => '18:00',
                'timezone' => 'Africa/Porto-Novo',
            ];
        } elseif ($calendar === null && ($this->has('settlementFrequencyDays') || $this->has('settlement_frequency_days'))) {
            $days = (int) ($this->input('settlementFrequencyDays') ?? $this->input('settlement_frequency_days'));
            $freq = $days >= 28 ? 'monthly' : ($days >= 7 ? 'weekly' : 'daily');
            $calendar = [
                'frequency' => $freq,
                'weekday' => $freq === 'weekly' ? 1 : null,
                'day' => $freq === 'monthly' ? 1 : null,
                'time' => '18:00',
                'timezone' => 'Africa/Porto-Novo',
            ];
        }

        $this->merge([
            'country' => $this->input('country') ?: 'BJ',
            'responsible_name' => $this->input('responsible_name') ?? $this->input('managerName') ?? $this->input('manager_name'),
            'email' => is_string($this->input('email')) && trim($this->input('email')) !== ''
                ? strtolower(trim($this->input('email')))
                : null,
            'organization_uuid' => $this->input('organization_uuid') ?? $this->input('organizationUuid'),
            'commission_basis_points' => $this->input('commission_basis_points') ?? $this->input('commissionRateBps'),
            'ceiling_amount' => $this->input('ceiling_amount') ?? $this->input('creditLimit'),
            'settlement_calendar' => $calendar,
        ]);
    }

    /**
     * Returns the payload validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $subject = $this->route('point');
        $ignoredId = $subject instanceof KoraPoint ? $subject->agency_id : null;
        $responsibleNameRequired = ! ($subject instanceof KoraPoint && $this->user()?->hasRole('kora_point_supervisor'));

        return [
            'code' => ['nullable', 'string', 'max:80', Rule::unique('agencies', 'code')->ignore($ignoredId)],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', Rule::in(['BJ', 'TG', 'CI'])],
            'department' => ['nullable', 'string', 'max:120'],
            'responsible_name' => [
                Rule::requiredIf($responsibleNameRequired),
                'nullable',
                'string',
                'max:255',
            ],
            'email' => [
                Rule::requiredIf(fn (): bool => ! ($subject instanceof KoraPoint)),
                'nullable',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:102400'],
            'remove_logo' => ['sometimes', 'boolean'],
            'document_file' => [
                'sometimes',
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:102400',
                Rule::prohibitedIf(fn (): bool => filled($this->input('organization_uuid'))),
            ],
            'document_title' => ['nullable', 'string', 'max:255', 'required_with:document_file'],
            'organization_uuid' => ['nullable', 'uuid', Rule::exists('kora_point_organizations', 'uuid')->where('status', 'active')],
            'commission_basis_points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'ceiling_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'settlement_calendar' => ['nullable', 'array:frequency,time,timezone,weekday,day'],
            'settlement_calendar.frequency' => ['required_with:settlement_calendar', 'in:daily,weekly,monthly'],
            'settlement_calendar.time' => ['required_with:settlement_calendar', 'date_format:H:i'],
            'settlement_calendar.timezone' => ['required_with:settlement_calendar', 'timezone'],
            'settlement_calendar.weekday' => ['required_if:settlement_calendar.frequency,weekly', 'nullable', 'integer', 'between:1,7'],
            'settlement_calendar.day' => ['required_if:settlement_calendar.frequency,monthly', 'nullable', 'integer', 'between:1,28'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.string' => 'Le code du Point doit être du texte.',
            'code.max' => 'Le code du Point ne peut pas dépasser 80 caractères.',
            'code.unique' => 'Ce code est déjà utilisé. Choisissez-en un autre.',
            'name.required' => 'Saisissez le nom du Point de vente.',
            'name.string' => 'Le nom du Point doit être du texte.',
            'name.max' => 'Le nom du Point ne peut pas dépasser 255 caractères.',
            'city.required' => 'Saisissez la ville du Point.',
            'city.string' => 'La ville doit être du texte.',
            'city.max' => 'Le nom de la ville ne peut pas dépasser 255 caractères.',
            'country.required' => 'Sélectionnez le pays du Point.',
            'country.in' => 'Choisissez un pays pris en charge : Bénin, Togo ou Côte d’Ivoire.',
            'department.string' => 'Le département ou la région doit être du texte.',
            'department.max' => 'Le département ou la région ne peut pas dépasser 120 caractères.',
            'responsible_name.required' => 'Saisissez le nom du responsable du Point.',
            'responsible_name.string' => 'Le nom du responsable doit être du texte.',
            'responsible_name.max' => 'Le nom du responsable ne peut pas dépasser 255 caractères.',
            'email.required' => 'L’e-mail est obligatoire pour créer le compte web de l’agent du Point.',
            'email.email' => 'Saisissez une adresse e-mail valide.',
            'email.max' => 'L’adresse e-mail ne peut pas dépasser 255 caractères.',
            'email.unique' => 'Cette adresse e-mail est déjà associée à un compte. Affectez ce compte existant au point depuis Utilisateurs.',
            'phone.string' => 'Saisissez un numéro de téléphone valide.',
            'phone.max' => 'Le numéro de téléphone est trop long.',
            'address.string' => 'L’adresse doit être du texte.',
            'address.max' => 'L’adresse ne peut pas dépasser 2 000 caractères.',
            'latitude.numeric' => 'La latitude doit être un nombre valide.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.numeric' => 'La longitude doit être un nombre valide.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
            'logo.image' => 'Sélectionnez un fichier image valide pour le logo.',
            'logo.mimes' => 'Le logo doit être au format PNG, JPEG ou WebP.',
            'logo.max' => 'Le logo ne peut pas dépasser 2 Mo.',
            'remove_logo.boolean' => 'La demande de suppression du logo est invalide.',
            'document_file.prohibited' => 'Les documents d’un Point rattaché se gèrent sur le profil de l’organisation.',
            'document_file.file' => 'Sélectionnez un document valide.',
            'document_file.mimes' => 'Le document doit être au format PDF, JPG ou PNG.',
            'document_file.max' => 'Le document ne peut pas dépasser 10 Mo.',
            'document_title.required_with' => 'Saisissez un titre pour le document.',
            'document_title.string' => 'Le titre du document doit être du texte.',
            'document_title.max' => 'Le titre du document ne peut pas dépasser 255 caractères.',
            'organization_uuid.uuid' => 'Sélectionnez une organisation valide.',
            'organization_uuid.exists' => 'L’organisation sélectionnée est introuvable ou inactive.',
            'commission_basis_points.integer' => 'Saisissez la commission en pourcentage.',
            'commission_basis_points.min' => 'La commission ne peut pas être négative.',
            'commission_basis_points.max' => 'La commission ne peut pas dépasser 100 %.',
            'ceiling_amount.integer' => 'Saisissez un plafond en XOF sans décimales.',
            'ceiling_amount.min' => 'Le plafond ne peut pas être négatif; une allocation de Point rattaché doit être supérieure à zéro.',
            'ceiling_amount.max' => 'Le plafond indiqué est trop élevé.',
            'settlement_calendar.array' => 'Choisissez une fréquence de reversement valide.',
            'settlement_calendar.frequency.required_with' => 'Choisissez la fréquence de reversement.',
            'settlement_calendar.frequency.in' => 'Choisissez une fréquence de reversement valide.',
            'settlement_calendar.time.required_with' => 'L’heure du reversement est manquante.',
            'settlement_calendar.time.date_format' => 'L’heure du reversement doit être au format HH:MM.',
            'settlement_calendar.timezone.required_with' => 'Le fuseau horaire du reversement est manquant.',
            'settlement_calendar.timezone.timezone' => 'Choisissez un fuseau horaire valide.',
            'settlement_calendar.weekday.required_if' => 'Choisissez le jour du reversement hebdomadaire.',
            'settlement_calendar.weekday.between' => 'Choisissez un jour de la semaine valide.',
            'settlement_calendar.day.required_if' => 'Choisissez le jour du reversement mensuel.',
            'settlement_calendar.day.between' => 'Choisissez un jour du mois entre 1 et 28.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nom du Point',
            'city' => 'ville',
            'country' => 'pays',
            'department' => 'département ou région',
            'responsible_name' => 'nom du responsable',
            'email' => 'adresse e-mail',
            'phone' => 'téléphone',
            'address' => 'adresse',
            'ceiling_amount' => 'plafond ou allocation',
            'settlement_calendar.frequency' => 'fréquence de reversement',
        ];
    }
}
