<?php

namespace App\Modules\Passengers\Services;

use App\Models\User;
use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Ticketing\Actions\ResolveTicketAmountAction;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service de consultation et d'agrégation de l'annuaire des passagers.
 */
class PassengerDirectoryService
{
    public function __construct(
        private readonly ResolveTicketAmountAction $resolveTicketAmount,
    ) {}

    /**
     * @param  array{q?: string|null, status?: string|null, route?: string|null, frequency?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters = [], int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $allAggregates = $this->fetchAggregatedPassengers();

        $filtered = $this->applyFilters($allAggregates, $filters);

        $total = $filtered->count();
        $items = $filtered->forPage($page, $perPage)->values();

        // Enrichit les passagers de la page courante avec les détails calculés
        $enrichedItems = $items->map(fn (array $item) => $this->enrichPassengerSummary($item));

        return new LengthAwarePaginator(
            $enrichedItems,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    /**
     * Retourne la fiche détaillée complète d'un passager par son numéro de téléphone.
     *
     * @return array<string, mixed>|null
     */
    public function findByPhone(string $phone): ?array
    {
        $cleanPhone = trim($phone);
        $passengers = ReservationPassenger::query()
            ->where('phone', $cleanPhone)
            ->with([
                'reservation.segments.originStop',
                'reservation.segments.destinationStop',
                'reservation.segments.seat',
                'reservation.segments.departure.line',
                'user',
            ])
            ->get();

        if ($passengers->isEmpty()) {
            return null;
        }

        $summary = $this->buildAggregateFromCollection($cleanPhone, $passengers);

        return $this->enrichPassengerDetail($summary, $passengers);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchAggregatedPassengers(): Collection
    {
        $rows = DB::table('reservation_passengers')
            ->selectRaw('
                phone,
                MAX(first_name) as first_name,
                MAX(last_name) as last_name,
                MAX(email) as email,
                MAX(nationality) as nationality,
                MAX(document_type) as document_type,
                MAX(document_number) as document_number,
                MAX(birth_date) as birth_date,
                MAX(user_id) as user_id,
                MAX(yellow_fever_vaccinated) as yellow_fever_vaccinated,
                COUNT(DISTINCT reservation_id) as reservations_count,
                COUNT(DISTINCT id) as records_count,
                MAX(created_at) as last_seen_at,
                MIN(created_at) as first_seen_at
            ')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->orderByDesc('last_seen_at')
            ->get();

        /** @var Collection<int, array<string, mixed>> $collection */
        $collection = collect($rows)->map(fn ($row): array => (array) $row);

        return $collection;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $passengers
     * @param  array{q?: string|null, status?: string|null, route?: string|null, frequency?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $passengers, array $filters): Collection
    {
        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $status = trim((string) ($filters['status'] ?? 'all'));
        $route = trim((string) ($filters['route'] ?? 'all'));
        $frequency = trim((string) ($filters['frequency'] ?? 'all'));

        return $passengers->filter(function (array $passenger) use ($query, $status, $frequency): bool {
            if ($query !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $passenger['first_name'] ?? '',
                    $passenger['last_name'] ?? '',
                    $passenger['phone'] ?? '',
                    $passenger['email'] ?? '',
                    $passenger['document_number'] ?? '',
                ]));

                if (! str_contains($haystack, $query)) {
                    return false;
                }
            }

            if ($frequency === 'frequent' && (int) ($passenger['reservations_count'] ?? 0) < 5) {
                return false;
            }
            if ($frequency === 'occasional' && ((int) ($passenger['reservations_count'] ?? 0) < 2 || (int) ($passenger['reservations_count'] ?? 0) >= 5)) {
                return false;
            }
            if ($frequency === 'new' && (int) ($passenger['reservations_count'] ?? 0) > 1) {
                return false;
            }

            $hasApp = ! empty($passenger['user_id']);
            $isComplete = ! empty($passenger['nationality']) && ! empty($passenger['document_number']);

            if ($status === 'app' && ! $hasApp) {
                return false;
            }
            if ($status === 'to_verify' && $isComplete) {
                return false;
            }
            if ($status === 'active' && ! $isComplete && ! $hasApp && (int) ($passenger['reservations_count'] ?? 0) === 0) {
                return false;
            }

            return true;
        });
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function enrichPassengerSummary(array $summary): array
    {
        $phone = $summary['phone'];
        $passengerIds = DB::table('reservation_passengers')
            ->where('phone', $phone)
            ->pluck('id');

        $tickets = Ticket::query()
            ->whereIn('reservation_passenger_id', $passengerIds)
            ->with([
                'reservation.segments.originStop',
                'reservation.segments.destinationStop',
                'reservation.segments.seat',
            ])
            ->orderByDesc('issued_at')
            ->get();

        $ticketsCount = $tickets->count();
        $totalSpend = 0;

        foreach ($tickets as $ticket) {
            $amount = $this->resolveTicketAmount->execute($ticket);
            if ($amount === 0 && $ticket->reservation !== null) {
                $amount = (int) ($ticket->reservation->total_amount ?? 0);
            }
            $totalSpend += $amount;
        }

        $latestTicket = $tickets->first();
        $latestSegment = $latestTicket?->reservation?->segments?->sortBy('leg_order')->first();
        $lastSegment = $latestTicket?->reservation?->segments?->sortBy('leg_order')->last();

        $lastRoute = null;
        if ($latestSegment?->originStop !== null && $lastSegment?->destinationStop !== null) {
            $lastRoute = $latestSegment->originStop->city.' → '.$lastSegment->destinationStop->city;
        }

        $hasAccount = ! empty($summary['user_id']) || User::query()->where('phone', $phone)->exists();
        $isComplete = ! empty($summary['nationality']) && ! empty($summary['document_number']);

        $status = 'active';
        $statusLabel = 'Actif';
        if (! $isComplete && empty($summary['nationality'])) {
            $status = 'to_verify';
            $statusLabel = 'À compléter';
        } elseif ($hasAccount) {
            $statusLabel = 'Compte KORA';
        }

        $firstName = trim((string) ($summary['first_name'] ?? ''));
        $lastName = trim((string) ($summary['last_name'] ?? ''));
        $fullName = trim($firstName.' '.$lastName);
        if ($fullName === '') {
            $fullName = 'Passager inconnu';
        }

        return [
            'phone' => $phone,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'fullName' => $fullName,
            'email' => $summary['email'] ?? null,
            'nationality' => $summary['nationality'] ?? null,
            'nationalityLabel' => $this->resolveCountryName($summary['nationality'] ?? null),
            'documentType' => $summary['document_type'] instanceof PassengerDocumentType ? $summary['document_type']->value : ($summary['document_type'] ?? null),
            'documentTypeLabel' => $this->resolveDocumentLabel($summary['document_type'] ?? null),
            'documentNumber' => $summary['document_number'] ?? null,
            'birthDate' => $summary['birth_date'] ?? null,
            'hasAccount' => $hasAccount,
            'ticketsCount' => $ticketsCount > 0 ? $ticketsCount : (int) $summary['reservations_count'],
            'totalSpend' => $totalSpend,
            'totalSpendLabel' => number_format($totalSpend, 0, ',', ' ').' FCFA',
            'lastRoute' => $lastRoute,
            'lastSeenAt' => $summary['last_seen_at'] ? Carbon::parse($summary['last_seen_at'])->format('d/m/Y') : null,
            'lastSeenRelative' => $summary['last_seen_at'] ? Carbon::parse($summary['last_seen_at'])->diffForHumans() : null,
            'status' => $status,
            'statusLabel' => $statusLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  Collection<int, ReservationPassenger>  $passengers
     * @return array<string, mixed>
     */
    private function enrichPassengerDetail(array $summary, Collection $passengers): array
    {
        $enriched = $this->enrichPassengerSummary($summary);
        $passengerIds = $passengers->pluck('id');

        $tickets = Ticket::query()
            ->whereIn('reservation_passenger_id', $passengerIds)
            ->with([
                'reservation.segments.originStop',
                'reservation.segments.destinationStop',
                'reservation.segments.seat',
                'reservation.segments.departure.line',
            ])
            ->orderByDesc('issued_at')
            ->limit(20)
            ->get();

        $ticketsSerialized = $tickets->map(function (Ticket $ticket): array {
            $segments = $ticket->reservation?->segments?->sortBy('leg_order') ?? collect();
            $firstSegment = $segments->first();
            $lastSegment = $segments->last();
            $originCity = $firstSegment?->originStop->city ?? '—';
            $destinationCity = $lastSegment?->destinationStop->city ?? '—';

            $amount = $this->resolveTicketAmount->execute($ticket);
            if ($amount === 0 && $ticket->reservation !== null) {
                $amount = (int) ($ticket->reservation->total_amount ?? 0);
            }

            return [
                'uuid' => $ticket->uuid,
                'shortCode' => $ticket->short_code,
                'route' => $originCity.' → '.$destinationCity,
                'departureTime' => $firstSegment?->departure?->departure_time?->format('d/m/Y H:i') ?? '—',
                'lineCode' => $firstSegment?->departure?->line->code ?? 'Ligne',
                'seatCode' => $firstSegment?->seat->code ?? '—',
                'amount' => $amount,
                'amountLabel' => number_format($amount, 0, ',', ' ').' FCFA',
                'status' => $ticket->status->value,
                'statusLabel' => $this->ticketStatusLabel($ticket->status),
                'issuedAt' => $ticket->issued_at?->format('d/m/Y H:i') ?? '—',
            ];
        })->values()->all();

        $enriched['tickets'] = $ticketsSerialized;
        $enriched['documents'] = $passengers->filter(fn ($p) => ! empty($p->document_number))
            ->map(fn ($p) => [
                'type' => $p->document_type->value ?? $p->getRawOriginal('document_type'),
                'typeLabel' => $this->resolveDocumentLabel($p->document_type->value ?? (string) $p->getRawOriginal('document_type')),
                'number' => $p->document_number,
                'nationality' => $p->nationality,
                'country' => $this->resolveCountryName($p->nationality),
            ])
            ->unique('number')
            ->values()
            ->all();

        return $enriched;
    }

    /**
     * @param  Collection<int, ReservationPassenger>  $passengers
     * @return array<string, mixed>
     */
    private function buildAggregateFromCollection(string $phone, Collection $passengers): array
    {
        $first = $passengers->first();

        return [
            'phone' => $phone,
            'first_name' => $passengers->pluck('first_name')->filter()->last() ?? $first?->first_name,
            'last_name' => $passengers->pluck('last_name')->filter()->last() ?? $first?->last_name,
            'email' => $passengers->pluck('email')->filter()->last(),
            'nationality' => $passengers->pluck('nationality')->filter()->last(),
            'document_type' => $passengers->pluck('document_type')->filter()->last(),
            'document_number' => $passengers->pluck('document_number')->filter()->last(),
            'birth_date' => $passengers->pluck('birth_date')->filter()->last(),
            'user_id' => $passengers->pluck('user_id')->filter()->last(),
            'yellow_fever_vaccinated' => $passengers->pluck('yellow_fever_vaccinated')->filter()->last(),
            'reservations_count' => $passengers->pluck('reservation_id')->unique()->count(),
            'last_seen_at' => $passengers->max('created_at'),
            'first_seen_at' => $passengers->min('created_at'),
        ];
    }

    private function resolveCountryName(?string $code): string
    {
        return match (strtoupper(trim((string) $code))) {
            'BJ' => 'Bénin',
            'TG' => 'Togo',
            'CI' => "Côte d'Ivoire",
            'BF' => 'Burkina Faso',
            'NG' => 'Nigéria',
            'GH' => 'Ghana',
            'NE' => 'Niger',
            'ML' => 'Mali',
            'SN' => 'Sénégal',
            default => $code ?: '—',
        };
    }

    private function resolveDocumentLabel(string|PassengerDocumentType|null $type): string
    {
        $value = $type instanceof PassengerDocumentType ? $type->value : (string) $type;

        return match (strtolower(trim($value))) {
            'passport' => 'Passeport',
            'national_id', 'cni' => 'Carte Nationale d’Identité',
            'biometric_card', 'cip' => 'Certificat biométrique / CIP',
            'consular_card' => 'Carte consulaire',
            default => $value !== '' ? ucfirst($value) : 'Non renseigné',
        };
    }

    private function ticketStatusLabel(TicketStatus $status): string
    {
        return match ($status) {
            TicketStatus::Issued => 'Émis (Payé)',
            TicketStatus::Used => 'Utilisé (Voyagé)',
            TicketStatus::Cancelled => 'Annulé',
            TicketStatus::Refunded => 'Remboursé',
        };
    }
}
