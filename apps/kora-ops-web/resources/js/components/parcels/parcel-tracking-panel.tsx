import {
    AlertTriangle,
    ArrowRight,
    Bus,
    CalendarClock,
    Check,
    ChevronDown,
    Circle,
    Clock3,
    Gauge,
    MapPin,
    Package,
    PackageCheck,
    RefreshCw,
    Route as RouteIcon,
    Timer,
    Truck,
    Weight,
} from 'lucide-react';
import { lazy, Suspense, useMemo, useState } from 'react';
import {
    parcelTrackingEventMeta,
    parcelTrackingStatus,
    parcelTrackingVehicleStatus,
} from '@/components/parcels/parcel-tracking-meta';
import { cn } from '@/lib/utils';
import type {
    ParcelTracking,
    ParcelTrackingEvent,
    ParcelTrackingPoint,
} from '@/types/parcel-tracking';

const ParcelTrackingMap = lazy(() =>
    import('@/components/parcels/parcel-tracking-map').then((module) => ({
        default: module.ParcelTrackingMap,
    })),
);

type Props = {
    tracking: ParcelTracking | null;
    loading: boolean;
    error: string | null;
    onRetry: () => void;
};

type StageState = 'done' | 'current' | 'future';

type JourneyStage = {
    key: string;
    label: string;
    city: string | null;
    name: string | null;
    role: ParcelTrackingPoint['role'] | null;
    state: StageState;
    events: ParcelTrackingEvent[];
    latestAt: string | null;
};

const DATE_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Africa/Porto-Novo',
});

const DATE_ONLY_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeZone: 'Africa/Porto-Novo',
});

const TIME_ONLY_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    timeStyle: 'short',
    timeZone: 'Africa/Porto-Novo',
});

const CLOSED_STATUSES = [
    'withdrawn',
    'cancelled',
    'refused',
    'returned',
    'return_handed_over',
];

const SUMMARY_NOISE_EVENTS = new Set([
    'tracking_link_issued',
    'tracking_link_revoked',
    'print_requested',
    'print_result',
    'handoff_proof_requested',
]);

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '—' : DATE_FORMATTER.format(date);
}

function formatDateOnly(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? '—'
        : DATE_ONLY_FORMATTER.format(date);
}

function formatTimeOnly(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '' : TIME_ONLY_FORMATTER.format(date);
}

function formatWeight(grams: number | null): string {
    if (grams === null) {
        return '—';
    }

    return `${new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 2,
    }).format(grams / 1000)} kg`;
}

function eventPlace(event: ParcelTrackingEvent): string {
    return event.hub_name ?? event.city ?? 'Localisation non renseignée';
}

function stageLabel(point: ParcelTrackingPoint): string {
    if (point.role === 'origin') {
        return 'Départ';
    }

    if (point.role === 'destination') {
        return 'Arrivée';
    }

    return 'Passage';
}

function samePlace(
    event: ParcelTrackingEvent,
    point: ParcelTrackingPoint,
): boolean {
    const eventCity = event.city?.toLocaleLowerCase('fr-FR') ?? null;
    const pointCity = point.city?.toLocaleLowerCase('fr-FR') ?? null;
    const eventHub = event.hub_name?.toLocaleLowerCase('fr-FR') ?? null;
    const pointName = point.name?.toLocaleLowerCase('fr-FR') ?? null;

    return Boolean(
        (eventCity && pointCity && eventCity === pointCity) ||
        (eventHub && pointName && eventHub === pointName),
    );
}

function buildStages(
    tracking: ParcelTracking,
    timeline: ParcelTrackingEvent[],
): JourneyStage[] {
    const closed = CLOSED_STATUSES.includes(tracking.status);
    const route = tracking.planned;
    const fallbackCities = new Map<string, ParcelTrackingEvent[]>();

    for (const event of timeline) {
        const key = event.city ?? event.hub_name ?? 'Transit';
        fallbackCities.set(key, [...(fallbackCities.get(key) ?? []), event]);
    }

    if (route.length === 0) {
        return [...fallbackCities.entries()].map(([city, events], index) => ({
            key: `event-${city}-${index}`,
            label: index === 0 ? 'Départ' : 'Étape',
            city,
            name: null,
            role: null,
            state: closed
                ? 'done'
                : index === fallbackCities.size - 1
                  ? 'current'
                  : 'done',
            events,
            latestAt: events[events.length - 1]?.observed_at ?? null,
        }));
    }

    let currentIndex = closed ? route.length - 1 : 0;

    if (!closed) {
        for (const event of timeline) {
            const index = route.findIndex((point) => samePlace(event, point));

            if (index >= 0) {
                currentIndex = Math.max(currentIndex, index);
            }
        }
    }

    return route.map((point, index) => {
        const events = timeline.filter((event) => samePlace(event, point));
        const unlocatedTransit =
            index === 0
                ? timeline.filter(
                      (event) =>
                          !event.city &&
                          !event.hub_name &&
                          ['in_transit', 'loaded'].includes(event.kind),
                  )
                : [];
        const stageEvents = [...events, ...unlocatedTransit].sort(
            (left, right) =>
                new Date(left.observed_at).getTime() -
                new Date(right.observed_at).getTime(),
        );
        const state: StageState = closed
            ? 'done'
            : index < currentIndex
              ? 'done'
              : index === currentIndex
                ? 'current'
                : 'future';

        return {
            key: `${point.role}-${point.city ?? point.name}-${index}`,
            label: stageLabel(point),
            city: point.city,
            name: point.name,
            role: point.role,
            state,
            events: stageEvents,
            latestAt: stageEvents[stageEvents.length - 1]?.observed_at ?? null,
        };
    });
}

function isClosedStatus(status: string): boolean {
    return CLOSED_STATUSES.includes(status);
}

export function ParcelTrackingPanel({
    tracking,
    loading,
    error,
    onRetry,
}: Props) {
    const [historyOpen, setHistoryOpen] = useState(false);
    const [openStage, setOpenStage] = useState<string | null>(null);

    const status = tracking ? parcelTrackingStatus(tracking.status) : null;
    const timeline = useMemo(() => tracking?.events ?? [], [tracking?.events]);
    const lastEvent = useMemo(
        () =>
            [...timeline]
                .reverse()
                .find((event) => !SUMMARY_NOISE_EVENTS.has(event.kind)) ??
            timeline[timeline.length - 1] ??
            null,
        [timeline],
    );
    const stages = useMemo(
        () => (tracking ? buildStages(tracking, timeline) : []),
        [timeline, tracking],
    );
    const currentStage =
        stages.find((stage) => stage.state === 'current') ??
        stages[stages.length - 1] ??
        null;
    const nextStages = stages.filter((stage) => stage.state === 'future');
    const closed = tracking ? isClosedStatus(tracking.status) : false;
    const progress = tracking
        ? Math.min(100, Math.max(0, Math.round(tracking.progress_ratio * 100)))
        : 0;
    const etaValue = closed
        ? (tracking?.last_event_at ?? tracking?.eta ?? null)
        : (tracking?.eta ?? tracking?.last_event_at ?? null);

    if (loading && !tracking) {
        return (
            <div className="rounded-2xl border border-border bg-muted/20 p-4">
                <div className="h-5 w-48 animate-pulse rounded bg-muted" />
                <div className="mt-3 h-80 animate-pulse rounded-2xl bg-muted" />
            </div>
        );
    }

    if (error && !tracking) {
        return (
            <div className="flex items-start gap-3 rounded-2xl border border-destructive/25 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                <div className="flex-1">
                    <p className="font-bold">Suivi indisponible</p>
                    <p className="mt-1 text-xs leading-5">{error}</p>
                </div>
                <button
                    type="button"
                    onClick={onRetry}
                    className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-destructive/25 px-2.5 py-1.5 text-[11px] font-bold transition hover:bg-destructive/10"
                >
                    <RefreshCw className="size-3" />
                    Réessayer
                </button>
            </div>
        );
    }

    if (!tracking || !status) {
        return null;
    }

    return (
        <section
            aria-labelledby="parcel-tracking-title"
            className="overflow-hidden rounded-2xl border border-border bg-card"
        >
            <div className="relative overflow-hidden bg-kora text-white">
                <div className="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-gold/15 blur-2xl" />
                <div className="relative flex flex-col gap-4 px-5 py-5 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 text-[10px] font-black tracking-[0.16em] text-gold uppercase">
                                <RouteIcon className="size-3.5" />
                                Suivi du colis
                            </span>
                            {tracking.incident_open && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-rose-500/20 px-2.5 py-1 text-[10px] font-black text-rose-100 ring-1 ring-rose-300/30">
                                    <AlertTriangle className="size-3" />
                                    Incident ouvert
                                </span>
                            )}
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-3">
                            <span className="text-2xl leading-none font-black tracking-tight sm:text-[28px]">
                                {status.label}
                            </span>
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-bold text-white/90 ring-1 ring-white/15">
                                <span
                                    className={cn(
                                        'size-1.5 rounded-full',
                                        status.dot,
                                    )}
                                />
                                Statut actuel
                            </span>
                        </div>

                        <h3
                            id="parcel-tracking-title"
                            className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-lg font-black"
                        >
                            {tracking.origin ?? 'Origine'}
                            <ArrowRight className="size-4 text-gold" />
                            {tracking.destination ?? 'Destination'}
                        </h3>
                        <p className="mt-1.5 max-w-xl text-xs leading-5 text-white/70">
                            {status.description}
                        </p>
                    </div>

                    <div className="w-full shrink-0 rounded-2xl border border-white/12 bg-white/[0.07] p-3.5 backdrop-blur lg:w-[21rem]">
                        <p className="flex items-center gap-1.5 text-[9px] font-black tracking-[0.14em] text-white/60 uppercase">
                            <Timer className="size-3 text-gold" />
                            {closed ? 'Dossier clôturé le' : 'Arrivée estimée'}
                        </p>
                        <p className="mt-1 text-xl leading-tight font-black">
                            {formatDateOnly(etaValue)}
                        </p>
                        <p className="mt-0.5 text-xs font-bold text-gold">
                            {formatTimeOnly(etaValue) || '—'}
                        </p>
                        <div className="mt-3">
                            <div className="flex items-center justify-between text-[9px] font-bold text-white/55">
                                <span>Progression du parcours</span>
                                <span>{progress}%</span>
                            </div>
                            <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/15">
                                <div
                                    className="h-full rounded-full bg-gold transition-[width]"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="relative grid grid-cols-2 gap-px border-t border-white/10 bg-white/10 sm:grid-cols-4">
                    <HeroFact
                        icon={Weight}
                        label="Poids"
                        value={formatWeight(tracking.weight_grams)}
                    />
                    <HeroFact
                        icon={Clock3}
                        label="Dernier scan"
                        value={formatDate(tracking.last_event_at)}
                    />
                    <HeroFact
                        icon={Package}
                        label="Retirer avant"
                        value={formatDate(tracking.withdraw_by)}
                    />
                    <HeroFact
                        icon={MapPin}
                        label="Étape actuelle"
                        value={
                            currentStage?.city ??
                            currentStage?.name ??
                            'Parcours terminé'
                        }
                    />
                </div>
            </div>

            {tracking.vehicle && (
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-border bg-gold/[0.06] px-4 py-3 text-xs">
                    <span className="inline-flex items-center gap-1.5 font-black text-kora">
                        <Bus className="size-4 text-gold" />
                        {tracking.vehicle.registration_number ??
                            'Bus non affecté'}
                    </span>
                    {(tracking.vehicle.line_code ||
                        tracking.vehicle.line_name) && (
                        <span className="inline-flex items-center gap-1.5 font-semibold text-muted-foreground">
                            <RouteIcon className="size-3.5 text-gold" />
                            {tracking.vehicle.line_code}
                            {tracking.vehicle.line_name
                                ? ` · ${tracking.vehicle.line_name}`
                                : ''}
                        </span>
                    )}
                    {tracking.vehicle.departure_at && (
                        <span className="inline-flex items-center gap-1.5 font-semibold text-muted-foreground">
                            <CalendarClock className="size-3.5 text-gold" />
                            Départ {formatDate(tracking.vehicle.departure_at)}
                        </span>
                    )}
                    {tracking.vehicle.position && (
                        <span className="inline-flex items-center gap-1.5 font-semibold text-muted-foreground">
                            <Gauge className="size-3.5 text-gold" />
                            {tracking.vehicle.position.speed_kmh !== null
                                ? `${Math.round(tracking.vehicle.position.speed_kmh)} km/h`
                                : 'Position GPS'}
                            {' · '}
                            <span
                                className={
                                    tracking.vehicle.position.fresh
                                        ? 'font-bold text-emerald-700'
                                        : 'font-bold text-amber-700'
                                }
                            >
                                {tracking.vehicle.position.fresh
                                    ? 'position récente'
                                    : 'dernière position'}
                            </span>
                        </span>
                    )}
                    {tracking.vehicle.status && (
                        <span className="ml-auto rounded-full bg-white px-2.5 py-1 text-[10px] font-bold text-muted-foreground ring-1 ring-border">
                            {parcelTrackingVehicleStatus(
                                tracking.vehicle.status,
                            )}
                        </span>
                    )}
                </div>
            )}

            <div className="p-4">
                <Suspense
                    fallback={
                        <div className="h-[340px] animate-pulse rounded-2xl bg-muted" />
                    }
                >
                    <ParcelTrackingMap
                        planned={tracking.planned}
                        traveled={tracking.traveled}
                        current={tracking.current}
                        vehicle={tracking.vehicle}
                        progressRatio={tracking.progress_ratio}
                    />
                </Suspense>

                <div className="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] font-bold text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="h-0.5 w-5 rounded-full border-t-2 border-dashed border-kora/45" />
                        Itinéraire prévu
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span className="h-1 w-5 rounded-full bg-kora" />
                        Trajet parcouru
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2.5 rounded-full bg-gold ring-2 ring-kora" />
                        Bus / position actuelle
                    </span>
                    <span className="ml-auto inline-flex items-center gap-1 text-muted-foreground/70">
                        <MapPin className="size-3" />
                        Survolez ou cliquez un point
                    </span>
                </div>

                <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
                    <div className="overflow-hidden rounded-2xl border border-border bg-white">
                        <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                            <div>
                                <p className="text-[10px] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    Étapes du parcours
                                </p>
                                <p className="mt-0.5 text-xs font-bold text-foreground">
                                    {stages.length} étapes · {timeline.length}{' '}
                                    scans
                                </p>
                            </div>
                            <span className="rounded-full bg-kora/5 px-2.5 py-1 text-[10px] font-black text-kora ring-1 ring-kora/10">
                                {progress}% effectué
                            </span>
                        </div>
                        <div className="h-1 bg-muted">
                            <div
                                className="h-full bg-gold transition-[width]"
                                style={{ width: `${progress}%` }}
                            />
                        </div>

                        <ol className="p-3">
                            {stages.map((stage, index) => (
                                <StageRow
                                    key={stage.key}
                                    stage={stage}
                                    last={index === stages.length - 1}
                                    open={openStage === stage.key}
                                    onToggle={() =>
                                        setOpenStage((value) =>
                                            value === stage.key
                                                ? null
                                                : stage.key,
                                        )
                                    }
                                />
                            ))}
                        </ol>
                    </div>

                    <div className="space-y-3">
                        {closed ? (
                            <div className="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4">
                                <div className="flex items-center gap-2 text-emerald-800">
                                    <span className="grid size-8 place-items-center rounded-full bg-emerald-600 text-white">
                                        <PackageCheck className="size-4" />
                                    </span>
                                    <div>
                                        <p className="text-[10px] font-black tracking-[0.12em] uppercase">
                                            Parcours terminé
                                        </p>
                                        <p className="text-sm font-black">
                                            {status.label}
                                        </p>
                                    </div>
                                </div>
                                <p className="mt-2 text-[11px] leading-5 text-emerald-900/80">
                                    Dernier scan&nbsp;: {formatDate(etaValue)}.
                                    Aucune étape à venir sur ce dossier.
                                </p>
                            </div>
                        ) : (
                            <div className="rounded-2xl border border-gold/35 bg-gold/[0.08] p-4">
                                <p className="flex items-center gap-1.5 text-[10px] font-black tracking-[0.14em] text-kora uppercase">
                                    <Truck className="size-3.5 text-gold" />
                                    Prochaine étape
                                </p>
                                {nextStages.length > 0 ? (
                                    <>
                                        <p className="mt-1.5 text-lg font-black text-kora">
                                            {nextStages[0].city ??
                                                nextStages[0].name}
                                        </p>
                                        <p className="mt-0.5 text-[11px] font-semibold text-muted-foreground">
                                            {nextStages[0].label} ·{' '}
                                            {nextStages.length > 1
                                                ? `${nextStages.length} étapes restantes`
                                                : 'dernière étape'}
                                        </p>
                                        {tracking.eta && (
                                            <p className="mt-2 inline-flex items-center gap-1 rounded-lg bg-white/80 px-2 py-1 text-[10px] font-bold text-kora ring-1 ring-gold/30">
                                                <Timer className="size-3 text-gold" />
                                                ETA {formatDate(tracking.eta)}
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <p className="mt-1.5 text-sm font-black text-kora">
                                            En cours de traitement
                                        </p>
                                        <p className="mt-1 text-[11px] leading-5 text-muted-foreground">
                                            Le prochain scan mettra à jour cette
                                            étape.
                                        </p>
                                    </>
                                )}
                            </div>
                        )}

                        <div className="rounded-2xl border border-border bg-muted/15 p-4">
                            <p className="text-[10px] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                Dernier événement
                            </p>
                            {lastEvent ? (
                                <>
                                    <p className="mt-1.5 text-sm font-black text-foreground">
                                        {
                                            parcelTrackingEventMeta(
                                                lastEvent.kind,
                                            ).label
                                        }
                                    </p>
                                    <p className="mt-1 text-[11px] leading-5 text-muted-foreground">
                                        {
                                            parcelTrackingEventMeta(
                                                lastEvent.kind,
                                            ).help
                                        }
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] font-bold text-muted-foreground">
                                        <span className="inline-flex items-center gap-1">
                                            <MapPin className="size-2.5" />
                                            {eventPlace(lastEvent)}
                                        </span>
                                        <span className="inline-flex items-center gap-1">
                                            <Clock3 className="size-2.5" />
                                            {formatDate(lastEvent.observed_at)}
                                        </span>
                                    </div>
                                </>
                            ) : (
                                <p className="mt-1.5 text-[11px] text-muted-foreground">
                                    Aucun événement pour le moment.
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                {timeline.length > 0 && (
                    <div className="mt-4">
                        <button
                            type="button"
                            onClick={() => setHistoryOpen((value) => !value)}
                            aria-expanded={historyOpen}
                            className="inline-flex items-center gap-1.5 rounded-lg px-1.5 py-1 text-[11px] font-bold text-kora transition hover:bg-kora/5"
                        >
                            <ChevronDown
                                className={cn(
                                    'size-3.5 transition-transform',
                                    historyOpen && 'rotate-180',
                                )}
                            />
                            {historyOpen
                                ? 'Masquer l’historique détaillé'
                                : `Historique détaillé (${timeline.length})`}
                        </button>

                        {historyOpen && (
                            <ol className="mt-2 divide-y divide-border/70 rounded-xl border border-border bg-muted/15">
                                {timeline.map((event, index) => {
                                    const meta = parcelTrackingEventMeta(
                                        event.kind,
                                    );
                                    const current =
                                        index === timeline.length - 1;

                                    return (
                                        <li
                                            key={`${event.kind}-${event.observed_at}-${index}-detail`}
                                            className="flex gap-3 px-3 py-2.5"
                                        >
                                            <span
                                                className={cn(
                                                    'mt-0.5 grid size-5 shrink-0 place-items-center rounded-full',
                                                    current
                                                        ? 'bg-gold text-kora'
                                                        : 'bg-white text-kora ring-1 ring-border',
                                                )}
                                            >
                                                {current ? (
                                                    <span className="size-1.5 rounded-full bg-kora" />
                                                ) : (
                                                    <Check className="size-3" />
                                                )}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-xs font-black text-foreground">
                                                        {meta.label}
                                                    </span>
                                                    {!event.public && (
                                                        <span className="rounded bg-kora/8 px-1.5 py-0.5 text-[9px] font-black tracking-wide text-kora uppercase">
                                                            OPS
                                                        </span>
                                                    )}
                                                    {current && (
                                                        <span className="rounded bg-gold/20 px-1.5 py-0.5 text-[9px] font-black tracking-wide text-kora uppercase">
                                                            Actuel
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="mt-0.5 text-[11px] leading-4 text-muted-foreground">
                                                    {meta.help}
                                                </p>
                                                <div className="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-[10px] font-semibold text-muted-foreground">
                                                    <span className="inline-flex items-center gap-1">
                                                        <MapPin className="size-2.5" />
                                                        {eventPlace(event)}
                                                    </span>
                                                    <span className="inline-flex items-center gap-1">
                                                        <Clock3 className="size-2.5" />
                                                        {formatDate(
                                                            event.observed_at,
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </div>
                )}

                {timeline.length === 0 && (
                    <div className="mt-4 rounded-xl border border-dashed border-border bg-muted/20 px-4 py-6 text-center">
                        <Truck className="mx-auto size-6 text-muted-foreground/40" />
                        <p className="mt-2 text-xs font-bold text-foreground">
                            Aucun événement pour le moment
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Le parcours s’enrichira dès la première opération.
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}

function HeroFact({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Weight;
    label: string;
    value: string;
}) {
    return (
        <div className="bg-kora px-4 py-2.5">
            <p className="flex items-center gap-1 text-[9px] font-black tracking-wide text-white/50 uppercase">
                <Icon className="size-2.5 text-gold" />
                {label}
            </p>
            <p className="mt-0.5 truncate text-[11px] font-bold text-white">
                {value}
            </p>
        </div>
    );
}

function StageRow({
    stage,
    last,
    open,
    onToggle,
}: {
    stage: JourneyStage;
    last: boolean;
    open: boolean;
    onToggle: () => void;
}) {
    const hasDetails = stage.events.length > 0;

    return (
        <li className="relative pl-7">
            {!last && (
                <span
                    className={cn(
                        'absolute top-5 bottom-0 left-[9px] w-px',
                        stage.state === 'done' ? 'bg-kora/35' : 'bg-border',
                    )}
                />
            )}
            <span
                className={cn(
                    'absolute top-1 left-0 grid size-[19px] place-items-center rounded-full',
                    stage.state === 'done' &&
                        'bg-kora text-white ring-2 ring-kora/15',
                    stage.state === 'current' &&
                        'bg-gold text-kora ring-2 ring-gold/30',
                    stage.state === 'future' &&
                        'bg-white text-muted-foreground ring-2 ring-border',
                )}
            >
                {stage.state === 'done' ? (
                    <Check className="size-3" />
                ) : stage.state === 'current' ? (
                    <span className="size-1.5 animate-pulse rounded-full bg-kora" />
                ) : (
                    <Circle className="size-2.5" />
                )}
            </span>

            <div className={cn('pb-4', last && 'pb-0')}>
                <button
                    type="button"
                    onClick={hasDetails ? onToggle : undefined}
                    disabled={!hasDetails}
                    className={cn(
                        'flex w-full items-start justify-between gap-2 rounded-lg px-2 py-1.5 text-left transition',
                        hasDetails && 'hover:bg-muted/60',
                        !hasDetails && 'cursor-default',
                    )}
                >
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span
                                className={cn(
                                    'text-xs font-black',
                                    stage.state === 'current'
                                        ? 'text-kora'
                                        : stage.state === 'future'
                                          ? 'text-muted-foreground'
                                          : 'text-foreground',
                                )}
                            >
                                {stage.label} · {stage.city ?? stage.name}
                            </span>
                            {stage.state === 'current' && (
                                <span className="rounded-full bg-gold/20 px-1.5 py-0.5 text-[9px] font-black tracking-wide text-kora uppercase">
                                    Actuel
                                </span>
                            )}
                        </div>
                        <p className="mt-0.5 text-[10px] font-semibold text-muted-foreground">
                            {stage.role === 'origin'
                                ? 'Point de départ du colis'
                                : stage.role === 'destination'
                                  ? 'Hub de destination'
                                  : 'Arrêt du parcours'}
                        </p>
                    </div>
                    <div className="shrink-0 text-right">
                        {stage.latestAt ? (
                            <p className="text-[10px] font-bold text-foreground">
                                {formatDate(stage.latestAt)}
                            </p>
                        ) : (
                            <p className="text-[10px] font-bold text-muted-foreground/70">
                                À venir
                            </p>
                        )}
                        {hasDetails && (
                            <p className="mt-0.5 inline-flex items-center gap-1 text-[9px] font-semibold text-muted-foreground">
                                {stage.events.length} scan
                                {stage.events.length > 1 ? 's' : ''}
                                <ChevronDown
                                    className={cn(
                                        'size-2.5 transition-transform',
                                        open && 'rotate-180',
                                    )}
                                />
                            </p>
                        )}
                    </div>
                </button>

                {open && hasDetails && (
                    <ul className="mt-1 space-y-1 rounded-lg bg-muted/35 p-2">
                        {stage.events.map((event, index) => {
                            const meta = parcelTrackingEventMeta(event.kind);

                            return (
                                <li
                                    key={`${event.kind}-${event.observed_at}-${index}`}
                                    className="flex items-center justify-between gap-2 rounded-md bg-white/80 px-2 py-1.5 ring-1 ring-border/60"
                                >
                                    <span className="text-[10px] font-bold text-foreground">
                                        {meta.label}
                                    </span>
                                    <span className="text-[9px] font-semibold text-muted-foreground">
                                        {formatDate(event.observed_at)}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </li>
    );
}
