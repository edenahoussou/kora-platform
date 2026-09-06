<?php

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Actions\CalculateFarePreviewAction;
use App\Modules\DynamicPricing\Actions\CalculateQuoteAction;
use App\Modules\DynamicPricing\Data\FareDirection;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');

    $this->line = Line::query()->create([
        'code' => 'DAF-LINE',
        'name' => 'Direction Aware Fare Line',
        'countries_crossed' => ['BJ'],
        'distance_km' => 200,
        'estimated_duration_minutes' => 180,
        'status' => 'active',
    ]);

    $this->cityAPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'City A Physical',
        'city' => 'CityA',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $this->cityBPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'City B Physical',
        'city' => 'CityB',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $this->stopA = Stop::query()->create([
        'line_id' => $this->line->id,
        'physical_stop_id' => $this->cityAPhysical->id,
        'name' => 'City A',
        'city' => 'CityA',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);

    $this->stopB = Stop::query()->create([
        'line_id' => $this->line->id,
        'physical_stop_id' => $this->cityBPhysical->id,
        'name' => 'City B',
        'city' => 'CityB',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 200,
        'status' => 'active',
    ]);

    $this->serviceClass = ServiceClass::query()->create([
        'uuid' => (string) Str::uuid(),
        'code' => 'confort',
        'name' => 'Confort',
    ]);

    $this->bus = Bus::query()->create([
        'registration_number' => 'DAF-BUS',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);

    Seat::query()->create([
        'bus_id' => $this->bus->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $this->departure = Departure::query()->create([
        'line_id' => $this->line->id,
        'bus_id' => $this->bus->id,
        'departure_date' => '2026-08-20',
        'departure_time' => '08:00',
        'estimated_arrival_at' => Carbon::parse('2026-08-20 11:00'),
        'status' => 'scheduled',
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('uses a both-direction fare for outbound quotes', function (): void {
    Fare::query()->create([
        'line_id' => $this->line->id,
        'service_class_id' => $this->serviceClass->id,
        'origin_stop_id' => $this->stopA->id,
        'destination_stop_id' => $this->stopB->id,
        'direction' => FareDirection::Both->value,
        'base_amount' => 5000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $action = app(CalculateQuoteAction::class);
    $quote = $action->handle($this->departure, $this->stopA, $this->stopB, $this->serviceClass);

    expect($quote->base_amount)->toBe(5000);
});

it('prefers an explicit outbound fare over a both fare', function (): void {
    Fare::query()->create([
        'line_id' => $this->line->id,
        'service_class_id' => $this->serviceClass->id,
        'origin_stop_id' => $this->stopA->id,
        'destination_stop_id' => $this->stopB->id,
        'direction' => FareDirection::Both->value,
        'base_amount' => 5000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    Fare::query()->create([
        'line_id' => $this->line->id,
        'service_class_id' => $this->serviceClass->id,
        'origin_stop_id' => $this->stopA->id,
        'destination_stop_id' => $this->stopB->id,
        'direction' => FareDirection::Outbound->value,
        'base_amount' => 7000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $action = app(CalculateQuoteAction::class);
    $quote = $action->handle($this->departure, $this->stopA, $this->stopB, $this->serviceClass);

    expect($quote->base_amount)->toBe(7000);
});

it('falls back to the inverse-direction fare when no direct fare exists', function (): void {
    // Only a return fare exists (stopB → stopA), but we quote stopA → stopB.
    // The fallback should find and use the inverse fare.
    Fare::query()->create([
        'line_id' => $this->line->id,
        'service_class_id' => $this->serviceClass->id,
        'origin_stop_id' => $this->stopB->id,
        'destination_stop_id' => $this->stopA->id,
        'direction' => FareDirection::Return->value,
        'base_amount' => 8000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $action = app(CalculateQuoteAction::class);
    $quote = $action->handle($this->departure, $this->stopA, $this->stopB, $this->serviceClass);

    expect($quote->base_amount)->toBe(8000);
});

it('blocks the quote when no fare exists in either direction', function (): void {
    $action = app(CalculateQuoteAction::class);

    expect(fn () => $action->handle($this->departure, $this->stopA, $this->stopB, $this->serviceClass))
        ->toThrow(ValidationException::class);
});

it('uses a both fare in the fare preview for either direction', function (): void {
    Fare::query()->create([
        'line_id' => $this->line->id,
        'service_class_id' => $this->serviceClass->id,
        'origin_stop_id' => $this->stopA->id,
        'destination_stop_id' => $this->stopB->id,
        'direction' => FareDirection::Both->value,
        'base_amount' => 6000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $action = app(CalculateFarePreviewAction::class);
    $travelDate = Carbon::parse('2026-08-20');

    // Forward direction
    $preview = $action->handle($this->line, $this->stopA, $this->stopB, $travelDate);
    expect($preview)->not->toBeNull()
        ->and($preview['base_amount'])->toBe(6000);

    // Reverse direction — both fare still applies
    $reversePreview = $action->handle($this->line, $this->stopB, $this->stopA, $travelDate);
    expect($reversePreview)->not->toBeNull()
        ->and($reversePreview['base_amount'])->toBe(6000);
});

it('falls back to the inverse fare in the fare preview', function (): void {
    // Only a return fare (stopB → stopA) exists. Requesting stopA → stopB
    // should fall back to the inverse.
    Fare::query()->create([
        'line_id' => $this->line->id,
        'service_class_id' => $this->serviceClass->id,
        'origin_stop_id' => $this->stopB->id,
        'destination_stop_id' => $this->stopA->id,
        'direction' => FareDirection::Return->value,
        'base_amount' => 9000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $action = app(CalculateFarePreviewAction::class);
    $travelDate = Carbon::parse('2026-08-20');

    $preview = $action->handle($this->line, $this->stopA, $this->stopB, $travelDate);
    expect($preview)->not->toBeNull()
        ->and($preview['base_amount'])->toBe(9000);
});

it('returns null from fare preview when no fare exists in either direction', function (): void {
    $action = app(CalculateFarePreviewAction::class);
    $travelDate = Carbon::parse('2026-08-20');

    $preview = $action->handle($this->line, $this->stopA, $this->stopB, $travelDate);
    expect($preview)->toBeNull();
});

it('accepts a reversed segment when the departure has reversed stop_times', function (): void {
    // Build a return departure with reversed stop_times order.
    $returnDeparture = Departure::query()->create([
        'line_id' => $this->line->id,
        'bus_id' => $this->bus->id,
        'departure_date' => '2026-08-21',
        'departure_time' => '14:00',
        'estimated_arrival_at' => Carbon::parse('2026-08-21 17:00'),
        'status' => 'scheduled',
        'stop_times' => [
            [
                'stop_uuid' => $this->stopB->uuid,
                'departure_minutes' => 0,
                'arrival_minutes' => null,
                'timezone' => 'Africa/Porto-Novo',
            ],
            [
                'stop_uuid' => $this->stopA->uuid,
                'departure_minutes' => 180,
                'arrival_minutes' => 180,
                'timezone' => 'Africa/Porto-Novo',
            ],
        ],
    ]);

    // Fare defined in the return direction (stopB → stopA).
    Fare::query()->create([
        'line_id' => $this->line->id,
        'service_class_id' => $this->serviceClass->id,
        'origin_stop_id' => $this->stopB->id,
        'destination_stop_id' => $this->stopA->id,
        'direction' => FareDirection::Return->value,
        'base_amount' => 4500,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $action = app(CalculateQuoteAction::class);
    $quote = $action->handle($returnDeparture, $this->stopB, $this->stopA, $this->serviceClass);

    expect($quote->base_amount)->toBe(4500);
});

it('rejects a forward segment when the departure has reversed stop_times', function (): void {
    $returnDeparture = Departure::query()->create([
        'line_id' => $this->line->id,
        'bus_id' => $this->bus->id,
        'departure_date' => '2026-08-21',
        'departure_time' => '14:00',
        'estimated_arrival_at' => Carbon::parse('2026-08-21 17:00'),
        'status' => 'scheduled',
        'stop_times' => [
            [
                'stop_uuid' => $this->stopB->uuid,
                'departure_minutes' => 0,
                'arrival_minutes' => null,
                'timezone' => 'Africa/Porto-Novo',
            ],
            [
                'stop_uuid' => $this->stopA->uuid,
                'departure_minutes' => 180,
                'arrival_minutes' => 180,
                'timezone' => 'Africa/Porto-Novo',
            ],
        ],
    ]);

    Fare::query()->create([
        'line_id' => $this->line->id,
        'service_class_id' => $this->serviceClass->id,
        'origin_stop_id' => $this->stopA->id,
        'destination_stop_id' => $this->stopB->id,
        'direction' => FareDirection::Both->value,
        'base_amount' => 5000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $action = app(CalculateQuoteAction::class);

    // stopA → stopB is forward, but the departure goes stopB → stopA.
    expect(fn () => $action->handle($returnDeparture, $this->stopA, $this->stopB, $this->serviceClass))
        ->toThrow(ValidationException::class);
});
