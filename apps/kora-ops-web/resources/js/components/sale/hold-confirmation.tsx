import { CheckCircle2, Clock3, ShieldCheck } from 'lucide-react';
import type { SaleReservation } from './sale-types';
import { formatMoney, formatRemainingTime } from './sale-utils';

export function HoldConfirmation({
    reservation,
}: {
    reservation: SaleReservation;
}) {
    return (
        <section className="overflow-hidden rounded-[1.25rem] border border-digital/20 bg-card shadow-[0_18px_45px_rgba(0,29,78,0.08)]">
            <div className="flex items-start gap-4 bg-digital p-5 text-white sm:p-6">
                <span className="grid size-11 shrink-0 place-items-center rounded-full bg-white text-digital">
                    <CheckCircle2 className="size-6" />
                </span>
                <div>
                    <span className="block text-[0.625rem] font-black tracking-[0.14em] text-white/65 uppercase">
                        Vente guichet
                    </span>
                    <h2 className="mt-1 text-[1.25rem] font-extrabold">
                        Places bloquées avec succès
                    </h2>
                    <p className="mt-1 text-[0.6875rem] text-white/70">
                        La réservation est prête pour le prochain lot de
                        passagers et de paiement.
                    </p>
                </div>
            </div>
            <div className="grid gap-3 p-5 sm:grid-cols-3 sm:p-6">
                <div>
                    <span className="block text-[0.625rem] text-muted-foreground">
                        Réservation
                    </span>
                    <strong className="mt-1 block text-[0.75rem] font-black break-all text-foreground">
                        {reservation.uuid}
                    </strong>
                </div>
                <div>
                    <span className="block text-[0.625rem] text-muted-foreground">
                        Montant
                    </span>
                    <strong className="mt-1 block text-[0.9rem] font-black text-foreground">
                        {formatMoney(
                            reservation.totalAmount,
                            reservation.currency,
                        )}
                    </strong>
                </div>
                <div>
                    <span className="block text-[0.625rem] text-muted-foreground">
                        Expiration
                    </span>
                    <strong className="mt-1 flex items-center gap-1.5 text-[0.9rem] font-black text-gold">
                        <Clock3 className="size-4" />
                        {formatRemainingTime(reservation.expiresAt)}
                    </strong>
                </div>
            </div>
            <div className="flex items-center gap-2 border-t border-border px-5 py-4 text-[0.6875rem] text-muted-foreground sm:px-6">
                <ShieldCheck className="size-4 text-digital" />
                Le paiement et les informations passagers seront ajoutés dans le
                prochain lot.
            </div>
        </section>
    );
}
