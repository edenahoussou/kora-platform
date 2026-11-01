<?php

namespace App\Modules\KoraPoints\Services;

use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\Settings\Settings\OperationsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class KoraPointTermsService
{
    public function __construct(private readonly OperationsSettings $settings) {}

    /** @return array<string, mixed> */
    /**
     * Resolves the point effective terms with inheritance sources (point, organization, operations defaults).
     *
     * @return array<string, mixed>
     */
    public function resolve(KoraPoint $point): array
    {
        $point->loadMissing('organization');
        $defaults = $this->defaults();
        $values = [];
        $sources = [];
        foreach ($defaults as $key => $default) {
            $values[$key] = $point->getAttribute($key) ?? $point->organization?->getAttribute($key) ?? $default;
            $sources[$key] = $point->getAttribute($key) !== null ? 'point' : ($point->organization?->getAttribute($key) !== null ? 'organization' : 'operations');
        }

        return $values + ['sources' => $sources];
    }

    /** @return array<string, mixed> */
    /**
     * Returns the operations-level default terms (commission, ceiling, settlement calendar).
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $this->settings->refresh();

        return ['commission_basis_points' => $this->settings->koraPointCommissionBasisPoints,
            'ceiling_amount' => $this->settings->koraPointCeilingAmount,
            'settlement_calendar' => $this->settings->koraPointSettlementCalendar];
    }

    /** @return array<string, mixed> */
    /**
     * Returns resolved terms or throws when a required term is missing before selling.
     *
     * @return array<string, mixed>
     */
    public function required(KoraPoint $point): array
    {
        $terms = $this->resolve($point);
        foreach (['commission_basis_points', 'ceiling_amount', 'settlement_calendar'] as $key) {
            if ($terms[$key] === null) {
                throw ValidationException::withMessages(['terms' => 'Configurez la commission, le plafond et le calendrier de reversement avant toute vente.']);
            }
        }

        return $terms;
    }

    /** @param array<string, mixed> $calendar */
    /**
     * Computes the next settlement due date in UTC from a settlement calendar and a payment instant.
     *
     * @param  array<string, mixed>  $calendar
     */
    public function dueAt(array $calendar, CarbonImmutable $paidAt): CarbonImmutable
    {
        $local = $paidAt->setTimezone($calendar['timezone']);
        $due = $local->setTimeFromTimeString($calendar['time']);
        if ($calendar['frequency'] === 'weekly') {
            $offset = ((int) $calendar['weekday'] - $due->isoWeekday() + 7) % 7;
            $due = $due->addDays($offset);
            if ($due->lessThanOrEqualTo($local)) {
                $due = $due->addWeek();
            }
        } elseif ($calendar['frequency'] === 'monthly') {
            $due = $due->day((int) $calendar['day']);
            if ($due->lessThanOrEqualTo($local)) {
                $due = $due->addMonthNoOverflow();
            }
        } elseif ($due->lessThanOrEqualTo($local)) {
            $due = $due->addDay();
        }

        return $due->utc();
    }

    /**
     * Returns the validation rules for term payloads, optionally prefixed for nested shapes.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'commission_basis_points' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
            $prefix.'ceiling_amount' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000000'],
            $prefix.'settlement_calendar' => ['sometimes', 'nullable', 'array:frequency,time,timezone,weekday,day'],
            $prefix.'settlement_calendar.frequency' => ['required_with:'.$prefix.'settlement_calendar', 'in:daily,weekly,monthly'],
            $prefix.'settlement_calendar.time' => ['required_with:'.$prefix.'settlement_calendar', 'date_format:H:i'],
            $prefix.'settlement_calendar.timezone' => ['required_with:'.$prefix.'settlement_calendar', 'timezone'],
            $prefix.'settlement_calendar.weekday' => ['required_if:'.$prefix.'settlement_calendar.frequency,weekly', 'nullable', 'integer', 'between:1,7'],
            $prefix.'settlement_calendar.day' => ['required_if:'.$prefix.'settlement_calendar.frequency,monthly', 'nullable', 'integer', 'between:1,28'],
        ];
    }

    /**
     * Returns the configured grace days before a due settlement counts as overdue.
     */
    public function graceDays(): int
    {
        $this->settings->refresh();

        return $this->settings->koraPointGraceDays;
    }
}
