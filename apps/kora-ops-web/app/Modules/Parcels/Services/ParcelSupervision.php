<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCharge;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelIncident;
use App\Modules\Payments\Data\PaymentRefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use Illuminate\Database\Eloquent\Builder;

/**
 * Agrégats de supervision Colis. Toutes les requêtes passent par le périmètre
 * d'accès OPS afin qu'un agent d'agence ne voie jamais les colis d'une autre.
 */
class ParcelSupervision
{
    public const STATUSES = [
        'preregistered', 'weighed', 'paid', 'registered', 'ready', 'assigned', 'loaded',
        'in_transit', 'arrived', 'available', 'withdrawn', 'refused', 'cancelled',
        'return_pending', 'return_transit', 'return_arrived', 'returned',
    ];

    public function __construct(
        private readonly ParcelAccess $access,
        private readonly ParcelRouting $routing,
    ) {}

    /** @return array<string, mixed> */
    public function overview(User $actor): array
    {
        $parcels = $this->access->operations($actor);
        $statusCounts = (clone $parcels)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $byStatus = collect(self::STATUSES)
            ->map(fn (string $status): array => [
                'status' => $status,
                'count' => (int) ($statusCounts[$status] ?? 0),
            ])
            ->values()
            ->all();

        $corridorCounts = (clone $parcels)
            ->whereNotNull('corridor_id')
            ->selectRaw('corridor_id, COUNT(*) as total')
            ->groupBy('corridor_id')
            ->pluck('total', 'corridor_id');
        $corridors = ParcelCorridor::query()
            ->with(['originHub.stop', 'destinationHub.stop'])
            ->when(! $actor->can('parcels.network'), fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->whereRelation('originHub', 'agency_id', $actor->agency_id)
                    ->orWhereRelation('destinationHub', 'agency_id', $actor->agency_id)
            ))
            ->get()
            ->map(fn (ParcelCorridor $corridor): array => [
                'uuid' => $corridor->uuid,
                'label' => $this->corridorLabel($corridor),
                'enabled' => $corridor->enabled,
                'count' => (int) ($corridorCounts[$corridor->id] ?? 0),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $openIncidents = ParcelIncident::query()
            ->whereNull('resolved_at')
            ->whereIn('parcel_id', (clone $parcels)->select('id'));
        $openIncidentCount = (clone $openIncidents)->count();
        $recentIncidents = (clone $openIncidents)
            ->with('parcel')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (ParcelIncident $incident): array => [
                'uuid' => $incident->uuid,
                'parcel_uuid' => $incident->parcel?->uuid,
                'parcel_reference' => $incident->parcel?->reference,
                'kind' => $incident->kind,
                'blocking' => $incident->blocking,
                'description' => $incident->description,
                'opened_at' => $incident->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $capacity = $this->capacity($actor);
        $health = $this->configurationHealth($actor, $capacity);
        $finance = $actor->can('parcels.finance') ? $this->financeQueue($parcels) : null;

        $count = fn (string $status): int => (int) ($statusCounts[$status] ?? 0);
        $returns = $count('return_pending') + $count('return_transit') + $count('return_arrived') + $count('returned');

        return [
            'generated_at' => now()->toIso8601String(),
            'meta' => [
                'total' => (int) $statusCounts->sum(),
                'awaiting_reception' => $count('arrived'),
                'available' => $count('available'),
                'returns_to_handle' => $returns,
                'open_incidents' => $openIncidentCount,
                'withdrawn_today' => $this->withdrawnToday($parcels),
            ],
            'by_status' => $byStatus,
            'by_corridor' => $corridors,
            'incidents' => [
                'open' => $openIncidentCount,
                'blocking' => (clone $openIncidents)->where('blocking', true)->count(),
                'recent' => $recentIncidents,
            ],
            'capacity' => $capacity,
            'config_health' => $health,
            'finance' => $finance,
            'permissions' => [
                'can_finance' => $actor->can('parcels.finance'),
                'can_configure' => $actor->can('parcels.configure'),
            ],
        ];
    }

    /**
     * Lignes CSV agrégées, sans identité, code de retrait ni payload fournisseur.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string|int, 4: string|int|null}>
     */
    public function exportRows(User $actor): array
    {
        $overview = $this->overview($actor);
        $rows = [];

        foreach ($overview['by_status'] as $row) {
            $rows[] = ['Colis par statut', 'Statut', $row['status'], (int) $row['count'], null];
        }
        foreach ($overview['by_corridor'] as $row) {
            $rows[] = ['Colis par corridor', 'Corridor', $row['label'], (int) $row['count'], null];
        }
        foreach ($overview['config_health']['alerts'] as $alert) {
            $rows[] = ['Configuration', $alert['severity'], $alert['message'], 1, null];
        }
        if (is_array($overview['finance'])) {
            foreach (['reconciliations', 'pending_refunds', 'pending_invoices'] as $queue) {
                $rows[] = ['Finance', 'File', $queue, (int) $overview['finance'][$queue]['count'], (int) $overview['finance'][$queue]['total_amount']];
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function capacity(User $actor): array
    {
        $corridors = ParcelCorridor::query()
            ->where('enabled', true)
            ->with(['originHub.stop', 'destinationHub.stop'])
            ->when(! $actor->can('parcels.network'), fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->whereRelation('originHub', 'agency_id', $actor->agency_id)
                    ->orWhereRelation('destinationHub', 'agency_id', $actor->agency_id)
            ))
            ->get();
        $availableCorridors = $this->routing->filterOperationallyAvailable($corridors);
        $rows = [];

        foreach ($availableCorridors as $corridor) {
            if ($corridor->originHub === null || $corridor->destinationHub === null) {
                continue;
            }
            foreach ($this->routing->availability($corridor->originHub, $corridor->destinationHub) as $availability) {
                $departure = $availability['departure'];
                $uuid = $departure->uuid;
                if (! isset($rows[$uuid])) {
                    $rows[$uuid] = [
                        'uuid' => $uuid,
                        'line_code' => $departure->line?->code,
                        'label' => $this->corridorLabel($corridor),
                        'departure_at' => $departure->scheduledDepartureAt()->toIso8601String(),
                        'capacity_grams' => $availability['capacity_grams'],
                        'reserved_grams' => $availability['reserved_grams'],
                        'available_grams' => $availability['capacity_grams'] === null
                            ? null
                            : max(0, $availability['capacity_grams'] - $availability['reserved_grams']),
                    ];
                }
            }
        }

        $departures = collect($rows)->sortBy('departure_at')->values();
        $withoutBudget = $departures->whereNull('capacity_grams')->values();
        $saturated = $departures->filter(fn (array $row): bool => $row['capacity_grams'] !== null && $row['available_grams'] <= 0)->values();

        return [
            'horizon_days' => $this->routing->horizonDays(),
            'departures_count' => $departures->count(),
            'without_capacity_count' => $withoutBudget->count(),
            'saturated_count' => $saturated->count(),
            'available_grams' => $departures->sum(fn (array $row): int => (int) ($row['available_grams'] ?? 0)),
            'without_capacity' => $withoutBudget->take(10)->all(),
            'saturated' => $saturated->take(10)->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $capacity
     * @return array<string, mixed>
     */
    private function configurationHealth(User $actor, array $capacity): array
    {
        $hubs = ParcelHub::query()
            ->active()
            ->with(['agency', 'stop'])
            ->when(! $actor->can('parcels.network'), fn (Builder $query) => $query->where('agency_id', $actor->agency_id))
            ->get();
        $corridors = ParcelCorridor::query()
            ->where('enabled', true)
            ->with(['originHub', 'destinationHub', 'tariffs'])
            ->when(! $actor->can('parcels.network'), fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->whereRelation('originHub', 'agency_id', $actor->agency_id)
                    ->orWhereRelation('destinationHub', 'agency_id', $actor->agency_id)
            ))
            ->get();
        $available = $this->routing->filterOperationallyAvailable($corridors);
        $activeByOrigin = $corridors->countBy('origin_hub_id');
        $availableByOrigin = $available->countBy('origin_hub_id');
        $hubWithoutCorridor = [];
        $hubWithoutDeparture = [];
        $hubWithoutWeighing = [];

        foreach ($hubs as $hub) {
            $label = $this->hubLabel($hub);
            if ((int) ($activeByOrigin[$hub->id] ?? 0) === 0) {
                $hubWithoutCorridor[] = ['uuid' => $hub->uuid, 'label' => $label];

                continue;
            }
            if ((int) ($availableByOrigin[$hub->id] ?? 0) === 0) {
                $hubWithoutDeparture[] = ['uuid' => $hub->uuid, 'label' => $label];
            }
            if ($hub->supports_weighing !== true) {
                $hubWithoutWeighing[] = ['uuid' => $hub->uuid, 'label' => $label];
            }
        }

        $corridorsWithoutTariff = $corridors->filter(fn (ParcelCorridor $corridor): bool => $corridor->tariffs->isEmpty())
            ->map(fn (ParcelCorridor $corridor): array => ['uuid' => $corridor->uuid, 'label' => $this->corridorLabel($corridor)])
            ->values()
            ->all();

        $alerts = [];
        foreach ($hubWithoutCorridor as $hub) {
            $alerts[] = ['code' => 'hub_without_corridor', 'severity' => 'warning', 'message' => "Aucun corridor actif au départ de {$hub['label']}."];
        }
        foreach ($hubWithoutDeparture as $hub) {
            $alerts[] = ['code' => 'hub_without_departure', 'severity' => 'warning', 'message' => "Aucun départ compatible pour {$hub['label']} dans l’horizon de routage."];
        }
        foreach ($corridorsWithoutTariff as $corridor) {
            $alerts[] = ['code' => 'corridor_without_tariff', 'severity' => 'blocking', 'message' => "Le corridor {$corridor['label']} n’a aucun tarif publié."];
        }
        foreach ($hubWithoutWeighing as $hub) {
            $alerts[] = ['code' => 'hub_without_weighing', 'severity' => 'warning', 'message' => "Le lieu {$hub['label']} n’accepte pas la pesée."];
        }
        foreach ($capacity['without_capacity'] as $departure) {
            $alerts[] = ['code' => 'departure_without_capacity', 'severity' => 'warning', 'message' => "Le départ {$departure['line_code']} du {$departure['departure_at']} n’a pas de budget de soute."];
        }

        return [
            'hubs_without_corridor' => $hubWithoutCorridor,
            'hubs_without_departure' => $hubWithoutDeparture,
            'hubs_without_weighing' => $hubWithoutWeighing,
            'corridors_without_tariff' => $corridorsWithoutTariff,
            'departures_without_capacity' => $capacity['without_capacity'],
            'alerts' => array_slice($alerts, 0, 20),
        ];
    }

    /** @param Builder<Parcel> $parcels */
    private function withdrawnToday(Builder $parcels): int
    {
        return (clone $parcels)->whereHas('events', fn (Builder $events): Builder => $events
            ->where('kind', 'withdrawn')
            ->whereDate('observed_at', now()->toDateString()))->count();
    }

    /**
     * @param  Builder<Parcel>  $parcels
     * @return array<string, mixed>
     */
    private function financeQueue(Builder $parcels): array
    {
        $chargeIds = ParcelCharge::query()
            ->whereIn('parcel_id', (clone $parcels)->select('id'))
            ->pluck('id');

        $reconciliations = Payment::query()
            ->whereIn('parcel_charge_id', $chargeIds)
            ->where('failure_code', 'parcel_reconciliation_required')
            ->with('parcelCharge.parcel')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Payment $payment): array => [
                'uuid' => $payment->uuid,
                'parcel_uuid' => $payment->parcelCharge?->parcel?->uuid,
                'parcel_reference' => $payment->parcelCharge?->parcel?->reference,
                'amount' => (int) $payment->amount,
                'currency' => $payment->currency,
                'detected_at' => $payment->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $pendingRefunds = PaymentRefund::query()
            ->where('status', PaymentRefundStatus::Pending)
            ->whereHas('payment', fn (Builder $query): Builder => $query->whereIn('parcel_charge_id', $chargeIds))
            ->with('payment.parcelCharge.parcel')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (PaymentRefund $refund): array => [
                'uuid' => $refund->uuid,
                'parcel_uuid' => $refund->payment?->parcelCharge?->parcel?->uuid,
                'parcel_reference' => $refund->payment?->parcelCharge?->parcel?->reference,
                'amount' => (int) $refund->amount,
                'currency' => $refund->currency,
                'status' => $refund->status->value,
                'requested_at' => $refund->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $pendingInvoices = FiscalInvoice::query()
            ->whereIn('status', [FiscalInvoiceStatus::Pending, FiscalInvoiceStatus::Failed])
            ->whereIn('parcel_charge_id', $chargeIds)
            ->with('parcelCharge.parcel')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (FiscalInvoice $invoice): array => [
                'uuid' => $invoice->uuid,
                'parcel_uuid' => $invoice->parcelCharge?->parcel?->uuid,
                'parcel_reference' => $invoice->parcelCharge?->parcel?->reference,
                'kora_reference' => $invoice->kora_reference,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'total' => (int) $invoice->total_with_tax,
                'requested_at' => $invoice->requested_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'reconciliations' => [
                'count' => Payment::query()->whereIn('parcel_charge_id', $chargeIds)
                    ->where('failure_code', 'parcel_reconciliation_required')->count(),
                'total_amount' => (int) Payment::query()->whereIn('parcel_charge_id', $chargeIds)
                    ->where('failure_code', 'parcel_reconciliation_required')->sum('amount'),
                'items' => $reconciliations,
            ],
            'pending_refunds' => [
                'count' => PaymentRefund::query()->where('status', PaymentRefundStatus::Pending)
                    ->whereHas('payment', fn (Builder $query): Builder => $query->whereIn('parcel_charge_id', $chargeIds))->count(),
                'total_amount' => (int) PaymentRefund::query()->where('status', PaymentRefundStatus::Pending)
                    ->whereHas('payment', fn (Builder $query): Builder => $query->whereIn('parcel_charge_id', $chargeIds))->sum('amount'),
                'items' => $pendingRefunds,
            ],
            'pending_invoices' => [
                'count' => FiscalInvoice::query()
                    ->whereIn('status', [FiscalInvoiceStatus::Pending, FiscalInvoiceStatus::Failed])
                    ->whereIn('parcel_charge_id', $chargeIds)->count(),
                'total_amount' => (int) FiscalInvoice::query()
                    ->whereIn('status', [FiscalInvoiceStatus::Pending, FiscalInvoiceStatus::Failed])
                    ->whereIn('parcel_charge_id', $chargeIds)->sum('total_with_tax'),
                'items' => $pendingInvoices,
            ],
        ];
    }

    private function corridorLabel(ParcelCorridor $corridor): string
    {
        $origin = data_get($corridor, 'originHub.stop.city')
            ?? data_get($corridor, 'originHub.agency.name')
            ?? 'Origine';
        $destination = data_get($corridor, 'destinationHub.stop.city')
            ?? data_get($corridor, 'destinationHub.agency.name')
            ?? 'Destination';

        return (is_scalar($origin) ? (string) $origin : 'Origine').' → '.(is_scalar($destination) ? (string) $destination : 'Destination');
    }

    private function hubLabel(ParcelHub $hub): string
    {
        $agency = $hub->agency?->name;
        $city = $hub->stop?->city;

        return implode(' · ', array_filter([$agency, $city])) ?: $hub->uuid;
    }
}
