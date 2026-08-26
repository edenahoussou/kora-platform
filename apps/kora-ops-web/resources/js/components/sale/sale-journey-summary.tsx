import {
    Armchair,
    BadgeCheck,
    Banknote,
    BusFront,
    CalendarDays,
    Check,
    CheckCircle2,
    Clock3,
    HandCoins,
    Receipt,
    Route as RouteIcon,
    ShieldCheck,
    Smartphone,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type {
    SaleCashSession,
    SaleDeparture,
    SaleMobileMoneyAccount,
    SalePaymentMethod,
    SaleReservation,
} from './sale-types';
import { formatDate, formatMoney, formatTime } from './sale-utils';
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
    cashSession?: SaleCashSession;
    onOpenCash?: () => void;
    paymentMethod?: SalePaymentMethod;
    cashBreakdown?: CashBreakdown;
    mobileChecklist?: MobileChecklistItem[];
    mobileChecklistDone?: number;
    selectedNetwork?: { label: string; color: string } | null | undefined;
    selectedAccount?: SaleMobileMoneyAccount | null | undefined;
};

export function SaleJourneySummary({
    departure,
    reservation,
    variant,
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

    return (
        <aside className="journey-summary summary">
            <div className="summary-hero">
                <small>Dossier de vente</small>
                <h3>
                    {departure
                        ? `${departure.originStop.city} → ${departure.destinationStop.city}`
                        : 'Vente guichet'}
                </h3>
                <p>
                    {departure
                        ? `${formatDate(departure.departureDate)} · ${formatTime(departure.departureTime)}`
                        : 'Réservation temporaire'}
                </p>
            </div>
            <div className="summary-route">
                <span>
                    <b>{departure?.originStop.city ?? '—'}</b>
                    <small>{departure?.originStop.name ?? 'Origine'}</small>
                </span>
                <i aria-hidden="true">
                    <em>K</em>
                </i>
                <span>
                    <b>{departure?.destinationStop.city ?? '—'}</b>
                    <small>
                        {departure?.destinationStop.name ?? 'Destination'}
                    </small>
                </span>
            </div>
            <SummaryRow icon={CalendarDays} label="Date">
                {departure ? formatDate(departure.departureDate) : '—'}
            </SummaryRow>
            <SummaryRow icon={Clock3} label="Départ">
                {departure ? formatTime(departure.departureTime) : '—'}
            </SummaryRow>
            <SummaryRow icon={Armchair} label="Sièges">
                {reservation.segments
                    .map((segment) => segment.seatCode)
                    .join(', ')}
            </SummaryRow>
            <SummaryRow icon={RouteIcon} label="Ligne">
                {departure?.line.code ?? '—'}
            </SummaryRow>
            <SummaryRow icon={BusFront} label="Véhicule">
                {departure
                    ? [
                          [departure.bus.brand, departure.bus.model]
                              .filter(Boolean)
                              .join(' ') || 'Véhicule',
                          departure.bus.registrationNumber,
                      ].join(' · ')
                    : '—'}
            </SummaryRow>
            <SummaryRow icon={Users} label="Passagers">
                {reservation.passengers.length}
            </SummaryRow>
            <SummaryRow icon={BadgeCheck} label="Service">
                {departure?.farePreview?.service_class_name ??
                    departure?.farePreview?.service_class_code ??
                    'Standard'}
            </SummaryRow>

            {variant === 'payment' &&
            paymentMethod === 'cash' &&
            cashBreakdown ? (
                <CashBreakdownPanel breakdown={cashBreakdown} />
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
                cashSession?.isOpen ? (
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
