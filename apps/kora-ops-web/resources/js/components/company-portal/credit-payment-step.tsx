import {
    Armchair,
    ArrowLeft,
    Building2,
    Clock3,
    CreditCard,
    FileText,
    ShieldCheck,
    UserRound,
    Users,
} from 'lucide-react';
import type {
    SaleItineraryQuote,
    SaleReservation,
} from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';
import { useHoldCountdown } from '@/components/sale/use-hold-countdown';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    account: { uuid: string; name: string; reference: string };
    credit: { available: number; monthlyLimit: number; periodKey: string };
    quote: SaleItineraryQuote | null;
    reservation: SaleReservation;
    seatsByPassenger?: Map<
        number,
        Array<{ code: string; direction?: 'outbound' | 'inbound' }>
    >;
    processing: boolean;
    onConfirm: () => void;
    onBack: () => void;
};

const documentTypeLabels: Record<string, string> = {
    national_id: 'Carte nationale',
    biometric_card: 'Carte biométrique',
    passport: 'Passeport',
    consular_card: 'Carte consulaire',
};

export function CreditPaymentStep({
    account,
    credit,
    reservation,
    seatsByPassenger,
    processing,
    onConfirm,
    onBack,
}: Props) {
    const amount = reservation.totalAmount;
    const monthlyLimit = credit.monthlyLimit;
    const currentAvailable = credit.available;
    const availableBeforeHold = currentAvailable + amount;
    const previouslyConsumed = Math.max(0, monthlyLimit - availableBeforeHold);
    const newlyConsumed = amount;
    const remainingAfter = currentAvailable;

    const pctPreviouslyConsumed =
        monthlyLimit > 0 ? (previouslyConsumed / monthlyLimit) * 100 : 0;
    const pctThisOrder =
        monthlyLimit > 0 ? (newlyConsumed / monthlyLimit) * 100 : 0;
    const pctRemaining =
        monthlyLimit > 0
            ? Math.max(0, 100 - pctPreviouslyConsumed - pctThisOrder)
            : 0;
    const totalConsumedPct = Math.min(
        100,
        Math.round(pctPreviouslyConsumed + pctThisOrder),
    );

    const holdCountdown = useHoldCountdown(
        reservation.expiresAt,
        reservation.heldAt,
    );

    return (
        <section className="grid content-start gap-5">
            {/* Header with validation & countdown */}
            <div>
                <div className="finalization-heading">
                    <div>
                        <span className="section-kicker">
                            Étape finale · Imputation crédit entreprise
                        </span>
                        <h2 className="mt-1 text-2xl font-black text-foreground">
                            Confirmer le paiement
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Vérifiez l’impact sur votre crédit mensuel et la
                            liste des voyageurs avant d’émettre les billets.
                        </p>
                    </div>
                    <div
                        className={cn(
                            'finalization-status',
                            holdCountdown.isExpired
                                ? 'border-destructive/40 bg-destructive/10 text-destructive'
                                : 'is-complete',
                        )}
                        aria-live="polite"
                    >
                        <Clock3 className="size-4" />
                        <strong>
                            {holdCountdown.isExpired
                                ? 'Réservation expirée'
                                : `Expire dans ${holdCountdown.label}`}
                        </strong>
                    </div>
                </div>
            </div>

            {/* Credit Impact & Consumption Card */}
            <div className="rounded-2xl border border-border bg-card p-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-gold/10 text-gold">
                            <Building2 className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Compte entreprise
                            </span>
                            <h3 className="text-base font-extrabold text-foreground">
                                {account.name}
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Réf. client :{' '}
                                <span className="font-mono font-medium text-foreground">
                                    {account.reference}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div className="text-right">
                        <span className="text-[11px] font-semibold text-muted-foreground">
                            Consommation plafond
                        </span>
                        <div className="text-sm font-black text-foreground">
                            {totalConsumedPct}%
                        </div>
                    </div>
                </div>

                {/* 3-segment Gauge */}
                <div className="mt-4">
                    <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted/60 p-0.5">
                        {pctPreviouslyConsumed > 0 && (
                            <div
                                style={{ width: `${pctPreviouslyConsumed}%` }}
                                className="h-full rounded-l-full bg-slate-600 transition-all duration-300 dark:bg-slate-400"
                                title={`Déjà consommé : ${formatMoney(previouslyConsumed, reservation.currency)}`}
                            />
                        )}
                        <div
                            style={{ width: `${pctThisOrder}%` }}
                            className={cn(
                                'h-full bg-gold transition-all duration-300',
                                pctPreviouslyConsumed === 0 && 'rounded-l-full',
                                pctRemaining === 0 && 'rounded-r-full',
                            )}
                            title={`Cette commande : ${formatMoney(newlyConsumed, reservation.currency)}`}
                        />
                        {pctRemaining > 0 && (
                            <div
                                style={{ width: `${pctRemaining}%` }}
                                className="h-full rounded-r-full bg-emerald-500/30 transition-all duration-300"
                                title={`Restant disponible : ${formatMoney(remainingAfter, reservation.currency)}`}
                            />
                        )}
                    </div>

                    {/* Legend */}
                    <div className="mt-2.5 flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground">
                        <div className="flex items-center gap-1.5">
                            <span className="size-2 rounded-full bg-slate-600 dark:bg-slate-400" />
                            <span>Déjà consommé</span>
                        </div>
                        <div className="flex items-center gap-1.5 font-semibold text-foreground">
                            <span className="size-2 rounded-full bg-gold" />
                            <span>
                                Cette commande (
                                {formatMoney(amount, reservation.currency)})
                            </span>
                        </div>
                        <div className="flex items-center gap-1.5 font-semibold text-emerald-700 dark:text-emerald-400">
                            <span className="size-2 rounded-full bg-emerald-500" />
                            <span>Restant disponible</span>
                        </div>
                    </div>
                </div>

                {/* 3 Metrics Cards */}
                <div className="mt-4 grid grid-cols-1 gap-2.5 rounded-xl border border-border/60 bg-muted/30 p-3 sm:grid-cols-3">
                    <div className="flex flex-col">
                        <span className="text-[11px] text-muted-foreground">
                            Plafond mensuel
                        </span>
                        <strong className="text-sm font-extrabold text-foreground">
                            {formatMoney(monthlyLimit, reservation.currency)}
                        </strong>
                    </div>
                    <div className="flex flex-col">
                        <span className="text-[11px] font-semibold text-gold">
                            Débit de cet achat
                        </span>
                        <strong className="text-sm font-extrabold text-gold">
                            −{formatMoney(amount, reservation.currency)}
                        </strong>
                    </div>
                    <div className="flex flex-col">
                        <span className="text-[11px] text-muted-foreground">
                            Nouveau disponible
                        </span>
                        <strong className="text-sm font-extrabold text-emerald-600 dark:text-emerald-400">
                            {formatMoney(remainingAfter, reservation.currency)}
                        </strong>
                    </div>
                </div>
            </div>

            {/* Assigned Travelers Recap Card ("Bon à tirer") */}
            <div className="rounded-2xl border border-border bg-card p-5">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <Users className="size-4 text-gold" />
                        <h3 className="text-sm font-extrabold text-foreground">
                            Voyageurs assignés ({reservation.passengers.length})
                        </h3>
                    </div>
                    <button
                        type="button"
                        onClick={onBack}
                        className="text-xs font-semibold text-gold hover:underline"
                    >
                        Modifier
                    </button>
                </div>

                <div className="mt-3 divide-y divide-border/60 rounded-xl border border-border/60 bg-muted/20">
                    {reservation.passengers.map((passenger, idx) => {
                        const pIndex = passenger.passengerIndex ?? idx;
                        const pSeats = seatsByPassenger?.get(pIndex) ?? [];
                        const docLabel = passenger.documentType
                            ? (documentTypeLabels[passenger.documentType] ??
                              passenger.documentType)
                            : null;

                        return (
                            <div
                                key={passenger.uuid || idx}
                                className="flex flex-col gap-2.5 p-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex items-center gap-2.5">
                                    {/* Seat Badges */}
                                    <div className="flex shrink-0 items-center gap-1">
                                        {pSeats.length > 0 ? (
                                            pSeats.map((seat, sIdx) => (
                                                <span
                                                    key={sIdx}
                                                    className="inline-flex items-center gap-1 rounded-lg border border-gold/20 bg-gold/10 px-2 py-1 text-xs font-bold text-gold"
                                                >
                                                    <Armchair className="size-3 shrink-0" />
                                                    <span>
                                                        {pSeats.length > 1 &&
                                                        seat.direction ===
                                                            'outbound'
                                                            ? `${seat.code} · Aller`
                                                            : pSeats.length >
                                                                    1 &&
                                                                seat.direction ===
                                                                    'inbound'
                                                              ? `${seat.code} · Retour`
                                                              : seat.code}
                                                    </span>
                                                </span>
                                            ))
                                        ) : (
                                            <span className="inline-flex items-center gap-1 rounded-lg border border-gold/20 bg-gold/10 px-2 py-1 text-xs font-bold text-gold">
                                                <UserRound className="size-3 shrink-0" />
                                                <span>Siège {pIndex + 1}</span>
                                            </span>
                                        )}
                                    </div>

                                    {/* Identity */}
                                    <div>
                                        <div className="text-sm font-bold text-foreground">
                                            {passenger.firstName}{' '}
                                            {passenger.lastName}
                                        </div>
                                        {passenger.phone && (
                                            <div className="text-xs text-muted-foreground">
                                                {passenger.phone}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Document & Vaccine */}
                                <div className="flex flex-wrap items-center gap-2 text-xs">
                                    {docLabel && passenger.documentNumber ? (
                                        <span className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-1 text-muted-foreground">
                                            <FileText className="size-3 text-muted-foreground" />
                                            <span>
                                                {docLabel} :{' '}
                                                {passenger.documentNumber}
                                            </span>
                                        </span>
                                    ) : null}

                                    {passenger.yellowFeverVaccinated ? (
                                        <span className="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-2 py-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                            <ShieldCheck className="size-3" />
                                            <span>Vacciné</span>
                                        </span>
                                    ) : null}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Actions & Security Reassurance */}
            <div className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button
                        type="button"
                        variant="kora-secondary"
                        size="kora"
                        onClick={onBack}
                        disabled={processing}
                    >
                        <ArrowLeft className="size-4" />
                        Modifier les voyageurs
                    </Button>
                    <Button
                        type="button"
                        variant="kora"
                        size="kora"
                        onClick={onConfirm}
                        disabled={processing || holdCountdown.isExpired}
                    >
                        <CreditCard className="size-4" />
                        {processing
                            ? 'Imputation en cours…'
                            : holdCountdown.isExpired
                              ? 'Réservation expirée'
                              : `Confirmer et débiter ${formatMoney(amount, reservation.currency)}`}
                    </Button>
                </div>

                <div className="flex items-center justify-center gap-2 rounded-xl bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                    <ShieldCheck className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span>
                        Paiement sécurisé par crédit entreprise · Billets émis
                        immédiatement après confirmation
                    </span>
                </div>
            </div>
        </section>
    );
}
