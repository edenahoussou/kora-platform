<?php

namespace App\Modules\Notifications\Services\Sms;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Notifications\Notifications\PassengerSmsNotification;
use Illuminate\Support\Facades\Notification;

final class PassengerSmsDispatcher
{
    /** @var list<string> */
    private const array ROUTED_TYPES = ['reservation_confirmed', 'departure_cancelled', 'departure_delayed'];

    public function __construct(
        private readonly SmsRecipientResolver $resolver,
        private readonly PassengerSmsTemplate $template,
    ) {}

    /** @param array<string, mixed> $context */
    public function dispatch(Reservation $reservation, string $type, array $context = []): void
    {
        if (! in_array($type, self::ROUTED_TYPES, true)) {
            return;
        }

        $recipient = $this->resolver->resolve($reservation);
        $content = $this->template->build($type, $reservation, $context);

        if ($recipient === null || $content === null) {
            return;
        }

        Notification::route('sms', $recipient)->notify(new PassengerSmsNotification(
            content: $content,
            dedupeKey: 'bus:sms:'.$type.':'.$reservation->uuid.':'.($context['dedupe_suffix'] ?? ''),
            type: $type,
            referenceType: $reservation->getMorphClass(),
            referenceId: (int) $reservation->getKey(),
        ));
    }
}
