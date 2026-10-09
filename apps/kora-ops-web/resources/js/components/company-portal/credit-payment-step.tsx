import { ArrowLeft, Building2, CircleCheck, CreditCard } from 'lucide-react';
import type {
    SaleItineraryQuote,
    SaleReservation,
} from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';

type Props = {
    quote: SaleItineraryQuote | null;
    reservation: SaleReservation;
    creditAvailable: number;
    processing: boolean;
    onConfirm: () => void;
    onBack: () => void;
};

export function CreditPaymentStep({
    quote,
    reservation,
    creditAvailable,
    processing,
    onConfirm,
    onBack,
}: Props) {
    const amount = reservation.totalAmount;
    const creditBeforeHold = creditAvailable + amount;
    const negotiatedDiscount = quote?.companyDiscount?.amount ?? 0;

    return (
        <section className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="grid gap-4">
                <div className="rounded-2xl border border-border bg-card p-5">
                    <div className="flex items-start gap-3">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-gold/10 text-gold">
                            <Building2 className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Paiement sécurisé
                            </span>
                            <h2 className="mt-1 text-lg font-black text-foreground">
                                Débiter le crédit entreprise
                            </h2>
                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                Le payeur et le moyen de paiement sont imposés
                                par le compte.
                            </p>
                        </div>
                    </div>
                    <h3 className="mt-5 text-sm font-extrabold text-foreground">
                        Détail du montant
                    </h3>
                    <div className="mt-4 grid gap-3 text-sm">
                        <AmountRow
                            label="Sous-total"
                            amount={quote?.subtotalAmount ?? amount}
                            currency={reservation.currency}
                        />
                        {negotiatedDiscount > 0 && (
                            <AmountRow
                                label="Remise entreprise"
                                amount={-negotiatedDiscount}
                                currency={reservation.currency}
                                discount
                            />
                        )}
                        <div className="flex items-center justify-between border-t border-border pt-3 text-base">
                            <strong>Total à débiter</strong>
                            <strong>
                                {formatMoney(amount, reservation.currency)}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <aside className="grid content-start gap-4">
                <div className="rounded-2xl border border-gold/30 bg-gold/5 p-5">
                    <span className="section-kicker">Crédit mensuel</span>
                    <div className="mt-4 grid gap-3">
                        <AmountRow
                            label="Disponible avant l’achat"
                            amount={creditBeforeHold}
                            currency={reservation.currency}
                        />
                        <AmountRow
                            label="Après cette réservation"
                            amount={creditAvailable}
                            currency={reservation.currency}
                        />
                    </div>
                    <div className="mt-4 flex items-start gap-2 rounded-xl bg-background/80 p-3 text-xs leading-5 text-muted-foreground">
                        <CircleCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                        Le montant de{' '}
                        {formatMoney(amount, reservation.currency)} est déjà
                        réservé sur le compte.
                    </div>
                </div>
                <div className="grid gap-2">
                    <button
                        type="button"
                        className="btn btn-primary justify-center"
                        onClick={onConfirm}
                        disabled={processing}
                    >
                        <CreditCard className="size-4" />
                        {processing
                            ? 'Débit en cours…'
                            : 'Confirmer et débiter le crédit'}
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost justify-center"
                        onClick={onBack}
                        disabled={processing}
                    >
                        <ArrowLeft className="size-4" />
                        Retour aux voyageurs
                    </button>
                </div>
            </aside>
        </section>
    );
}

function AmountRow({
    label,
    amount,
    currency,
    discount = false,
}: {
    label: string;
    amount: number;
    currency: string;
    discount?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <strong
                className={
                    discount
                        ? 'text-emerald-700 dark:text-emerald-400'
                        : 'text-foreground'
                }
            >
                {discount ? '−' : ''}
                {formatMoney(Math.abs(amount), currency)}
            </strong>
        </div>
    );
}
