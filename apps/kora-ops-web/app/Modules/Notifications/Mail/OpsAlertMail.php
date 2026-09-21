<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\Notifications\Models\OpsNotificationEvent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail d'alerte opérationnelle boarding. Même contenu que la notification :
 * aucune donnée financière, aucun identifiant numérique.
 */
class OpsAlertMail extends Mailable
{
    public function __construct(public readonly OpsNotificationEvent $event) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[KORA OPS] '.$this->event->title);
    }

    public function content(): Content
    {
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
