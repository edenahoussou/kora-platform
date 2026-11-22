import {
    AlertTriangle,
    ArrowRight,
    Bus,
    CalendarClock,
    Check,
    ChevronDown,
    Clock3,
    Gauge,
    MapPin,
    Package,
    RefreshCw,
    Route as RouteIcon,
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

const DATE_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Africa/Porto-Novo',
});

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '—' : DATE_FORMATTER.format(date);
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

export function ParcelTrackingPanel({
    tracking,
    loading,
    error,
    onRetry,
}: Props) {
    const [historyOpen, setHistoryOpen] = useState(false);

    const status = tracking ? parcelTrackingStatus(tracking.status) : null;
    const timeline = useMemo(() => tracking?.events ?? [], [tracking?.events]);
    const destinationStep = useMemo(() => {
        if (!tracking?.destination) {
            return null;
        }

        const last = timeline[timeline.length - 1];
        const closed = [
            'withdrawn',
            'cancelled',
            'refused',
            'returned',
            'return_handed_over',
        ].includes(tracking.status);

        if (closed || last?.city === tracking.destination) {
            return null;
        }

        return {
            kind: 'destination',
            label: 'Arrivée prévue',
            city: tracking.destination,
            at: tracking.eta,
        };
    }, [timeline, tracking]);

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
            <div className="flex flex-col gap-4 border-b border-border bg-muted/20 px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center gap-1.5 text-[11px] font-black tracking-[0.14em] text-muted-foreground uppercase">
                            <RouteIcon className="size-3.5 text-gold" />
                            Suivi du colis
                        </span>
                        <span
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-black ring-1',
                                status.badge,
                            )}
                        >
                            <span
                                className={cn(
                                    'size-1.5 rounded-full',
                                    status.dot,
                                )}
                            />
                            {status.label}
                        </span>
                        {tracking.incident_open && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2.5 py-1 text-[10px] font-black text-destructive ring-1 ring-destructive/20">
                                <AlertTriangle className="size-3" />
                                Incident ouvert
                            </span>
                        )}
                    </div>
                    <h3
                        id="parcel-tracking-title"
                        className="mt-2 text-base font-black text-foreground"
                    >
                        {tracking.origin ?? 'Origine'}{' '}
                        <span className="text-gold">→</span>{' '}
                        {tracking.destination ?? 'Destination'}
                    </h3>
                    <p className="mt-1 max-w-2xl text-xs leading-5 text-muted-foreground">
                        {status.description}
                    </p>
                </div>

                <div className="grid shrink-0 grid-cols-2 gap-2 sm:w-[19rem]">
                    <TrackingFact
                        icon={Weight}
                        label="Poids"
                        value={formatWeight(tracking.weight_grams)}
                    />
                    <TrackingFact
                        icon={CalendarClock}
                        label="Arrivée estimée"
                        value={formatDate(tracking.eta)}
                    />
                    <TrackingFact
                        icon={Clock3}
                        label="Dernière étape"
                        value={formatDate(tracking.last_event_at)}
                    />
                    <TrackingFact
                        icon={Package}
                        label="Retirer avant"
                        value={formatDate(tracking.withdraw_by)}
                    />
                </div>
            </div>

            {tracking.vehicle && (
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-border bg-kora/[0.035] px-4 py-3 text-xs">
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
                        <div className="h-[320px] animate-pulse rounded-2xl bg-muted" />
                    }
                >
                    <ParcelTrackingMap
                        planned={tracking.planned}
                        traveled={tracking.traveled}
                        current={tracking.current}
                        vehicle={tracking.vehicle}
                    />
                </Suspense>

                <div className="mt-2.5 flex flex-wrap items-center gap-x-5 gap-y-2 text-[10px] font-bold text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="h-0.5 w-5 rounded-full border-t-2 border-dashed border-gold" />
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
                    <span className="ml-auto hidden items-center gap-1 text-muted-foreground/70 sm:inline-flex">
                        <MapPin className="size-3" />
                        Survolez un point pour son détail
                    </span>
                </div>

                {timeline.length > 0 && (
                    <div className="mt-5">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-[10px] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                Progression
                            </p>
                            <span className="text-[10px] font-bold text-muted-foreground">
                                {Math.round(tracking.progress_ratio * 100)}% du
                                trajet
                            </span>
                        </div>

                        <div className="mt-3 overflow-x-auto pb-2">
                            <ol className="flex min-w-max items-start gap-0">
                                {timeline.map((event, index) => {
                                    const meta = parcelTrackingEventMeta(
                                        event.kind,
                                    );
                                    const current =
                                        index === timeline.length - 1 &&
                                        !destinationStep;

                                    return (
                                        <StepperStep
                                            key={`${event.kind}-${event.observed_at}-${index}`}
                                            label={meta.label}
                                            place={
                                                event.city ?? eventPlace(event)
                                            }
                                            at={event.observed_at}
                                            state={current ? 'current' : 'done'}
                                            last={
                                                index === timeline.length - 1 &&
                                                !destinationStep
                                            }
                                        />
                                    );
                                })}
                                {destinationStep && (
                                    <StepperStep
                                        label={destinationStep.label}
                                        place={destinationStep.city}
                                        at={destinationStep.at}
                                        state="future"
                                        last
                                    />
                                )}
                            </ol>
                        </div>

                        <button
                            type="button"
                            onClick={() => setHistoryOpen((value) => !value)}
                            aria-expanded={historyOpen}
                            className="mt-2 inline-flex items-center gap-1.5 rounded-lg px-1.5 py-1 text-[11px] font-bold text-kora transition hover:bg-kora/5"
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

function TrackingFact({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Weight;
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-white/80 px-2.5 py-2">
            <p className="flex items-center gap-1 text-[9px] font-black tracking-wide text-muted-foreground uppercase">
                <Icon className="size-2.5 text-gold" />
                {label}
            </p>
            <p className="mt-0.5 truncate text-[11px] font-bold text-foreground">
                {value}
            </p>
        </div>
    );
}

function StepperStep({
    label,
    place,
    at,
    state,
    last,
}: {
    label: string;
    place: string | null;
    at: string | null;
    state: 'done' | 'current' | 'future';
    last?: boolean;
}) {
    return (
        <li className="relative w-[9.5rem] shrink-0 pr-1">
            <div className="flex items-center">
                <span
                    className={cn(
                        'relative z-10 grid size-5 shrink-0 place-items-center rounded-full text-[9px]',
                        state === 'done' &&
                            'bg-kora text-white ring-2 ring-kora/15',
                        state === 'current' &&
                            'bg-gold text-kora ring-2 ring-gold/25',
                        state === 'future' &&
                            'bg-white text-muted-foreground ring-2 ring-border',
                    )}
                >
                    {state === 'done' ? (
                        <Check className="size-3" />
                    ) : state === 'current' ? (
                        <span className="size-1.5 animate-pulse rounded-full bg-kora" />
                    ) : (
                        <ArrowRight className="size-3" />
                    )}
                </span>
                {!last && (
                    <span
                        className={cn(
                            'h-0.5 flex-1',
                            state === 'done' ? 'bg-kora/50' : 'bg-border',
                        )}
                    />
                )}
            </div>
            <div className="mt-1.5 pr-2">
                <p
                    className={cn(
                        'text-[10px] leading-3.5 font-black',
                        state === 'current'
                            ? 'text-kora'
                            : state === 'future'
                              ? 'text-muted-foreground'
                              : 'text-foreground',
                    )}
                >
                    {label}
                </p>
                <p className="mt-0.5 truncate text-[9px] font-semibold text-muted-foreground">
                    {place ?? '—'}
                </p>
                {at && (
                    <p className="mt-0.5 text-[9px] text-muted-foreground/80">
                        {formatDate(at)}
                    </p>
                )}
            </div>
        </li>
    );
}
