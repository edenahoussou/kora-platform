<?php

namespace App\Modules\Departures\Events;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DepartureDelayRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Departure $departure,
        public User $actor,
        public int $previousDelayMinutes,
        public int $delayMinutes,
        public string $reason,
        public CarbonImmutable $recordedAt,
    ) {}
}
