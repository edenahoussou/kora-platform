<?php

use App\Jobs\SendReservationWhatsAppNotificationJob;
use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Data\WhatsAppDeliveryStatus;
use App\Modules\Notifications\Models\WhatsAppDelivery;
use App\Modules\Notifications\Services\WhatsApp\GenukaWhatsAppSender;
use App\Modules\Notifications\Services\WhatsApp\WahaWhatsAppSender;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Models\Ticket;
use Exception;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-08 10:00:00');
    // Genuka est configuré par défaut dans les tests pour exercer le chemin primaire.
    config([
        'services.genuka.token' => 'test-genuka-token',
        'services.genuka.connection_id' => 'test-conn',
    ]);
    // Désactive le délai anti-blocage de 30-60s pour ne pas ralentir les tests.
    config(['services.waha.new_contact_delay_seconds' => 0]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('sends a WhatsApp message via Genuka and records the delivery', function (): void {
    Http::fake([
        'wa.genuka.com/*' => Http::response(['id' => 'genuka-msg-123'], 200),
    ]);

    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);
    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));

    $delivery = WhatsAppDelivery::query()->where('ticket_id', $scenario['ticket']->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(WhatsAppDeliveryStatus::Sent)
        ->and($delivery->provider)->toBe('genuka')
        ->and($delivery->provider_message_id)->toBe('genuka-msg-123')
        ->and($delivery->sent_at)->not->toBeNull();
});

it('falls back to Waha when Genuka returns a 5xx error', function (): void {
    Http::fake([
        'wa.genuka.com/*' => Http::response(['error' => 'server error'], 500),
        config('services.waha.url').'/*' => Http::response(['id' => 'waha-msg-456'], 200),
    ]);

    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);
    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));

    $delivery = WhatsAppDelivery::query()->where('ticket_id', $scenario['ticket']->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(WhatsAppDeliveryStatus::Sent)
        ->and($delivery->provider)->toBe('waha')
        ->and($delivery->provider_message_id)->toBe('waha-msg-456');
});

it('falls back directly to Waha when Genuka is not configured (token empty)', function (): void {
    config([
        'services.genuka.token' => null,
        'services.genuka.connection_id' => null,
    ]);
    Http::fake([
        'wa.genuka.com/*' => Http::response(['error' => 'should not be called'], 500),
        config('services.waha.url').'/*' => Http::response(['id' => 'waha-direct-789'], 200),
    ]);

    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);
    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));

    $delivery = WhatsAppDelivery::query()->where('ticket_id', $scenario['ticket']->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(WhatsAppDeliveryStatus::Sent)
        ->and($delivery->provider)->toBe('waha')
        ->and($delivery->provider_message_id)->toBe('waha-direct-789');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'wa.genuka.com'));
});

it('falls back to Waha when Genuka returns 401 (token invalid)', function (): void {
    Http::fake([
        'wa.genuka.com/*' => Http::response(['error' => 'unauthorized'], 401),
        config('services.waha.url').'/*' => Http::response(['id' => 'waha-401-fb'], 200),
    ]);

    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);
    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));

    $delivery = WhatsAppDelivery::query()->where('ticket_id', $scenario['ticket']->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(WhatsAppDeliveryStatus::Sent)
        ->and($delivery->provider)->toBe('waha')
        ->and($delivery->provider_message_id)->toBe('waha-401-fb');
});

it('marks the delivery as failed when both Genuka and Waha fail', function (): void {
    Http::fake([
        'wa.genuka.com/*' => Http::response(['error' => 'server error'], 500),
        config('services.waha.url').'/*' => Http::response(['error' => 'waha down'], 500),
    ]);

    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);

    try {
        $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));
    } catch (Exception $e) {
        // Expected: Waha also fails
    }

    $delivery = WhatsAppDelivery::query()->where('ticket_id', $scenario['ticket']->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(WhatsAppDeliveryStatus::Failed)
        ->and($delivery->provider)->toBe('waha')
        ->and($delivery->last_error)->not->toBeNull();
});

it('skips delivery when no phone number is available', function (): void {
    $scenario = createWhatsAppScenario(phone: null, userPhone: null);
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);
    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));

    $delivery = WhatsAppDelivery::query()->where('ticket_id', $scenario['ticket']->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(WhatsAppDeliveryStatus::Skipped);
});

it('does not create duplicate delivery records on retry', function (): void {
    Http::fake([
        'wa.genuka.com/*' => Http::response(['id' => 'genuka-msg-789'], 200),
    ]);

    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);

    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));
    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));

    expect(WhatsAppDelivery::query()->where('ticket_id', $scenario['ticket']->id)->count())->toBe(1);
});

it('sends only a document (not a duplicate text) via Waha when documentData is set', function (): void {
    Http::fake([
        config('services.waha.url').'/api/sendFile' => Http::response(['id' => 'waha-file-only'], 200),
        config('services.waha.url').'/api/sendText' => Http::response(['error' => 'should not be called'], 500),
    ]);

    $sender = app(WahaWhatsAppSender::class);
    $result = $sender->send('+2290197421860', 'Billet KORA', base64_encode('fake-pdf-content'), 'billet.pdf');

    expect($result['message_id'])->toBe('waha-file-only');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/sendFile');
    });
    Http::assertNotSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/sendText');
    });
});

it('sends startTyping and stopTyping before the message via Waha (anti-blocage)', function (): void {
    Http::fake([
        config('services.waha.url').'/api/startTyping' => Http::response(['ok' => true], 200),
        config('services.waha.url').'/api/stopTyping' => Http::response(['ok' => true], 200),
        config('services.waha.url').'/api/sendText' => Http::response(['id' => 'waha-typing-001'], 200),
    ]);

    $sender = app(WahaWhatsAppSender::class);
    $result = $sender->send('+2290197421860', 'Bonjour, billet KORA', null, null);

    expect($result['message_id'])->toBe('waha-typing-001');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/startTyping'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/stopTyping'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/sendText'));
});

it('personalizes the WhatsApp message with the passenger first name', function (): void {
    Http::fake([
        'wa.genuka.com/*' => Http::response(['id' => 'genuka-perso-001'], 200),
    ]);

    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);
    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'wa.genuka.com')) {
            return false;
        }
        $body = json_decode($request->body(), true);
        // Le texte est dans "caption" quand un document est joint.
        $text = $body['text'] ?? $body['document']['caption'] ?? '';

        return str_contains($text, 'Aïcha');
    });
});

it('marks delivery as Skipped (not Failed) when WAHA returns error 463 (Reachout Timelock)', function (): void {
    config([
        'services.genuka.token' => null,
        'services.genuka.connection_id' => null,
    ]);
    Http::fake([
        config('services.waha.url').'/api/startTyping' => Http::response(['ok' => true], 200),
        config('services.waha.url').'/api/stopTyping' => Http::response(['ok' => true], 200),
        config('services.waha.url').'/api/sendFile' => Http::response('server returned error 463', 200),
    ]);

    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);
    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));

    $delivery = WhatsAppDelivery::query()->where('ticket_id', $scenario['ticket']->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(WhatsAppDeliveryStatus::Skipped)
        ->and($delivery->provider)->toBe('waha')
        ->and($delivery->last_error)->toContain('463');
});

it('marks delivery as Skipped (not Failed) when WAHA returns error 475 (Message Capping)', function (): void {
    config([
        'services.genuka.token' => null,
        'services.genuka.connection_id' => null,
    ]);
    Http::fake([
        config('services.waha.url').'/api/startTyping' => Http::response(['ok' => true], 200),
        config('services.waha.url').'/api/stopTyping' => Http::response(['ok' => true], 200),
        config('services.waha.url').'/api/sendFile' => Http::response(['error' => 'server returned error 475'], 500),
    ]);

    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $job = new SendReservationWhatsAppNotificationJob($scenario['reservation']->id, $scenario['ticket']->id);
    $job->handle(app(GenukaWhatsAppSender::class), app(WahaWhatsAppSender::class), app(GenerateTicketPdfAction::class));

    $delivery = WhatsAppDelivery::query()->where('ticket_id', $scenario['ticket']->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(WhatsAppDeliveryStatus::Skipped)
        ->and($delivery->last_error)->toContain('475');
});

it('re-dispatches Skipped WhatsApp deliveries older than the threshold via whatsapp:retry-skipped', function (): void {
    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $delivery = WhatsAppDelivery::query()->create([
        'uuid' => (string) Str::uuid(),
        'ticket_id' => $scenario['ticket']->id,
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'waha',
        'recipient_phone' => '+2290197421860',
        'status' => WhatsAppDeliveryStatus::Skipped,
        'last_error' => 'WhatsApp a temporairement restreint l\'envoi (code 463).',
    ]);
    // Force un updated_at ancien (Eloquent écrase updated_at à la création).
    $delivery->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();

    Queue::fake();
    $this->artisan('whatsapp:retry-skipped', ['--hours' => 2])
        ->assertSuccessful()
        ->expectsOutput('1 livraison(s) WhatsApp Skipped re-dispatchée(s) vers le queue.');

    $delivery->refresh();
    expect($delivery->status)->toBe(WhatsAppDeliveryStatus::Pending)
        ->and($delivery->last_error)->toBeNull();

    Queue::assertPushed(SendReservationWhatsAppNotificationJob::class);
});

it('does not re-dispatch recent Skipped deliveries via whatsapp:retry-skipped', function (): void {
    $scenario = createWhatsAppScenario(phone: '+2290197421860');
    $delivery = WhatsAppDelivery::query()->create([
        'uuid' => (string) Str::uuid(),
        'ticket_id' => $scenario['ticket']->id,
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'waha',
        'recipient_phone' => '+2290197421860',
        'status' => WhatsAppDeliveryStatus::Skipped,
        'last_error' => 'WhatsApp a temporairement restreint l\'envoi (code 463).',
    ]);

    Queue::fake();
    $this->artisan('whatsapp:retry-skipped', ['--hours' => 2])
        ->assertSuccessful()
        ->expectsOutput('Aucune livraison WhatsApp Skipped à relancer.');

    $delivery->refresh();
    expect($delivery->status)->toBe(WhatsAppDeliveryStatus::Skipped);
    Queue::assertNotPushed(SendReservationWhatsAppNotificationJob::class);
});

/** @return array<string, mixed> */
function createWhatsAppScenario(?string $phone, ?string $userPhone = '+22901000000'): array
{
    $user = User::factory()->create(['name' => 'Agent WA', 'phone' => $userPhone]);

    $line = Line::query()->firstOrCreate(
        ['code' => 'WA-L1'],
        ['name' => 'Cotonou - Parakou WA', 'countries_crossed' => ['BJ'], 'distance_km' => 450, 'estimated_duration_minutes' => 360, 'status' => 'active'],
    );

    $originPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'WA Origin Physical'],
        ['city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active'],
    );
    $origin = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 1],
        ['physical_stop_id' => $originPhysical->id, 'name' => 'WA Origin', 'city' => 'Cotonou', 'country' => 'BJ', 'km_from_origin' => 0, 'status' => 'active'],
    );
    $destinationPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'WA Dest Physical'],
        ['city' => 'Parakou', 'country' => 'BJ', 'status' => 'active'],
    );
    $destination = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 2],
        ['physical_stop_id' => $destinationPhysical->id, 'name' => 'WA Dest', 'city' => 'Parakou', 'country' => 'BJ', 'km_from_origin' => 450, 'status' => 'active'],
    );

    $bus = Bus::query()->firstOrCreate(
        ['registration_number' => 'WA-BUS-001'],
        ['brand' => 'KORA', 'model' => 'Coach WA', 'seat_count' => 12, 'status' => 'active'],
    );

    $seat = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '1A'],
        ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
    );

    $departure = Departure::query()->firstOrCreate(
        ['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-09-10', 'departure_time' => '07:00'],
        ['estimated_arrival_at' => Carbon::parse('2026-09-10 13:00'), 'status' => 'scheduled'],
    );

    $reservation = Reservation::create([
        'created_by_user_id' => $user->id,
        'payer_user_id' => $user->id,
        'payer_type' => PayerType::Personal,
        'status' => ReservationStatus::Ticketed,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'counter',
        'expires_at' => now()->addMinutes(15),
    ]);

    $passenger = ReservationPassenger::create([
        'reservation_id' => $reservation->id,
        'first_name' => 'Aïcha',
        'last_name' => 'TEST',
        'phone' => $phone ?? '',
        'nationality' => 'BJ',
        'passenger_index' => 0,
    ]);

    ReservationSegment::create([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'departure_id' => $departure->id,
        'seat_id' => $seat->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'origin_stop_order' => $origin->stop_order,
        'destination_stop_order' => $destination->stop_order,
        'leg_order' => 0,
        'status' => ReservationSegmentStatus::Ticketed,
    ]);

    $payment = Payment::create([
        'reservation_id' => $reservation->id,
        'method' => 'cash',
        'provider' => 'cash',
        'provider_reference' => 'CASH-WA-'.$reservation->id,
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Confirmed,
        'payer_phone' => $phone,
        'confirmed_at' => now(),
    ]);

    $ticket = Ticket::create([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'QR-WA-'.$reservation->id,
        'short_code' => 'WA-'.Str::random(8),
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    return compact('user', 'reservation', 'ticket', 'passenger', 'payment');
}
