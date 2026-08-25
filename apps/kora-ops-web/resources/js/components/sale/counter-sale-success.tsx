import { CheckCircle2, Printer, Send, TicketCheck } from 'lucide-react';
import type { SaleDeparture, SaleReservation } from './sale-types';
import { formatDate, formatMoney, formatTime } from './sale-utils';

export function CounterSaleSuccess({ departure, reservation }: { departure: SaleDeparture | null; reservation: SaleReservation }) {
    const payment = reservation.payment;

    return (
        <div className="grid gap-5">
            <section className="kora-panel overflow-hidden p-8 text-center sm:p-12">
                <span className="mx-auto grid size-16 place-items-center rounded-full bg-digital/12 text-digital"><CheckCircle2 className="size-9" /></span>
                <span className="section-kicker mt-5 block">Vente enregistrée</span>
                <h2 className="mt-2 text-3xl font-extrabold tracking-tight text-foreground">Paiement confirmé</h2>
                <p className="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">La vente est rattachée à la caisse de l’agence et les billets sont prêts. L’impression P501 et l’envoi WhatsApp pourront être lancés séparément.</p>
                <div className="mx-auto mt-7 grid max-w-3xl gap-3 text-left sm:grid-cols-3">
                    <Summary label="Réservation" value={reservation.uuid.slice(0, 8).toUpperCase()} />
                    <Summary label="Montant" value={formatMoney(reservation.totalAmount, reservation.currency)} />
                    <Summary label="Paiement" value={payment?.method === 'mobile_money' ? (payment.network ? networkLabel(payment.network) : 'Mobile Money') : 'Espèces'} />
                </div>
            </section>
            <section className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
                <div className="kora-panel p-6">
                    <div className="mb-5 flex items-center gap-3"><span className="grid size-10 place-items-center rounded-[0.75rem] bg-gold/15 text-[var(--kora)]"><TicketCheck className="size-5" /></span><div><h3 className="font-bold text-foreground">Billets émis</h3><p className="text-xs text-muted-foreground">Un billet par passager.</p></div></div>
                    <div className="grid gap-3">
                        {reservation.tickets.map((ticket) => (
                            <div key={ticket.uuid} className="flex items-center justify-between rounded-[0.8rem] border border-border bg-muted/25 px-4 py-3">
                                <div>
                                    <strong className="block text-sm text-foreground">{ticket.shortCode}</strong>
                                    <span className="text-xs text-muted-foreground">Siège {ticket.seatCode ?? '—'}</span>
                                </div>
                                <span className="text-xs font-semibold text-digital">Émis</span>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="kora-panel p-6">
                    <h3 className="font-bold text-foreground">Détails du trajet</h3>
                    <div className="mt-4 grid gap-3 text-sm">
                        <Summary label="Trajet" value={departure ? `${departure.originStop.city} → ${departure.destinationStop.city}` : '—'} />
                        <Summary label="Départ" value={departure ? `${formatDate(departure.departureDate)} · ${formatTime(departure.departureTime)}` : '—'} />
                        <Summary label="Agence" value={payment?.agencyName ?? '—'} />
                        <Summary label="Agent" value={payment?.verifiedByName ?? '—'} />
                    </div>
                    <div className="mt-6 flex flex-wrap gap-2">
                        <button type="button" disabled className="inline-flex h-10 items-center gap-2 rounded-[0.75rem] border border-border px-4 text-xs font-semibold text-muted-foreground opacity-60"><Printer className="size-4" /> Imprimer · bientôt</button>
                        <button type="button" disabled className="inline-flex h-10 items-center gap-2 rounded-[0.75rem] border border-border px-4 text-xs font-semibold text-muted-foreground opacity-60"><Send className="size-4" /> WhatsApp · bientôt</button>
                    </div>
                </div>
            </section>
        </div>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <span className="block text-xs text-muted-foreground">{label}</span>
            <strong className="block text-sm text-foreground">{value}</strong>
        </div>
    );
}

function networkLabel(
    value: NonNullable<NonNullable<SaleReservation['payment']>['network']>,
) {
    return value === 'mtn_momo'
        ? 'MTN MoMo'
        : value === 'moov_flooz'
          ? 'Moov Money / Flooz'
          : 'Celtis Cash';
}
