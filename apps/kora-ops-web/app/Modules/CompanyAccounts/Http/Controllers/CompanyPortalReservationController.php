<?php

namespace App\Modules\CompanyAccounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;
use App\Modules\CompanyAccounts\Services\CompanyPurchaseHistoryReadService;
use App\Modules\Finance\Services\FinanceExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyPortalReservationController extends Controller
{
    public function __construct(
        private readonly FinanceExportService $exports,
        private readonly CompanyPurchaseHistoryReadService $history,
    ) {}

    public function index(Request $request): Response
    {
        $account = $this->account($request);

        $reservations = $this->history->filteredQuery($request, $account)
            ->paginate(15)
            ->withQueryString();

        $reservations->setCollection($reservations->getCollection()->map(
            fn (Reservation $reservation): array => $this->history->serializeSummary($reservation),
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
                fn (ReservationStatus $status): array => [$status->value => $this->history->statusLabel($status)],
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
            'reservation' => $this->history->serializeDetail($reservation, $transactions),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $account = $this->account($request);
        $month = $request->string('month')->toString() !== '' ? $request->string('month')->toString() : 'toutes-periodes';

        $rows = $this->history->filteredQuery($request, $account)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                $reservation->reference,
                $reservation->created_at?->format('Y-m-d'),
                $this->history->itinerarySummary($reservation)['label'],
                $this->history->itinerarySummary($reservation)['travelDate'] ?? '',
                $reservation->group_size,
                $this->history->statusLabel($reservation->status),
                $reservation->total_amount,
            ]);

        return $this->exports->csv(
            "achats-{$account->reference}-{$month}.csv",
            ['Référence', 'Date d’achat', 'Itinéraire', 'Date de voyage', 'Voyageurs', 'Statut', 'Montant (F CFA)'],
            $rows,
        );
    }

    private function account(Request $request): CompanyAccount
    {
        /** @var CompanyAccount $account */
        $account = $request->attributes->get('companyAccount');

        return $account;
    }
}
