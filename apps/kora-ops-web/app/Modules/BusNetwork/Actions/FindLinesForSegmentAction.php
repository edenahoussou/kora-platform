<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FindLinesForSegmentAction
{
    /**
     * @return Collection<int, Line>
     */
    public function handle(?string $from, ?string $to): Collection
    {
        $lines = Line::query()
            ->where('status', LineStatus::Active->value)
            ->with(['stops' => fn ($query) => $query
                ->where('status', StopStatus::Active->value)
                ->orderBy('stop_order')])
            ->when($from, fn ($query) => $query->whereHas('stops', fn ($stops) => $stops
                ->whereRaw('LOWER(city) = ?', [Str::lower($from)])
                ->where('status', StopStatus::Active->value)))
            ->when($to, fn ($query) => $query->whereHas('stops', fn ($stops) => $stops
                ->whereRaw('LOWER(city) = ?', [Str::lower($to)])
                ->where('status', StopStatus::Active->value)))
            ->orderBy('name')
            ->get();

        if (! $from || ! $to) {
            return $lines;
        }

        return $lines
            ->filter(fn (Line $line): bool => $this->lineContainsOrderedSegment($line, $from, $to))
            ->values();
    }

    private function lineContainsOrderedSegment(Line $line, string $from, string $to): bool
    {
        /** @var EloquentCollection<int, Stop> $stops */
        $stops = $line->getRelation('stops');

        $origin = $stops->first(fn ($stop): bool => Str::lower($stop->city) === Str::lower($from));
        $destination = $stops->first(fn ($stop): bool => Str::lower($stop->city) === Str::lower($to));

        return $origin && $destination && $origin->stop_order < $destination->stop_order;
    }
}
