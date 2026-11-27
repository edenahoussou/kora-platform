<?php

namespace App\Modules\AssistedBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AssistedBooking\Data\AssistedBookingStatus;
use App\Modules\AssistedBooking\Http\Requests\PrepareAssistedBookingSaleRequest;
use App\Modules\AssistedBooking\Http\Requests\ReassignAssistedBookingRequest;
use App\Modules\AssistedBooking\Http\Requests\RefuseAssistedBookingRequest;
use App\Modules\AssistedBooking\Http\Requests\ReleaseAssistedBookingRequest;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\AssistedBooking\Services\AssistedBookingWorkflow;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\SeatInventory\Actions\BuildCounterSaleSeatAvailabilityAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AssistedBookingController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AssistedBooking::class);
        $query = AssistedBooking::query()
            ->with([
                'reservation.segments.originStop',
                'reservation.segments.destinationStop',
                'reservation.segments.departure.line',
                'handlingAgency',
                'assignedUser',
            ])
            ->latest('created_at');
        if (! $request->user()->can('assisted-bookings.view-network')) {
            $query->where(function ($builder) use ($request): void {
                $builder->where(function ($pending): void {
                    $pending->whereNull('preferred_agency_id')
                        ->where('status', AssistedBookingStatus::Pending->value);
                })->orWhere('preferred_agency_id', $request->user()->agency_id);
            });
        }
        if ($search = $request->string('q')->trim()->value()) {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->whereRaw('LOWER(contact_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(contact_phone) LIKE ?', [$term])
                    ->orWhereHas('reservation', fn ($reservation) => $reservation->whereRaw('LOWER(reference) LIKE ?', [$term]));
            });
        }
        if ($status = $request->string('status')->trim()->value()) {
            if (in_array($status, AssistedBookingStatus::values(), true)) {
                $query->where('status', $status);
            }
        }
        if ($agencyUuid = $request->string('agency')->trim()->value()) {
            $query->whereHas('handlingAgency', fn ($agency) => $agency->where('uuid', $agencyUuid));
        }
        if ($date = $request->date('date')) {
            $query->whereDate('created_at', $date);
        }

        $counts = (clone $query)->reorder()->toBase()
            ->selectRaw("count(*) as total, count(case when status = 'pending' then 1 end) as pending, count(case when status = 'in_progress' then 1 end) as in_progress, count(case when status = 'expired' then 1 end) as expired, count(case when status = 'finalized' then 1 end) as finalized")
            ->first();

        return Inertia::render('assisted-bookings/index', [
            'assistedBookings' => $query->paginate(20)->withQueryString()->through(fn (AssistedBooking $item): array => $this->serializeListItem($item)),
            'counters' => [
                'pending' => (int) ($counts->pending ?? 0),
                'inProgress' => (int) ($counts->in_progress ?? 0),
                'expired' => (int) ($counts->expired ?? 0),
                'finalized' => (int) ($counts->finalized ?? 0),
            ],
            'filters' => $request->only(['q', 'status', 'agency', 'date']),
            'statuses' => AssistedBookingStatus::translatedOptions(),
            'agencies' => $this->eligibleAgencies($request)->map(fn (Agency $agency): array => [
                'uuid' => $agency->uuid,
                'name' => $agency->name,
                'city' => $agency->city,
            ]),
        ]);
    }

    public function show(AssistedBooking $assistedBooking): RedirectResponse
    {
        Gate::authorize('view', $assistedBooking);

        return to_route('assisted-bookings.index', ['open' => $assistedBooking->uuid]);
    }

    public function detail(
        Request $request,
        AssistedBooking $assistedBooking,
        BuildCounterSaleSeatAvailabilityAction $buildSeatAvailability,
    ): JsonResponse {
        Gate::authorize('view', $assistedBooking);

        return response()->json([
            'assistedBooking' => $this->serializeDetail($assistedBooking, $buildSeatAvailability),
            'agencies' => $this->eligibleAgencies($request)->map(fn (Agency $agency): array => [
                'uuid' => $agency->uuid, 'name' => $agency->name, 'city' => $agency->city,
            ]),
            'permissions' => [
                'canClaim' => Gate::allows('claim', $assistedBooking),
                'canRelease' => Gate::allows('release', $assistedBooking),
                'canReassign' => Gate::allows('reassign', $assistedBooking),
                'canRefuse' => Gate::allows('refuse', $assistedBooking),
                'canPrepareSale' => Gate::allows('prepareSale', $assistedBooking),
            ],
        ]);
    }

    public function claim(Request $request, AssistedBooking $assistedBooking, AssistedBookingWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('claim', $assistedBooking);
        $workflow->claim($assistedBooking, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Réservation prise en charge.')]);

        return back();
    }

    public function release(ReleaseAssistedBookingRequest $request, AssistedBooking $assistedBooking, AssistedBookingWorkflow $workflow): RedirectResponse
    {
        $workflow->release($assistedBooking, $request->user(), $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Réservation replacée dans la file.')]);

        return back();
    }

    public function reassign(ReassignAssistedBookingRequest $request, AssistedBooking $assistedBooking, AssistedBookingWorkflow $workflow): RedirectResponse
    {
        $agency = Agency::query()->where('uuid', $request->validated('agency_uuid'))->firstOrFail();
        $workflow->reassign($assistedBooking, $request->user(), $agency, $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Réservation réaffectée.')]);

        return back();
    }

    public function refuse(RefuseAssistedBookingRequest $request, AssistedBooking $assistedBooking, AssistedBookingWorkflow $workflow): RedirectResponse
    {
        $workflow->refuse($assistedBooking, $request->user(), $request->validated('reason_code'), $request->validated('reason_note'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Réservation refusée et clôturée.')]);

        return back();
    }

    public function prepareSale(PrepareAssistedBookingSaleRequest $request, AssistedBooking $assistedBooking, AssistedBookingWorkflow $workflow): RedirectResponse
    {
        $reservation = $workflow->prepareAgencySale($assistedBooking, $request->user(), $request->validated('passengers'));

        return to_route('sale', ['reservation_uuid' => $reservation->uuid]);
    }

    /** @return array<string, mixed> */
    private function serializeListItem(AssistedBooking $item): array
    {
        return [
            'uuid' => $item->uuid,
            'status' => $item->status->value,
            'channel' => $item->handoff_channel->value,
            'reference' => $item->reservation->reference,
            'reservationUuid' => $item->reservation->uuid,
            'reservationStatus' => $item->reservation->status->value,
            'contactName' => $item->contact_name,
            'contactPhone' => $item->contact_phone,
            'handlingAgency' => $item->handlingAgency ? [
                'uuid' => $item->handlingAgency->uuid,
                'name' => $item->handlingAgency->name,
            ] : null,
            'assignee' => $item->assignedUser ? ['uuid' => $item->assignedUser->uuid, 'name' => $item->assignedUser->name] : null,
            'legs' => $item->reservation->segments
                ->unique('leg_order')
                ->sortBy('leg_order')
                ->map(fn ($segment): array => [
                    'legOrder' => $segment->leg_order,
                    'direction' => $segment->direction,
                    'line' => $segment->departure->line->name,
                    'label' => $segment->originStop->name.' → '.$segment->destinationStop->name,
                    'departureAt' => $segment->departure->scheduledDepartureAt()->toIso8601String(),
                ])->values()->all(),
            'originalAmount' => $item->original_total_amount,
            'currentAmount' => $item->current_total_amount,
            'currency' => $item->currency,
            'expiresAt' => $item->expires_at->toIso8601String(),
            'createdAt' => $item->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDetail(AssistedBooking $assistedBooking, BuildCounterSaleSeatAvailabilityAction $buildSeatAvailability): array
    {
        $assistedBooking->load([
            'reservation.segments.departure.line', 'reservation.segments.originStop',
            'reservation.segments.destinationStop', 'reservation.segments.seat',
            'reservation.passengers', 'handlingAgency', 'assignedUser',
        ]);
        $reservation = $assistedBooking->reservation;
        $legs = $reservation->segments->unique('leg_order')->sortBy('leg_order')->map(function ($segment) use ($buildSeatAvailability, $reservation): array {
            $seats = $buildSeatAvailability->handle($segment->departure, $segment->originStop, $segment->destinationStop);

            return [
                'legOrder' => $segment->leg_order,
                'direction' => $segment->direction,
                'departureUuid' => $segment->departure->uuid,
                'canViewDeparture' => Gate::allows('view', $segment->departure),
                'line' => $segment->departure->line->name,
                'label' => $segment->originStop->name.' → '.$segment->destinationStop->name,
                'departureAt' => $segment->departure->scheduledDepartureAt()->toIso8601String(),
                'seats' => $seats->map(fn ($seat): array => [
                    'uuid' => $seat->seat->uuid,
                    'code' => $seat->seat->code,
                    'status' => $seat->availabilityStatus->value,
                    'requestedBy' => $reservation->segments
                        ->where('leg_order', $segment->leg_order)
                        ->where('seat_id', $seat->seat->id)
                        ->pluck('passenger_index')->values()->all(),
                ])->values()->all(),
            ];
        })->values()->all();

        $activities = [];
        foreach (Activity::query()->forSubject($assistedBooking)->with('causer')->oldest()->get() as $activity) {
            $causer = $activity->causer;
            $activities[] = [
                'event' => $activity->event,
                'description' => $activity->description,
                'actor' => $causer instanceof User ? $causer->name : null,
                'properties' => $activity->properties?->toArray() ?? [],
                'createdAt' => $activity->created_at?->toIso8601String(),
            ];
        }

        return array_merge($this->serializeListItem($assistedBooking), [
            'snapshot' => $assistedBooking->snapshot,
            'reasonCode' => $assistedBooking->reason_code,
            'reasonNote' => $assistedBooking->reason_note,
            'ticketIssuanceStatus' => $reservation->ticket_issuance_status->value,
            'passengers' => $reservation->passengers->sortBy('passenger_index')->map(fn ($passenger): array => [
                'uuid' => $passenger->uuid,
                'index' => $passenger->passenger_index,
                'firstName' => $passenger->first_name,
                'lastName' => $passenger->last_name,
                'phone' => $passenger->phone,
            ])->values()->all(),
            'legs' => $legs,
            'history' => $activities,
        ]);
    }

    /** @return Collection<int, Agency> */
    private function eligibleAgencies(Request $request): Collection
    {
        return Agency::query()
            ->where('status', AgencyStatus::Active)
            ->when(! $request->user()->can('assisted-bookings.view-network'), fn ($query) => $query->whereKey($request->user()->agency_id))
            ->orderBy('name')
            ->get();
    }
}
