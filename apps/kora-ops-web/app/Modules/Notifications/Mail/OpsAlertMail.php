<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\AssistedBooking\Services\AssistedBookingSupportChannels;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail d’alerte opérationnelle envoyé aux destinataires autorisés.
 */
class OpsAlertMail extends Mailable
{
    private ?AssistedBooking $assistedBooking = null;

    public function __construct(public readonly OpsNotificationEvent $event)
    {
        $uuid = $event->data['assisted_booking_uuid'] ?? null;
        if ($event->type === OpsNotificationType::AssistedBookingRequested && is_string($uuid)) {
            $this->assistedBooking = AssistedBooking::query()->with('reservation.tickets')->where('uuid', $uuid)->first();
        }
    }

    public function envelope(): Envelope
    {
        $reference = $this->assistedBooking?->reservation->reference;
        $subject = $reference === null ? $this->event->title : 'Réservation '.$reference.' à prendre en charge';

        return new Envelope(subject: '[KORA OPS] '.$subject);
    }

    public function content(): Content
    {
        if ($this->event->type === OpsNotificationType::AssistedBookingRequested) {
            $booking = $this->assistedBooking;

            return new Content(
                markdown: 'mail.assisted-booking-requested',
                with: [
                    'reference' => $booking?->reservation->reference,
                    'contactName' => $booking?->contact_name,
                    'contactPhone' => $booking?->contact_phone,
                    'passengerCount' => (int) ($booking?->snapshot['passenger_count'] ?? 0),
                    'itinerary' => $booking === null ? [] : app(AssistedBookingSupportChannels::class)->itinerary($booking),
                    'ticketCodes' => $booking?->reservation->tickets->pluck('short_code')->filter()->implode(', '),
                    'detailUrl' => route('assisted-bookings.index', $booking === null ? [] : ['open' => $booking->uuid]),
                ],
            );
        }

        $departure = $this->event->departure;
        $terminal = $this->event->terminal;

        return new Content(
            markdown: 'mail.ops-alert',
            with: [
                'title' => $this->event->title,
                'alertMessage' => $this->event->message,
                'code' => $this->event->type->value,
                'severity' => $this->event->severity->value,
                'occurredAt' => $this->event->occurred_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'departure' => $departure === null ? null : sprintf(
                    '%s — %s à %s',
                    $departure->line->code ?? '—',
                    $departure->departure_date?->format('d/m/Y') ?? '—',
                    substr((string) $departure->departure_time, 0, 5),
                ),
                'terminal' => $terminal?->serial_number,
                'convoyeur' => $this->event->subject?->name,
            ],
        );
    }
}
