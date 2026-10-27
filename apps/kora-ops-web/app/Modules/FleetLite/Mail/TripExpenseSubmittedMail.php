<?php

namespace App\Modules\FleetLite\Mail;

use App\Models\User;
use App\Modules\Expenses\Models\Expense;
use App\Modules\FleetLite\Data\OdometerReadingKind;
use App\Modules\FleetLite\Data\TripExpenseKind;
use App\Modules\FleetLite\Models\DepartureOdometerReading;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * E-mail détaillé « dépense de voyage à valider » (carnet de bord OPS
 * Mobile) : montant, détails terrain (litres, prix au litre, station,
 * poste, pays, compteur), voyage (ligne, bus, équipage, kilométrage),
 * cumul des dépenses du voyage et lien direct vers la validation sur OPS
 * Web. La photo du ticket est jointe si disponible.
 *
 * Envoyé après validation de la transaction (afterCommit) : jamais d'e-mail
 * pour une saisie annulée.
 */
class TripExpenseSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private ?Expense $loaded = null;

    public function __construct(public int $expenseId)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $expense = $this->expense();
        $kind = $expense->tripDetails?->kind;

        return new Envelope(subject: sprintf(
            '[KORA OPS] Dépense de voyage à valider — %s %s XOF — %s',
            $kind?->label() ?? 'Dépense',
            $this->money((int) $expense->amount),
            $expense->bus?->registration_number ?? $expense->reference,
        ));
    }

    public function content(): Content
    {
        $expense = $this->expense();
        $details = $expense->tripDetails;
        $departure = $expense->departure;
        $submitter = $details?->submitter ?? $expense->creator;
        $timezone = 'Africa/Porto-Novo';

        $readings = $departure === null
            ? collect()
            : DepartureOdometerReading::query()->where('departure_id', $departure->getKey())->get()
                ->keyBy(fn (DepartureOdometerReading $reading): string => $reading->kind->value);
        $startKm = $readings->get(OdometerReadingKind::Start->value)?->value_km;
        $endKm = $readings->get(OdometerReadingKind::End->value)?->value_km;
        $distance = $startKm !== null && $endKm !== null ? $endKm - $startKm : null;

        $tripExpenses = $departure === null
            ? collect()
            : Expense::query()
                ->with(['tripDetails'])
                ->where('departure_id', $departure->getKey())
                ->whereHas('tripDetails')
                ->whereIn('status', ['pending', 'approved'])
                ->get();

        $totals = [];
        foreach (TripExpenseKind::cases() as $kind) {
            $items = $tripExpenses->filter(fn (Expense $item): bool => $item->tripDetails?->kind === $kind);
            if ($items->isNotEmpty()) {
                $totals[] = [
                    'label' => $kind->label(),
                    'count' => $items->count(),
                    'amount' => $this->money((int) $items->sum('amount')),
                ];
            }
        }
        $totalLiters = (float) $tripExpenses->filter(fn (Expense $item): bool => $item->tripDetails?->kind === TripExpenseKind::Fuel)
            ->sum(fn (Expense $item): float => (float) $item->tripDetails?->liters);

        return new Content(
            markdown: 'mail.trip-expense-submitted',
            with: [
                'kindLabel' => $details?->kind->label() ?? ($expense->category->label ?? 'Dépense'),
                'isFuel' => $details?->kind === TripExpenseKind::Fuel,
                'reference' => $expense->reference,
                'amount' => $this->money((int) $expense->amount),
                'currency' => $expense->currency,
                'paymentChannel' => $expense->payment_channel->label(),
                'statusLabel' => $expense->status->label(),
                'occurredAt' => $expense->occurred_at->setTimezone($timezone)->format('d/m/Y à H:i'),
                'submittedAt' => $expense->created_at?->setTimezone($timezone)->format('d/m/Y à H:i'),
                'agency' => $expense->agency?->name,
                'category' => $expense->category?->label,
                'liters' => $details?->liters !== null ? number_format((float) $details->liters, 2, ',', ' ') : null,
                'unitPrice' => $details?->unit_price !== null ? $this->money((int) $details->unit_price) : null,
                'station' => $details?->station_label,
                'checkpoint' => $details?->checkpoint_label,
                'country' => $details?->country_code,
                'odometerKm' => $details?->odometer_km !== null ? $this->km((int) $details->odometer_km) : null,
                'submitter' => $submitter?->name,
                'submitterRole' => $this->roleLabel($submitter),
                'submitterPhone' => $submitter?->phone,
                'terminal' => $details?->terminal_p501_id !== null
                    ? TerminalP501::query()->whereKey($details->terminal_p501_id)->value('serial_number')
                    : null,
                'line' => $departure?->line !== null ? trim(($departure->line->code ?? '').' — '.($departure->line->name ?? '')) : null,
                'departureAt' => $departure?->scheduledDepartureAt()->setTimezone($timezone)->format('d/m/Y à H:i'),
                'departureStatus' => match ($departure?->status?->value) {
                    'scheduled' => 'Programmé',
                    'boarding' => 'Embarquement',
                    'departed' => 'En route',
                    'arrived' => 'Arrivé',
                    'cancelled' => 'Annulé',
                    default => null,
                },
                'bus' => $expense->bus !== null ? trim($expense->bus->registration_number.' '.($expense->bus->brand ?? '').' '.($expense->bus->model ?? '')) : null,
                'driver' => $departure?->driver?->name,
                'conductor' => $departure?->conductor?->name,
                'startKm' => $startKm !== null ? $this->km($startKm) : null,
                'endKm' => $endKm !== null ? $this->km($endKm) : null,
                'distance' => $distance !== null ? $this->km($distance) : null,
                'consumption' => $distance !== null && $distance > 0 && $totalLiters > 0
                    ? number_format($totalLiters * 100 / $distance, 1, ',', ' ').' L/100 km'
                    : null,
                'totals' => $totals,
                'tripTotal' => $this->money((int) $tripExpenses->sum('amount')),
                'hasEvidence' => $expense->hasMedia('evidence'),
                'url' => route('expenses.show', $expense->uuid),
                // Les directeurs des opérations n'ont pas l'écran Dépenses :
                // la fiche bus (onglets Carburant / Kilométrage) leur est
                // ouverte (`fleet.view`).
                'busUrl' => $expense->bus !== null ? route('fleet.show', $expense->bus->uuid) : null,
                'description' => $expense->description,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! (bool) config('ops_trip_logs.attach_evidence', true)) {
            return [];
        }

        return $this->expense()->getMedia('evidence')
            ->take(3)
            ->map(fn (Media $media): Attachment => Attachment::fromStorageDisk($media->disk, $media->getPathRelativeToRoot())
                ->as($media->file_name)
                ->withMime((string) $media->mime_type))
            ->values()
            ->all();
    }

    private function expense(): Expense
    {
        return $this->loaded ??= Expense::query()
            ->with(['category', 'agency', 'creator', 'bus', 'line', 'departure.line', 'departure.driver', 'departure.conductor', 'tripDetails.submitter', 'media'])
            ->findOrFail($this->expenseId);
    }

    private function roleLabel(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return match (true) {
            $user->hasRole('conductor') => 'Convoyeur',
            $user->hasRole('bus_driver') => 'Chauffeur',
            default => null,
        };
    }

    private function money(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }

    private function km(int $value): string
    {
        return number_format($value, 0, ',', ' ').' km';
    }
}
