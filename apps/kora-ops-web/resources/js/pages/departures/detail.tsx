import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    Armchair,
    ArrowLeft,
    BusFront,
    CheckCircle2,
    Clock,
    FileText,
    Gauge,
    LifeBuoy,
    ListChecks,
    LogOut,
    MapPin,
    Package,
    Route,
    ScanLine,
    TriangleAlert,
    Undo2,
    UserCheck,
    UserX,
    Users,
    X,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTooltip } from '@/components/kora-tooltip';
import { PanelAlert } from '@/components/panel-alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import departures from '@/routes/departures';
import manifest from '@/routes/manifest';
import type { BreadcrumbItem } from '@/types';

type Stop = {
    uuid: string;
    name: string;
    stopOrder: number;
    circulationOrder: number;
    city: string;
    physicalStopName: string | null;
};

type SeatItem = {
    uuid: string;
    code: string;
    type: string;
    sellable: boolean;
    status: string;
    occupied: boolean;
    boarded: boolean;
};

type DepartureDetail = {
    uuid: string;
    lineUuid: string | null;
    lineCode: string;
    lineName: string;
    lineEstimatedDuration: number | null;
    originCity: string | null;
    destinationCity: string | null;
    originPoint: string | null;
    isReturn: boolean;
    stops: Stop[];
    busUuid: string | null;
    busRegistration: string;
    busSeatCount: number;
    seats: SeatItem[];
    departureDate: string | null;
    departureTime: string | null;
    delayMinutes: number;
    delayReason: string | null;
    delayRecordedAt: string | null;
    estimatedArrivalAt: string | null;
    driverUuid: string | null;
    driverName: string | null;
    driverAvatar: string | null;
    conductorUuid: string | null;
    conductorName: string | null;
    conductorAvatar: string | null;
    creatorName: string | null;
    fillRate: number;
    averageFillRate: number;
    peakLegLabel: string | null;
    peakLoad: number;
    reservationsCount: number;
    status: string;
    createdAt: string | null;
    updatedAt: string | null;
};

type PassengerRow = {
    segmentUuid: string;
    segmentStatus: string;
    passenger: {
        uuid: string;
        firstName: string;
        lastName: string;
        phone: string | null;
    } | null;
    seatCode: string;
    originStop: string;
    originStopOrder: number;
    destinationStop: string;
    destinationStopOrder: number;
    reservation: { uuid: string; isGroupPurchase: boolean | null };
    journeyStatus: { value: string; label: string };
    ticket: {
        uuid: string;
        shortCode: string;
        status: string;
        statusLabel: string;
        issuedAt: string | null;
    } | null;
};

type BoardingSummary = {
    total: number;
    boarded: number;
    absent: number;
    ticketIssued: number;
    paymentPending: number;
    holdExpired: number;
    cancelled: number;
};

type TimelineEvent = {
    label: string;
    date: string | null;
};

type PageProps = {
    departure: DepartureDetail;
    passengers: PassengerRow[];
    boardingSummary: BoardingSummary;
    timeline: TimelineEvent[];
    permissions: {
        canUpdate: boolean;
        canCancel: boolean;
        canManageReleases: boolean;
        canReverseBoarding: boolean;
        canViewTickets: boolean;
    };
};

const statusLabels: Record<string, string> = {
    scheduled: 'Programmé',
    boarding: 'Embarquement',
    departed: 'En route',
    arrived: 'Arrivé',
    cancelled: 'Annulé',
};

const statusTones: Record<
    string,
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive'
> = {
    scheduled: 'navy',
    boarding: 'gold',
    departed: 'blue',
    arrived: 'navy',
    cancelled: 'muted',
};

const journeyTones: Record<
    string,
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive'
> = {
    boarded: 'navy',
    ticket_issued: 'blue',
    payment_confirmed: 'blue',
    payment_pending: 'gold',
    hold_pending: 'gold',
    hold_expired: 'muted',
    absent: 'destructive',
    cancelled: 'muted',
};

const BUSINESS_RULE =
    "Le véhicule, l'équipage, le quai et le manifeste doivent être conformes avant le départ.";

type Tab = 'exploitation' | 'passengers' | 'crew' | 'parcels';

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDateShort(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function passengerName(row: PassengerRow): string {
    if (!row.passenger) {
        return 'Passager';
    }

    return `${row.passenger.firstName} ${row.passenger.lastName}`.trim();
}

export default function DepartureDetailPage({
    departure,
    passengers,
    boardingSummary,
    timeline,
    permissions,
}: PageProps) {
    // Statut, chronologie et passagers suivent les changements faits
    // depuis le terminal du convoyeur (MEZ-98) sans recharger la page.
    usePoll(30000, {
        only: ['departure', 'timeline', 'passengers', 'boardingSummary'],
    });

    const [tab, setTab] = useState<Tab>('exploitation');
    const [confirmStatus, setConfirmStatus] = useState<string | null>(null);
    const [noShowRow, setNoShowRow] = useState<PassengerRow | null>(null);
    const [earlyExitRow, setEarlyExitRow] = useState<PassengerRow | null>(null);
    const [reverseRow, setReverseRow] = useState<PassengerRow | null>(null);
    const [confirmCloseNoShows, setConfirmCloseNoShows] = useState(false);
    const [processing, setProcessing] = useState(false);

    const changeStatus = (status: string) => {
        setProcessing(true);
        router.patch(
            departures.status.url({ departure: departure.uuid }),
            { status },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setConfirmStatus(null);
                },
            },
        );
    };

    const submitNoShow = () => {
        if (!noShowRow?.ticket) {
            return;
        }

        setProcessing(true);
        router.post(
            departures.noShow.url({ departure: departure.uuid }),
            { ticket_uuid: noShowRow.ticket.uuid },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setNoShowRow(null);
                },
            },
        );
    };

    const submitEarlyExit = (stopUuid: string) => {
        if (!earlyExitRow?.ticket) {
            return;
        }

        setProcessing(true);
        router.post(
            departures.earlyExit.url({ departure: departure.uuid }),
            { ticket_uuid: earlyExitRow.ticket.uuid, stop_uuid: stopUuid },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setEarlyExitRow(null);
                },
            },
        );
    };

    const submitCloseNoShows = () => {
        setProcessing(true);
        router.post(
            departures.closeNoShows.url({ departure: departure.uuid }),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setConfirmCloseNoShows(false);
                },
            },
        );
    };

    const submitReverse = (reason: string) => {
        if (!reverseRow?.ticket) {
            return;
        }

        setProcessing(true);
        router.post(
            departures.reverseBoarding.url({ departure: departure.uuid }),
            { ticket_uuid: reverseRow.ticket.uuid, reason },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setReverseRow(null);
                },
            },
        );
    };

    const tabs: {
        key: Tab;
        label: string;
        icon: LucideIcon;
        count?: number;
    }[] = [
        { key: 'exploitation', label: 'Exploitation', icon: ListChecks },
        {
            key: 'passengers',
            label: 'Passagers',
            icon: Users,
            count: passengers.length,
        },
        { key: 'crew', label: 'Équipage', icon: UserCheck },
        { key: 'parcels', label: 'Colis', icon: Package },
    ];

    const tabLabel = tabs.find((t) => t.key === tab)?.label ?? '';

    // Passagers regroupés par arrêt de montée — c'est là que l'embarquement
    // se produit physiquement sur une ligne multi-arrêts.
    const passengerGroups = useMemo(() => {
        const groups = new Map<string, PassengerRow[]>();

        for (const row of passengers) {
            const list = groups.get(row.originStop) ?? [];
            list.push(row);
            groups.set(row.originStop, list);
        }

        return [...groups.entries()]
            .map(([stop, rows]) => ({
                stop,
                order: rows[0].originStopOrder,
                rows: rows.sort((a, b) =>
                    a.seatCode.localeCompare(b.seatCode, 'fr', {
                        numeric: true,
                    }),
                ),
                boarded: rows.filter((r) => r.journeyStatus.value === 'boarded')
                    .length,
            }))
            .sort((a, b) => a.order - b.order);
    }, [passengers]);

    const toControl =
        boardingSummary.ticketIssued + boardingSummary.paymentPending;

    const canNoShow = (row: PassengerRow): boolean =>
        permissions.canManageReleases &&
        row.ticket !== null &&
        row.journeyStatus.value === 'ticket_issued' &&
        ['departed', 'arrived'].includes(departure.status);

    const canEarlyExit = (row: PassengerRow): boolean =>
        permissions.canManageReleases &&
        row.ticket !== null &&
        row.journeyStatus.value === 'boarded' &&
        !['arrived', 'cancelled'].includes(departure.status);

    // Correction d'un scan erroné : le segment repasse à « ticketed ».
    const canReverseBoarding = (row: PassengerRow): boolean =>
        permissions.canReverseBoarding &&
        row.ticket !== null &&
        row.journeyStatus.value === 'boarded' &&
        !['cancelled'].includes(departure.status);

    // Clôture en masse : pertinente une fois le départ en route/arrivé,
    // quand des billets émis n'ont jamais été scannés.
    const canCloseNoShows =
        permissions.canManageReleases &&
        ['departed', 'arrived'].includes(departure.status) &&
        boardingSummary.ticketIssued > 0;

    const renderActions = () => {
        if (
            departure.status === 'arrived' ||
            departure.status === 'cancelled'
        ) {
            return null;
        }

        return (
            <>
                {departure.status === 'scheduled' && permissions.canUpdate && (
                    <button
                        type="button"
                        onClick={() => setConfirmStatus('boarding')}
                        className="btn btn-success"
                    >
                        <CheckCircle2 className="size-4" />
                        Ouvrir l'embarquement
                    </button>
                )}
                {departure.status === 'boarding' && permissions.canUpdate && (
                    <button
                        type="button"
                        onClick={() => setConfirmStatus('departed')}
                        className="btn btn-success"
                    >
                        <BusFront className="size-4" />
                        Enregistrer le départ effectif
                    </button>
                )}
                {departure.status === 'departed' && permissions.canUpdate && (
                    <button
                        type="button"
                        onClick={() => setConfirmStatus('arrived')}
                        className="btn btn-success"
                    >
                        <CheckCircle2 className="size-4" />
                        Déclarer l'arrivée
                    </button>
                )}
                {permissions.canCancel &&
                    ['scheduled', 'boarding'].includes(departure.status) && (
                        <button
                            type="button"
                            onClick={() => setConfirmStatus('cancelled')}
                            className="btn btn-danger"
                        >
                            <XCircle className="size-4" />
                            Annuler le départ
                        </button>
                    )}
            </>
        );
    };

    // Horaires estimés par arrêt, répartis sur la durée de la ligne.
    const schedule = useMemo(() => {
        if (!departure.stops.length || !departure.departureTime) {
            return [];
        }

        const [hours, minutes] = departure.departureTime.split(':').map(Number);
        const baseTime = new Date();
        baseTime.setHours(hours, minutes, 0, 0);

        const totalDuration = departure.lineEstimatedDuration ?? 0;
        const stopCount = departure.stops.length;

        return departure.stops.map((stop, index) => {
            const fraction = stopCount > 1 ? index / (stopCount - 1) : 0;
            const time = new Date(
                baseTime.getTime() +
                    Math.round(totalDuration * fraction) * 60000,
            );

            return {
                ...stop,
                estimatedTime: time.toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                }),
            };
        });
    }, [departure]);

    const occupiedSeatsCount = departure.seats.filter(
        (s) => s.sellable && s.occupied,
    ).length;
    const sellableSeatsCount = departure.seats.filter((s) => s.sellable).length;

    // Rangées de la cabine : les codes siège « 3A » sont regroupés par
    // numéro de rangée, comme dans la modale sièges de la vente guichet.
    const seatRows = useMemo(() => {
        const rows = new Map<number, SeatItem[]>();

        departure.seats.forEach((seat, index) => {
            const match = seat.code.match(/^(\d+)([A-Za-z]+)$/);
            const key = match
                ? Number(match[1])
                : Number.MAX_SAFE_INTEGER - index;
            const list = rows.get(key) ?? [];
            list.push(seat);
            rows.set(key, list);
        });

        return [...rows.entries()].sort((a, b) => a[0] - b[0]);
    }, [departure.seats]);

    return (
        <>
            <Head title={`${departure.lineCode} — Départ`} />

            {/* Page-head */}
            <div className="cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Exploitation / Départs / {departure.lineCode}{' '}
                        · {departure.departureTime}
                    </div>
                    <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                        Départ {departure.originCity ?? departure.lineCode}
                        {departure.destinationCity &&
                            ` → ${departure.destinationCity}`}
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                        {departure.lineCode} · {departure.lineName} ·{' '}
                        {formatDateShort(departure.departureDate)} ·{' '}
                        {departure.busRegistration}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        onClick={() => router.get(departures.index.url())}
                        className="btn btn-navy"
                    >
                        <ArrowLeft className="size-4" />
                        Retour
                    </button>
                    <Link
                        href={manifest.show.url({
                            departure: departure.uuid,
                        })}
                        className="btn btn-primary"
                    >
                        <FileText className="size-4" />
                        Manifeste
                    </Link>
                    {renderActions()}
                </div>
            </div>

            {/* Hero — identité du départ */}
            <section className="ops-ribbon cash-reveal mb-4">
                <div>
                    <span className="section-kicker">Départ</span>
                    <h2>
                        {departure.departureTime} ·{' '}
                        {departure.originCity ?? departure.lineCode}
                        {departure.destinationCity &&
                            ` → ${departure.destinationCity}`}
                    </h2>
                    <p>
                        {departure.lineCode} · {departure.lineName}
                        {departure.originPoint &&
                            ` — quai ${departure.originPoint}`}{' '}
                        · {departure.busRegistration} ·{' '}
                        {departure.driverName ??
                            departure.conductorName ??
                            'Équipage à affecter'}
                    </p>
                </div>
                <span className="absolute top-6 right-7 z-10 flex items-center gap-2">
                    {departure.isReturn && (
                        <KoraStatusBadge
                            label="Sens retour"
                            tone="muted"
                            className="border border-white/20 bg-white/5"
                        />
                    )}
                    <KoraStatusBadge
                        label={
                            statusLabels[departure.status] ?? departure.status
                        }
                        tone={statusTones[departure.status] ?? 'gold'}
                        className="border border-white/20 bg-white/5"
                    />
                </span>
            </section>

            {/* Compteurs d'embarquement — cartes façon Caisse */}
            <section className="cash-ledger-grid cash-reveal mb-6">
                <article className="cash-ledger-card">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-emerald-500/10 text-emerald-600">
                                <UserCheck
                                    className="size-4"
                                    strokeWidth={1.7}
                                />
                            </span>
                            <div>
                                <span className="cash-ledger-title">
                                    Embarqués
                                </span>
                                <small>Billets contrôlés</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong>{boardingSummary.boarded}</strong>
                    </div>
                </article>
                <article className="cash-ledger-card">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-digital/10 text-digital">
                                <ScanLine
                                    className="size-4"
                                    strokeWidth={1.7}
                                />
                            </span>
                            <div>
                                <span className="cash-ledger-title">
                                    À contrôler
                                </span>
                                <small>Billets en attente</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong>{toControl}</strong>
                    </div>
                </article>
                <article className="cash-ledger-card">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-destructive/10 text-destructive">
                                <UserX className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">
                                    Absents
                                </span>
                                <small>No-show déclarés</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong>{boardingSummary.absent}</strong>
                    </div>
                </article>
                <KoraTooltip
                    side="bottom"
                    content={
                        <>
                            <span className="block font-black">
                                Tronçon le plus chargé
                            </span>
                            {departure.peakLegLabel ?? '—'} :{' '}
                            {departure.peakLoad} passager
                            {departure.peakLoad > 1 ? 's' : ''} /{' '}
                            {departure.busSeatCount} sièges · moyenne ligne{' '}
                            {departure.averageFillRate}%
                        </>
                    }
                >
                    <article className="cash-ledger-card h-full">
                        <div className="cash-ledger-head">
                            <div className="cash-ledger-identity">
                                <span className="cash-ledger-brand bg-gold/15 text-gold">
                                    <Gauge
                                        className="size-4"
                                        strokeWidth={1.7}
                                    />
                                </span>
                                <div>
                                    <span className="cash-ledger-title">
                                        Remplissage
                                    </span>
                                    <small>Pointe par tronçon</small>
                                </div>
                            </div>
                        </div>
                        <div className="cash-ledger-balance">
                            <span>Taux</span>
                            <strong>{departure.fillRate} %</strong>
                        </div>
                    </article>
                </KoraTooltip>
            </section>

            {/* Tabs */}
            <div className="mb-5 flex gap-1 overflow-x-auto border-b border-border/60">
                {tabs.map(({ key, label, icon: Icon, count }) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setTab(key)}
                        className={`inline-flex items-center gap-2 border-b-2 px-4 py-3 text-[0.6875rem] font-extrabold tracking-[0.035em] whitespace-nowrap transition-colors ${
                            tab === key
                                ? 'border-gold text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <Icon
                            className={`size-3.5 ${tab === key ? 'text-gold' : ''}`}
                            strokeWidth={2}
                        />
                        {label}
                        {count !== undefined && count > 0 && (
                            <span className="rounded-full bg-muted px-1.5 py-0.5 text-[0.5625rem] font-bold text-muted-foreground">
                                {count}
                            </span>
                        )}
                    </button>
                ))}
            </div>

            {/* Detail grid */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(340px,0.85fr)]">
                {/* Panel principal */}
                <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-[0.9375rem] font-bold text-foreground">
                                {tabLabel}
                            </h2>
                            <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                Informations propres à ce départ
                            </p>
                        </div>
                    </div>

                    {/* Tab: Exploitation */}
                    {tab === 'exploitation' && (
                        <div className="space-y-5">
                            {departure.delayMinutes > 0 && (
                                <PanelAlert tone="warning" icon={Clock}>
                                    Retard déclaré de{' '}
                                    <strong>
                                        {departure.delayMinutes} min
                                    </strong>
                                    {departure.delayReason &&
                                        ` — ${departure.delayReason}`}
                                </PanelAlert>
                            )}
                            {(!departure.driverName ||
                                !departure.conductorName) && (
                                <PanelAlert tone="info" icon={TriangleAlert}>
                                    Équipage incomplet :{' '}
                                    {[
                                        !departure.driverName && 'conducteur',
                                        !departure.conductorName && 'convoyeur',
                                    ]
                                        .filter(Boolean)
                                        .join(' et ')}{' '}
                                    à affecter avant l'ouverture de
                                    l'embarquement.
                                </PanelAlert>
                            )}
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <InfoCard
                                    icon={MapPin}
                                    label="Point de départ"
                                    value={departure.originPoint ?? '—'}
                                />
                                <InfoCard
                                    icon={Clock}
                                    label="Arrivée estimée"
                                    value={formatTime(
                                        departure.estimatedArrivalAt,
                                    )}
                                />
                                <InfoCard
                                    icon={TriangleAlert}
                                    label="Retard"
                                    value={
                                        departure.delayMinutes > 0
                                            ? `${departure.delayMinutes} min`
                                            : 'À l’heure'
                                    }
                                />
                                <InfoCard
                                    icon={Route}
                                    label="Ligne"
                                    value={`${departure.lineCode} · ${departure.lineName}`}
                                />
                                <InfoCard
                                    icon={BusFront}
                                    label="Bus"
                                    value={departure.busRegistration}
                                />
                                <InfoCard
                                    icon={Armchair}
                                    label="Places"
                                    value={`${departure.busSeatCount}`}
                                />
                            </div>

                            {schedule.length > 0 && (
                                <div>
                                    <div className="mb-3 flex items-center gap-2">
                                        <Clock className="size-3.5 text-muted-foreground" />
                                        <span className="text-[0.5625rem] font-black tracking-[0.13em] text-muted-foreground uppercase">
                                            Horaires estimés par arrêt
                                        </span>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                                    <th className="pr-4 pb-3 font-medium">
                                                        Arrêt
                                                    </th>
                                                    <th className="pr-4 pb-3 font-medium">
                                                        Ville
                                                    </th>
                                                    <th className="pr-4 pb-3 font-medium">
                                                        Heure estimée
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {schedule.map((stop) => (
                                                    <tr
                                                        key={stop.uuid}
                                                        className="border-b border-border/30 hover:bg-muted/30"
                                                    >
                                                        <td className="py-2.5 pr-4 font-bold text-foreground">
                                                            {stop.stopOrder
                                                                .toString()
                                                                .padStart(
                                                                    2,
                                                                    '0',
                                                                )}{' '}
                                                            {stop.physicalStopName ??
                                                                stop.name}
                                                        </td>
                                                        <td className="py-2.5 pr-4 text-muted-foreground">
                                                            {stop.city}
                                                        </td>
                                                        <td className="py-2.5 font-medium text-foreground">
                                                            {stop.estimatedTime}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            <div className="rounded-[0_0.875rem_0.875rem_0] border-l-[3px] border-gold bg-muted p-4">
                                <span className="block text-[0.5rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                    Règle métier
                                </span>
                                <p className="mt-1.5 text-[0.6875rem] leading-relaxed text-foreground">
                                    {BUSINESS_RULE}
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Tab: Passagers — occupation par segment, groupée par
                        arrêt de montée (l'embarquement se fait à chaque stop) */}
                    {tab === 'passengers' && (
                        <div>
                            <div className="mb-4 flex flex-wrap items-end justify-between gap-4 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Occupation par segment
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        {passengers.length} passager
                                        {passengers.length > 1
                                            ? 's'
                                            : ''} · {departure.fillRate}% de
                                        remplissage
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Un no-show libère tout le trajet. Une
                                        descente anticipée libère uniquement les
                                        tronçons non parcourus.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <KoraStatusBadge
                                        label={`${boardingSummary.boarded} embarqué${boardingSummary.boarded > 1 ? 's' : ''}`}
                                        tone="navy"
                                    />
                                    <KoraStatusBadge
                                        label={`${toControl} à contrôler`}
                                        tone="blue"
                                    />
                                    <KoraStatusBadge
                                        label={`${boardingSummary.absent} absent${boardingSummary.absent > 1 ? 's' : ''}`}
                                        tone={
                                            boardingSummary.absent > 0
                                                ? 'destructive'
                                                : 'muted'
                                        }
                                    />
                                    {canCloseNoShows && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setConfirmCloseNoShows(true)
                                            }
                                            className="btn btn-danger btn-sm"
                                            title="Tous les billets émis jamais scannés deviennent no-show"
                                        >
                                            <UserX className="size-3.5" />
                                            Clôturer les absents (
                                            {boardingSummary.ticketIssued})
                                        </button>
                                    )}
                                </div>
                            </div>

                            {passengers.length > 0 && (
                                <div className="mb-5 space-y-2.5">
                                    {boardingSummary.absent > 0 && (
                                        <PanelAlert tone="danger" icon={UserX}>
                                            <strong>
                                                {boardingSummary.absent} no-show
                                                {boardingSummary.absent > 1
                                                    ? 's'
                                                    : ''}
                                            </strong>{' '}
                                            déclaré
                                            {boardingSummary.absent > 1
                                                ? 's'
                                                : ''}{' '}
                                            — leurs sièges sont libérés sur tout
                                            leur trajet et remis en vente.
                                        </PanelAlert>
                                    )}
                                    {toControl > 0 &&
                                        ['boarding', 'departed'].includes(
                                            departure.status,
                                        ) && (
                                            <PanelAlert
                                                tone="warning"
                                                icon={ScanLine}
                                            >
                                                <strong>{toControl}</strong>{' '}
                                                billet
                                                {toControl > 1 ? 's' : ''} à
                                                contrôler — les no-shows
                                                pourront être signalés une fois
                                                le départ en route.
                                            </PanelAlert>
                                        )}
                                    {toControl === 0 &&
                                        boardingSummary.absent === 0 &&
                                        boardingSummary.boarded > 0 && (
                                            <PanelAlert
                                                tone="success"
                                                icon={CheckCircle2}
                                            >
                                                Tous les passagers attendus sont
                                                traités — aucun billet en
                                                attente de contrôle.
                                            </PanelAlert>
                                        )}
                                </div>
                            )}

                            {passengerGroups.length > 0 ? (
                                <div className="space-y-5">
                                    {passengerGroups.map((group) => (
                                        <div key={group.stop}>
                                            <div className="mb-2 flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Route className="size-3.5 text-gold" />
                                                    <strong className="text-[0.8125rem] font-bold text-foreground">
                                                        Montée · {group.stop}
                                                    </strong>
                                                </div>
                                                <span className="text-[0.625rem] font-semibold text-muted-foreground">
                                                    {group.rows.length} attendu
                                                    {group.rows.length > 1
                                                        ? 's'
                                                        : ''}{' '}
                                                    · {group.boarded} embarqué
                                                    {group.boarded > 1
                                                        ? 's'
                                                        : ''}
                                                </span>
                                            </div>
                                            <div className="overflow-x-auto rounded-[0.75rem] border border-border/50">
                                                <table className="w-full text-sm">
                                                    <thead>
                                                        <tr className="border-b border-border/60 bg-muted/40 text-left text-xs text-muted-foreground">
                                                            <th className="pr-4 pb-2.5 pl-4 font-medium">
                                                                Siège
                                                            </th>
                                                            <th className="pr-4 pb-2.5 font-medium">
                                                                Passager
                                                            </th>
                                                            <th className="pr-4 pb-2.5 font-medium">
                                                                Trajet
                                                            </th>
                                                            <th className="pr-4 pb-2.5 font-medium">
                                                                Billet
                                                            </th>
                                                            <th className="pr-4 pb-2.5 font-medium">
                                                                Statut
                                                            </th>
                                                            {permissions.canManageReleases && (
                                                                <th className="pr-4 pb-2.5 text-right font-medium">
                                                                    Actions
                                                                </th>
                                                            )}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {group.rows.map(
                                                            (row) => (
                                                                <tr
                                                                    key={
                                                                        row.segmentUuid
                                                                    }
                                                                    className="border-b border-border/30 last:border-0 hover:bg-muted/30"
                                                                >
                                                                    <td className="py-2.5 pr-4 pl-4 font-bold text-foreground">
                                                                        {
                                                                            row.seatCode
                                                                        }
                                                                    </td>
                                                                    <td className="py-2.5 pr-4">
                                                                        <span className="font-medium text-foreground">
                                                                            {passengerName(
                                                                                row,
                                                                            )}
                                                                        </span>
                                                                        {row
                                                                            .passenger
                                                                            ?.phone && (
                                                                            <span className="block text-[0.625rem] text-muted-foreground">
                                                                                {
                                                                                    row
                                                                                        .passenger
                                                                                        .phone
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="py-2.5 pr-4 text-muted-foreground">
                                                                        {
                                                                            row.originStop
                                                                        }{' '}
                                                                        →{' '}
                                                                        {
                                                                            row.destinationStop
                                                                        }
                                                                    </td>
                                                                    <td className="py-2.5 pr-4 font-mono text-[0.75rem] text-muted-foreground">
                                                                        {row
                                                                            .ticket
                                                                            ?.shortCode ??
                                                                            '—'}
                                                                    </td>
                                                                    <td className="py-2.5 pr-4">
                                                                        <KoraStatusBadge
                                                                            label={
                                                                                row
                                                                                    .journeyStatus
                                                                                    .label
                                                                            }
                                                                            tone={
                                                                                journeyTones[
                                                                                    row
                                                                                        .journeyStatus
                                                                                        .value
                                                                                ] ??
                                                                                'muted'
                                                                            }
                                                                        />
                                                                    </td>
                                                                    {permissions.canManageReleases && (
                                                                        <td className="py-2.5 pr-4">
                                                                            <div className="flex justify-end gap-1.5">
                                                                                {canEarlyExit(
                                                                                    row,
                                                                                ) && (
                                                                                    <button
                                                                                        type="button"
                                                                                        onClick={() =>
                                                                                            setEarlyExitRow(
                                                                                                row,
                                                                                            )
                                                                                        }
                                                                                        className="btn btn-navy btn-sm"
                                                                                        title="Descente anticipée — libère les tronçons non parcourus"
                                                                                    >
                                                                                        <LogOut className="size-3.5" />
                                                                                        Descente
                                                                                    </button>
                                                                                )}
                                                                                {canNoShow(
                                                                                    row,
                                                                                ) && (
                                                                                    <button
                                                                                        type="button"
                                                                                        onClick={() =>
                                                                                            setNoShowRow(
                                                                                                row,
                                                                                            )
                                                                                        }
                                                                                        className="btn btn-danger btn-sm"
                                                                                        title="No-show — libère tout le trajet"
                                                                                    >
                                                                                        <UserX className="size-3.5" />
                                                                                        No-show
                                                                                    </button>
                                                                                )}
                                                                                {canReverseBoarding(
                                                                                    row,
                                                                                ) && (
                                                                                    <button
                                                                                        type="button"
                                                                                        onClick={() =>
                                                                                            setReverseRow(
                                                                                                row,
                                                                                            )
                                                                                        }
                                                                                        className="btn btn-danger btn-sm"
                                                                                        title="Annuler l'embarquement — correction d'un scan erroné (motif requis, audité)"
                                                                                    >
                                                                                        <Undo2 className="size-3.5" />
                                                                                        Annuler
                                                                                    </button>
                                                                                )}
                                                                            </div>
                                                                        </td>
                                                                    )}
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-[1rem] border border-dashed border-border/60 bg-card p-12 text-center">
                                    <Users className="mx-auto size-10 text-muted-foreground/40" />
                                    <h4 className="mt-3 text-sm font-bold text-foreground">
                                        Aucune réservation
                                    </h4>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Les passagers apparaîtront ici dès
                                        qu'une réservation couvrira ce départ.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Tab: Équipage */}
                    {tab === 'crew' && (
                        <div>
                            <div className="mb-4 flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Équipe affectée
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        Conducteur et convoyeur
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Les habilitations sont contrôlées avant
                                        l'ouverture de l'embarquement.
                                    </p>
                                </div>
                            </div>

                            {(!departure.driverName ||
                                !departure.conductorName) && (
                                <div className="mb-4">
                                    <PanelAlert
                                        tone="warning"
                                        icon={TriangleAlert}
                                    >
                                        Affectation requise : l'embarquement ne
                                        peut pas être ouvert sans conducteur et
                                        convoyeur confirmés.
                                    </PanelAlert>
                                </div>
                            )}

                            <div className="grid gap-3">
                                <CrewCard
                                    role="Conducteur"
                                    icon={LifeBuoy}
                                    name={departure.driverName}
                                    avatar={departure.driverAvatar}
                                    hint={
                                        departure.driverName
                                            ? 'Permis · habilitation requise'
                                            : 'Aucun conducteur assigné à ce départ'
                                    }
                                    badge={
                                        departure.driverName
                                            ? 'Habilité'
                                            : 'À affecter'
                                    }
                                />
                                <CrewCard
                                    role="Convoyeur"
                                    icon={ScanLine}
                                    name={departure.conductorName}
                                    avatar={departure.conductorAvatar}
                                    hint={
                                        departure.conductorName
                                            ? 'Terminal P501 · session prête'
                                            : 'Aucun convoyeur assigné à ce départ'
                                    }
                                    badge={
                                        departure.conductorName
                                            ? 'Disponible'
                                            : 'À affecter'
                                    }
                                />
                            </div>
                        </div>
                    )}

                    {/* Tab: Colis */}
                    {tab === 'parcels' && (
                        <div>
                            <div className="mb-4 flex items-end justify-between gap-5 border-b border-border/60 pb-4">
                                <div>
                                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                                        Soute du départ
                                    </span>
                                    <h3 className="text-[1.1875rem] font-bold text-foreground">
                                        Colis embarqués
                                    </h3>
                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                        Chaque colis doit être scanné avant la
                                        fermeture de la soute.
                                    </p>
                                </div>
                            </div>

                            <div className="rounded-[1rem] border border-dashed border-border/60 bg-muted/20 p-8 text-center">
                                <span className="mx-auto grid size-12 place-items-center rounded-[0.9rem] bg-muted text-muted-foreground">
                                    <Package className="size-5" />
                                </span>
                                <h4 className="mt-3 text-sm font-bold text-foreground">
                                    Module colis — à venir
                                </h4>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Le suivi des colis (référence, destination,
                                    poids, scan) sera alimenté par le module
                                    dédié.
                                </p>
                            </div>
                        </div>
                    )}
                </section>

                {/* Aside — le plan de sièges (info live, scannable) passe
                    avant le cycle du départ (audit, consulté après) */}
                <aside className="space-y-4">
                    {/* Plan de sièges — occupation réelle du départ */}
                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-4 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <h3 className="mb-1 text-[0.8125rem] font-bold tracking-tight text-foreground uppercase">
                            Plan de sièges
                        </h3>
                        <p className="mb-3 text-[0.625rem] text-muted-foreground">
                            {departure.busRegistration} · {occupiedSeatsCount}/
                            {sellableSeatsCount} vendables occupés
                        </p>
                        {departure.seats.length > 0 ? (
                            <>
                                {/* Cabine façon modale sièges de la vente
                                    guichet : volant conducteur, allée
                                    centrale, rangée arrière pleine */}
                                <div className="bus-cabin">
                                    <span
                                        className="driver-wheel"
                                        title="Conducteur"
                                    >
                                        <LifeBuoy className="size-3" />
                                    </span>
                                    <div className="seat-grid">
                                        {seatRows.map(([rowNumber, seats]) => (
                                            <div
                                                key={rowNumber}
                                                className={`seat-row${seats.length === 5 ? 'rear' : ''}`}
                                            >
                                                {seats.map((seat) => (
                                                    <span
                                                        key={seat.uuid}
                                                        className={`seat${!seat.sellable ? 'blocked' : seat.boarded ? 'busy' : seat.occupied ? 'reserved' : ''}`}
                                                        title={`${seat.code} · ${
                                                            !seat.sellable
                                                                ? 'Bloqué / équipage'
                                                                : seat.boarded
                                                                  ? 'Embarqué sur ce départ'
                                                                  : seat.occupied
                                                                    ? 'Réservé sur ce départ'
                                                                    : 'Libre'
                                                        }`}
                                                    >
                                                        {seat.code.replace(
                                                            /^\d+/,
                                                            '',
                                                        ) || seat.code}
                                                    </span>
                                                ))}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-2 text-[0.5625rem] text-muted-foreground">
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-[0.2rem] border border-border bg-card" />
                                        Libre
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-[0.2rem] border border-gold bg-gold/20" />
                                        Réservé
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-[0.2rem] bg-digital" />
                                        Embarqué
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2.5 rounded-[0.2rem] bg-[#454d5c]" />
                                        Bloqué
                                    </span>
                                </div>
                            </>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Aucun plan de sièges disponible pour ce
                                véhicule.
                            </p>
                        )}
                    </section>

                    {/* Chronologie réelle */}
                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <h3 className="mb-1 text-[0.8125rem] font-bold tracking-tight text-foreground uppercase">
                            Cycle du départ
                        </h3>
                        <p className="mb-4 text-[0.625rem] text-muted-foreground">
                            Événements horodatés — scans P501 inclus
                        </p>
                        {timeline.length > 0 ? (
                            <div className="relative ml-2 space-y-4 border-l-2 border-border/60 pl-6">
                                {timeline.map((event, index) => (
                                    <div key={index} className="relative">
                                        <span className="absolute top-1 -left-[31px] size-3 rounded-full border-2 border-card bg-gold" />
                                        <p className="text-sm font-bold text-foreground">
                                            {event.label}
                                        </p>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDate(event.date)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Aucun événement enregistré.
                            </p>
                        )}
                    </section>
                </aside>
            </div>

            {/* Confirmations design system (remplace window.confirm) */}
            <ConfirmDialog
                open={confirmStatus !== null}
                title={
                    confirmStatus === 'cancelled'
                        ? 'Annuler ce départ ?'
                        : confirmStatus === 'departed'
                          ? 'Enregistrer le départ effectif ?'
                          : confirmStatus === 'arrived'
                            ? "Déclarer l'arrivée ?"
                            : "Ouvrir l'embarquement ?"
                }
                message={
                    confirmStatus === 'cancelled'
                        ? `Le départ ${departure.lineCode} · ${departure.departureTime} sera annulé. Les passagers et le manifeste seront impactés.`
                        : `Le statut du départ ${departure.lineCode} · ${departure.departureTime} sera mis à jour et tracé en audit.`
                }
                confirmLabel={
                    confirmStatus === 'cancelled'
                        ? 'Annuler le départ'
                        : 'Confirmer'
                }
                danger={confirmStatus === 'cancelled'}
                processing={processing}
                onConfirm={() => confirmStatus && changeStatus(confirmStatus)}
                onClose={() => setConfirmStatus(null)}
            />

            <ConfirmDialog
                open={noShowRow !== null}
                title="Signaler un no-show ?"
                message={
                    noShowRow
                        ? `${passengerName(noShowRow)} (siège ${noShowRow.seatCode}) ne s'est pas présenté : tout le trajet ${noShowRow.originStop} → ${noShowRow.destinationStop} sera libéré et remis en vente.`
                        : ''
                }
                confirmLabel="Confirmer le no-show"
                danger
                processing={processing}
                onConfirm={submitNoShow}
                onClose={() => setNoShowRow(null)}
            />

            <EarlyExitDialog
                row={earlyExitRow}
                stops={departure.stops}
                processing={processing}
                onConfirm={submitEarlyExit}
                onClose={() => setEarlyExitRow(null)}
            />

            <ConfirmDialog
                open={confirmCloseNoShows}
                title="Clôturer les absents ?"
                message={`Les ${boardingSummary.ticketIssued} billet${boardingSummary.ticketIssued > 1 ? 's' : ''} émis jamais scannés deviendront no-show : tout leur trajet sera libéré et remis en vente. Action tracée en audit.`}
                confirmLabel="Clôturer les absents"
                danger
                processing={processing}
                onConfirm={submitCloseNoShows}
                onClose={() => setConfirmCloseNoShows(false)}
            />

            <ReverseBoardingDialog
                row={reverseRow}
                processing={processing}
                onConfirm={submitReverse}
                onClose={() => setReverseRow(null)}
            />
        </>
    );
}

function InfoCard({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-start gap-3 rounded-[0.75rem] bg-muted p-3.5">
            <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-[0.6rem] bg-card text-[var(--digital)]">
                <Icon className="size-3.5" strokeWidth={1.8} />
            </span>
            <div className="min-w-0">
                <small className="block text-[0.5625rem] text-muted-foreground">
                    {label}
                </small>
                <strong className="mt-1 block truncate text-[0.75rem] font-bold text-foreground">
                    {value}
                </strong>
            </div>
        </div>
    );
}

function CrewCard({
    role,
    icon: Icon,
    name,
    avatar,
    hint,
    badge,
}: {
    role: string;
    icon: LucideIcon;
    name: string | null;
    avatar?: string | null;
    hint: string;
    badge: string;
}) {
    return (
        <article className="flex items-center gap-3.5 rounded-[1rem] border border-border/50 bg-card p-4 shadow-[0_10px_24px_rgba(0,29,78,0.06)]">
            <Avatar className="size-12 shrink-0 rounded-[0.75rem]">
                <AvatarImage src={avatar ?? undefined} alt={name ?? role} />
                <AvatarFallback className="rounded-[0.75rem] bg-gold/20 text-[0.875rem] font-black text-[var(--kora)] dark:bg-gold/15 dark:text-gold">
                    {name
                        ? name
                              .split(' ')
                              .map((n) => n[0])
                              .join('')
                              .slice(0, 2)
                              .toUpperCase()
                        : '—'}
                </AvatarFallback>
            </Avatar>
            <div className="flex-1">
                <small className="flex items-center gap-1 text-[0.5rem] font-bold text-[var(--digital)]">
                    <Icon className="size-3" />
                    {role}
                </small>
                <strong className="mt-1 block text-[0.8125rem] font-bold text-foreground">
                    {name ?? 'À affecter'}
                </strong>
                <p className="mt-0.5 text-[0.5625rem] text-muted-foreground">
                    {hint}
                </p>
            </div>
            <KoraStatusBadge label={badge} tone={name ? 'navy' : 'muted'} />
        </article>
    );
}

function ConfirmDialog({
    open,
    title,
    message,
    confirmLabel,
    danger = false,
    processing,
    onConfirm,
    onClose,
}: {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    danger?: boolean;
    processing: boolean;
    onConfirm: () => void;
    onClose: () => void;
}) {
    if (!open) {
        return null;
    }

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onClick={onClose}
        >
            <section
                className="cash-modal max-w-md"
                role="dialog"
                aria-modal="true"
                aria-labelledby="departure-confirm-title"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="cash-modal-head">
                    <div>
                        <h3 id="departure-confirm-title">{title}</h3>
                        <p>{message}</p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                        aria-label="Fermer"
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-navy"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        className={`btn ${danger ? 'btn-danger' : 'btn-success'}`}
                        onClick={onConfirm}
                        disabled={processing}
                    >
                        {processing ? 'Traitement…' : confirmLabel}
                    </button>
                </div>
            </section>
        </div>
    );
}

function EarlyExitDialog({
    row,
    stops,
    processing,
    onConfirm,
    onClose,
}: {
    row: PassengerRow | null;
    stops: Stop[];
    processing: boolean;
    onConfirm: (stopUuid: string) => void;
    onClose: () => void;
}) {
    const [stopUuid, setStopUuid] = useState('');

    if (!row) {
        return null;
    }

    // Arrêts compatibles : entre l'origine (incluse) et la destination
    // (exclue) du segment — une descente à la destination finale est la
    // fin normale du trajet, pas une descente anticipée.
    const eligibleStops = stops.filter(
        (s) =>
            s.circulationOrder >= row.originStopOrder &&
            s.circulationOrder < row.destinationStopOrder,
    );

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onClick={onClose}
        >
            <section
                className="cash-modal max-w-md"
                role="dialog"
                aria-modal="true"
                aria-labelledby="early-exit-title"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="cash-modal-head">
                    <div>
                        <h3 id="early-exit-title">Descente anticipée</h3>
                        <p>
                            {passengerName(row)} · siège {row.seatCode} ·{' '}
                            {row.originStop} → {row.destinationStop}. Les
                            tronçons non parcourus seront libérés et remis en
                            vente.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                        aria-label="Fermer"
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <div className="px-6 pb-4">
                    <label className="mb-1.5 block text-[0.5625rem] font-black tracking-[0.13em] text-muted-foreground uppercase">
                        Arrêt de descente réel
                    </label>
                    <select
                        value={stopUuid}
                        onChange={(e) => setStopUuid(e.target.value)}
                        className="h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                    >
                        <option value="">Choisir l'arrêt…</option>
                        {eligibleStops.map((stop) => (
                            <option key={stop.uuid} value={stop.uuid}>
                                {stop.physicalStopName ?? stop.name} ·{' '}
                                {stop.city}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-navy"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={() => onConfirm(stopUuid)}
                        disabled={processing || stopUuid === ''}
                    >
                        {processing
                            ? 'Traitement…'
                            : 'Libérer le trajet restant'}
                    </button>
                </div>
            </section>
        </div>
    );
}

function ReverseBoardingDialog({
    row,
    processing,
    onConfirm,
    onClose,
}: {
    row: PassengerRow | null;
    processing: boolean;
    onConfirm: (reason: string) => void;
    onClose: () => void;
}) {
    const [reason, setReason] = useState('');

    if (!row) {
        return null;
    }

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onClick={onClose}
        >
            <section
                className="cash-modal max-w-md"
                role="dialog"
                aria-modal="true"
                aria-labelledby="reverse-boarding-title"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="cash-modal-head">
                    <div>
                        <h3 id="reverse-boarding-title">
                            Annuler l'embarquement
                        </h3>
                        <p>
                            {passengerName(row)} · siège {row.seatCode}. Le
                            billet repasse à « émis » et redevient contrôlable.
                            Le motif est obligatoire — la correction est tracée
                            en audit, l'événement d'origine reste inchangé.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                        aria-label="Fermer"
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <div className="px-6 pb-4">
                    <label className="mb-1.5 block text-[0.5625rem] font-black tracking-[0.13em] text-muted-foreground uppercase">
                        Motif de l'annulation
                    </label>
                    <textarea
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        rows={3}
                        maxLength={500}
                        placeholder="Ex. scan erroné, passager non présent à bord…"
                        className="w-full resize-none rounded-xl bg-muted/55 px-3 py-2.5 text-xs font-bold text-foreground ring-1 ring-black/[0.045] outline-none placeholder:font-normal placeholder:text-muted-foreground/60 focus:bg-card focus:ring-2 focus:ring-gold/35"
                    />
                </div>
                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-navy"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        className="btn btn-danger"
                        onClick={() => onConfirm(reason)}
                        disabled={processing || reason.trim().length < 5}
                    >
                        {processing ? 'Traitement…' : "Annuler l'embarquement"}
                    </button>
                </div>
            </section>
        </div>
    );
}

DepartureDetailPage.layout = {
    breadcrumbs: [
        { title: 'Départs', href: '/departures' } satisfies BreadcrumbItem,
        { title: 'Détail départ', href: '' } satisfies BreadcrumbItem,
    ],
};
