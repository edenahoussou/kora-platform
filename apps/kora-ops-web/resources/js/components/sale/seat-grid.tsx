import { cn } from '@/lib/utils';
import type { SaleSeat } from './sale-types';

type Props = {
    seats: SaleSeat[];
    selectedSeatUuids: string[];
    takenSeatUuids?: string[];
    loading: boolean;
    onToggleSeat: (seat: SaleSeat) => void;
};

type SeatRow = {
    number: number;
    rear: boolean;
    seats: Array<SaleSeat | null>;
};

const standardLetters = ['A', 'B', 'C', 'D'];
const rearLetters = ['A', 'B', 'C', 'D', 'E'];

function buildSeatRows(seats: SaleSeat[]): SeatRow[] {
    const parsed = seats.flatMap((seat) => {
        const match = /^(\d+)([A-E])$/i.exec(seat.code);

        return match
            ? [
                  {
                      seat,
                      number: Number(match[1]),
                      letter: match[2].toUpperCase(),
                  },
              ]
            : [];
    });

    if (parsed.length === 0) {
        return [];
    }

    const rearNumber = parsed.some(({ letter }) => letter === 'E')
        ? Math.max(...parsed.map(({ number }) => number))
        : null;
    const rowNumbers = [...new Set(parsed.map(({ number }) => number))].sort(
        (a, b) => a - b,
    );

    return rowNumbers.map((number) => {
        const rear = rearNumber === number;
        const letters = rear ? rearLetters : standardLetters;

        return {
            number,
            rear,
            seats: letters.map(
                (letter) =>
                    parsed.find(
                        (item) =>
                            item.number === number && item.letter === letter,
                    )?.seat ?? null,
            ),
        };
    });
}

function seatStatus(
    seat: SaleSeat,
    selectedSeatUuids: string[],
    takenSeatUuids: string[],
) {
    if (takenSeatUuids.includes(seat.uuid)) {
        return 'taken';
    }

    return selectedSeatUuids.includes(seat.uuid)
        ? 'selected'
        : seat.availabilityStatus;
}

function expiryLabel(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

export function SeatGrid({
    seats,
    selectedSeatUuids,
    takenSeatUuids = [],
    loading,
    onToggleSeat,
}: Props) {
    const rows = buildSeatRows(seats);

    if (loading) {
        return (
            <div className="bus-cabin seat-grid-loading">
                Chargement des places…
            </div>
        );
    }

    return (
        <div className="bus-cabin">
            <div className="driver-wheel" aria-label="Conducteur">
                ◉
            </div>
            <div className="seat-grid">
                {rows.map((row) => (
                    <div
                        key={row.number}
                        className={cn('seat-row', row.rear && 'rear')}
                    >
                        {row.seats.map((seat, index) => {
                            const letter = (
                                row.rear ? rearLetters : standardLetters
                            )[index];

                            if (!seat) {
                                return (
                                    <span
                                        key={`${row.number}${letter}`}
                                        className={`seat-placeholder seat-position-${letter.toLowerCase()}`}
                                        aria-hidden="true"
                                    />
                                );
                            }

                            const status = seatStatus(
                                seat,
                                selectedSeatUuids,
                                takenSeatUuids,
                            );
                            const disabled =
                                status !== 'available' && status !== 'selected';

                            const ariaStatusLabel =
                                status === 'taken'
                                    ? 'prise par un autre voyageur'
                                    : status;

                            return (
                                <button
                                    key={seat.uuid}
                                    type="button"
                                    disabled={disabled}
                                    onClick={() => onToggleSeat(seat)}
                                    className={cn(
                                        'seat',
                                        `seat-position-${letter.toLowerCase()}`,
                                        status,
                                    )}
                                    aria-label={`${seat.code} · ${ariaStatusLabel}`}
                                >
                                    <span>{seat.code}</span>
                                    {status === 'reserved' && (
                                        <small>
                                            {expiryLabel(seat.expiresAt)}
                                        </small>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                ))}
            </div>
        </div>
    );
}
