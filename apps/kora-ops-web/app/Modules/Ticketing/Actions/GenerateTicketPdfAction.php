<?php

namespace App\Modules\Ticketing\Actions;

use App\Modules\Booking\Actions\DescribeReservationDiscountAction;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonInterface;
use Spatie\Browsershot\Browsershot;

/**
 * Generates the ticket PDF as raw binary content.
 *
 * Shared between the HTTP controller (download) and the WhatsApp
 * notification job (base64 attachment), so both surfaces produce
 * the exact same document.
 */
class GenerateTicketPdfAction
{
    public function __construct(
        private readonly DepartureStopTimeResolver $stopTimeResolver,
        private readonly GenerateTicketQrCodeAction $generateQr,
        private readonly ResolveTicketAmountAction $resolveTicketAmount,
        private readonly DescribeReservationDiscountAction $describeDiscount,
    ) {}

    /**
     * @param  bool  $hideAmount  Omit financial fields (amount, MECeF UID)
     *                            from the rendered document — used by the
     *                            OPS Mobile boarding-confirmation endpoint,
     *                            which must never expose pricing to crew.
     *                            Defaults to false so every existing caller
     *                            (passenger download, web preview, WhatsApp
     *                            attachment) is unaffected.
     * @return array{content: string, filename: string}
     */
    public function execute(Ticket $ticket, bool $hideAmount = false): array
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
            ? $this->passengerOriginalShare($reservation, $ticket->reservationPassenger?->getKey())
            : null;
        $discountAmount = $originalAmount !== null ? max(0, $originalAmount - $amount) : null;

        $data = [
            'isPdf' => true,
            'hideAmount' => $hideAmount,
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

        $html = view('tickets.show', $data)->render();
        $profile = config('pdf.profiles.ticket', []);
        $configuredCommonArgs = config('pdf.common_args', []);
        $commonArgs = is_array($configuredCommonArgs)
            ? array_values(array_filter($configuredCommonArgs, 'is_string'))
            : [];
        $profileArgs = is_array($profile['args'] ?? null)
            ? array_values(array_filter($profile['args'], 'is_string'))
            : [];
        $chromiumArguments = array_values(array_unique(array_map(
            static fn (string $argument): string => ltrim($argument, '-'),
            array_merge($commonArgs, $profileArgs),
        )));

        $browsershot = Browsershot::html($html)
            ->noSandbox()
            ->addChromiumArguments(array_values(array_filter(
                $chromiumArguments,
                static fn (string $argument): bool => $argument !== 'no-sandbox',
            )))
            ->showBackground()
            ->emulateMedia($profile['emulate_media'] ?? 'print')
            ->windowSize(...($profile['window_size'] ?? [302, 1200]))
            ->deviceScaleFactor($profile['device_scale_factor'] ?? 2)
            ->paperSize(...($profile['paper_size'] ?? [80, 297]))
            ->margins(...($profile['margins'] ?? [0, 0, 0, 0]))
            ->timeout(config('pdf.timeout', 60000));

        $chromePath = config('pdf.chrome_path');
        if (is_string($chromePath) && $chromePath !== '') {
            $browsershot->setChromePath($chromePath);
        }

        $nodeBinary = config('pdf.node_binary');
        if (is_string($nodeBinary) && $nodeBinary !== '') {
            $browsershot->setNodeBinary($nodeBinary);
        }

        $chromeHome = config('pdf.home_dir');
        if (is_string($chromeHome) && $chromeHome !== '') {
            if (! is_dir($chromeHome) && ! mkdir($chromeHome, 0775, true) && ! is_dir($chromeHome)) {
                throw new \RuntimeException('Unable to prepare the Chromium home directory.');
            }

            $browsershot->setNodeEnv(['HOME' => $chromeHome]);
        }

        if ($profile['wait_until_network_idle'] ?? false) {
            $browsershot->waitUntilNetworkIdle();
        }

        $content = $browsershot->pdf();

        return [
            'content' => $content,
            'filename' => "billet-{$ticket->short_code}.pdf",
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
            'company_portal' => 'Espace entreprise',
            'ops' => 'Vente OPS',
            default => 'KORA EXPRESS',
        };
    }
}
