<?php

namespace App\Modules\Passengers\Actions;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Ticketing\Actions\ResolveTicketAmountAction;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Génère l'archive ZIP des données personnelles d'un passager (portabilité).
 */
class ExportPassengerDataAction
{
    public function __construct(
        private readonly ResolveTicketAmountAction $resolveTicketAmount,
    ) {}

    /**
     * @return array{path: string, checksum: string, scope: array<string, int>}|null
     */
    public function execute(string $phone): ?array
    {
        $passengers = ReservationPassenger::query()
            ->where('phone', $phone)
            ->with([
                'reservation.segments.originStop',
                'reservation.segments.destinationStop',
                'reservation.segments.seat',
                'reservation.segments.departure.line',
                'user',
            ])
            ->orderBy('created_at')
            ->get();

        if ($passengers->isEmpty()) {
            return null;
        }

        $tickets = Ticket::query()
            ->whereIn('reservation_passenger_id', $passengers->pluck('id'))
            ->orderBy('issued_at')
            ->get();

        $reservations = $passengers->pluck('reservation')->filter()->unique('id')->values();
        $documents = $this->documents($passengers);
        $identity = $this->identity($passengers);
        $linkedAccount = $passengers->pluck('user')->filter()->first();

        $profile = [
            'export' => [
                'format_version' => 1,
                'subject' => 'passenger',
                'generated_at' => now()->toIso8601String(),
                'generator' => 'KORA OPS',
            ],
            'identity' => $identity,
            'account' => $linkedAccount !== null ? [
                'uuid' => $linkedAccount->uuid,
                'status' => $linkedAccount->status->value,
                'created_at' => $linkedAccount->created_at?->toIso8601String(),
            ] : null,
            'documents' => $documents,
            'reservations' => $reservations->map(fn ($reservation) => [
                'uuid' => $reservation->uuid,
                'reference' => $reservation->reference,
                'status' => $reservation->status->value,
                'total_amount' => $reservation->total_amount,
                'created_at' => $reservation->created_at?->toIso8601String(),
                'segments' => $reservation->segments->sortBy('leg_order')->map(fn ($segment) => [
                    'origin' => $segment->originStop?->city,
                    'destination' => $segment->destinationStop?->city,
                    'departure_time' => $segment->departure?->departure_time?->toIso8601String(),
                    'line' => $segment->departure?->line?->name,
                    'seat' => $segment->seat?->code,
                ])->values()->all(),
            ])->values()->all(),
            'tickets' => $tickets->map(fn (Ticket $ticket) => [
                'uuid' => $ticket->uuid,
                'short_code' => $ticket->short_code,
                'status' => $ticket->status->value,
                'amount' => $this->resolveTicketAmount->execute($ticket),
                'issued_at' => $ticket->issued_at?->toIso8601String(),
            ])->values()->all(),
        ];

        $zipPath = $this->buildZip($phone, $profile, $documents, $reservations, $tickets);

        return [
            'path' => $zipPath,
            'checksum' => (string) hash_file('sha256', $zipPath),
            'scope' => [
                'records' => $passengers->count(),
                'reservations' => $reservations->count(),
                'tickets' => $tickets->count(),
                'documents' => count($documents),
            ],
        ];
    }

    /**
     * @param  Collection<int, ReservationPassenger>  $passengers
     * @return array<string, mixed>
     */
    private function identity(Collection $passengers): array
    {
        $latest = $passengers->last();

        return [
            'first_name' => $passengers->pluck('first_name')->filter()->last(),
            'last_name' => $passengers->pluck('last_name')->filter()->last(),
            'phone' => $latest?->phone,
            'email' => $passengers->pluck('email')->filter()->last(),
            'nationality' => $passengers->pluck('nationality')->filter()->last(),
            'birth_date' => $latest?->birth_date?->toDateString(),
            'yellow_fever_vaccinated' => $passengers->pluck('yellow_fever_vaccinated')->filter()->last(),
        ];
    }

    /**
     * @param  Collection<int, ReservationPassenger>  $passengers
     * @return array<int, array<string, mixed>>
     */
    private function documents(Collection $passengers): array
    {
        return $passengers
            ->filter(fn (ReservationPassenger $p) => ! empty($p->document_number))
            ->map(fn (ReservationPassenger $p) => [
                'type' => $p->document_type?->value,
                'number' => $p->document_number,
                'nationality' => $p->nationality,
            ])
            ->unique('number')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $documents
     * @param  Collection<int, Reservation>  $reservations
     * @param  Collection<int, Ticket>  $tickets
     */
    private function buildZip(string $phone, array $profile, array $documents, Collection $reservations, Collection $tickets): string
    {
        $dir = storage_path('app/temp/passenger-exports');
        File::ensureDirectoryExists($dir);
        $zipPath = $dir.'/passenger-data-'.Str::uuid().'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('profile.json', json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $zip->addFromString('documents.csv', $this->toCsv(
            ['type', 'number', 'nationality'],
            array_map(fn (array $doc) => [$doc['type'], $doc['number'], $doc['nationality']], $documents),
        ));
        $zip->addFromString('reservations.csv', $this->toCsv(
            ['reference', 'status', 'total_amount', 'created_at'],
            $reservations->map(fn ($r) => [$r->reference, $r->status->value, $r->total_amount, $r->created_at?->toIso8601String()])->all(),
        ));
        $zip->addFromString('tickets.csv', $this->toCsv(
            ['short_code', 'status', 'amount', 'issued_at'],
            $tickets->map(fn (Ticket $t) => [$t->short_code, $t->status->value, $this->resolveTicketAmount->execute($t), $t->issued_at?->toIso8601String()])->all(),
        ));
        $zip->addFromString('LISEZMOI.txt', "Export des données personnelles — KORA BUS\nTéléphone : {$phone}\nGénéré le : ".now()->toDateTimeString()."\n");
        $zip->close();

        return $zipPath;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function toCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);

        return (string) stream_get_contents($handle);
    }
}
