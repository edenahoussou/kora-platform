<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Ticketing\Actions\ResolveTicketAmountAction;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Services\TicketVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class TicketIndexController extends Controller
{
    public function __construct(
        private readonly TicketVisibility $visibility,
        private readonly ResolveTicketAmountAction $resolveAmount,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ticket::class);
        $baseQuery = $this->visibility->query($request->user());
        $query = (clone $baseQuery)
            ->with([
                'reservationPassenger',
                'reservation.segments.originStop',
                'reservation.segments.destinationStop',
                'reservation.fiscalInvoice.emecfInvoice',
            ])
            ->orderByDesc('issued_at');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(short_code) LIKE ?', [$term])
                    ->orWhereHas('reservationPassenger', function ($rp) use ($term): void {
                        $rp->whereRaw('LOWER(first_name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$term]);
                    });
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(TicketStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $channel = $request->string('channel')->trim()->value();
        if ($channel !== '' && in_array($channel, array_column(ReservationChannel::cases(), 'value'), true)) {
            $query->whereHas('reservation', function ($r) use ($channel): void {
                $r->where('channel', $channel);
            });
        }

        $route = $request->string('route')->trim()->value();
        if ($route !== '' && str_contains($route, '→')) {
            [$originCity, $destinationCity] = explode('→', $route, 2);
            $originCity = trim($originCity);
            $destinationCity = trim($destinationCity);
            $query->whereHas('reservation.segments', function ($s) use ($originCity, $destinationCity): void {
                $s->whereHas('originStop', fn ($stop) => $stop->where('city', $originCity))
                    ->whereHas('destinationStop', fn ($stop) => $stop->where('city', $destinationCity));
            });
        }

        $paginated = $query->paginate(15)->withQueryString();
        $kpis = $this->buildKpis(clone $baseQuery);
        $routes = $this->buildRoutes(clone $baseQuery);

        return inertia('tickets', [
            'tickets' => $paginated->through(fn (Ticket $ticket): array => $this->serializeTicket($ticket)),
            'statuses' => TicketStatus::options(),
            'channels' => ReservationChannel::options(),
            'routes' => $routes,
            'kpis' => $kpis,
        ]);
    }

    /**
     * @param  Builder<Ticket>  $visibleTickets
     * @return array<int, string>
     */
    private function buildRoutes(Builder $visibleTickets): array
    {
        $segments = ReservationSegment::query()
            ->whereIn('reservation_id', $visibleTickets->select('reservation_id'))
            ->with(['originStop', 'destinationStop'])
            ->get();

        $routes = collect();
        foreach ($segments as $segment) {
            $origin = $segment->originStop?->city;
            $destination = $segment->destinationStop?->city;
            if ($origin !== null && $destination !== null) {
                $route = $origin.' → '.$destination;
                if (! $routes->contains($route)) {
                    $routes->push($route);
                }
            }
        }

        return $routes->sort()->values()->all();
    }

    /**
     * @param  Builder<Ticket>  $baseQuery
     * @return array<string, mixed>
     */
    private function buildKpis(Builder $baseQuery): array
    {

        $total = (clone $baseQuery)->count();
        $issued = (clone $baseQuery)->where('status', TicketStatus::Issued)->count();
        $used = (clone $baseQuery)->where('status', TicketStatus::Used)->count();
        $cancelled = (clone $baseQuery)->where('status', TicketStatus::Cancelled)->count();
        $refunded = (clone $baseQuery)->where('status', TicketStatus::Refunded)->count();

        $totalRevenue = 0;
        foreach ((clone $baseQuery)->whereIn('status', [TicketStatus::Issued, TicketStatus::Used])
            ->with(['reservation', 'reservationPassenger'])->lazyById(500) as $ticket) {
            $totalRevenue += $this->resolveAmount->execute($ticket);
        }

        return [
            'total' => $total,
            'issued' => $issued,
            'used' => $used,
            'cancelled' => $cancelled,
            'refunded' => $refunded,
            'totalRevenue' => (int) $totalRevenue,
            'totalRevenueLabel' => $this->formatMoney((int) $totalRevenue, 'XOF'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTicket(Ticket $ticket): array
    {
        $reservation = $ticket->reservation;
        $passenger = $ticket->reservationPassenger;
        $segments = $reservation?->segments?->sortBy('leg_order') ?? collect();
        $firstSegment = $segments->first();
        $lastSegment = $segments->last();

        $passengerName = $passenger !== null
            ? trim($passenger->first_name.' '.$passenger->last_name)
            : '—';

        $channel = $reservation?->getRawOriginal('channel');
        $amount = $this->resolveAmount->execute($ticket);
        $currency = $reservation->currency ?? 'XOF';
        $fiscalInvoice = $reservation?->fiscalInvoice;

        $originCity = $firstSegment?->originStop->city ?? '—';
        $destinationCity = $lastSegment?->destinationStop->city ?? '—';

        return [
            'uuid' => $ticket->uuid,
            'shortCode' => $ticket->short_code,
            'passengerName' => $passengerName,
            'passengerPhone' => $passenger?->phone,
            'channel' => $channel,
            'channelLabel' => $this->channelLabel($channel),
            'originCity' => $originCity,
            'destinationCity' => $destinationCity,
            'route' => $originCity.' → '.$destinationCity,
            'amount' => $amount,
            'currency' => $currency,
            'amountLabel' => $this->formatMoney($amount, $currency),
            'status' => $ticket->status->value,
            'statusLabel' => $this->statusLabel($ticket->status),
            'issuedAt' => $ticket->issued_at?->toIso8601String(),
            'issuedAtLabel' => $ticket->issued_at?->format('d/m/Y H:i') ?? '—',
            'mecefUid' => $fiscalInvoice?->mecef_uid,
            'mecefCode' => $fiscalInvoice?->emecfInvoice?->code_mec_ef_dgi,
            'mecefNim' => $fiscalInvoice?->emecfInvoice?->nim,
            'fiscalStatus' => $fiscalInvoice?->getRawOriginal('status'),
        ];
    }

    private function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'counter' => 'Guichet',
            'online' => 'En ligne',
            'mobile' => 'Mobile',
            'ops' => 'OPS',
            default => '—',
        };
    }

    private function statusLabel(TicketStatus $status): string
    {
        return match ($status) {
            TicketStatus::Issued => 'Émis',
            TicketStatus::Used => 'Utilisé',
            TicketStatus::Cancelled => 'Annulé',
            TicketStatus::Refunded => 'Remboursé',
        };
    }

    private function formatMoney(int $amount, string $currency): string
    {
        $formatted = number_format($amount, 0, ',', ' ');

        return $formatted.' '.$currency;
    }
}
