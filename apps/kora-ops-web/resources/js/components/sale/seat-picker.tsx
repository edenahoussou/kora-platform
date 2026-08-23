import { BusFront, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SaleDeparture, SaleSeat } from './sale-types';

type Props = {
    departure: SaleDeparture;
    seats: SaleSeat[];
    selectedSeatUuids: string[];
    loading: boolean;
    onToggleSeat: (seat: SaleSeat) => void;
    onClose: () => void;
    onConfirm: () => void;
};

function seatStatus(seat: SaleSeat, selectedSeatUuids: string[]) {
    if (selectedSeatUuids.includes(seat.uuid)) {
        return 'selected';
    }

    return seat.availabilityStatus;
}

export function SeatPicker({
    departure,
    seats,
    selectedSeatUuids,
    loading,
    onToggleSeat,
    onClose,
    onConfirm,
}: Props) {
    return (
        <div className="fixed inset-0 z-50 grid place-items-center bg-[rgba(0,29,78,0.72)] p-4 backdrop-blur-md">
            <section
                className="max-h-[min(46rem,calc(100vh-2rem))] w-full max-w-2xl overflow-y-auto rounded-[1.5rem] bg-card shadow-[0_30px_90px_rgba(0,29,78,0.35)]"
                role="dialog"
                aria-modal="true"
                aria-labelledby="seat-picker-title"
            >
                <header className="flex items-start justify-between gap-4 border-b border-border p-5 sm:p-6">
                    <div>
                        <span className="block text-[0.625rem] font-black tracking-[0.15em] text-digital uppercase">
                            {departure.line.code} ·{' '}
                            {departure.bus.model ??
                                departure.bus.brand ??
                                'Autocar'}{' '}
                            · {departure.bus.seatCount} places
                        </span>
                        <h2
                            id="seat-picker-title"
                            className="mt-1.5 text-[1.4rem] font-extrabold tracking-[-0.045em] text-foreground"
                        >
                            Choisir les places
                        </h2>
                        <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                            La disponibilité est vérifiée par segment auprès du
                            serveur.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="grid size-9 shrink-0 place-items-center rounded-[0.7rem] bg-muted text-foreground transition hover:bg-gold"
                        aria-label="Fermer la sélection de places"
                    >
                        <X className="size-4" />
                    </button>
                </header>
                <div className="p-5 sm:p-6">
                    <div className="flex items-center gap-3 rounded-[1rem] bg-[var(--kora)] p-4 text-white">
                        <span className="grid size-10 place-items-center rounded-[0.7rem] bg-gold text-[var(--kora)]">
                            <BusFront className="size-5" />
                        </span>
                        <div>
                            <strong className="block text-[0.8rem]">
                                {departure.originStop.city} →{' '}
                                {departure.destinationStop.city}
                            </strong>
                            <span className="mt-0.5 block text-[0.625rem] text-white/55">
                                {departure.originStop.name} ·{' '}
                                {departure.destinationStop.name}
                            </span>
                        </div>
                    </div>
                    <div className="my-5 flex flex-wrap justify-center gap-x-5 gap-y-2 text-[0.625rem] text-muted-foreground">
                        {[
                            ['bg-card border-border', 'Disponible'],
                            ['bg-gold border-gold', 'Sélectionnée'],
                            ['bg-muted border-border', 'Occupée'],
                            [
                                'bg-[var(--kora)] border-[var(--kora)]',
                                'Bloquée',
                            ],
                        ].map(([className, label]) => (
                            <span
                                key={label}
                                className="inline-flex items-center gap-1.5"
                            >
                                <i
                                    className={cn(
                                        'size-3 rounded-[0.25rem] border',
                                        className,
                                    )}
                                />
                                {label}
                            </span>
                        ))}
                    </div>
                    {loading ? (
                        <div className="grid min-h-64 place-items-center rounded-[4rem_4rem_1.5rem_1.5rem] border-2 border-border bg-muted text-[0.75rem] font-bold text-muted-foreground">
                            Chargement des places…
                        </div>
                    ) : (
                        <div className="mx-auto max-w-[20rem] rounded-[4rem_4rem_1.5rem_1.5rem] border-2 border-border bg-muted px-7 pt-12 pb-6">
                            <div className="mb-5 flex items-center justify-between text-[0.625rem] font-extrabold text-muted-foreground">
                                <span>Avant</span>
                                <span className="grid size-7 place-items-center rounded-full border-2 border-[var(--kora)] text-[var(--kora)]">
                                    ◉
                                </span>
                            </div>
                            <div className="grid grid-cols-4 gap-2.5">
                                {seats.map((seat) => {
                                    const status = seatStatus(
                                        seat,
                                        selectedSeatUuids,
                                    );
                                    const disabled =
                                        status !== 'available' &&
                                        status !== 'selected';

                                    return (
                                        <button
                                            key={seat.uuid}
                                            type="button"
                                            disabled={disabled}
                                            onClick={() => onToggleSeat(seat)}
                                            className={cn(
                                                'relative h-10 rounded-[0.55rem] border text-[0.5625rem] font-black transition',
                                                status === 'available' &&
                                                    'border-border bg-card text-foreground hover:-translate-y-0.5 hover:border-gold',
                                                status === 'selected' &&
                                                    'border-gold bg-gold text-[var(--kora)] shadow-[0_6px_15px_rgba(246,176,0,0.22)]',
                                                status === 'occupied' &&
                                                    'cursor-not-allowed border-border bg-muted text-muted-foreground',
                                                status === 'blocked' &&
                                                    'cursor-not-allowed border-[var(--kora)] bg-[var(--kora)] text-white/80',
                                            )}
                                        >
                                            {seat.code}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                    <div className="mt-5 flex items-center justify-between gap-3">
                        <span className="text-[0.6875rem] text-muted-foreground">
                            <strong className="text-foreground">
                                {selectedSeatUuids.length}
                            </strong>{' '}
                            place{selectedSeatUuids.length > 1 ? 's' : ''}{' '}
                            sélectionnée
                            {selectedSeatUuids.length > 1 ? 's' : ''}
                        </span>
                        <button
                            type="button"
                            onClick={onConfirm}
                            disabled={selectedSeatUuids.length === 0 || loading}
                            className="inline-flex h-10 items-center justify-center rounded-[0.7rem] bg-gold px-4 text-[0.6875rem] font-black text-[var(--kora)] transition hover:bg-[#ffc329] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Valider la sélection
                        </button>
                    </div>
                </div>
            </section>
        </div>
    );
}
