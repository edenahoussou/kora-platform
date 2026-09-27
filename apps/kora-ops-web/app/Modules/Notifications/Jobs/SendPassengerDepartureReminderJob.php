<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Services\PassengerNotificationPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPassengerDepartureReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $reservationId,
        public readonly int $departureId,
        public readonly int $offsetHours,
        public readonly string $expectedDepartureAt,
    ) {}

    public function handle(PassengerNotificationPublisher $notifications): void
    {
        $reservation = Reservation::query()->find($this->reservationId);
        $departure = Departure::query()->find($this->departureId);
        if ($reservation === null || $departure === null || $reservation->status !== ReservationStatus::Ticketed ||
            $departure->status === DepartureStatus::Cancelled ||
            $departure->scheduledDepartureAt()->addMinutes((int) $departure->delay_minutes)->toIso8601String() !== $this->expectedDepartureAt) {
            return;
        }

        $notifications->departureReminder($reservation, $departure->uuid.':'.$this->offsetHours.'h:'.$this->expectedDepartureAt);
    }
}
