<?php

namespace App\Modules\Dashboard\Services;

use App\Models\User;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\Notifications\Data\OpsNotificationSeverity;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardReadService
{
    private const CURRENCY = 'XOF';

    /**
     * Seuil de retard (minutes) au-delà duquel un départ n'est plus ponctuel.
     */
    private const PUNCTUALITY_TOLERANCE_MINUTES = 10;

    /**
     * Agrège les données du centre de pilotage pour la période demandée.
     * Les profils d'agence sont scopés à leur agence ; les profils centraux
     * voient l'ensemble du réseau.
     *
     * @return array<string, mixed>
     */
    public function build(User $user, RevenuePeriod $period): array
    {
        $agencyId = $this->agencyScope($user);
        $payments = $this->payments($period->startUtc(), $period->endExclusiveUtc(), $agencyId);
        $comparisonPayments = $this->payments($period->comparisonStartUtc(), $period->comparisonEndExclusiveUtc(), $agencyId);
        $refunds = $this->refundTotal($period->startUtc(), $period->endExclusiveUtc(), $agencyId);
        $comparisonRefunds = $this->refundTotal($period->comparisonStartUtc(), $period->comparisonEndExclusiveUtc(), $agencyId);
        $tickets = $this->tickets($period->startUtc(), $period->endExclusiveUtc(), $agencyId);
        $comparisonTickets = $this->tickets($period->comparisonStartUtc(), $period->comparisonEndExclusiveUtc(), $agencyId);
        $departures = $this->departures($period);
        $comparisonDepartures = $this->departures($period, true);

        $net = (int) $payments->sum('amount') - $refunds;
        $comparisonNet = (int) $comparisonPayments->sum('amount') - $comparisonRefunds;

        return [
            'metrics' => [
                'sales' => [
                    'value' => $this->money($net),
                    'trend' => $this->trendPercent($net, $comparisonNet),
                    'spark' => $this->sparkValues($payments, $period, fn (Payment $payment): array => [
                        CarbonImmutable::parse($payment->confirmed_at)->setTimezone(RevenuePeriod::TIMEZONE)->toDateString(),
                        (int) $payment->amount,
                    ]),
                ],
                'tickets' => [
                    'value' => (string) $tickets->count(),
                    'trend' => $this->trendPercent($tickets->count(), $comparisonTickets->count()),
                    'spark' => $this->sparkValues($tickets, $period, fn (Ticket $ticket): ?array => $ticket->issued_at === null ? null : [
                        CarbonImmutable::parse($ticket->issued_at)->setTimezone(RevenuePeriod::TIMEZONE)->toDateString(),
                        1,
                    ]),
                ],
                'cash' => [
                    'value' => $this->money($this->expectedCash($agencyId)),
                    'trend' => $this->openSessionsLabel($agencyId),
                    'spark' => [],
                ],
                'departures' => [
                    'value' => (string) $departures->count(),
                    'trend' => $this->nextDepartureLabel(),
                    'spark' => $this->sparkValues($departures, $period, fn (Departure $departure): array => [
                        $departure->scheduledDepartureAt()->setTimezone(RevenuePeriod::TIMEZONE)->toDateString(),
                        1,
                    ]),
                ],
            ],
            'activity' => $this->activityTimeline($payments, $refunds, $period),
            'priorities' => $this->priorities($user),
            'network' => $this->networkReadings($user, $period),
            'heroAction' => $this->heroAction($user),
            'quickActions' => $this->quickActions($user),
        ];
    }

    /**
     * Un utilisateur rattaché à une agence (agent / manager) est scopé à cette
     * agence. Tous les profils centraux voient le réseau complet.
     */
    private function agencyScope(User $user): ?int
    {
        return $user->hasAnyRole([SystemRole::AgencyAgent->value, SystemRole::AgencyManager->value])
            ? $user->agency_id
            : null;
    }

    /**
     * @return Collection<int, Payment>
     */
    private function payments(CarbonImmutable $start, CarbonImmutable $end, ?int $agencyId): Collection
    {
        return Payment::query()
            ->where('status', PaymentStatus::Confirmed->value)
            ->where('currency', self::CURRENCY)
            ->where('confirmed_at', '>=', $start)
            ->where('confirmed_at', '<', $end)
            ->when($agencyId !== null, fn (Builder $query) => $query->whereHas(
                'reservation',
                fn (Builder $reservation) => $reservation->where('selling_agency_id', $agencyId),
            ))
            ->get(['id', 'amount', 'confirmed_at', 'reservation_id']);
    }

    /**
     * Total des remboursements rattachés à des paiements confirmés XOF,
     * enregistrés dans la période (même source de vérité que le rapport
     * recettes : obligations de remboursement sur encaissements confirmés).
     */
    private function refundTotal(CarbonImmutable $start, CarbonImmutable $end, ?int $agencyId): int
    {
        return (int) PaymentRefund::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereHas('payment', function (Builder $payment) use ($agencyId): void {
                $payment->where('status', PaymentStatus::Confirmed->value)
                    ->where('currency', self::CURRENCY)
                    ->when($agencyId !== null, fn (Builder $query) => $query->whereHas(
                        'reservation',
                        fn (Builder $reservation) => $reservation->where('selling_agency_id', $agencyId),
                    ));
            })
            ->sum('amount');
    }

    /**
     * @return Collection<int, Ticket>
     */
    private function tickets(CarbonImmutable $start, CarbonImmutable $end, ?int $agencyId): Collection
    {
        return Ticket::query()
            ->whereIn('status', [TicketStatus::Issued->value, TicketStatus::Used->value])
            ->where('issued_at', '>=', $start)
            ->where('issued_at', '<', $end)
            ->when($agencyId !== null, fn (Builder $query) => $query->whereHas(
                'reservation',
                fn (Builder $reservation) => $reservation->where('selling_agency_id', $agencyId),
            ))
            ->get(['id', 'issued_at', 'reservation_id']);
    }

    /**
     * Départs de la période (hors annulés). `scheduled_at` est nullable : les
     * départs historiques ne portent que `departure_date`/`departure_time`
     * (heure locale) — on couvre les deux formes.
     * Les départs n'étant pas rattachés à une agence, ce KPI reste réseau
     * pour tous les profils.
     *
     * @return Collection<int, Departure>
     */
    private function departures(RevenuePeriod $period, bool $comparison = false): Collection
    {
        [$start, $end, $localStart, $localEnd] = $comparison
            ? [$period->comparisonStartUtc(), $period->comparisonEndExclusiveUtc(), $period->comparisonLocalStart, $period->comparisonLocalEnd]
            : [$period->startUtc(), $period->endExclusiveUtc(), $period->localStart, $period->localEnd];

        return Departure::query()
            ->where(fn (Builder $query) => $query
                ->whereBetween('scheduled_at', [$start, $end])
                ->orWhere(fn (Builder $legacy) => $legacy
                    ->whereNull('scheduled_at')
                    ->whereBetween('departure_date', [$localStart->toDateString(), $localEnd->toDateString()])))
            ->where('status', '!=', DepartureStatus::Cancelled->value)
            ->get(['id', 'scheduled_at', 'departure_date', 'departure_time', 'status', 'delay_minutes']);
    }

    /**
     * Encaissements attendus dans les caisses actuellement ouvertes.
     */
    private function expectedCash(?int $agencyId): int
    {
        return (int) CashSession::query()
            ->where('status', CashSessionStatus::Open->value)
            ->when($agencyId !== null, fn (Builder $query) => $query->where('agency_id', $agencyId))
            ->sum('expected_balance');
    }

    private function openSessionsLabel(?int $agencyId): string
    {
        $open = CashSession::query()
            ->where('status', CashSessionStatus::Open->value)
            ->when($agencyId !== null, fn (Builder $query) => $query->where('agency_id', $agencyId))
            ->count();

        return $open === 0
            ? 'Aucune caisse ouverte'
            : trans_choice(':count caisse ouverte|:count caisses ouvertes', $open);
    }

    private function nextDepartureLabel(): string
    {
        $now = CarbonImmutable::now();
        $today = $now->setTimezone(RevenuePeriod::TIMEZONE);

        $next = Departure::query()
            ->whereNotIn('status', [DepartureStatus::Cancelled->value, DepartureStatus::Departed->value, DepartureStatus::Arrived->value])
            ->where(fn (Builder $query) => $query
                ->where('scheduled_at', '>=', $now)
                ->orWhere(fn (Builder $legacy) => $legacy
                    ->whereNull('scheduled_at')
                    ->where(fn (Builder $date) => $date
                        ->whereDate('departure_date', '>', $today->toDateString())
                        ->orWhere(fn (Builder $sameDay) => $sameDay
                            ->whereDate('departure_date', $today->toDateString())
                            ->where('departure_time', '>=', $today->format('H:i:s'))))))
            ->get(['id', 'scheduled_at', 'departure_date', 'departure_time'])
            ->sortBy(fn (Departure $departure) => $departure->scheduledDepartureAt())
            ->first();

        if ($next === null) {
            return 'Aucun départ programmé';
        }

        return 'Prochain à '.$next->scheduledDepartureAt()->setTimezone(RevenuePeriod::TIMEZONE)->format('H:i');
    }

    /**
     * Valeurs journalières d'une collection pour les sparklines (normalisées
     * en % par le front). `$resolve` retourne [dateLocale, valeur] ou null.
     *
     * @param  Collection<int, mixed>  $rows
     * @return list<float>
     */
    private function sparkValues(Collection $rows, RevenuePeriod $period, callable $resolve): array
    {
        $byDay = [];

        foreach ($rows as $row) {
            $resolved = $resolve($row);
            if ($resolved === null) {
                continue;
            }

            [$day, $value] = $resolved;
            $byDay[$day] = ($byDay[$day] ?? 0) + $value;
        }

        $values = [];
        for ($date = $period->localStart; $date->lte($period->localEnd); $date = $date->addDay()) {
            $values[] = (float) ($byDay[$date->toDateString()] ?? 0);
        }

        return $values;
    }

    /**
     * Série du bar chart « Activité » : recettes nettes par bucket.
     * Jour courant → buckets horaires 06h–22h ; autre période → par jour.
     *
     * @param  Collection<int, Payment>  $payments
     * @return array{bucket: string, points: list<array{label: string, value: float, isBest: bool}>}
     */
    private function activityTimeline(Collection $payments, int $refunds, RevenuePeriod $period): array
    {
        $singleDay = $period->localStart->isSameDay($period->localEnd);
        $points = [];

        if ($singleDay) {
            // Buckets horaires 06h–22h (plage d'exploitation) sur le jour demandé.
            for ($hour = 6; $hour <= 22; $hour++) {
                $points[$hour] = [
                    'label' => sprintf('%02dh', $hour),
                    'value' => 0.0,
                    'isBest' => false,
                ];
            }

            foreach ($payments as $payment) {
                $hour = (int) CarbonImmutable::parse($payment->confirmed_at)
                    ->setTimezone(RevenuePeriod::TIMEZONE)
                    ->format('G');
                if (isset($points[$hour])) {
                    $points[$hour]['value'] += (int) $payment->amount;
                }
            }

            // Les remboursements du jour sont déduits du bucket courant.
            $currentHour = (int) CarbonImmutable::now(RevenuePeriod::TIMEZONE)->format('G');
            if (isset($points[$currentHour])) {
                $points[$currentHour]['value'] = max(0, $points[$currentHour]['value'] - $refunds);
            }

            $points = array_values($points);
        } else {
            for ($date = $period->localStart; $date->lte($period->localEnd); $date = $date->addDay()) {
                $points[$date->toDateString()] = [
                    'label' => $date->isoFormat('dd DD'),
                    'value' => 0.0,
                    'isBest' => false,
                ];
            }

            foreach ($payments as $payment) {
                $day = CarbonImmutable::parse($payment->confirmed_at)
                    ->setTimezone(RevenuePeriod::TIMEZONE)
                    ->toDateString();
                if (isset($points[$day])) {
                    $points[$day]['value'] += (int) $payment->amount;
                }
            }

            // Répartir le total des remboursements sur le dernier bucket non vide
            // serait artificiel : on déduit du bucket du dernier jour de la période.
            $last = $period->localEnd->toDateString();
            if (isset($points[$last])) {
                $points[$last]['value'] = max(0, $points[$last]['value'] - $refunds);
            }

            $points = array_values($points);
        }

        $best = null;
        foreach ($points as $index => $point) {
            if ($point['value'] > 0 && ($best === null || $point['value'] > $points[$best]['value'])) {
                $best = $index;
            }
        }

        return [
            'bucket' => $singleDay ? 'hour' : 'day',
            'points' => array_map(
                fn (array $point, int $index): array => [
                    'label' => $point['label'],
                    'value' => (float) $point['value'],
                    'isBest' => $index === $best,
                ],
                $points,
                array_keys($points),
            ),
        ];
    }

    /**
     * Fil « À suivre maintenant » : notifications Ops non lues du destinataire,
     * les plus récentes d'abord.
     *
     * @return list<array{uuid: string, category: string, title: string, detail: string, time: string, tone: string, href: string|null}>
     */
    private function priorities(User $user): array
    {
        $notifications = OpsNotification::query()
            ->with('event.departure:id,uuid')
            ->where('user_id', $user->id)
            ->where('audience', 'ops')
            ->unread()
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();

        $priorities = [];
        foreach ($notifications as $notification) {
            // `event_id` est nullable en base (nullOnDelete).
            // On utilise instanceof pour aider PHPStan à comprendre le typage nullable
            // sans déclencher l'erreur "nullsafe.neverNull".
            $event = $notification->event;

            /** @var array<string, mixed> $payload */
            $payload = (array) $notification->data;

            $severity = ($event instanceof OpsNotificationEvent ? $event->severity : null)
                ?? OpsNotificationSeverity::tryFrom((string) ($payload['severity'] ?? ''))
                ?? OpsNotificationSeverity::Info;

            $departureUuid = ($event instanceof OpsNotificationEvent && $event->departure !== null)
                ? $event->departure->uuid
                : null;

            $priorities[] = [
                'uuid' => $notification->uuid,
                'category' => $this->severityLabel($severity),
                'title' => ($event instanceof OpsNotificationEvent ? $event->title : null) ?? (string) ($payload['title'] ?? 'Mise à jour'),
                'detail' => ($event instanceof OpsNotificationEvent ? $event->message : null) ?? (string) ($payload['message'] ?? ''),
                'time' => $notification->created_at->setTimezone(RevenuePeriod::TIMEZONE)->format('H:i'),
                'tone' => match ($severity) {
                    OpsNotificationSeverity::Critical => 'urgent',
                    OpsNotificationSeverity::Warning => 'control',
                    default => 'success',
                },
                'href' => $departureUuid !== null ? route('departures.show', $departureUuid) : null,
            ];
        }

        return $priorities;
    }

    private function severityLabel(OpsNotificationSeverity $severity): string
    {
        return match ($severity) {
            OpsNotificationSeverity::Critical => 'Critique',
            OpsNotificationSeverity::Warning => 'Attention',
            OpsNotificationSeverity::Info => 'Info',
        };
    }

    /**
     * Indicateurs « État du réseau » du hero.
     *
     * @return array{punctuality: string, tracked: string, toWatch: string}
     */
    private function networkReadings(User $user, RevenuePeriod $period): array
    {
        $departures = $this->departures($period);
        $past = $departures->filter(
            fn (Departure $departure): bool => in_array($departure->status, [DepartureStatus::Departed, DepartureStatus::Arrived], true),
        );
        $onTime = $past->filter(
            fn (Departure $departure): bool => $departure->delay_minutes <= self::PUNCTUALITY_TOLERANCE_MINUTES,
        );

        $punctuality = $past->isEmpty()
            ? '—'
            : number_format(round($onTime->count() / $past->count() * 100, 1), 1, ',', ' ').' %';

        return [
            'punctuality' => $punctuality,
            'tracked' => (string) $departures->count(),
            'toWatch' => (string) OpsNotification::query()->where('user_id', $user->id)->where('audience', 'ops')->unread()->count(),
        ];
    }

    /**
     * CTA principal du hero selon le profil (maquette) :
     * admin → gestion des utilisateurs ; agence / commercial → Nouvelle vente ;
     * exploitation → Créer un départ ; finance / direction → écran des recettes.
     *
     * @return array{label: string, href: string}
     */
    private function heroAction(User $user): array
    {
        if ($user->hasRole(SystemRole::SystemAdmin->value)) {
            return ['label' => 'Nouvel utilisateur', 'href' => route('users')];
        }

        if ($user->hasAnyRole([SystemRole::AgencyAgent->value, SystemRole::AgencyManager->value, SystemRole::CommercialDirector->value])) {
            return ['label' => 'Nouvelle vente', 'href' => route('sale')];
        }

        if ($user->hasRole(SystemRole::OperationsDirector->value)) {
            return ['label' => 'Créer un départ', 'href' => route('departures.index')];
        }

        if ($user->hasAnyRole([SystemRole::Daf->value, SystemRole::GeneralManagement->value])) {
            return ['label' => 'Voir les recettes', 'href' => route('revenue')];
        }

        return ['label' => 'Consulter les rapports', 'href' => route('reports')];
    }

    /**
     * Raccourcis métiers définis par profil (maquette). Les cartes dont
     * l'écran n'est pas encore implémenté portent `href: null` — le front
     * les affiche avec un badge « Bientôt ».
     *
     * @return list<array{title: string, description: string, icon: string, href: string|null, accent: string}>
     */
    private function quickActions(User $user): array
    {
        return match (true) {
            $user->hasRole(SystemRole::SystemAdmin->value) => [
                [
                    'title' => 'Nouvel utilisateur',
                    'description' => 'Créer un compte et lui assigner un rôle',
                    'icon' => 'user-plus',
                    'href' => route('users'),
                    'accent' => 'gold',
                ],
                [
                    'title' => 'Ajouter un rôle',
                    'description' => 'Définir les permissions d’un profil',
                    'icon' => 'shield',
                    'href' => route('roles'),
                    'accent' => 'blue',
                ],
                [
                    'title' => 'Paramètres',
                    'description' => 'Configuration générale de la plateforme',
                    'icon' => 'settings',
                    'href' => route('administration.settings'),
                    'accent' => 'navy',
                ],
                [
                    'title' => 'Terminaux P501',
                    'description' => 'Parc d’appareils d’embarquement',
                    'icon' => 'terminal',
                    'href' => route('terminals'),
                    'accent' => 'green',
                ],
            ],
            $user->hasAnyRole([SystemRole::AgencyAgent->value, SystemRole::AgencyManager->value]) => [
                [
                    'title' => 'Nouvelle vente',
                    'description' => 'Émettre un billet en quelques étapes',
                    'icon' => 'ticket',
                    'href' => route('sale'),
                    'accent' => 'gold',
                ],
                [
                    'title' => 'Ma caisse',
                    'description' => 'Contrôler les encaissements du jour',
                    'icon' => 'cash',
                    'href' => route('cash'),
                    'accent' => 'blue',
                ],
                [
                    'title' => 'Départs',
                    'description' => 'Superviser la cadence du réseau',
                    'icon' => 'route',
                    'href' => route('departures.index'),
                    'accent' => 'green',
                ],
                [
                    'title' => 'Nouveau colis',
                    'description' => 'Enregistrer un envoi pour un client',
                    'icon' => 'package',
                    'href' => null,
                    'accent' => 'navy',
                ],
            ],
            $user->hasRole(SystemRole::OperationsDirector->value) => [
                [
                    'title' => 'Créer un départ',
                    'description' => 'Planifier une rotation sur une ligne',
                    'icon' => 'route',
                    'href' => route('departures.index'),
                    'accent' => 'gold',
                ],
                [
                    'title' => 'Gestion de la flotte',
                    'description' => 'Disponibilité et état des véhicules',
                    'icon' => 'fleet',
                    'href' => route('fleet.index'),
                    'accent' => 'blue',
                ],
                [
                    'title' => 'Intervention',
                    'description' => 'Déclencher une maintenance véhicule',
                    'icon' => 'wrench',
                    'href' => null,
                    'accent' => 'green',
                ],
                [
                    'title' => 'Rapports d’exploitation',
                    'description' => 'Cadence, remplissage et incidents',
                    'icon' => 'report',
                    'href' => null,
                    'accent' => 'navy',
                ],
            ],
            $user->hasRole(SystemRole::Daf->value) => [
                [
                    'title' => 'Recettes',
                    'description' => 'Encaissements consolidés du réseau',
                    'icon' => 'cash',
                    'href' => route('revenue'),
                    'accent' => 'gold',
                ],
                [
                    'title' => 'Facturation',
                    'description' => 'Factures fiscales et conformité MECeF',
                    'icon' => 'invoice',
                    'href' => route('fiscal-invoices.index'),
                    'accent' => 'blue',
                ],
                [
                    'title' => 'Rentabilité',
                    'description' => 'Contribution des lignes et départs',
                    'icon' => 'chart',
                    'href' => route('profitability'),
                    'accent' => 'green',
                ],
                [
                    'title' => 'Dépenses',
                    'description' => 'Décaissements et justificatifs',
                    'icon' => 'wallet',
                    'href' => route('expenses.index'),
                    'accent' => 'navy',
                ],
            ],
            $user->hasRole(SystemRole::GeneralManagement->value) => [
                [
                    'title' => 'Rentabilité',
                    'description' => 'Contribution des lignes et départs',
                    'icon' => 'chart',
                    'href' => route('profitability'),
                    'accent' => 'gold',
                ],
                [
                    'title' => 'Rapport exécutif',
                    'description' => 'Synthèse décisionnelle du réseau',
                    'icon' => 'report',
                    'href' => null,
                    'accent' => 'blue',
                ],
                [
                    'title' => 'Disponibilité flotte',
                    'description' => 'Véhicules actifs et immobilisés',
                    'icon' => 'fleet',
                    'href' => route('fleet.index'),
                    'accent' => 'green',
                ],
                [
                    'title' => 'Alertes',
                    'description' => 'Toutes les situations à suivre',
                    'icon' => 'alert',
                    'href' => null,
                    'accent' => 'navy',
                ],
            ],
            $user->hasRole(SystemRole::CommercialDirector->value) => [
                [
                    'title' => 'Tarification dynamique',
                    'description' => 'Règles de prix réseau, ligne, segment',
                    'icon' => 'pricing',
                    'href' => route('pricing-rules.index'),
                    'accent' => 'gold',
                ],
                [
                    'title' => 'Promotions',
                    'description' => 'Codes promo et offres commerciales',
                    'icon' => 'promo',
                    'href' => route('promotions.index'),
                    'accent' => 'blue',
                ],
                [
                    'title' => 'Comptes entreprises',
                    'description' => 'Clients corporate et crédit',
                    'icon' => 'company',
                    'href' => route('companies.index'),
                    'accent' => 'green',
                ],
                [
                    'title' => 'Rapports',
                    'description' => 'Analyses commerciales du réseau',
                    'icon' => 'report',
                    'href' => null,
                    'accent' => 'navy',
                ],
            ],
            default => [],
        };
    }

    private function trendPercent(float|int $current, float|int $previous): string
    {
        if ($previous === 0 || $previous === 0.0) {
            return $current > 0 ? 'Nouvelle activité' : 'Stable';
        }

        $delta = round(($current - $previous) / abs($previous) * 100, 1);
        $sign = $delta > 0 ? '+ ' : '';

        return $sign.number_format($delta, 1, ',', ' ').' %';
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' FCFA';
    }
}
