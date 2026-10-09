<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendReservationWhatsAppNotificationJob;
use App\Modules\Booking\Actions\DescribeReservationDiscountAction;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Notifications\Models\WhatsAppDelivery;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Actions\GenerateTicketQrCodeAction;
use App\Modules\Ticketing\Actions\ResolveTicketAmountAction;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Renders a single ticket as a standalone Blade page (no dashboard layout)
 * suitable for printing and PDF generation.
 */
class TicketViewController extends Controller
{
    public function __construct(
        private readonly DepartureStopTimeResolver $stopTimeResolver,
        private readonly GenerateTicketQrCodeAction $generateQr,
        private readonly ResolveTicketAmountAction $resolveTicketAmount,
        private readonly GenerateTicketPdfAction $generatePdf,
        private readonly DescribeReservationDiscountAction $describeDiscount,
    ) {}

    public function show(Ticket $ticket, Request $request): Response
    {
        $this->loadRelations($ticket);
        Gate::authorize('view', $ticket);

        return response()->view('tickets.show', $this->viewData($ticket, isPdf: false));
    }

    public function pdf(Ticket $ticket, Request $request): Response
    {
        $this->loadRelations($ticket);
        Gate::authorize('view', $ticket);

        $pdf = $this->generatePdf->execute($ticket);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
        ]);
    }

    public function signedPdf(Ticket $ticket): Response
    {
        $pdf = $this->generatePdf->execute($ticket);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
        ]);
    }

    public function sendWhatsApp(Ticket $ticket, Request $request): RedirectResponse
    {
        $this->loadRelations($ticket);
        Gate::authorize('view', $ticket);

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $phoneOverride = $request->string('phone')->trim()->value();
        if ($phoneOverride !== '' && ! str_starts_with($phoneOverride, '+')) {
            return back()->withErrors(['phone' => 'Le numéro doit être au format international (ex: +2290197421860).'])->withInput();
        }

        $delivery = WhatsAppDelivery::query()->where('ticket_id', $ticket->id)->first();
        $alreadySent = $delivery !== null && $delivery->getRawOriginal('status') === 'sent';

        if (! $alreadySent) {
            SendReservationWhatsAppNotificationJob::dispatch(
                $ticket->reservation_id,
                $ticket->id,
                $phoneOverride !== '' ? $phoneOverride : null,
            );
        }

        return back()->with('whatsapp_status', $alreadySent ? 'already_sent' : 'dispatched');
    }

    private function loadRelations(Ticket $ticket): void
    {
        $ticket->loadMissing([
            'reservation.sellingAgency',
            'reservationPassenger.segments.departure.line',
            'reservationPassenger.segments.departure.bus',
            'reservationPassenger.segments.seat',
            'reservationPassenger.segments.originStop',
            'reservationPassenger.segments.destinationStop',
            'reservation.passengers',
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

        $legs = $segments->map(fn ($segment): array => $this->legData($segment))->values()->all();

        $firstLeg = $segments->first();
        $lastLeg = $segments->last();
        $originCity = $this->stopCity($firstLeg?->originStop) ?? '—';
        $destinationCity = $this->stopCity($lastLeg?->destinationStop) ?? '—';

        $agency = $reservation?->sellingAgency;
        $fiscalInvoice = FiscalInvoice::query()
            ->where('reservation_id', $reservation?->getKey())
            ->first();

        $passengerName = $passenger !== null
            ? trim($passenger->first_name.' '.$passenger->last_name)
            : '—';

        $passengerDocument = $passenger?->document_number;
        $passengerPhone = $passenger?->phone;

        $qrSvg = $this->generateQr->execute($ticket->qr_code ?? $ticket->uuid);

        $amount = $this->resolveTicketAmount->execute($ticket);
        $currency = $reservation->currency ?? 'XOF';

        $discountLabel = $this->describeDiscount->execute($reservation);
        $originalAmount = $discountLabel !== null
            ? $this->passengerOriginalShare($reservation, $passenger?->getKey())
            : null;
        $discountAmount = $originalAmount !== null ? max(0, $originalAmount - $amount) : null;

        return [
            'isPdf' => $isPdf,
            'ticketUuid' => $ticket->uuid,
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
            'discountLabel' => $discountLabel,
            'discountAmountLabel' => $discountAmount !== null && $discountAmount > 0
                ? '-'.$this->formatMoney($discountAmount, $currency)
                : null,
            'originalAmountLabel' => $originalAmount !== null && $originalAmount > $amount
                ? $this->formatMoney($originalAmount, $currency)
                : null,
            'issuedAtLabel' => $this->formatDate($ticket->issued_at),
            'agencyName' => $agency?->name,
            'channelLabel' => $this->channelLabel($reservation?->getRawOriginal('channel')),
            'qrSvg' => $qrSvg,
            'pdfUrl' => route('tickets.pdf', $ticket),
        ];
    }

    /** Pre-discount share for this passenger, distributed like the invoice lines. */
    private function passengerOriginalShare(Reservation $reservation, ?int $passengerId): ?int
    {
        if ($passengerId === null) {
            return null;
        }

        $actives = $reservation->passengers->where('status', 'active')->values();
        $count = max(1, $actives->count());
        $position = $actives->search(fn ($p): bool => $p->getKey() === $passengerId);
        if ($position === false) {
            return null;
        }

        $total = $this->describeDiscount->originalTotal($reservation);

        return intdiv($total, $count) + ($position < $total % $count ? 1 : 0);
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

    private function formatMoney(int $amount, string $currency): string
    {
        $formatted = number_format($amount, 0, ',', ' ');

        return $formatted.' '.$currency;
    }

    private function formatDate(?CarbonInterface $date): string
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
            'company_portal' => 'Vente portail entreprise',
            'ops' => 'Vente OPS',
            default => 'KORA EXPRESS',
        };
    }
}
