<?php

namespace App\Modules\Finance\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class RevenuePeriod
{
    public const TIMEZONE = 'Africa/Porto-Novo';

    /** @var list<string> */
    public const PRESETS = ['today', 'this_week', 'this_month', 'last_month', 'last_year', 'custom'];

    public function __construct(
        public string $preset,
        public CarbonImmutable $localStart,
        public CarbonImmutable $localEnd,
        public CarbonImmutable $comparisonLocalStart,
        public CarbonImmutable $comparisonLocalEnd,
    ) {}

    /** @param array<string, string> $filters */
    public static function fromFilters(array $filters, ?CarbonImmutable $now = null): self
    {
        $preset = (string) ($filters['period'] ?? 'this_month');

        if (! in_array($preset, self::PRESETS, true)) {
            throw new InvalidArgumentException('Période de recettes inconnue.');
        }

        $today = ($now ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE)->startOfDay();

        [$start, $end] = match ($preset) {
            'today' => [$today, $today],
            'this_week' => [$today->startOfWeek(), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->startOfMonth()->subDay()],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()->startOfDay()],
            'custom' => self::customDates($filters),
        };

        $durationInDays = $start->diffInDays($end) + 1;
        $comparisonEnd = $start->subDay();
        $comparisonStart = $comparisonEnd->subDays($durationInDays - 1);

        return new self($preset, $start, $end, $comparisonStart, $comparisonEnd);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private static function customDates(array $filters): array
    {
        $from = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($filters['from'] ?? ''), self::TIMEZONE);
        $to = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($filters['to'] ?? ''), self::TIMEZONE);

        if ($from === null || $to === null || $to->isBefore($from)) {
            throw new InvalidArgumentException('La période personnalisée est invalide.');
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    public function startUtc(): CarbonImmutable
    {
        return $this->localStart->utc();
    }

    public function endExclusiveUtc(): CarbonImmutable
    {
        return $this->localEnd->addDay()->utc();
    }

    public function comparisonStartUtc(): CarbonImmutable
    {
        return $this->comparisonLocalStart->utc();
    }

    public function comparisonEndExclusiveUtc(): CarbonImmutable
    {
        return $this->comparisonLocalEnd->addDay()->utc();
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->greaterThanOrEqualTo($this->startUtc()) && $date->lessThan($this->endExclusiveUtc());
    }

    public function containsComparison(CarbonImmutable $date): bool
    {
        return $date->greaterThanOrEqualTo($this->comparisonStartUtc()) && $date->lessThan($this->comparisonEndExclusiveUtc());
    }

    public function bucket(): string
    {
        $days = $this->localStart->diffInDays($this->localEnd) + 1;

        return match (true) {
            $days <= 45 => 'day',
            $days <= 180 => 'week',
            default => 'month',
        };
    }

    public function label(): string
    {
        if ($this->localStart->isSameDay($this->localEnd)) {
            return $this->localStart->format('d/m/Y');
        }

        return $this->localStart->format('d/m/Y')
            .' — '
            .$this->localEnd->format('d/m/Y');
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'from' => $this->localStart->toDateString(),
            'to' => $this->localEnd->toDateString(),
            'label' => $this->label(),
            'timezone' => self::TIMEZONE,
            'bucket' => $this->bucket(),
            'comparisonFrom' => $this->comparisonLocalStart->toDateString(),
            'comparisonTo' => $this->comparisonLocalEnd->toDateString(),
        ];
    }
}
