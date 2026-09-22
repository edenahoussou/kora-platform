import type { RequestPayload } from '@inertiajs/core';
import { http, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowLeftRight,
    ArrowRight,
    Armchair,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    Clock3,
    HandHelping,
    History,
    Info,
    MessageCircle,
    Phone,
    RefreshCcw,
    Route,
    ShieldAlert,
    ShieldCheck,
    TimerOff,
    UserRoundCheck,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import {
    claim,
    detail,
    prepareSale,
    reassign,
    refuse,
    release,
} from '@/routes/assisted-bookings';

export type QueueLeg = {
    legOrder: number;
    direction: string;
    line: string;
    label: string;
    departureAt: string;
};

type Seat = {
    uuid: string;
    code: string;
    status: string;
    requestedBy: number[];
};

type DetailLeg = QueueLeg & { seats: Seat[] };

type Passenger = {
    uuid: string;
    index: number;
    firstName: string;
    lastName: string;
    phone: string | null;
};

type Detail = {
    assistedBooking: {
        uuid: string;
        status: string;
        channel: string;
        reference: string;
        reservationStatus: string;
        contactName: string;
        contactPhone: string;
        handlingAgency: { uuid: string; name: string } | null;
        assignee: { uuid: string; name: string } | null;
        originalAmount: number;
        currentAmount: number | null;
        currency: string;
        expiresAt: string;
        createdAt: string;
        reasonCode: string | null;
        reasonNote: string | null;
        ticketIssuanceStatus: string;
        passengers: Passenger[];
        legs: DetailLeg[];
        history: {
            event: string;
            actor: string | null;
            createdAt: string;
        }[];
    };
    agencies: { uuid: string; name: string; city: string }[];
    permissions: {
        canClaim: boolean;
        canRelease: boolean;
        canReassign: boolean;
        canRefuse: boolean;
        canPrepareSale: boolean;
    };
};

export const assistedStatusLabels: Record<string, string> = {
    pending: 'En attente',
    in_progress: 'En cours',
    cancelled: 'Reprise en ligne',
    refused: 'Refusée',
    unavailable: 'Indisponible',
    expired: 'Expirée',
    finalized: 'Finalisée',
};

export const assistedStatusTones: Record<
    string,
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive'
> = {
    pending: 'gold',
    in_progress: 'blue',
    finalized: 'navy',
    refused: 'destructive',
    unavailable: 'muted',
    cancelled: 'muted',
    expired: 'muted',
};

const reservationStatusLabels: Record<string, string> = {
    held: 'Pré-réservée',
    assistance_pending: 'Assistance en attente',
    pending: 'En attente',
    confirmed: 'Confirmée',
    paid: 'Payée',
    ticketed: 'Billetterie émise',
    boarded: 'Embarquée',
    cancelled: 'Annulée',
    expired: 'Expirée',
    unavailable: 'Indisponible',
};

const issuanceLabels: Record<string, string> = {
    not_started: 'Non démarrée',
    pending: 'En attente',
    processing: 'En cours',
    issued: 'Billets émis',
    failed: 'Échec d’émission',
};

const channelLabels: Record<string, string> = {
    whatsapp: 'WhatsApp',
    call: 'Téléphone',
};

const reasonLabels: Record<string, string> = {
    customer_request: 'Demande du client',
    payment_not_received: 'Paiement non reçu',
    fraud_risk: 'Risque de fraude',
    insufficient_capacity: 'Capacité insuffisante',
    other: 'Autre',
};

const eventLabels: Record<string, string> = {
    requested: 'Transférée depuis l’application mobile',
    claimed: 'Prise en charge par un agent',
    released: 'Replacée dans la file d’attente',
    reassigned: 'Réaffectée à une autre équipe',
    refused: 'Refusée et clôturée',
    sale_prepared: 'Reprise au guichet préparée',
    resumed_online: 'Reprise en ligne par le client',
    expired: 'Expirée',
    unavailable: 'Marquée indisponible',
    finalized: 'Finalisée',
    cancelled: 'Annulée',
};

function money(amount: number, currency: string): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }).format(new Date(value));
}

function formatTime(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

type DrawerTab = 'booking' | 'treatment' | 'history';

const drawerTabs: { key: DrawerTab; label: string; icon: LucideIcon }[] = [
    { key: 'booking', label: 'Réservation', icon: Route },
    { key: 'treatment', label: 'Traitement', icon: HandHelping },
    { key: 'history', label: 'Historique', icon: History },
];

export function AssistedBookingDrawer({
    uuid,
    onClose,
}: {
    uuid: string;
    onClose: () => void;
}) {
    const [data, setData] = useState<Detail | null>(null);
    const [expiry, setExpiry] = useState<{
        expired: boolean;
        soon: boolean;
    } | null>(null);
    const [loading, setLoading] = useState(true);
    const [tab, setTab] = useState<DrawerTab>('booking');
    const [openTile, setOpenTile] = useState<string | null>(null);
    const [selections, setSelections] = useState<Record<string, string>>({});
    const [reason, setReason] = useState('');
    const [reasonCode, setReasonCode] = useState('customer_request');
    const [agencyUuid, setAgencyUuid] = useState('');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    async function load() {
        try {
            const result = await http.getClient().request({
                url: detail.url(uuid),
                method: 'get',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const parsed = JSON.parse(result.data) as Detail;
            setData(parsed);
            const diff =
                new Date(parsed.assistedBooking.expiresAt).getTime() -
                Date.now();
            setExpiry({
                expired: diff <= 0,
                soon: diff > 0 && diff < 6 * 3600_000,
            });
            setAgencyUuid(
                parsed.assistedBooking.handlingAgency?.uuid ??
                    parsed.agencies[0]?.uuid ??
                    '',
            );
            setSelections(
                Object.fromEntries(
                    parsed.assistedBooking.passengers.flatMap((passenger) =>
                        parsed.assistedBooking.legs.map((leg) => {
                            const requested = leg.seats.find(
                                (seat) =>
                                    seat.requestedBy.includes(
                                        passenger.index,
                                    ) && seat.status === 'available',
                            );

                            return [
                                `${passenger.index}-${leg.legOrder}`,
                                requested?.uuid ?? '',
                            ];
                        }),
                    ),
                ),
            );
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [uuid]);

    const item = data?.assistedBooking;
    const permissions = data?.permissions;

    const post = (url: string, payload: RequestPayload = {}) => {
        setBusy(true);
        router.post(url, payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => load(),
            onError: (formErrors) => setErrors(formErrors),
            onFinish: () => setBusy(false),
        });
    };

    const prepare = () => {
        if (!item) {
            return;
        }

        post(prepareSale.url(item.uuid), {
            passengers: item.passengers.map((passenger) => ({
                passenger_index: passenger.index,
                seat_assignments: item.legs.map((leg) => ({
                    leg_order: leg.legOrder,
                    seat_uuid: selections[`${passenger.index}-${leg.legOrder}`],
                })),
            })),
        });
    };

    const isTerminal = item
        ? [
            'cancelled',
            'refused',
            'unavailable',
            'expired',
            'finalized',
        ].includes(item.status)
        : false;

    return (
        <Sheet
            open
            onOpenChange={(open) => {
                if (!open && !busy) {
                    onClose();
                }
            }}
        >
            <SheetContent className="w-full gap-0 p-0 sm:max-w-[700px] [&>button]:top-5 [&>button]:right-5 [&>button]:z-10 [&>button]:bg-white/10 [&>button]:text-white [&>button]:opacity-90 [&>button]:hover:opacity-100">
                {loading || !item || !permissions ? (
                    <>
                        <SheetTitle className="sr-only">
                            Réservation assistée
                        </SheetTitle>
                        <div className="grid flex-1 place-items-center">
                            <div className="grid justify-items-center gap-3 text-muted-foreground">
                                <span className="size-10 animate-pulse rounded-2xl bg-muted" />
                                <p className="text-xs font-semibold">
                                    Chargement de la réservation…
                                </p>
                            </div>
                        </div>
                    </>
                ) : (
                    <>
                        {/* HERO */}
                        <div className="relative shrink-0 overflow-hidden bg-gradient-to-br from-[var(--kora)] to-[var(--digital)] px-6 pt-6 pb-6">
                            <div
                                aria-hidden
                                className="pointer-events-none absolute -top-20 -right-12 size-52 rounded-full bg-gold/15 blur-3xl"
                            />
                            <span className="relative flex items-center gap-1.5 text-[0.5625rem] font-black tracking-[0.14em] text-gold uppercase">
                                {item.channel === 'whatsapp' ? (
                                    <MessageCircle
                                        size={11}
                                        strokeWidth={2.5}
                                    />
                                ) : (
                                    <Phone size={11} strokeWidth={2.5} />
                                )}
                                Réservation assistée ·{' '}
                                {channelLabels[item.channel] ?? item.channel}
                            </span>
                            <div className="relative mt-3 flex items-end justify-between gap-5">
                                <div className="min-w-0">
                                    <SheetTitle className="font-mono text-[1.375rem] leading-tight font-extrabold tracking-[-0.03em] text-white">
                                        {item.reference}
                                    </SheetTitle>
                                    <SheetDescription className="mt-1.5 text-xs font-medium text-white/70">
                                        <span className="font-semibold text-white">
                                            {item.contactName}
                                        </span>{' '}
                                        · {item.contactPhone} ·{' '}
                                        {item.handlingAgency?.name ?? 'File réseau'}
                                    </SheetDescription>
                                    <div className="mt-3.5 flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center gap-1.5 rounded-md bg-white/10 px-2 py-1 text-[0.625rem] font-extrabold text-white ring-1 ring-white/20">
                                            {assistedStatusLabels[
                                                item.status
                                            ] ?? item.status}
                                        </span>
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[0.625rem] font-extrabold ring-1',
                                                expiry?.expired
                                                    ? 'bg-red-400/15 text-red-200 ring-red-300/25'
                                                    : expiry?.soon
                                                        ? 'bg-gold/15 text-amber-200 ring-gold/30'
                                                        : 'bg-white/10 text-white/80 ring-white/15',
                                            )}
                                        >
                                            <Clock3 size={11} />
                                            {expiry?.expired
                                                ? 'Expirée'
                                                : `Expire ${formatDateTime(item.expiresAt)}`}
                                        </span>
                                    </div>
                                </div>
                                <div className="shrink-0 text-right">
                                    <span className="block text-[0.5625rem] font-bold tracking-[0.1em] text-white/60 uppercase">
                                        Devis
                                    </span>
                                    <span className="block text-[1.375rem] leading-tight font-extrabold text-white tabular-nums">
                                        {money(
                                            item.currentAmount ??
                                            item.originalAmount,
                                            item.currency,
                                        )}
                                    </span>
                                    {item.currentAmount !== null &&
                                        item.currentAmount !==
                                        item.originalAmount && (
                                            <span className="block text-[0.625rem] font-medium text-white/50 tabular-nums">
                                                initial{' '}
                                                {money(
                                                    item.originalAmount,
                                                    item.currency,
                                                )}
                                            </span>
                                        )}
                                </div>
                            </div>
                        </div>

                        {/* TABS */}
                        <div className="flex shrink-0 gap-1 border-b border-border/60 px-6">
                            {drawerTabs.map(({ key, label, icon: Icon }) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setTab(key)}
                                    className={cn(
                                        '-mb-px inline-flex items-center gap-1.5 border-b-2 px-3.5 py-3 text-[0.6875rem] font-extrabold tracking-[0.035em] whitespace-nowrap transition-colors',
                                        tab === key
                                            ? 'border-gold text-foreground'
                                            : 'border-transparent text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    <Icon size={13} strokeWidth={2} />
                                    {label}
                                    {key === 'history' &&
                                        item.history.length > 0 && (
                                            <span className="rounded-full bg-muted px-1.5 py-0.5 text-[0.5625rem] font-bold text-muted-foreground tabular-nums">
                                                {item.history.length}
                                            </span>
                                        )}
                                </button>
                            ))}
                        </div>

                        {/* CONTENT */}
                        <div className="flex-1 overflow-y-auto px-6 py-5">
                            {tab === 'booking' && (
                                <div className="grid gap-5">
                                    {/* ALERTES CONTEXTUELLES */}
                                    {expiry?.expired &&
                                        item.status !== 'expired' && (
                                            <Alert
                                                tone="destructive"
                                                icon={TimerOff}
                                                title="Délai de validité dépassé"
                                            >
                                                Cette réservation a dépassé sa
                                                date d’expiration. Elle sera
                                                marquée expirée lors du prochain
                                                balayage.
                                            </Alert>
                                        )}
                                    {expiry?.soon && (
                                        <Alert
                                            tone="warning"
                                            icon={Clock3}
                                            title="Expiration proche"
                                        >
                                            La réservation expire dans moins de
                                            6 heures. Prenez-la en charge
                                            rapidement pour ne pas perdre le
                                            client.
                                        </Alert>
                                    )}
                                    {item.status === 'pending' && (
                                        <Alert
                                            tone="info"
                                            icon={Info}
                                            title="En attente de prise en charge"
                                        >
                                            Aucun siège n’est bloqué tant que la
                                            réservation n’est pas attribuée à un
                                            agent.
                                        </Alert>
                                    )}
                                    {isTerminal && item.reasonNote && (
                                        <Alert
                                            tone="muted"
                                            icon={ShieldAlert}
                                            title={
                                                reasonLabels[
                                                item.reasonCode ?? ''
                                                ] ?? 'Motif'
                                            }
                                        >
                                            {item.reasonNote}
                                        </Alert>
                                    )}

                                    {/* STRIP DE STATS */}
                                    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                        <StatTile
                                            icon={Users}
                                            label="Passagers"
                                            value={String(
                                                item.passengers.length,
                                            )}
                                        />
                                        <StatTile
                                            icon={Route}
                                            label="Trajets"
                                            value={String(item.legs.length)}
                                        />
                                        <StatTile
                                            icon={
                                                item.channel === 'whatsapp'
                                                    ? MessageCircle
                                                    : Phone
                                            }
                                            label="Canal"
                                            value={
                                                channelLabels[item.channel] ??
                                                item.channel
                                            }
                                        />
                                        <StatTile
                                            icon={Clock3}
                                            label="Validité"
                                            value={
                                                expiry?.expired
                                                    ? 'Expirée'
                                                    : formatDateTime(
                                                        item.expiresAt,
                                                    )
                                            }
                                            tone={
                                                expiry?.expired
                                                    ? 'text-destructive'
                                                    : expiry?.soon
                                                        ? 'text-amber-600 dark:text-amber-300'
                                                        : undefined
                                            }
                                        />
                                    </div>

                                    {/* TRAJETS */}
                                    <section className="grid gap-3">
                                        <h3 className="flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                                            <Route
                                                size={14}
                                                className="text-kora dark:text-gold"
                                            />
                                            {item.legs.length > 1
                                                ? `Trajets (${item.legs.length})`
                                                : 'Trajet'}
                                        </h3>
                                        <div
                                            className={cn(
                                                'grid gap-3',
                                                item.legs.length > 1 &&
                                                'sm:grid-cols-2',
                                            )}
                                        >
                                            {item.legs.map((leg) => {
                                                const [from, to] =
                                                    leg.label.split(' → ');

                                                return (
                                                    <article
                                                        key={leg.legOrder}
                                                        className="grid gap-2.5 rounded-2xl border border-border bg-card p-3.5"
                                                    >
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span
                                                                className={cn(
                                                                    'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.5625rem] font-black tracking-[0.06em] uppercase',
                                                                    leg.direction ===
                                                                        'return'
                                                                        ? 'bg-gold/20 text-kora dark:text-gold'
                                                                        : 'bg-digital/15 text-digital',
                                                                )}
                                                            >
                                                                {leg.direction ===
                                                                    'return' ? (
                                                                    <ArrowLeft
                                                                        size={
                                                                            10
                                                                        }
                                                                        strokeWidth={
                                                                            2.5
                                                                        }
                                                                    />
                                                                ) : (
                                                                    <ArrowRight
                                                                        size={
                                                                            10
                                                                        }
                                                                        strokeWidth={
                                                                            2.5
                                                                        }
                                                                    />
                                                                )}
                                                                {leg.direction ===
                                                                    'return'
                                                                    ? 'Retour'
                                                                    : 'Aller'}
                                                            </span>
                                                            <span className="truncate text-[0.625rem] font-bold text-muted-foreground">
                                                                {leg.line}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="size-1.5 shrink-0 rounded-full bg-gold" />
                                                            <span className="min-w-0 truncate text-xs font-extrabold text-foreground">
                                                                {from}
                                                            </span>
                                                            <span className="h-px flex-1 border-t border-dashed border-border" />
                                                            <span className="size-1.5 shrink-0 rounded-full bg-kora dark:bg-gold" />
                                                            <span className="min-w-0 truncate text-xs font-extrabold text-foreground">
                                                                {to}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <span className="flex items-center gap-1 text-[0.6875rem] font-semibold text-muted-foreground">
                                                                <CalendarDays
                                                                    size={11}
                                                                />
                                                                {formatDate(
                                                                    leg.departureAt,
                                                                )}
                                                            </span>
                                                            <span className="font-mono text-sm font-extrabold text-foreground tabular-nums">
                                                                {formatTime(
                                                                    leg.departureAt,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-1.5 border-t border-border/50 pt-2.5">
                                                            <Armchair
                                                                size={12}
                                                                className="text-muted-foreground"
                                                            />
                                                            {item.passengers.map(
                                                                (passenger) => {
                                                                    const seat =
                                                                        leg.seats.find(
                                                                            (
                                                                                s,
                                                                            ) =>
                                                                                s.requestedBy.includes(
                                                                                    passenger.index,
                                                                                ),
                                                                        );

                                                                    return (
                                                                        <span
                                                                            key={`${leg.legOrder}-${passenger.uuid}`}
                                                                            className="inline-flex items-center gap-1 rounded-md bg-muted/60 px-2 py-1 text-[0.625rem] font-bold text-foreground"
                                                                        >
                                                                            {seat?.code ??
                                                                                '—'}
                                                                            <span className="font-medium text-muted-foreground">
                                                                                {
                                                                                    passenger.firstName
                                                                                }
                                                                            </span>
                                                                        </span>
                                                                    );
                                                                },
                                                            )}
                                                        </div>
                                                    </article>
                                                );
                                            })}
                                        </div>
                                    </section>

                                    {/* PASSAGERS + INFOS */}
                                    <div className="grid items-start gap-3 sm:grid-cols-2">
                                        <section className="grid gap-3 rounded-2xl border border-border bg-card p-4">
                                            <h3 className="flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                                                <Users
                                                    size={14}
                                                    className="text-kora dark:text-gold"
                                                />
                                                Passagers (
                                                {item.passengers.length})
                                            </h3>
                                            <div className="flex flex-wrap gap-1.5">
                                                {item.passengers.map(
                                                    (passenger) => (
                                                        <span
                                                            key={passenger.uuid}
                                                            className="inline-flex items-center gap-1.5 rounded-lg bg-muted/60 py-1 pr-2.5 pl-1 text-[0.6875rem] font-semibold text-foreground"
                                                        >
                                                            <span className="grid size-5 place-items-center rounded-md bg-card text-[0.5625rem] font-black text-kora dark:text-gold">
                                                                {
                                                                    passenger
                                                                        .firstName?.[0]
                                                                }
                                                                {
                                                                    passenger
                                                                        .lastName?.[0]
                                                                }
                                                            </span>
                                                            {
                                                                passenger.firstName
                                                            }{' '}
                                                            {passenger.lastName}
                                                            <span className="text-muted-foreground">
                                                                {
                                                                    passenger.phone
                                                                }
                                                            </span>
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        </section>

                                        <section className="grid grid-cols-2 gap-x-4 gap-y-3 rounded-2xl border border-border bg-muted/30 p-4">
                                            <InfoCell
                                                label="Attributaire"
                                                value={
                                                    item.assignee?.name ??
                                                    'Non attribuée'
                                                }
                                            />
                                            <InfoCell
                                                label="Agence traitante"
                                                value={item.handlingAgency?.name ?? 'File réseau'}
                                            />
                                            <InfoCell
                                                label="Réservation"
                                                value={
                                                    reservationStatusLabels[
                                                    item.reservationStatus
                                                    ] ?? item.reservationStatus
                                                }
                                            />
                                            <InfoCell
                                                label="Billetterie"
                                                value={
                                                    issuanceLabels[
                                                    item
                                                        .ticketIssuanceStatus
                                                    ] ??
                                                    item.ticketIssuanceStatus
                                                }
                                            />
                                            <InfoCell
                                                label="Transférée le"
                                                value={formatDateTime(
                                                    item.createdAt,
                                                )}
                                            />
                                            <InfoCell
                                                label="Montant initial"
                                                value={money(
                                                    item.originalAmount,
                                                    item.currency,
                                                )}
                                            />
                                        </section>
                                    </div>
                                </div>
                            )}

                            {tab === 'treatment' && (
                                <div className="grid items-start gap-3 sm:grid-cols-2">
                                    {permissions.canClaim &&
                                        item.status === 'pending' && (
                                            <ActionTile
                                                icon={UserRoundCheck}
                                                title="Me l'attribuer"
                                                hint="Devis recalculé, aucun siège bloqué"
                                                tone="gold"
                                                open={openTile === 'claim'}
                                                onToggle={() =>
                                                    setOpenTile(
                                                        openTile === 'claim'
                                                            ? null
                                                            : 'claim',
                                                    )
                                                }
                                            >
                                                <p className="text-xs leading-relaxed text-muted-foreground">
                                                    La réservation vous sera
                                                    attribuée. Le devis est
                                                    recalculé à l'attribution,
                                                    sans bloquer de siège.
                                                </p>
                                                <Button
                                                    disabled={busy}
                                                    onClick={() =>
                                                        post(
                                                            claim.url(
                                                                item.uuid,
                                                            ),
                                                        )
                                                    }
                                                    className="bg-gold font-extrabold text-[var(--kora)] hover:bg-gold/90"
                                                >
                                                    <UserRoundCheck className="size-4" />
                                                    Confirmer l'attribution
                                                </Button>
                                            </ActionTile>
                                        )}

                                    {permissions.canPrepareSale && (
                                        <ActionTile
                                            icon={Armchair}
                                            title="Revalider les sièges"
                                            hint={`${item.passengers.length} passager(s) · ${item.legs.length} trajet(s)`}
                                            tone="digital"
                                            open={openTile === 'seats'}
                                            onToggle={() =>
                                                setOpenTile(
                                                    openTile === 'seats'
                                                        ? null
                                                        : 'seats',
                                                )
                                            }
                                        >
                                            <div className="grid gap-3">
                                                {item.passengers.map(
                                                    (passenger) => (
                                                        <div
                                                            key={passenger.uuid}
                                                            className="rounded-xl bg-muted/45 p-3.5"
                                                        >
                                                            <strong className="text-xs font-extrabold">
                                                                {
                                                                    passenger.firstName
                                                                }{' '}
                                                                {
                                                                    passenger.lastName
                                                                }
                                                            </strong>
                                                            <div className="mt-2.5 grid gap-3 sm:grid-cols-2">
                                                                {item.legs.map(
                                                                    (leg) => (
                                                                        <div
                                                                            key={
                                                                                leg.legOrder
                                                                            }
                                                                        >
                                                                            <Label className="text-[0.6875rem]">
                                                                                {
                                                                                    leg.label
                                                                                }
                                                                            </Label>
                                                                            <select
                                                                                value={
                                                                                    selections[
                                                                                    `${passenger.index}-${leg.legOrder}`
                                                                                    ] ??
                                                                                    ''
                                                                                }
                                                                                onChange={(
                                                                                    event,
                                                                                ) =>
                                                                                    setSelections(
                                                                                        (
                                                                                            current,
                                                                                        ) => ({
                                                                                            ...current,
                                                                                            [`${passenger.index}-${leg.legOrder}`]:
                                                                                                event
                                                                                                    .target
                                                                                                    .value,
                                                                                        }),
                                                                                    )
                                                                                }
                                                                                className="mt-1 h-10 w-full rounded-lg border border-border bg-background px-3 text-xs"
                                                                            >
                                                                                <option value="">
                                                                                    Choisir
                                                                                    un
                                                                                    siège
                                                                                </option>
                                                                                {leg.seats
                                                                                    .filter(
                                                                                        (
                                                                                            seat,
                                                                                        ) =>
                                                                                            seat.status ===
                                                                                            'available',
                                                                                    )
                                                                                    .map(
                                                                                        (
                                                                                            seat,
                                                                                        ) => (
                                                                                            <option
                                                                                                key={
                                                                                                    seat.uuid
                                                                                                }
                                                                                                value={
                                                                                                    seat.uuid
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    seat.code
                                                                                                }
                                                                                                {seat.requestedBy.includes(
                                                                                                    passenger.index,
                                                                                                )
                                                                                                    ? ' — souhaité'
                                                                                                    : ''}
                                                                                            </option>
                                                                                        ),
                                                                                    )}
                                                                            </select>
                                                                        </div>
                                                                    ),
                                                                )}
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                            <InputError
                                                message={
                                                    errors.passengers ??
                                                    errors.seats
                                                }
                                            />
                                            <Button
                                                disabled={busy}
                                                onClick={prepare}
                                                className="bg-gold font-extrabold text-[var(--kora)] hover:bg-gold/90"
                                            >
                                                <ShieldCheck className="size-4" />
                                                Reprendre dans le guichet
                                            </Button>
                                        </ActionTile>
                                    )}

                                    {permissions.canRelease && (
                                        <ActionTile
                                            icon={RefreshCcw}
                                            title="Libérer"
                                            hint="Replacer la réservation dans la file"
                                            open={openTile === 'release'}
                                            onToggle={() =>
                                                setOpenTile(
                                                    openTile === 'release'
                                                        ? null
                                                        : 'release',
                                                )
                                            }
                                        >
                                            <div>
                                                <Label
                                                    className="text-[0.6875rem]"
                                                    htmlFor="drawer-release-reason"
                                                >
                                                    Motif obligatoire
                                                </Label>
                                                <Input
                                                    id="drawer-release-reason"
                                                    value={reason}
                                                    onChange={(event) =>
                                                        setReason(
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="mt-1 h-10 text-xs"
                                                    placeholder="Motif visible dans l'historique…"
                                                />
                                                <InputError
                                                    message={errors.reason}
                                                />
                                            </div>
                                            <Button
                                                variant="outline"
                                                disabled={busy}
                                                onClick={() =>
                                                    post(
                                                        release.url(item.uuid),
                                                        { reason },
                                                    )
                                                }
                                                className="text-xs"
                                            >
                                                <RefreshCcw className="size-4" />
                                                Libérer la réservation
                                            </Button>
                                        </ActionTile>
                                    )}

                                    {permissions.canRefuse && (
                                        <ActionTile
                                            icon={ShieldAlert}
                                            title="Refuser"
                                            hint="Clôturer définitivement"
                                            tone="destructive"
                                            open={openTile === 'refuse'}
                                            onToggle={() =>
                                                setOpenTile(
                                                    openTile === 'refuse'
                                                        ? null
                                                        : 'refuse',
                                                )
                                            }
                                        >
                                            <div>
                                                <Label
                                                    className="text-[0.6875rem]"
                                                    htmlFor="drawer-refuse-code"
                                                >
                                                    Motif du refus
                                                </Label>
                                                <select
                                                    id="drawer-refuse-code"
                                                    value={reasonCode}
                                                    onChange={(event) =>
                                                        setReasonCode(
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="mt-1 h-10 w-full rounded-lg border border-border bg-background px-3 text-xs"
                                                >
                                                    <option value="customer_request">
                                                        Demande du client
                                                    </option>
                                                    <option value="payment_not_received">
                                                        Paiement non reçu
                                                    </option>
                                                    <option value="fraud_risk">
                                                        Risque de fraude
                                                    </option>
                                                    <option value="other">
                                                        Autre
                                                    </option>
                                                </select>
                                            </div>
                                            <div>
                                                <Label
                                                    className="text-[0.6875rem]"
                                                    htmlFor="drawer-refuse-reason"
                                                >
                                                    Note
                                                </Label>
                                                <Input
                                                    id="drawer-refuse-reason"
                                                    value={reason}
                                                    onChange={(event) =>
                                                        setReason(
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="mt-1 h-10 text-xs"
                                                    placeholder="Note visible dans l'historique…"
                                                />
                                                <InputError
                                                    message={errors.reason_note}
                                                />
                                            </div>
                                            <Button
                                                variant="destructive"
                                                disabled={busy}
                                                onClick={() =>
                                                    post(
                                                        refuse.url(item.uuid),
                                                        {
                                                            reason_code:
                                                                reasonCode,
                                                            reason_note: reason,
                                                        },
                                                    )
                                                }
                                                className="text-xs"
                                            >
                                                Refuser et clôturer
                                            </Button>
                                        </ActionTile>
                                    )}

                                    {permissions.canReassign && (
                                        <ActionTile
                                            icon={ArrowLeftRight}
                                            title="Réaffecter"
                                            hint="Changer d’agence traitante"
                                            wide
                                            open={openTile === 'reassign'}
                                            onToggle={() =>
                                                setOpenTile(
                                                    openTile === 'reassign'
                                                        ? null
                                                        : 'reassign',
                                                )
                                            }
                                        >
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <Label className="text-[0.6875rem]">
                                                        Agence
                                                    </Label>
                                                    <select
                                                        value={agencyUuid}
                                                        onChange={(event) => {
                                                            setAgencyUuid(event.target.value);
                                                        }}
                                                        className="mt-1 h-10 w-full rounded-lg border border-border bg-background px-3 text-xs"
                                                    >
                                                        {data.agencies.map(
                                                            (agency) => (
                                                                <option
                                                                    key={
                                                                        agency.uuid
                                                                    }
                                                                    value={
                                                                        agency.uuid
                                                                    }
                                                                >
                                                                    {
                                                                        agency.name
                                                                    }{' '}
                                                                    —{' '}
                                                                    {
                                                                        agency.city
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <Label
                                                    className="text-[0.6875rem]"
                                                    htmlFor="drawer-reassign-reason"
                                                >
                                                    Motif (facultatif)
                                                </Label>
                                                <Input
                                                    id="drawer-reassign-reason"
                                                    value={reason}
                                                    onChange={(event) =>
                                                        setReason(
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="mt-1 h-10 text-xs"
                                                    placeholder="Motif visible dans l'historique…"
                                                />
                                            </div>
                                            <Button
                                                variant="outline"
                                                disabled={busy}
                                                onClick={() =>
                                                    post(
                                                        reassign.url(item.uuid),
                                                        {
                                                            agency_uuid: agencyUuid,
                                                            reason,
                                                        },
                                                    )
                                                }
                                                className="text-xs"
                                            >
                                                Confirmer la réaffectation
                                            </Button>
                                        </ActionTile>
                                    )}

                                    {!permissions.canClaim &&
                                        !permissions.canPrepareSale &&
                                        !permissions.canRelease &&
                                        !permissions.canRefuse &&
                                        !permissions.canReassign && (
                                            <div className="grid place-items-center gap-3 py-14 text-center sm:col-span-2">
                                                <span className="rounded-2xl bg-muted p-3.5 text-muted-foreground">
                                                    <HandHelping
                                                        size={22}
                                                        strokeWidth={1.5}
                                                    />
                                                </span>
                                                <div>
                                                    <p className="text-sm font-bold text-foreground">
                                                        Aucune action disponible
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Votre rôle ne permet pas
                                                        de traiter cette
                                                        réservation.
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                </div>
                            )}

                            {tab === 'history' &&
                                (item.history.length > 0 ? (
                                    <ol className="px-1 py-1">
                                        {item.history.map((entry, i) => (
                                            <li
                                                key={i}
                                                className="relative flex gap-3.5 pb-5 last:pb-0"
                                            >
                                                {i <
                                                    item.history.length - 1 && (
                                                        <span
                                                            aria-hidden
                                                            className="absolute top-5 left-[5px] h-[calc(100%-14px)] w-px bg-border"
                                                        />
                                                    )}
                                                <span className="relative z-10 mt-1.5 size-3 shrink-0 rounded-full border-2 border-gold/60 bg-card" />
                                                <div className="min-w-0">
                                                    <p className="text-xs leading-relaxed font-semibold text-foreground">
                                                        {eventLabels[
                                                            entry.event
                                                        ] ?? entry.event}
                                                    </p>
                                                    <p className="mt-0.5 text-[0.6875rem] font-medium text-muted-foreground">
                                                        {entry.actor ??
                                                            'Système'}{' '}
                                                        ·{' '}
                                                        {formatDateTime(
                                                            entry.createdAt,
                                                        )}
                                                    </p>
                                                </div>
                                            </li>
                                        ))}
                                    </ol>
                                ) : (
                                    <div className="grid place-items-center gap-3 py-14 text-center">
                                        <span className="rounded-2xl bg-muted p-3.5 text-muted-foreground">
                                            <History
                                                size={22}
                                                strokeWidth={1.5}
                                            />
                                        </span>
                                        <div>
                                            <p className="text-sm font-bold text-foreground">
                                                Aucun événement enregistré
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Les actions sur cette
                                                réservation apparaîtront ici.
                                            </p>
                                        </div>
                                    </div>
                                ))}
                        </div>

                        {/* ACTION BAR */}
                        {!isTerminal &&
                            (item.status === 'pending' ||
                                permissions.canPrepareSale) && (
                                <div className="shrink-0 border-t border-border/70 bg-card/80 px-6 py-4">
                                    <div className="flex items-center gap-2.5">
                                        {item.status === 'pending' &&
                                            permissions.canClaim ? (
                                            <Button
                                                disabled={busy}
                                                onClick={() =>
                                                    post(claim.url(item.uuid))
                                                }
                                                className="flex-1 gap-2 bg-gold font-extrabold text-[var(--kora)] hover:bg-gold/90"
                                            >
                                                <CheckCircle2 size={15} />
                                                Prendre en charge
                                            </Button>
                                        ) : permissions.canPrepareSale ? (
                                            <Button
                                                onClick={() =>
                                                    setTab('treatment')
                                                }
                                                className="flex-1 gap-2 bg-gold font-extrabold text-[var(--kora)] hover:bg-gold/90"
                                            >
                                                <ShieldCheck size={15} />
                                                Traiter la réservation
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            )}
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}

function Alert({
    tone,
    icon: Icon,
    title,
    children,
}: {
    tone: 'destructive' | 'warning' | 'info' | 'muted';
    icon: LucideIcon;
    title: string;
    children: React.ReactNode;
}) {
    const tones = {
        destructive:
            'border-destructive/30 bg-destructive/10 text-destructive [&_svg]:text-destructive',
        warning:
            'border-gold/30 bg-gold/10 text-foreground [&_svg]:text-amber-600 dark:[&_svg]:text-gold',
        info: 'border-digital/25 bg-digital/[0.07] text-foreground [&_svg]:text-digital',
        muted: 'border-border bg-muted/40 text-muted-foreground [&_svg]:text-muted-foreground',
    };

    return (
        <div
            className={cn(
                'flex items-start gap-3 rounded-xl border p-4 text-xs',
                tones[tone],
            )}
        >
            <Icon size={15} className="mt-0.5 shrink-0" strokeWidth={2} />
            <div>
                <p className="font-extrabold">{title}</p>
                <p className="mt-1 leading-relaxed">{children}</p>
            </div>
        </div>
    );
}

function StatTile({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    tone?: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-card px-3 py-2.5">
            <span className="flex items-center gap-1 text-[0.5625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                <Icon size={10} strokeWidth={2.5} />
                {label}
            </span>
            <span
                className={cn(
                    'mt-1 block truncate text-sm font-extrabold text-foreground tabular-nums',
                    tone,
                )}
            >
                {value}
            </span>
        </div>
    );
}

function InfoCell({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <span className="block text-[0.5625rem] font-bold tracking-[0.07em] text-muted-foreground uppercase">
                {label}
            </span>
            <span className="mt-0.5 block truncate text-xs font-bold text-foreground">
                {value}
            </span>
        </div>
    );
}

function ActionTile({
    icon: Icon,
    title,
    hint,
    tone = 'default',
    wide = false,
    open,
    onToggle,
    children,
}: {
    icon: LucideIcon;
    title: string;
    hint: string;
    tone?: 'default' | 'gold' | 'digital' | 'destructive';
    wide?: boolean;
    open: boolean;
    onToggle: () => void;
    children: React.ReactNode;
}) {
    const iconTones = {
        default: 'bg-muted text-muted-foreground',
        gold: 'bg-gold/15 text-amber-700 dark:text-amber-300',
        digital: 'bg-digital/10 text-digital',
        destructive: 'bg-destructive/10 text-destructive',
    };

    return (
        <div
            className={cn(
                'overflow-hidden rounded-2xl border transition-colors',
                (open || wide) && 'sm:col-span-2',
                open ? 'border-gold/40 bg-card' : 'border-border bg-card',
            )}
        >
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-center gap-3 p-4 text-left transition-colors hover:bg-muted/40"
            >
                <span
                    className={cn(
                        'grid size-9 shrink-0 place-items-center rounded-xl',
                        iconTones[tone],
                    )}
                >
                    <Icon size={16} strokeWidth={2} />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block text-xs font-extrabold text-foreground">
                        {title}
                    </span>
                    <span className="mt-0.5 block truncate text-[0.625rem] font-medium text-muted-foreground">
                        {hint}
                    </span>
                </span>
                <ChevronDown
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground transition-transform duration-200',
                        open && 'rotate-180',
                    )}
                />
            </button>
            {open && (
                <div className="grid gap-3 border-t border-border/50 px-4 pt-3.5 pb-4">
                    {children}
                </div>
            )}
        </div>
    );
}
