<?php

namespace App\Modules\CompanyAccounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyCreditHold;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;
use App\Modules\Finance\Services\FinanceExportService;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyPortalReservationController extends Controller
{
    public function __construct(private readonly FinanceExportService $exports) {}

    public function index(Request $request): Response
    {
        $account = $this->account($request);

        $reservations = $this->filteredQuery($request, $account)
            ->paginate(15)
            ->withQueryString();

        $reservations->setCollection($reservations->getCollection()->map(
            fn (Reservation $reservation): array => $this->serializeSummary($reservation),
        )->values());

        return Inertia::render('company-portal/reservations', [
            'account' => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'reference' => $account->reference,
            ],
            'reservations' => $reservations,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status', 'all')->toString(),
                'month' => $request->string('month')->toString(),
            ],
            'statuses' => collect(ReservationStatus::cases())->mapWithKeys(
                fn (ReservationStatus $status): array => [$status->value => $this->statusLabel($status)],
            )->all(),
        ]);
    }

    public function show(Request $request, Reservation $reservation): Response
    {
        $account = $this->account($request);
        abort_unless($reservation->company_account_id === $account->id, 404);

        $reservation->load([
            'itineraryQuote.legs.departure.line',
            'itineraryQuote.legs.originStop',
            'itineraryQuote.legs.destinationStop',
            'passengers',
            'segments.departure',
            'segments.originStop',
            'segments.destinationStop',
            'segments.seat',
            'tickets',
            'companyCreditHolds',
        ]);

        $transactions = CompanyCreditTransaction::query()
            ->where('reservation_id', $reservation->id)
            ->orderBy('created_at')
            ->get();

        return Inertia::render('company-portal/reservation-detail', [
            'account' => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'reference' => $account->reference,
            ],
            'reservation' => $this->serializeDetail($reservation, $transactions),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $account = $this->account($request);
        $month = $request->string('month')->toString() !== '' ? $request->string('month')->toString() : 'toutes-periodes';

        $rows = $this->filteredQuery($request, $account)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                $reservation->reference,
                $reservation->created_at?->format('Y-m-d'),
                $this->itinerarySummary($reservation)['label'],
                $this->itinerarySummary($reservation)['travelDate'] ?? '',
                $reservation->group_size,
                $this->statusLabel($reservation->status),
                $reservation->total_amount,
            ]);

        return $this->exports->csv(
            "achats-{$account->reference}-{$month}.csv",
            ['Référence', 'Date d’achat', 'Itinéraire', 'Date de voyage', 'Voyageurs', 'Statut', 'Montant (F CFA)'],
            $rows,
        );
    }

    /** @return Builder<Reservation> */
    private function filteredQuery(Request $request, CompanyAccount $account): Builder
    {
        $status = ReservationStatus::tryFrom($request->string('status')->toString());
        $month = $request->string('month')->toString();

        return Reservation::query()
            ->where('company_account_id', $account->id)
            ->with([
                'itineraryQuote.legs.departure',
                'itineraryQuote.legs.originStop',
                'itineraryQuote.legs.destinationStop',
                'passengers',
            ])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status->value))
            ->when(preg_match('/^\d{4}-\d{2}$/', $month) === 1, function (Builder $query) use ($month): void {
                $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
                $query->whereBetween('created_at', [$start, $start->endOfMonth()]);
            })
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn (Builder $query) => $query
                    ->where('reference', 'like', $term)
                    ->orWhereHas('passengers', fn (Builder $query) => $query
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)));
            })
            ->latest('created_at');
    }

    /** @return array{label: string, travelDate: ?string, roundTrip: bool} */
    private function itinerarySummary(Reservation $reservation): array
    {
        $legs = $reservation->itineraryQuote?->legs?->sortBy('leg_order');
        $first = $legs?->first();
        $last = $legs?->last();

        if ($first === null || $last === null) {
            return ['label' => 'Itinéraire indisponible', 'travelDate' => null, 'roundTrip' => false];
        }

        return [
            'label' => $first->originStop->name.' → '.$last->destinationStop->name,
            'travelDate' => $first->departure?->scheduled_at?->format('Y-m-d'),
            'roundTrip' => $legs->contains(fn ($leg): bool => $leg->direction === 'inbound'),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeSummary(Reservation $reservation): array
    {
        return [
            'uuid' => $reservation->uuid,
            'reference' => $reservation->reference,
            'createdAt' => $reservation->created_at?->toISOString(),
            'status' => $reservation->status->value,
            'statusLabel' => $this->statusLabel($reservation->status),
            'purchaseType' => $reservation->purchase_type->value,
            'passengerCount' => $reservation->group_size,
            'passengers' => $reservation->passengers
                ->map(fn (ReservationPassenger $passenger): string => trim("{$passenger->first_name} {$passenger->last_name}"))
                ->filter()
                ->take(3)
                ->values()
                ->all(),
            'totalAmount' => (int) $reservation->total_amount,
            'currency' => $reservation->currency,
            'itinerary' => $this->itinerarySummary($reservation),
        ];
    }

    /**
     * @param  Collection<int, CompanyCreditTransaction>  $transactions
     * @return array<string, mixed>
     */
    private function serializeDetail(Reservation $reservation, $transactions): array
    {
        $ticketsByPassenger = $reservation->tickets->groupBy('reservation_passenger_id');

        $passengers = $reservation->passengers->map(function (ReservationPassenger $passenger) use ($reservation, $ticketsByPassenger): array {
            /** @var Collection<int, Ticket> $tickets */
            $tickets = $ticketsByPassenger->get($passenger->id, collect());
            $seats = $reservation->segments
                ->where('reservation_passenger_id', $passenger->id)
                ->sortBy('leg_order')
                ->map(fn ($segment): array => [
                    'legOrder' => $segment->leg_order,
                    'direction' => $segment->direction,
                    'origin' => $segment->originStop?->name,
                    'destination' => $segment->destinationStop?->name,
                    'travelDate' => $segment->departure?->scheduled_at?->format('Y-m-d H:i'),
                    'seat' => $segment->seat?->code,
                ])->values()->all();

            return [
                'uuid' => $passenger->uuid,
                'firstName' => $passenger->first_name,
                'lastName' => $passenger->last_name,
                'phone' => $passenger->phone,
                'documentType' => $passenger->document_type instanceof PassengerDocumentType ? $passenger->document_type->value : null,
                'documentNumber' => $passenger->document_number,
                'seats' => $seats,
                'tickets' => $tickets->map(fn (Ticket $ticket): array => [
                    'shortCode' => $ticket->short_code,
                    'status' => $ticket->status->value,
                    'issuedAt' => $ticket->issued_at?->toISOString(),
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            ...$this->serializeSummary($reservation),
            'channel' => $reservation->channel?->value,
            'pricing' => [
                'totalAmount' => (int) $reservation->total_amount,
                'currency' => $reservation->currency,
                'promotionDiscountAmount' => (int) $reservation->promotion_discount_amount,
                'groupDiscount' => $reservation->total_snapshot['group_discount'] ?? null,
                'companyDiscount' => $reservation->total_snapshot['company_discount'] ?? null,
            ],
            'legs' => $reservation->itineraryQuote?->legs?->sortBy('leg_order')->map(fn ($leg): array => [
                'direction' => $leg->direction,
                'origin' => $leg->originStop?->name,
                'originCity' => $leg->originStop?->city,
                'destination' => $leg->destinationStop?->name,
                'destinationCity' => $leg->destinationStop?->city,
                'travelDate' => $leg->departure?->scheduled_at?->format('Y-m-d H:i'),
                'line' => $leg->departure?->line?->name,
            ])->values()->all() ?? [],
            'passengers' => $passengers,
            'credit' => [
                'holds' => $reservation->companyCreditHolds->map(fn (CompanyCreditHold $hold): array => [
                    'amount' => (int) $hold->amount,
                    'status' => $hold->status->value,
                    'periodKey' => $hold->period_key,
                    'convertedAt' => $hold->converted_at !== null ? Carbon::parse($hold->converted_at)->toISOString() : null,
                    'releasedAt' => $hold->released_at !== null ? Carbon::parse($hold->released_at)->toISOString() : null,
                ])->values()->all(),
                'transactions' => $transactions->map(fn (CompanyCreditTransaction $transaction): array => [
                    'type' => $transaction->type->value,
                    'amount' => (int) $transaction->amount,
                    'periodKey' => $transaction->period_key,
                    'reference' => $transaction->reference,
                    'createdAt' => $transaction->created_at?->toISOString(),
                ])->values()->all(),
            ],
        ];
    }

    private function statusLabel(ReservationStatus $status): string
    {
        return match ($status) {
            ReservationStatus::Held => 'En attente',
            ReservationStatus::AssistancePending => 'Assistance en cours',
            ReservationStatus::Confirmed => 'Confirmée',
            ReservationStatus::Paid => 'Payée',
            ReservationStatus::Ticketed => 'Billets émis',
            ReservationStatus::Boarded => 'Embarquée',
            ReservationStatus::Cancelled => 'Annulée',
            ReservationStatus::Expired => 'Expirée',
            ReservationStatus::Unavailable => 'Indisponible',
        };
    }

    private function account(Request $request): CompanyAccount
    {
        /** @var CompanyAccount $account */
        $account = $request->attributes->get('companyAccount');

        return $account;
    }
}
