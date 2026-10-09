import {
    ArrowRight,
    Banknote,
    Building2,
    Check,
    CheckCircle2,
    Clock3,
    HandCoins,
    Receipt,
    ShieldCheck,
    Smartphone,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type {
    SaleCashSession,
    SaleDeparture,
    SaleItineraryQuote,
    SaleItineraryQuoteLeg,
    SaleMobileMoneyAccount,
    SalePaymentMethod,
    SaleReservation,
    SaleSearch,
} from './sale-types';
import { formatDayMonth, formatIsoTime, formatMoney } from './sale-utils';
import { useHoldCountdown } from './use-hold-countdown';

type CashBreakdown = {
    total: number;
    received: number;
    change: number;
    isSufficient: boolean;
    currency: string;
};

type MobileChecklistItem = {
    label: string;
    done: boolean;
};

type Props = {
    departure: SaleDeparture | null;
    reservation: SaleReservation;
    variant: 'passengers' | 'payment';
    search?: SaleSearch | null;
    itineraryQuote?: SaleItineraryQuote | null;
    cashSession?: SaleCashSession;
    onOpenCash?: () => void;
    paymentMethod?: SalePaymentMethod;
    cashBreakdown?: CashBreakdown;
    mobileChecklist?: MobileChecklistItem[];
    mobileChecklistDone?: number;
    selectedNetwork?: { label: string; color: string } | null | undefined;
    selectedAccount?: SaleMobileMoneyAccount | null | undefined;
};

const DIRECTION_LABEL: Record<string, string> = {
    outbound: 'Aller',
    inbound: 'Retour',
};

type LegPassenger = {
    passengerIndex: number;
    seatCode: string | null;
};

type LegInfo = {
    legOrder: number;
    originStop: string | null;
    destinationStop: string | null;
    originStopCity: string | null;
    destinationStopCity: string | null;
    departureDate: string | null;
    lineCode: string | null;
    busRegistration: string | null;
    departureAt: string | null;
    arrivalAt: string | null;
    amount: number | null;
    currency: string | null;
    passengers: LegPassenger[];
};

type DirectionGroup = {
    direction: 'outbound' | 'inbound';
    legs: LegInfo[];
};

function buildDirectionGroups(
    reservation: SaleReservation,
    itineraryQuote: SaleItineraryQuote | null | undefined,
): DirectionGroup[] {
    const quoteLegsByOrder = new Map<number, SaleItineraryQuoteLeg>();

    if (itineraryQuote) {
        for (const leg of itineraryQuote.legs) {
            quoteLegsByOrder.set(leg.legOrder, leg);
        }
    }

    const directions: Array<'outbound' | 'inbound'> = ['outbound', 'inbound'];
    const groups: DirectionGroup[] = [];

    for (const direction of directions) {
        const segments = reservation.segments.filter(
            (segment) => (segment.direction ?? 'outbound') === direction,
        );

        if (segments.length === 0) {
            continue;
        }

        const legOrders = [
            ...new Set(segments.map((segment) => segment.legOrder ?? 0)),
        ].sort((a, b) => a - b);

        const legs = legOrders.map((legOrder) => {
            const legSegments = segments.filter(
                (segment) => (segment.legOrder ?? 0) === legOrder,
            );
            const first = legSegments[0] ?? segments[0];
            const quoteLeg = quoteLegsByOrder.get(legOrder);

            return {
                legOrder,
                originStop: first?.originStop ?? null,
                destinationStop: first?.destinationStop ?? null,
                originStopCity:
                    first?.originStopCity ?? quoteLeg?.originStop.city ?? null,
                destinationStopCity:
                    first?.destinationStopCity ??
                    quoteLeg?.destinationStop.city ??
                    null,
                departureDate: first?.departureDate ?? null,
                lineCode: first?.lineCode ?? quoteLeg?.line.code ?? null,
                busRegistration: first?.busRegistration ?? null,
                departureAt:
                    first?.departureAt ?? quoteLeg?.departureAt ?? null,
                arrivalAt: first?.arrivalAt ?? quoteLeg?.arrivalAt ?? null,
                amount: quoteLeg?.amount ?? null,
                currency:
                    quoteLeg?.currency ?? itineraryQuote?.currency ?? null,
                passengers: legSegments
                    .map((segment) => ({
                        passengerIndex: segment.passengerIndex ?? 0,
                        seatCode: segment.seatCode ?? null,
                    }))
                    .sort((a, b) => a.passengerIndex - b.passengerIndex),
            };
        });

        groups.push({ direction, legs });
    }

    return groups;
}

export function SaleJourneySummary({
    departure,
    reservation,
    variant,
    search = null,
    itineraryQuote = null,
    cashSession,
    onOpenCash,
    paymentMethod,
    cashBreakdown,
    mobileChecklist,
    mobileChecklistDone,
    selectedNetwork,
    selectedAccount,
}: Props) {
    const holdCountdown = useHoldCountdown(reservation.expiresAt);

    const directionGroups = useMemo(
        () => buildDirectionGroups(reservation, itineraryQuote),
        [reservation, itineraryQuote],
    );

    const isRoundTrip = directionGroups.length > 1;
    const isMultiLeg = directionGroups.some((group) => group.legs.length > 1);
    const allLegs = directionGroups.flatMap((group) => group.legs);
    const firstLeg = allLegs[0];
    const lastLeg = allLegs[allLegs.length - 1];

    // Passenger count: passengers are only persisted at the next step,
    // so derive the count from segment passenger indexes (or fall back
    // to groupSize / passengerAllocations) instead of reservation.passengers.length.
    const passengerCount = useMemo(() => {
        const fromSegments = new Set(
            reservation.segments.map(
                (segment, index) => segment.passengerIndex ?? index,
            ),
        ).size;

        if (fromSegments > 0) {
            return fromSegments;
        }

        return (
            reservation.groupSize ??
            reservation.passengerAllocations.length ??
            reservation.passengers.length
        );
    }, [reservation]);

    // Use search from/to (montée/descente) as the main route cities;
    // fall back to segment cities if search is unavailable.
    const outboundOrigin =
        search?.from ??
        firstLeg?.originStopCity ??
        departure?.originStop.city ??
        null;
    const outboundDestination =
        search?.to ??
        lastLeg?.destinationStopCity ??
        departure?.destinationStop.city ??
        null;
    const returnOrigin = outboundDestination;
    const returnDestination = outboundOrigin;

    const renderDirectionBlock = (group: DirectionGroup) => {
        const isOutbound = group.direction === 'outbound';
        const routeOrigin = isOutbound ? outboundOrigin : returnOrigin;
        const routeDestination = isOutbound
            ? outboundDestination
            : returnDestination;
        const firstLegInGroup = group.legs[0];
        const lastLegInGroup = group.legs[group.legs.length - 1];
        const directionDate = isOutbound
            ? (search?.date ?? firstLegInGroup?.departureDate ?? null)
            : (search?.returnDate ?? firstLegInGroup?.departureDate ?? null);
        const directionDepartTime = firstLegInGroup?.departureAt ?? null;
        const directionArriveTime = lastLegInGroup?.arrivalAt ?? null;

        return (
            <div key={group.direction} className="summary-direction-block">
                <div className="summary-direction-header">
                    <span className="summary-direction-label">
                        {DIRECTION_LABEL[group.direction] ?? group.direction}
                    </span>
                    <span className="summary-direction-date">
                        {directionDate ? formatDayMonth(directionDate) : '—'}
                    </span>
                </div>
                <div className="summary-direction-route">
                    <strong>{routeOrigin ?? '—'}</strong>
                    <ArrowRight className="size-3" />
                    <strong>{routeDestination ?? '—'}</strong>
                </div>
                <div className="summary-direction-times">
                    <span>
                        <Clock3 className="size-3" />
                        {directionDepartTime
                            ? formatIsoTime(directionDepartTime)
                            : '—'}
                    </span>
                    <ArrowRight className="size-3" />
                    <span>
                        {directionArriveTime
                            ? formatIsoTime(directionArriveTime)
                            : '—'}
                    </span>
                </div>

                {isMultiLeg && (
                    <div className="summary-direction-legs">
                        {group.legs.map((leg) => (
                            <div key={leg.legOrder} className="summary-leg">
                                <div className="summary-leg-header">
                                    <strong>{leg.lineCode ?? '—'}</strong>
                                    <span>Jambe {leg.legOrder}</span>
                                </div>
                                <div className="summary-leg-route">
                                    {leg.originStopCity ??
                                        leg.originStop ??
                                        '—'}{' '}
                                    →{' '}
                                    {leg.destinationStopCity ??
                                        leg.destinationStop ??
                                        '—'}
                                </div>
                                <div className="summary-leg-schedule">
                                    {leg.departureAt
                                        ? formatIsoTime(leg.departureAt)
                                        : '—'}
                                    {' → '}
                                    {leg.arrivalAt
                                        ? formatIsoTime(leg.arrivalAt)
                                        : '—'}
                                    {leg.busRegistration
                                        ? ` · ${leg.busRegistration}`
                                        : ''}
                                </div>
                                {leg.amount !== null && leg.currency && (
                                    <div className="summary-leg-price">
                                        {formatMoney(leg.amount, leg.currency)}{' '}
                                        / passager
                                    </div>
                                )}
                                <div className="summary-leg-seats">
                                    {leg.passengers.map((passenger) => (
                                        <span
                                            key={passenger.passengerIndex}
                                            className="summary-leg-seat"
                                        >
                                            <em>
                                                P{passenger.passengerIndex + 1}
                                            </em>
                                            <strong>
                                                {passenger.seatCode ?? '—'}
                                            </strong>
                                        </span>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {!isMultiLeg && firstLegInGroup && (
                    <>
                        {firstLegInGroup.amount !== null &&
                            firstLegInGroup.currency && (
                                <div className="summary-leg-price">
                                    {formatMoney(
                                        firstLegInGroup.amount,
                                        firstLegInGroup.currency,
                                    )}{' '}
                                    / passager
                                </div>
                            )}
                        <div className="summary-leg-seats">
                            {firstLegInGroup.passengers.map((passenger) => (
                                <span
                                    key={passenger.passengerIndex}
                                    className="summary-leg-seat"
                                >
                                    <em>P{passenger.passengerIndex + 1}</em>
                                    <strong>{passenger.seatCode ?? '—'}</strong>
                                </span>
                            ))}
                        </div>
                    </>
                )}
            </div>
        );
    };

    return (
        <aside className="journey-summary summary">
            <div className="summary-hero">
                <small>Réservation pour</small>
                <h3>
                    {outboundOrigin && outboundDestination
                        ? `${outboundOrigin} → ${outboundDestination}`
                        : 'Vente guichet'}
                </h3>
                {isRoundTrip && (
                    <p className="summary-hero-return">
                        <ArrowRight className="size-3" />
                        {returnOrigin} → {returnDestination}
                    </p>
                )}
            </div>

            {directionGroups.map(renderDirectionBlock)}

            <SummaryRow icon={Users} label="Passagers">
                {passengerCount}
                {reservation.purchaseType === 'group' && (
                    <span className="badge badge-group">
                        <Users className="size-3" />
                        Groupe
                    </span>
                )}
            </SummaryRow>
            {reservation.companyAccount && (
                <SummaryRow icon={Building2} label="Payeur">
                    {reservation.companyAccount.name}
                </SummaryRow>
            )}

            {variant === 'payment' &&
            paymentMethod === 'cash' &&
            cashBreakdown ? (
                <CashBreakdownPanel breakdown={cashBreakdown} />
            ) : null}

            {variant === 'payment' &&
                paymentMethod === 'company_credit' &&
                reservation.companyAccount && (
                    <div className="summary-callout success">
                        <div>
                            <Building2 className="size-4" />
                            Crédit entreprise
                        </div>
                        <p>
                            {reservation.companyAccount.name} · Réf{' '}
                            {reservation.companyAccount.reference}
                        </p>
                    </div>
                )}

            {reservation.discounts ? (
                <div className="summary-discounts">
                    <div className="summary-row">
                        <span>Sous-total</span>
                        <span>
                            {formatMoney(
                                reservation.discounts.subtotalAmount,
                                reservation.currency,
                            )}
                        </span>
                    </div>
                    <div className="summary-row summary-discount-row">
                        <span className="discount-label">
                            {reservation.discounts.label ?? 'Remise'}
                        </span>
                        <strong className="is-discount">
                            −
                            {formatMoney(
                                reservation.discounts.totalDiscount,
                                reservation.currency,
                            )}
                        </strong>
                    </div>
                </div>
            ) : null}

            <div className="summary-total">
                <span>Total à encaisser</span>
                <strong>
                    {formatMoney(reservation.totalAmount, reservation.currency)}
                </strong>
            </div>

            {variant === 'payment' &&
            paymentMethod === 'mobile_money' &&
            mobileChecklist ? (
                <MobileMoneyChecklistPanel
                    checklist={mobileChecklist}
                    doneCount={mobileChecklistDone ?? 0}
                    selectedNetwork={selectedNetwork}
                    selectedAccount={selectedAccount}
                />
            ) : null}

            <div
                className={cn(
                    'summary-callout',
                    'hold-countdown',
                    holdCountdown.isUrgent && 'is-urgent',
                    holdCountdown.isExpired && 'is-expired',
                )}
                role="status"
                aria-live="polite"
            >
                <div className="hold-countdown-top">
                    <span className="hold-countdown-icon">
                        <Clock3 className="size-4" />
                    </span>
                    <span className="hold-countdown-copy">
                        <small>Blocage temporaire</small>
                        <strong>
                            {holdCountdown.isExpired
                                ? 'Hold expiré'
                                : holdCountdown.isUrgent
                                  ? 'À finaliser rapidement'
                                  : 'Places sécurisées'}
                        </strong>
                    </span>
                    <time dateTime={reservation.expiresAt ?? undefined}>
                        {holdCountdown.label}
                    </time>
                </div>
                <div className="hold-countdown-track" aria-hidden="true">
                    <span style={{ width: `${holdCountdown.percentage}%` }} />
                </div>
                <p>
                    {holdCountdown.isExpired
                        ? 'Le blocage doit être renouvelé avant de poursuivre.'
                        : holdCountdown.isUrgent
                          ? 'Finalisez les informations ou modifiez les places avant la libération automatique.'
                          : 'Le temps restant est actualisé automatiquement.'}
                </p>
            </div>
            {variant === 'payment' ? (
                paymentMethod === 'company_credit' ? (
                    <div className="summary-callout success">
                        <div>
                            <Building2 className="size-4" />
                            Crédit entreprise
                        </div>
                        <p>
                            Le montant sera imputé au plafond mensuel du compte.
                            Aucun mouvement de caisse.
                        </p>
                    </div>
                ) : cashSession?.isOpen ? (
                    <div className="summary-callout success">
                        <div>
                            <Banknote className="size-4" />
                            Caisse prête
                        </div>
                        <p>La vente sera enregistrée dans la caisse du jour.</p>
                    </div>
                ) : (
                    <div className="summary-callout summary-cash-action">
                        <div>
                            <Banknote className="size-4" />
                            Caisse à ouvrir
                        </div>
                        <p>
                            Une caisse ouverte est nécessaire pour confirmer la
                            vente.
                        </p>
                        <button type="button" onClick={onOpenCash}>
                            Ouvrir la caisse
                        </button>
                    </div>
                )
            ) : (
                <div className="summary-callout success">
                    <div>
                        <ShieldCheck className="size-4" />
                        Places sécurisées
                    </div>
                    <p>Les sièges restent bloqués pendant la saisie.</p>
                </div>
            )}
        </aside>
    );
}

function CashBreakdownPanel({ breakdown }: { breakdown: CashBreakdown }) {
    const { total, received, change, isSufficient, currency } = breakdown;
    const progress = total > 0 ? Math.min(100, (received / total) * 100) : 0;

    return (
        <div className="summary-cash-breakdown">
            <div className="summary-cash-breakdown-head">
                <HandCoins className="size-4" />
                <strong>Espèces</strong>
            </div>
            <div className="summary-cash-breakdown-rows">
                <div className="summary-cash-row">
                    <span>Reçu</span>
                    <strong
                        className={cn(
                            received > 0 && isSufficient && 'is-positive',
                            received > 0 && !isSufficient && 'is-warning',
                        )}
                    >
                        {received > 0 ? formatMoney(received, currency) : '—'}
                    </strong>
                </div>
                <div className="summary-cash-row summary-cash-result">
                    <span>À rendre</span>
                    <strong
                        className={cn(
                            isSufficient && change > 0 && 'is-positive',
                            !isSufficient && received > 0 && 'is-warning',
                        )}
                    >
                        {!isSufficient && received > 0
                            ? `Manque ${formatMoney(total - received, currency)}`
                            : formatMoney(change, currency)}
                    </strong>
                </div>
            </div>
            <div className="summary-cash-progress">
                <span
                    style={{ width: `${progress}%` }}
                    className={cn(isSufficient ? 'is-complete' : 'is-partial')}
                />
            </div>
        </div>
    );
}

function MobileMoneyChecklistPanel({
    checklist,
    doneCount,
    selectedNetwork,
    selectedAccount,
}: {
    checklist: MobileChecklistItem[];
    doneCount: number;
    selectedNetwork: { label: string; color: string } | null | undefined;
    selectedAccount: SaleMobileMoneyAccount | null | undefined;
}) {
    const allDone = doneCount === checklist.length;

    return (
        <div className="summary-mm-checklist">
            <div className="summary-mm-checklist-head">
                <Smartphone className="size-4" />
                <strong>Checklist de vérification</strong>
                <span
                    className={cn(
                        'summary-mm-checklist-count',
                        allDone && 'is-complete',
                    )}
                >
                    {doneCount}/{checklist.length}
                </span>
            </div>
            {selectedNetwork && (
                <div className="summary-mm-network">
                    <span
                        className="summary-mm-network-dot"
                        style={{ background: selectedNetwork.color }}
                    />
                    <span>{selectedNetwork.label}</span>
                </div>
            )}
            {selectedAccount && (
                <div className="summary-mm-account">
                    <Receipt className="size-3.5" />
                    <span>{selectedAccount.accountIdentifier}</span>
                </div>
            )}
            <ul className="summary-mm-list">
                {checklist.map((item) => (
                    <li
                        key={item.label}
                        className={cn(
                            'summary-mm-item',
                            item.done && 'is-done',
                        )}
                    >
                        {item.done ? (
                            <CheckCircle2 className="size-3.5" />
                        ) : (
                            <Check className="is-pending size-3.5" />
                        )}
                        <span>{item.label}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function SummaryRow({
    icon: Icon,
    label,
    children,
}: {
    icon: LucideIcon;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="summary-row">
            <span className="summary-label">
                <Icon className="size-3.5" />
                {label}
            </span>
            <strong>{children}</strong>
        </div>
    );
}
