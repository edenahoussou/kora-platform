<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Ticketing\Actions\GenerateTicketQrCodeAction;
use App\Modules\Ticketing\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Renders a single ticket as a standalone Blade page (no dashboard layout)
 * suitable for printing and PDF generation.
 */
class TicketViewController extends Controller
{
    public function __construct(
        private readonly DepartureStopTimeResolver $stopTimeResolver,
        private readonly GenerateTicketQrCodeAction $generateQr,
    ) {}

    public function show(Ticket $ticket, Request $request): Response
    {
        $this->loadRelations($ticket);
        $this->authorizeOwner($ticket->reservation, $request);

        return response()->view('tickets.show', $this->viewData($ticket, isPdf: false));
    }

    public function pdf(Ticket $ticket, Request $request): Response
    {
        $this->loadRelations($ticket);
        $this->authorizeOwner($ticket->reservation, $request);

        $data = $this->viewData($ticket, isPdf: true);
        $pdf = Pdf::loadView('tickets.show', $data)
            ->setPaper([0, 0, 226.77, 600], 'portrait'); // 80mm width, auto height

        return $pdf->download("billet-{$ticket->short_code}.pdf");
    }

    private function loadRelations(Ticket $ticket): void
    {
        $ticket->loadMissing([
            'reservation.payments.verifier.agency',
            'reservation.creator.agency',
            'reservationPassenger.segments.departure.line',
            'reservationPassenger.segments.departure.bus',
            'reservationPassenger.segments.seat',
            'reservationPassenger.segments.originStop',
            'reservationPassenger.segments.destinationStop',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Ticket $ticket, bool $isPdf): array
    {
        $reservation = $ticket->reservation;
        $passenger = $ticket->reservationPassenger;
        $segments = $passenger?->segments?->sortBy('leg_order') ?? collect();

        $legs = $segments->map(fn (ReservationSegment $segment): array => $this->legData($segment))->values()->all();

        /** @var ReservationSegment|null $firstLeg */
        $firstLeg = $segments->first();
        /** @var ReservationSegment|null $lastLeg */
        $lastLeg = $segments->last();
        $originCity = $this->stopCity($firstLeg?->originStop) ?? '—';
        $destinationCity = $this->stopCity($lastLeg?->destinationStop) ?? '—';

        $payment = $reservation?->payments->sortByDesc('id')->first();
        $agency = $payment?->verifier->agency ?? $reservation?->creator?->agency;
        $fiscalInvoice = FiscalInvoice::query()
            ->where('reservation_id', $reservation?->getKey())
            ->first();

        $passengerName = $passenger !== null
            ? trim($passenger->first_name.' '.$passenger->last_name)
            : '—';

        $passengerDocument = $passenger?->document_number;
        $passengerPhone = $passenger?->phone;

        $qrSvg = $this->generateQr->execute($ticket->qr_code ?? $ticket->uuid);

        $amount = $this->resolveAmount($reservation, $passenger?->passenger_index);
        $currency = $reservation->currency ?? 'XOF';

        return [
            'isPdf' => $isPdf,
            'shortCode' => $ticket->short_code,
            'statusLabel' => 'Payé',
            'originCity' => $originCity,
            'destinationCity' => $destinationCity,
            'passengerName' => $passengerName,
            'passengerPhone' => $passengerPhone,
            'passengerDocument' => $passengerDocument,
            'legs' => $legs,
            'legsCount' => count($legs),
            'mecefUid' => $fiscalInvoice?->mecef_uid,
            'amountLabel' => $this->formatMoney($amount, $currency),
            'issuedAtLabel' => $this->formatDate($ticket->issued_at),
            'agencyName' => $agency?->name,
            'channelLabel' => $this->channelLabel($reservation?->getRawOriginal('channel')),
            'qrSvg' => $qrSvg,
            'pdfUrl' => route('tickets.pdf', $ticket),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legData(ReservationSegment $segment): array
    {
        $departure = $segment->departure;
        $originStop = $segment->originStop;
        $destinationStop = $segment->destinationStop;

        $departureAt = $originStop !== null
            ? $this->stopTimeResolver->departureAt($departure, $originStop)
            : null;

        $direction = $segment->direction ?? 'outbound';
        $directionLabel = $direction === 'inbound' ? 'Retour' : 'Aller';

        $departureTime = '—';
        if ($departureAt !== null) {
            $departureTime = $departureAt->setTimezone(
                $departure->departure_timezone ?? config('app.timezone'),
            )->format('H:i');
        } else {
            $departureTime = $departure->departure_time ?? '—';
        }

        return [
            'directionLabel' => $directionLabel,
            'originCity' => $this->stopCity($originStop) ?? '—',
            'destinationCity' => $this->stopCity($destinationStop) ?? '—',
            'dateLabel' => $this->formatDate($departure?->departure_date),
            'departureTime' => $departureTime,
            'lineCode' => $departure->line->code ?? '—',
            'busRegistration' => $departure->bus->registration_number ?? '—',
            'seatCode' => $segment->seat->code ?? '—',
        ];
    }

    /**
     * @param  object|null  $stop
     */
    private function stopCity($stop): ?string
    {
        if ($stop === null) {
            return null;
        }

        return $stop->city ?? $stop->name ?? null;
    }

    private function resolveAmount(?Reservation $reservation, ?int $passengerIndex): int
    {
        if ($reservation === null || $passengerIndex === null) {
            return 0;
        }

        $allocations = $reservation->total_snapshot['passenger_allocations'] ?? [];

        return (int) ($allocations[$passengerIndex] ?? 0);
    }

    private function formatMoney(int $amount, string $currency): string
    {
        $formatted = number_format($amount, 0, ',', ' ');

        return $formatted.' '.$currency;
    }

    private function formatDate(Carbon|CarbonImmutable|null $date): string
    {
        if ($date === null) {
            return '—';
        }

        return $date->format('d/m/Y');
    }

    private function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'counter' => 'Vente au guichet',
            'online' => 'Vente en ligne',
            'mobile' => 'Vente mobile',
            'ops' => 'Vente OPS',
            default => 'KORA EXPRESS',
        };
    }

    private function authorizeOwner(?Reservation $reservation, Request $request): void
    {
        if ($reservation === null
            || ($reservation->created_by_user_id !== $request->user()?->getKey()
                && $reservation->payer_user_id !== $request->user()?->getKey())) {
            throw new AuthorizationException('Vous n’avez pas accès à ce billet.');
        }
    }
}
