import { ArrowRight, CheckCircle2, Clock3, ShieldCheck } from 'lucide-react';
import type { SaleDeparture, SaleQuote, SaleReservation } from './sale-types';
import {
    formatDate,
    formatMoney,
    formatRemainingTime,
    formatTime,
} from './sale-utils';

type Props = {
    departure: SaleDeparture | null;
    selectedSeatCodes: string[];
    quote: SaleQuote | null;
    reservation: SaleReservation | null;
    processing: boolean;
    onQuote: () => void;
    onHold: () => void;
};

export function SaleSummaryCard({
    departure,
    selectedSeatCodes,
    quote,
    reservation,
    processing,
    onQuote,
    onHold,
}: Props) {
    if (!departure) {
        return (
            <aside className="sticky top-[6rem] h-fit rounded-[1.15rem] border border-dashed border-border bg-card p-6 text-center text-[0.75rem] text-muted-foreground">
                Sélectionnez un départ pour préparer la vente.
            </aside>
        );
    }

    const amount =
        quote?.totalAmount ??
        (departure.farePreview?.final_amount ?? 0) * selectedSeatCodes.length;
    const currency =
        quote?.currency ?? departure.farePreview?.currency ?? 'XOF';
    const canQuote = selectedSeatCodes.length > 0;
    const canHold =
        quote !== null && selectedSeatCodes.length === quote.passengerCount;
    const shouldHold = quote !== null && canHold;

    return (
        <aside className="sticky top-[6rem] h-fit overflow-hidden rounded-[1.15rem] border border-border bg-card shadow-[0_20px_55px_rgba(0,29,78,0.1)]">
            <div className="relative overflow-hidden bg-[var(--kora)] p-5 text-white">
                <span className="relative block text-[0.625rem] font-bold text-white/55">
                    Départ sélectionné
                </span>
                <h2 className="relative mt-1.5 text-[1.05rem] font-extrabold tracking-[-0.035em]">
                    {departure.originStop.city} →{' '}
                    {departure.destinationStop.city}
                </h2>
                <p className="relative mt-1 text-[0.6875rem] font-bold text-gold">
                    {formatDate(departure.departureDate)} ·{' '}
                    {formatTime(departure.departureTime)}
                </p>
            </div>
            <div className="p-5">
                <div className="flex items-center justify-between gap-4 border-b border-border py-3 text-[0.6875rem] text-muted-foreground">
                    <span>Référence</span>
                    <strong className="text-foreground">
                        {departure.line.code}
                    </strong>
                </div>
                <div className="flex items-center justify-between gap-4 border-b border-border py-3 text-[0.6875rem] text-muted-foreground">
                    <span>Places</span>
                    <strong className="text-right text-foreground">
                        {selectedSeatCodes.length
                            ? selectedSeatCodes.join(', ')
                            : 'À choisir'}
                    </strong>
                </div>
                <div className="flex items-end justify-between gap-4 py-4 text-[0.75rem] font-extrabold text-foreground">
                    <span>Total</span>
                    <strong className="text-[1.4rem] font-black tracking-[-0.06em]">
                        {amount ? formatMoney(amount, currency) : '—'}
                    </strong>
                </div>
                {reservation ? (
                    <div className="rounded-[0.8rem] bg-digital/10 p-3 text-[0.6875rem] text-digital">
                        <div className="flex items-center gap-2 font-black">
                            <CheckCircle2 className="size-4" />
                            Places bloquées
                        </div>
                        <p className="mt-1">
                            Expiration dans{' '}
                            {formatRemainingTime(reservation.expiresAt)}.
                        </p>
                    </div>
                ) : (
                    <>
                        {quote && (
                            <div className="mb-3 flex items-center gap-2 rounded-[0.7rem] bg-gold/10 p-2.5 text-[0.625rem] font-bold text-foreground">
                                <Clock3 className="size-3.5 text-gold" />
                                Devis valable encore{' '}
                                {formatRemainingTime(quote.expiresAt)}
                            </div>
                        )}
                        <button
                            type="button"
                            disabled={!canQuote || processing}
                            onClick={shouldHold ? onHold : onQuote}
                            className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-[0.75rem] bg-gold px-4 text-[0.75rem] font-black text-[var(--kora)] transition hover:-translate-y-0.5 hover:bg-[#ffc329] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing ? (
                                'Traitement…'
                            ) : shouldHold ? (
                                <>
                                    <ShieldCheck className="size-4" />
                                    Bloquer les places
                                </>
                            ) : (
                                <>
                                    {quote
                                        ? 'Actualiser le devis'
                                        : 'Calculer le devis'}{' '}
                                    <ArrowRight className="size-4" />
                                </>
                            )}
                        </button>
                        {quote && !canHold && (
                            <p className="mt-2 text-center text-[0.625rem] text-muted-foreground">
                                Sélectionnez exactement {quote.passengerCount}{' '}
                                place{quote.passengerCount > 1 ? 's' : ''}.
                            </p>
                        )}
                    </>
                )}
            </div>
        </aside>
    );
}
