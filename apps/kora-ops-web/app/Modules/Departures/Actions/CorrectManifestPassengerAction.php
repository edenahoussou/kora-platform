<?php

namespace App\Modules\Departures\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Correction, par le convoyeur, d'informations passager mal saisies
 * (nom, téléphone, pièce / n° de passeport, nationalité, naissance,
 * vaccin fièvre jaune...). Possible avant comme après l'embarquement.
 *
 * Chaque correction réelle est journalisée dans l'activitylog (auteur,
 * terminal, départ, billet, motif, anciennes/nouvelles valeurs). Les
 * données sensibles (n° de pièce, téléphone, email) sont masquées dans
 * ce journal. Une correction sans changement effectif n'écrit rien.
 */
class CorrectManifestPassengerAction
{
    /** Champs dont la valeur n'est jamais écrite en clair dans le journal. */
    private const SENSITIVE = ['document_number', 'phone', 'email'];

    public function __construct(private readonly PassengerIdentityResolver $identity) {}

    /**
     * @param  array<string, mixed>  $changes  clés = colonnes de reservation_passengers
     *
     * @throws ValidationException
     */
    public function execute(
        Departure $departure,
        Ticket $ticket,
        User $actor,
        array $changes,
        ?string $reason = null,
        ?TerminalP501 $terminal = null,
    ): ReservationPassenger {
        return DB::transaction(function () use ($departure, $ticket, $actor, $changes, $reason, $terminal): ReservationPassenger {
            /** @var ReservationPassenger $passenger */
            $passenger = ReservationPassenger::query()
                ->whereKey($ticket->reservation_passenger_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($passenger->anonymized_at !== null) {
                throw ValidationException::withMessages([
                    'passenger' => 'Les informations de ce passager ont été anonymisées et ne peuvent plus être modifiées.',
                ]);
            }

            $new = $this->normalize($changes);

            $docType = array_key_exists('document_type', $new) ? $new['document_type'] : $passenger->document_type?->value;
            $docNumber = array_key_exists('document_number', $new) ? $new['document_number'] : $passenger->document_number;
            if (($docType !== null) !== (filled($docNumber))) {
                // Même règle que la réservation (required_with entre type et n°).
                throw ValidationException::withMessages([
                    $docType !== null ? 'document_number' : 'document_type' => 'Le type de pièce et son numéro vont ensemble : renseignez les deux ou aucun.',
                ]);
            }

            $before = [];
            $after = [];
            foreach ($new as $column => $value) {
                $current = $this->currentValue($passenger, $column);
                if ($current === $value) {
                    continue;
                }
                $before[$column] = $current;
                $after[$column] = $value;
            }

            if ($after === []) {
                return $passenger;
            }

            $passenger->update($after);

            activity()
                ->performedOn($passenger)
                ->causedBy($actor)
                ->withProperties([
                    'reason' => $reason,
                    'ticket_uuid' => $ticket->uuid,
                    'departure_uuid' => $departure->uuid,
                    'terminal_uuid' => $terminal?->uuid,
                    'fields' => array_keys($after),
                    'old' => $this->redact($before),
                    'new' => $this->redact($after),
                ])
                ->log('manifest_passenger_corrected');

            return $passenger->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function normalize(array $changes): array
    {
        $new = [];

        foreach (['first_name', 'last_name'] as $field) {
            if (array_key_exists($field, $changes)) {
                $new[$field] = trim((string) $changes[$field]);
            }
        }

        if (array_key_exists('phone', $changes)) {
            $raw = trim((string) ($changes['phone'] ?? ''));
            if ($raw === '') {
                $new['phone'] = null;
            } else {
                $normalized = $this->identity->normalizePhone($raw);
                if ($normalized === null) {
                    throw ValidationException::withMessages(['phone' => 'Numéro de téléphone invalide.']);
                }
                $new['phone'] = $normalized;
            }
        }

        if (array_key_exists('email', $changes)) {
            $email = trim((string) ($changes['email'] ?? ''));
            $new['email'] = $email === '' ? null : mb_strtolower($email);
        }

        if (array_key_exists('document_type', $changes)) {
            $type = trim((string) ($changes['document_type'] ?? ''));
            $new['document_type'] = $type === '' ? null : PassengerDocumentType::from($type)->value;
        }

        if (array_key_exists('document_number', $changes)) {
            $number = trim((string) ($changes['document_number'] ?? ''));
            $new['document_number'] = $number === '' ? null : $number;
        }

        if (array_key_exists('nationality', $changes)) {
            $nationality = trim((string) ($changes['nationality'] ?? ''));
            $new['nationality'] = $nationality === '' ? null : strtoupper($nationality);
        }

        if (array_key_exists('birth_date', $changes)) {
            $birth = trim((string) ($changes['birth_date'] ?? ''));
            $new['birth_date'] = $birth === '' ? null : $birth;
        }

        if (array_key_exists('yellow_fever_vaccinated', $changes)) {
            $new['yellow_fever_vaccinated'] = $changes['yellow_fever_vaccinated'] === null
                ? null
                : (bool) $changes['yellow_fever_vaccinated'];
        }

        return $new;
    }

    private function currentValue(ReservationPassenger $passenger, string $column): mixed
    {
        return match ($column) {
            'document_type' => $passenger->document_type?->value,
            'birth_date' => $passenger->birth_date?->toDateString(),
            'yellow_fever_vaccinated' => $passenger->yellow_fever_vaccinated,
            default => $passenger->getAttribute($column),
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach (self::SENSITIVE as $field) {
            if (array_key_exists($field, $values) && filled($values[$field])) {
                $values[$field] = str_repeat('•', 5).substr((string) $values[$field], -3);
            }
        }

        return $values;
    }
}
