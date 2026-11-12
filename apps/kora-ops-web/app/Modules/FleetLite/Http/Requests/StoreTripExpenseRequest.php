<?php

namespace App\Modules\FleetLite\Http\Requests;

use App\Modules\Expenses\Data\ExpensePaymentChannel;
use App\Modules\FleetLite\Data\TripExpenseKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Dépense de voyage saisie depuis OPS Mobile (multipart si photo) :
 * - carburant : litres, prix au litre, station et photo du ticket
 *   obligatoires ; montant facultatif (calculé litres × prix sinon) ;
 * - péage / taxe frontière : montant et poste obligatoires, pays et photo
 *   facultatifs.
 */
class StoreTripExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $fuel = TripExpenseKind::Fuel->value;

        return [
            'client_uuid' => ['required', 'uuid'],
            'kind' => ['required', Rule::enum(TripExpenseKind::class)],
            'amount' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('kind') !== $fuel), 'integer', 'min:1', 'max:50000000'],
            'liters' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('kind') === $fuel), 'numeric', 'min:0.1', 'max:5000'],
            'unit_price' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('kind') === $fuel), 'integer', 'min:1', 'max:100000'],
            'station_label' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('kind') === $fuel), 'string', 'min:2', 'max:160'],
            'checkpoint_label' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('kind') !== $fuel), 'string', 'min:2', 'max:160'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'payment_channel' => ['nullable', Rule::enum(ExpensePaymentChannel::class)],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:+5 minutes', 'after:-7 days'],
            'note' => ['nullable', 'string', 'max:500'],
            'photo' => [
                Rule::requiredIf(fn (): bool => $this->input('kind') === $fuel),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'liters.required' => 'Saisissez la quantité de carburant (litres).',
            'unit_price.required' => 'Saisissez le prix au litre.',
            'station_label.required' => 'Saisissez la station.',
            'photo.required' => 'La photo du ticket de carburant est obligatoire.',
            'amount.required' => 'Saisissez le montant payé.',
            'checkpoint_label.required' => 'Saisissez le poste (péage ou frontière).',
        ];
    }

    /**
     * @return array{
     *     client_uuid: string,
     *     kind: TripExpenseKind,
     *     amount: int|null,
     *     liters: float|null,
     *     unit_price: int|null,
     *     station_label: string|null,
     *     checkpoint_label: string|null,
     *     country_code: string|null,
     *     odometer_km: int|null,
     *     payment_channel: ExpensePaymentChannel,
     *     occurred_at: Carbon|null,
     *     note: string|null,
     *     photo: UploadedFile|null,
     * }
     */
    public function tripExpenseData(): array
    {
        $photo = $this->file('photo');

        return [
            'client_uuid' => $this->string('client_uuid')->toString(),
            'kind' => TripExpenseKind::from($this->string('kind')->toString()),
            'amount' => $this->filled('amount') ? (int) $this->integer('amount') : null,
            'liters' => $this->filled('liters') ? (float) $this->input('liters') : null,
            'unit_price' => $this->filled('unit_price') ? (int) $this->integer('unit_price') : null,
            'station_label' => $this->filled('station_label') ? trim($this->string('station_label')->toString()) : null,
            'checkpoint_label' => $this->filled('checkpoint_label') ? trim($this->string('checkpoint_label')->toString()) : null,
            'country_code' => $this->filled('country_code') ? $this->string('country_code')->upper()->toString() : null,
            'odometer_km' => $this->filled('odometer_km') ? (int) $this->integer('odometer_km') : null,
            'payment_channel' => $this->filled('payment_channel')
                ? ExpensePaymentChannel::from($this->string('payment_channel')->toString())
                : ExpensePaymentChannel::Cash,
            'occurred_at' => $this->filled('occurred_at') ? Carbon::parse($this->string('occurred_at')->toString()) : null,
            'note' => $this->filled('note') ? $this->string('note')->toString() : null,
            'photo' => $photo instanceof UploadedFile ? $photo : null,
        ];
    }

    /** @return array<string, array{description: string, example?: mixed}> */
    public function bodyParameters(): array
    {
        return [
            'client_uuid' => ['description' => 'UUID de la saisie côté mobile (idempotence, file hors ligne).'],
            'kind' => ['description' => 'fuel, toll ou border_tax.', 'example' => 'fuel'],
            'amount' => ['description' => 'Montant payé en XOF (obligatoire hors carburant).', 'example' => 45000],
            'liters' => ['description' => 'Litres (carburant).', 'example' => 65.5],
            'unit_price' => ['description' => 'Prix au litre en XOF (carburant).', 'example' => 680],
            'station_label' => ['description' => 'Station (carburant).', 'example' => 'Total Godomey'],
            'checkpoint_label' => ['description' => 'Poste de péage ou frontière.', 'example' => 'Péage Ouèdo'],
            'country_code' => ['description' => 'Pays du poste (ISO 3166-1 alpha-2).', 'example' => 'TG'],
            'odometer_km' => ['description' => 'Kilométrage au moment de la dépense.', 'example' => 182510],
            'payment_channel' => ['description' => 'cash (défaut), mtn_momo, moov_flooz, celtiis_cash.'],
            'occurred_at' => ['description' => 'Heure locale de la dépense (ISO 8601).'],
            'note' => ['description' => 'Commentaire libre.'],
            'photo' => ['description' => 'Photo du ticket (obligatoire pour le carburant).'],
        ];
    }
}
