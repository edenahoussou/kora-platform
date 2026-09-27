import { Link } from '@inertiajs/react';
import {
    Activity,
    AlertOctagon,
    ArrowUpRight,
    Check,
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    Clock,
    Copy,
    Crosshair,
    FileText,
    Gauge,
    MapPin,
    Navigation,
    Phone,
    Radio,
    Route,
    ShieldCheck,
    Smartphone,
    TriangleAlert,
    User,
    UserCheck,
    Users,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { KoraTooltip } from '@/components/kora-tooltip';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type {
    FleetTrackingSession,
    TrackingCrewMember,
    TrackingIncident,
    TrackingPositionSource,
} from '@/types/tracking';
import {
    FleetTrackingBadge,
    INCIDENT_STATUS_META,
    INCIDENT_TYPE_META,
    countActiveIncidents,
    formatAge,
} from './fleet-tracking-badge';

type Props = {
    session: FleetTrackingSession | null;
    open: boolean;
    onClose: () => void;
    onCenterOnMap: (session: FleetTrackingSession) => void;
    onShowRoute?: (session: FleetTrackingSession) => void;
    onLocateIncident?: (incident: TrackingIncident) => void;
};

const SOURCE_LABELS: Record<TrackingPositionSource, string> = {
    gps: 'GPS Satellite Direct',
    network: 'Réseau Cellulaire',
    fused: 'Fused Location (Hybride Android)',
    unknown: 'Inconnue / Dégradée',
};

const ADHERENCE_META = {
    on_route: {
        label: 'Sur itinéraire',
        badgeClass:
            'bg-emerald-500/10 text-emerald-700 border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30',
        icon: Check,
        description: 'Le bus progresse dans le couloir de la ligne prévue.',
    },
    deviation: {
        label: 'Écart détecté',
        badgeClass:
            'bg-amber-500/10 text-amber-700 border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-400 dark:border-amber-500/30',
        icon: TriangleAlert,
        description: 'La position actuelle sort du couloir de la ligne prévue.',
    },
    insufficient_data: {
        label: 'Données insuffisantes',
        badgeClass:
            'bg-muted text-muted-foreground border-border/40',
        icon: CircleHelp,
        description: 'Pas assez de points GPS récents pour vérifier la trajectoire.',
    },
    unavailable: {
        label: 'Itinéraire indisponible',
        badgeClass:
            'bg-muted text-muted-foreground border-border/40',
        icon: AlertOctagon,
        description: 'Aucun arrêt géolocalisé pour tracer la ligne prévue.',
    },
} as const;

function formatDeviation(meters: number | null): string | null {
    if (meters === null) {
        return null;
    }

    if (meters >= 1000) {
        return `${(meters / 1000).toFixed(1).replace('.', ',')} km`;
    }

    return `${Math.round(meters)} m`;
}

function formatIncidentAge(isoDate: string | null): string {
    if (!isoDate) {
        return 'Heure inconnue';
    }

    const seconds = Math.max(
        0,
        Math.round((Date.now() - new Date(isoDate).getTime()) / 1000),
    );

    return formatAge(seconds);
}

const INCIDENT_STATUS_ORDER: Record<string, number> = {
    open: 0,
    in_progress: 1,
    resolved: 2,
    cancelled: 3,
};

const INCIDENTS_PER_PAGE = 2;

function formatHeadingText(degrees: number | null): string {
    if (degrees === null || degrees === undefined) {
        return 'Non disponible';
    }

    const directions = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'];
    const index = Math.round(((degrees % 360) / 45)) % 8;

    return `${degrees}° (${directions[index]})`;
}

export function FleetVehicleDetail({
    session,
    open,
    onClose,
    onCenterOnMap,
    onShowRoute,
    onLocateIncident,
}: Props) {
    const [copiedCoords, setCopiedCoords] = useState(false);
    const [incidentPage, setIncidentPage] = useState(0);
    const [incidentSlideDir, setIncidentSlideDir] = useState<'left' | 'right'>('right');
    const [lastSessionUuid, setLastSessionUuid] = useState(session?.session_uuid);

    if (session?.session_uuid !== lastSessionUuid) {
        setLastSessionUuid(session?.session_uuid);
        setIncidentPage(0);
        setIncidentSlideDir('right');
    }

    if (!session) {
        return null;
    }

    const pos = session.position;
    const history = session.recent_positions ?? [];
    const plannedStops = session.planned_stops;
    const adherence = session.route_adherence;
    const incidents = session.incidents;
    const crew = session.crew;
    const sortedIncidents = [...(incidents ?? [])].sort((a, b) => {
        const ta = a.occurred_at ? new Date(a.occurred_at).getTime() : 0;
        const tb = b.occurred_at ? new Date(b.occurred_at).getTime() : 0;

        if (tb !== ta) {
            return tb - ta;
        }

        return (
            (INCIDENT_STATUS_ORDER[a.status] ?? 9) -
            (INCIDENT_STATUS_ORDER[b.status] ?? 9)
        );
    });
    const activeIncidentCount = countActiveIncidents(session);
    const incidentTotalPages = Math.max(
        1,
        Math.ceil(sortedIncidents.length / INCIDENTS_PER_PAGE),
    );
    const safeIncidentPage = Math.min(incidentPage, incidentTotalPages - 1);
    const visibleIncidents = sortedIncidents.slice(
        safeIncidentPage * INCIDENTS_PER_PAGE,
        safeIncidentPage * INCIDENTS_PER_PAGE + INCIDENTS_PER_PAGE,
    );
    const handleIncidentPage = (next: number) => {
        setIncidentSlideDir(next > safeIncidentPage ? 'right' : 'left');
        setIncidentPage(next);
    };
    const adherenceMeta = adherence ? ADHERENCE_META[adherence.status] : null;
    const AdherenceIcon = adherenceMeta?.icon ?? CircleHelp;
    const deviationLabel = adherence
        ? formatDeviation(adherence.distance_from_route_m)
        : null;

    const crewRows: Array<{
        key: keyof NonNullable<FleetTrackingSession['crew']>;
        label: string;
        icon: typeof User;
        member: TrackingCrewMember | null | undefined;
    }> = [
        { key: 'driver', label: 'Chauffeur principal', icon: User, member: crew?.driver },
        { key: 'secondary_driver', label: 'Chauffeur secondaire', icon: Users, member: crew?.secondary_driver },
        { key: 'conductor', label: 'Convoyeur', icon: UserCheck, member: crew?.conductor },
        { key: 'mechanic', label: 'Mécanicien', icon: Wrench, member: crew?.mechanic },
    ];

    const handleCopyCoords = () => {
        if (!pos?.latitude || !pos?.longitude) {
            return;
        }

        const text = `${pos.latitude.toFixed(6)}, ${pos.longitude.toFixed(6)}`;
        navigator.clipboard.writeText(text);
        setCopiedCoords(true);
        toast.success(`Coordonnées copiées : ${text}`);
        setTimeout(() => setCopiedCoords(false), 2000);
    };

    return (
        <Sheet open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <SheetContent
                side="right"
                className="w-full sm:max-w-xl md:max-w-2xl overflow-y-auto p-0 flex flex-col bg-background border-l border-border/60 shadow-2xl z-[500]"
            >
                {/* Header */}
                <SheetHeader className="p-5 border-b border-border/50 bg-muted/20 space-y-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="font-mono text-base font-black tracking-tight text-foreground bg-primary/10 dark:bg-primary/20 text-primary dark:text-gold px-2.5 py-1 rounded-lg border border-primary/20">
                                {session.bus.registration_number}
                            </span>
                            <span className="text-xs font-bold text-muted-foreground">
                                {session.bus.model ?? 'Bus Interurbain'}
                            </span>
                        </div>
                        <FleetTrackingBadge
                            state={session.state}
                            ageSeconds={pos?.age_seconds}
                            size="md"
                        />
                    </div>

                    <div>
                        <SheetTitle className="text-lg font-extrabold text-foreground flex items-center gap-2">
                            {session.departure.line_code}
                            <span className="text-xs font-semibold text-muted-foreground">
                                • {session.departure.origin} ➔ {session.departure.destination}
                            </span>
                        </SheetTitle>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Départ planifié à {session.departure.time} le {session.departure.date}
                        </p>
                    </div>

                    {/* Quick Ops Actions Bar */}
                    <div className="flex items-center gap-2 pt-1">
                        <Button
                            size="sm"
                            onClick={() => onCenterOnMap(session)}
                            className="h-8 gap-1.5 rounded-lg bg-primary text-white hover:bg-primary/90 text-xs font-bold shadow-sm"
                        >
                            <Crosshair className="size-3.5 text-gold" />
                            Centrer sur carte
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="h-8 gap-1 rounded-lg border-border/70 text-xs font-semibold"
                        >
                            <Link href={`/departures/${session.departure.uuid}`}>
                                <ArrowUpRight className="size-3 text-muted-foreground" />
                                Fiche départ
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="h-8 gap-1 rounded-lg border-border/70 text-xs font-semibold"
                        >
                            <Link href={`/manifest/${session.departure.uuid}`}>
                                <FileText className="size-3 text-muted-foreground" />
                                Manifeste
                            </Link>
                        </Button>
                    </div>
                </SheetHeader>

                {/* Content Body */}
                <div className="flex-1 p-5 space-y-6">
                    {/* Live Telemetry Box */}
                    <section className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="text-xs font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Activity className="size-3.5 text-digital" />
                                Télémétrie GPS en direct
                            </h4>
                            {pos?.recorded_at && (
                                <span className="text-[0.625rem] text-muted-foreground">
                                    Reçu à {new Date(pos.recorded_at).toLocaleTimeString()}
                                </span>
                            )}
                        </div>

                        {pos ? (
                            <div className="grid gap-2.5 sm:grid-cols-2">
                                {/* Card: Speed + Heading */}
                                <div className="rounded-xl border border-border/60 bg-card shadow-sm">
                                    <div className="grid grid-cols-2 divide-x divide-border/50">
                                        <KoraTooltip
                                            side="top"
                                            content={
                                                <>
                                                    <span className="block font-black">Vitesse instantanée</span>
                                                    Calculée par le GPS du terminal Android à partir du déplacement
                                                    entre deux trames consécutives (Δdistance ÷ Δtemps).
                                                    0 km/h = véhicule à l'arrêt.
                                                </>
                                            }
                                        >
                                            <div className="p-3.5 space-y-1 cursor-help">
                                            <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1 underline decoration-dotted decoration-muted-foreground/40 underline-offset-2">
                                                <Gauge className="size-3 text-digital" />
                                                Vitesse
                                            </span>
                                            <div className="flex items-baseline gap-1">
                                                <span className="text-2xl font-black font-mono tracking-tight text-foreground">
                                                    {pos.speed_kmh !== null ? Math.round(pos.speed_kmh) : 0}
                                                </span>
                                                <span className="text-xs font-bold text-muted-foreground">
                                                    km/h
                                                </span>
                                            </div>
                                            <div className="w-full bg-muted rounded-full h-1.5 overflow-hidden mt-1.5">
                                                <div
                                                    className="bg-digital h-full rounded-full transition-all duration-500"
                                                    style={{
                                                        width: `${Math.min(100, ((pos.speed_kmh ?? 0) / 100) * 100)}%`,
                                                    }}
                                                />
                                            </div>
                                            </div>
                                        </KoraTooltip>

                                        <KoraTooltip
                                            side="top"
                                            content={
                                                <>
                                                    <span className="block font-black">Cap &amp; orientation</span>
                                                    Cap boussole en degrés (0–360°, 0 = Nord) remonté par le
                                                    GPS — il oriente l'encoche du marqueur bus sur la carte.
                                                </>
                                            }
                                        >
                                            <div className="p-3.5 space-y-1 cursor-help">
                                            <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1 underline decoration-dotted decoration-muted-foreground/40 underline-offset-2">
                                                <Navigation className="size-3 text-gold" />
                                                Cap
                                            </span>
                                            <div className="flex items-baseline gap-1">
                                                <span className="text-lg font-black font-mono tracking-tight text-foreground">
                                                    {formatHeadingText(pos.heading_degrees)}
                                                </span>
                                            </div>
                                            <p className="text-[0.625rem] text-muted-foreground truncate">
                                                Direction de progression
                                            </p>
                                            </div>
                                        </KoraTooltip>
                                    </div>
                                </div>

                                {/* Card: Accuracy + Freshness */}
                                <div className="rounded-xl border border-border/60 bg-card shadow-sm">
                                    <div className="grid grid-cols-2 divide-x divide-border/50">
                                        <KoraTooltip
                                            side="top"
                                            content={
                                                <>
                                                    <span className="block font-black">Précision du signal</span>
                                                    Rayon d'incertitude du fix GPS en mètres : plus la valeur
                                                    est faible, plus la position est fiable. Optimal ≤ ~10 m,
                                                    dégradé au-delà (zone dense, ciel couvert).
                                                </>
                                            }
                                        >
                                            <div className="p-3.5 space-y-1 cursor-help">
                                            <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1 underline decoration-dotted decoration-muted-foreground/40 underline-offset-2">
                                                <Radio className="size-3 text-emerald-500" />
                                                Précision
                                            </span>
                                            <div className="flex items-baseline gap-1.5">
                                                <span className="text-lg font-black font-mono text-foreground">
                                                    {pos.accuracy_meters !== null
                                                        ? `± ${pos.accuracy_meters} m`
                                                        : '—'}
                                                </span>
                                                <span
                                                    className={`rounded px-1.5 py-0.5 text-[0.5625rem] font-extrabold uppercase ${
                                                        pos.quality === 'accurate'
                                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                            : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                                    }`}
                                                >
                                                    {pos.quality === 'accurate' ? 'Optimal' : 'Dégradé'}
                                                </span>
                                            </div>
                                            <p className="text-[0.625rem] text-muted-foreground">
                                                Incertitude satellite
                                            </p>
                                            </div>
                                        </KoraTooltip>

                                        <KoraTooltip
                                            side="top"
                                            content={
                                                <>
                                                    <span className="block font-black">Fraîcheur du point</span>
                                                    Temps écoulé depuis la réception de la dernière trame GPS
                                                    par le serveur : &lt; 90 s = en direct, &gt; 90 s =
                                                    position ancienne.
                                                </>
                                            }
                                        >
                                            <div className="p-3.5 space-y-1 cursor-help">
                                            <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1 underline decoration-dotted decoration-muted-foreground/40 underline-offset-2">
                                                <Clock className="size-3 text-amber-500" />
                                                Fraîcheur
                                            </span>
                                            <div className="flex items-baseline gap-1">
                                                <span className="text-base font-black text-foreground">
                                                    {formatAge(pos.age_seconds)}
                                                </span>
                                            </div>
                                            <p className="text-[0.625rem] text-muted-foreground">
                                                Dernière trame reçue
                                            </p>
                                            </div>
                                        </KoraTooltip>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed border-border/80 p-4 text-center text-xs text-muted-foreground">
                                Aucune coordonnée GPS active pour ce véhicule.
                            </div>
                        )}

                        {/* Coordinates Bar with Copy */}
                        {pos?.latitude && pos?.longitude && (
                            <div className="flex items-center justify-between rounded-xl border border-border/60 bg-muted/30 px-3.5 py-2.5">
                                <div className="flex items-center gap-2">
                                    <MapPin className="size-4 text-primary shrink-0" />
                                    <span className="font-mono text-xs font-bold text-foreground">
                                        {pos.latitude.toFixed(6)}, {pos.longitude.toFixed(6)}
                                    </span>
                                </div>

                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleCopyCoords}
                                    className="h-7 gap-1 px-2 text-xs font-semibold text-muted-foreground hover:text-foreground"
                                >
                                    {copiedCoords ? (
                                        <>
                                            <Check className="size-3 text-emerald-500" />
                                            Copié
                                        </>
                                    ) : (
                                        <>
                                            <Copy className="size-3" />
                                            Copier
                                        </>
                                    )}
                                </Button>
                            </div>
                        )}
                    </section>

                    {/* Planned Route & Adherence Section */}
                    <section className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="text-xs font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Route className="size-3.5 text-gold" />
                                Itinéraire prévu & adhérence
                            </h4>
                            {plannedStops && plannedStops.length >= 2 && onShowRoute && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => onShowRoute(session)}
                                    className="h-7 gap-1 rounded-lg border-gold/50 px-2 text-[0.6875rem] font-bold hover:bg-gold/10"
                                >
                                    <Route className="size-3 text-gold" />
                                    Voir sur la carte
                                </Button>
                            )}
                        </div>

                        {plannedStops === undefined ? (
                            <div className="rounded-xl border border-dashed border-border/80 p-3.5 text-center text-xs text-muted-foreground">
                                Itinéraire prévu non exposé par le contrat actuel
                                (extension du contrat tracking prévue).
                            </div>
                        ) : plannedStops.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border/80 p-3.5 text-center text-xs text-muted-foreground">
                                Aucun arrêt géolocalisé sur cette ligne.
                            </div>
                        ) : (
                            <div className="rounded-xl border border-border/60 bg-card shadow-sm overflow-hidden">
                                {/* Adherence status banner */}
                                {adherence && adherenceMeta && (
                                    <div
                                        className={cn(
                                            'flex items-center gap-2.5 border-b border-border/40 px-3.5 py-2.5',
                                            adherence.status === 'deviation' &&
                                                'bg-amber-500/5 dark:bg-amber-500/10',
                                            adherence.status === 'on_route' &&
                                                'bg-emerald-500/5 dark:bg-emerald-500/10',
                                        )}
                                    >
                                        <AdherenceIcon
                                            className={cn(
                                                'size-4 shrink-0',
                                                adherence.status === 'on_route' &&
                                                    'text-emerald-600 dark:text-emerald-400',
                                                adherence.status === 'deviation' &&
                                                    'text-amber-600 dark:text-amber-400',
                                                (adherence.status === 'insufficient_data' ||
                                                    adherence.status === 'unavailable') &&
                                                    'text-muted-foreground',
                                            )}
                                        />
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <KoraTooltip
                                                    side="top"
                                                    content={
                                                        <>
                                                            <span className="block font-black">Adhérence à l'itinéraire</span>
                                                            Écart mesuré entre la position GPS du bus et le
                                                            couloir théorique de la ligne (segments reliant les
                                                            arrêts géolocalisés). Au-delà du seuil de tolérance,
                                                            l'écart est signalé.
                                                        </>
                                                    }
                                                >
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center rounded-full border px-2 py-0.5 text-[0.625rem] font-extrabold uppercase tracking-wide cursor-help',
                                                            adherenceMeta.badgeClass,
                                                        )}
                                                    >
                                                        {adherenceMeta.label}
                                                    </span>
                                                </KoraTooltip>
                                                {deviationLabel && (
                                                    <span className="text-[0.625rem] font-bold text-amber-700 dark:text-amber-400 font-mono">
                                                        ± {deviationLabel} du couloir
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-[0.625rem] text-muted-foreground mt-0.5 leading-snug">
                                                {adherenceMeta.description}
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {/* Planned stops horizontal stepper */}
                                <div className="overflow-x-auto p-3.5">
                                    <div className="flex min-w-max items-start">
                                        {plannedStops.map((stop, index) => {
                                            const isFirst = index === 0;
                                            const isLast = index === plannedStops.length - 1;
                                            const isTerminus = isFirst || isLast;

                                            return (
                                                <div
                                                    key={stop.uuid}
                                                    className="flex min-w-[88px] max-w-[110px] flex-1 flex-col items-center text-center"
                                                >
                                                    <div className="flex w-full items-center">
                                                        <div
                                                            className={cn(
                                                                'h-0 flex-1 border-t-2 border-dashed border-gold/60',
                                                                isFirst && 'border-transparent',
                                                            )}
                                                        />
                                                        <span
                                                            className={cn(
                                                                'shrink-0 rounded-full border-2 border-gold',
                                                                isTerminus
                                                                    ? 'size-3 bg-gold'
                                                                    : 'size-2.5 bg-background',
                                                            )}
                                                        />
                                                        <div
                                                            className={cn(
                                                                'h-0 flex-1 border-t-2 border-dashed border-gold/60',
                                                                isLast && 'border-transparent',
                                                            )}
                                                        />
                                                    </div>
                                                    <p
                                                        className={cn(
                                                            'mt-1.5 w-full truncate px-0.5 text-[0.6875rem]',
                                                            isTerminus
                                                                ? 'font-extrabold text-foreground'
                                                                : 'font-semibold text-foreground/90',
                                                        )}
                                                        title={`${stop.name} — ${stop.city}`}
                                                    >
                                                        {stop.name}
                                                    </p>
                                                    <p className="text-[0.5625rem] text-muted-foreground">
                                                        {stop.km_from_origin !== null
                                                            ? `km ${stop.km_from_origin}`
                                                            : stop.city}
                                                        {isFirst && ' • Origine'}
                                                        {isLast && ' • Dest.'}
                                                    </p>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        )}
                    </section>

                    {/* Field Incidents Section */}
                    <section className="space-y-3">
                        <div className="flex items-center justify-between">
                            <KoraTooltip
                                side="top"
                                content={
                                    <>
                                        <span className="block font-black">Incidents terrain</span>
                                        Signalements envoyés par le convoyeur depuis le terminal
                                        P501 embarqué : panne, retard, accident, sécurité ou autre
                                        événement. Les plus récents s'affichent en premier.
                                    </>
                                }
                            >
                                <h4 className="text-xs font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1.5 cursor-help">
                                    <TriangleAlert
                                        className={cn(
                                            'size-3.5',
                                            activeIncidentCount > 0
                                                ? 'text-rose-500'
                                                : 'text-muted-foreground',
                                        )}
                                    />
                                    Incidents terrain
                                </h4>
                            </KoraTooltip>
                            <div className="flex items-center gap-2">
                                {activeIncidentCount > 0 && (
                                    <span className="inline-flex items-center rounded-full bg-rose-500/15 border border-rose-500/30 px-2 py-0.5 text-[0.625rem] font-extrabold uppercase tracking-wide text-rose-700 dark:text-rose-400">
                                        {activeIncidentCount} actif{activeIncidentCount > 1 ? 's' : ''}
                                    </span>
                                )}
                                {sortedIncidents.length > INCIDENTS_PER_PAGE && (
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            onClick={() => handleIncidentPage(safeIncidentPage - 1)}
                                            disabled={safeIncidentPage === 0}
                                            className="size-6 rounded-md border-border/60 disabled:opacity-40"
                                            title="Incidents précédents"
                                        >
                                            <ChevronLeft className="size-3.5" />
                                        </Button>
                                        <span className="min-w-10 text-center text-[0.625rem] font-bold tabular-nums text-muted-foreground">
                                            {safeIncidentPage * INCIDENTS_PER_PAGE + 1}–
                                            {Math.min(
                                                (safeIncidentPage + 1) * INCIDENTS_PER_PAGE,
                                                sortedIncidents.length,
                                            )}
                                            /{sortedIncidents.length}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            onClick={() => handleIncidentPage(safeIncidentPage + 1)}
                                            disabled={safeIncidentPage >= incidentTotalPages - 1}
                                            className="size-6 rounded-md border-border/60 disabled:opacity-40"
                                            title="Incidents suivants"
                                        >
                                            <ChevronRight className="size-3.5" />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>

                        {incidents === undefined ? (
                            <div className="rounded-xl border border-dashed border-border/80 p-3.5 text-center text-xs text-muted-foreground">
                                Incidents non exposés par le contrat actuel
                                (extension du contrat tracking prévue).
                            </div>
                        ) : sortedIncidents.length === 0 ? (
                            <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-500/10 p-3.5 text-center">
                                <p className="text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                    Aucun incident déclaré sur ce départ
                                </p>
                                <p className="text-[0.625rem] text-muted-foreground mt-0.5">
                                    Le convoyeur n’a signalé aucune anomalie.
                                </p>
                            </div>
                        ) : (
                            <div
                                key={safeIncidentPage}
                                className={cn(
                                    'grid gap-2 sm:grid-cols-2 animate-in fade-in duration-500 ease-out',
                                    incidentSlideDir === 'right'
                                        ? 'slide-in-from-right-6'
                                        : 'slide-in-from-left-6',
                                )}
                            >
                                {visibleIncidents.map((incident) => {
                                    const typeMeta = INCIDENT_TYPE_META[incident.type];
                                    const statusMeta = INCIDENT_STATUS_META[incident.status];
                                    const TypeIcon = typeMeta.icon;
                                    const isActive =
                                        incident.status === 'open' ||
                                        incident.status === 'in_progress';
                                    const isCancelled = incident.status === 'cancelled';
                                    const hasCoords =
                                        incident.latitude !== null &&
                                        incident.longitude !== null;

                                    return (
                                        <div
                                            key={incident.uuid}
                                            className={cn(
                                                'rounded-xl border p-3 shadow-sm',
                                                isActive
                                                    ? 'border-rose-500/30 bg-rose-500/5 dark:bg-rose-500/10 dark:border-rose-500/20'
                                                    : 'border-border/60 bg-card',
                                                isCancelled && 'opacity-60',
                                            )}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex items-center gap-2 min-w-0">
                                                    <span
                                                        className={cn(
                                                            'flex size-7 shrink-0 items-center justify-center rounded-lg',
                                                            isActive
                                                                ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400'
                                                                : 'bg-muted text-muted-foreground',
                                                        )}
                                                    >
                                                        <TypeIcon className="size-3.5" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="text-xs font-extrabold text-foreground">
                                                            {typeMeta.label}
                                                        </p>
                                                        <p className="text-[0.625rem] text-muted-foreground">
                                                            {formatIncidentAge(incident.occurred_at)}
                                                            {incident.reporter_name &&
                                                                ` • par ${incident.reporter_name}`}
                                                        </p>
                                                    </div>
                                                </div>

                                                <span
                                                    className={cn(
                                                        'inline-flex shrink-0 items-center rounded-full border px-1.5 py-0.5 text-[0.5625rem] font-extrabold uppercase tracking-wide',
                                                        statusMeta.badgeClass,
                                                    )}
                                                >
                                                    {statusMeta.label}
                                                </span>
                                            </div>

                                            <p className="mt-2 text-[0.6875rem] leading-snug text-foreground/85">
                                                {incident.description}
                                            </p>

                                            {hasCoords && onLocateIncident && (
                                                <button
                                                    type="button"
                                                    onClick={() => onLocateIncident(incident)}
                                                    className="mt-2 inline-flex items-center gap-1 text-[0.6875rem] font-bold text-primary hover:underline"
                                                >
                                                    <MapPin className="size-3" />
                                                    Voir sur la carte
                                                </button>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </section>

                    {/* Terminal & Crew Section */}
                    <section className="space-y-3">
                        <h4 className="text-xs font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <ShieldCheck className="size-3.5 text-primary" />
                            Équipage & Terminal de bord
                        </h4>

                        <div className="rounded-xl border border-border/60 bg-card p-3.5 space-y-1 shadow-sm">
                            {crew === undefined ? (
                                <div className="flex items-center justify-between pb-2.5">
                                    <div className="flex items-center gap-2.5">
                                        <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <UserCheck className="size-4" />
                                        </div>
                                        <div>
                                            <p className="text-xs font-bold text-foreground">
                                                {session.conductor.name}
                                            </p>
                                            <p className="text-[0.625rem] text-muted-foreground">
                                                Convoyeur responsable
                                            </p>
                                        </div>
                                    </div>

                                    {session.conductor.phone && (
                                        <a
                                            href={`tel:${session.conductor.phone}`}
                                            className="inline-flex items-center gap-1 rounded-lg border border-border/70 px-2.5 py-1 text-xs font-semibold text-foreground hover:bg-muted"
                                        >
                                            <Phone className="size-3 text-muted-foreground" />
                                            Appeler
                                        </a>
                                    )}
                                </div>
                            ) : (
                                <div className="grid gap-x-4 sm:grid-cols-2">
                                    {crewRows.map((row) => {
                                        const RowIcon = row.icon;

                                        return (
                                            <div
                                                key={row.key}
                                                className="flex items-center justify-between py-2 border-b border-border/40 sm:[&:nth-last-child(-n+2)]:border-b-0"
                                            >
                                                <div className="flex items-center gap-2.5 min-w-0">
                                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                        <RowIcon className="size-4" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="text-xs font-bold text-foreground truncate">
                                                            {row.member?.name ?? 'Non affecté'}
                                                        </p>
                                                        <p className="text-[0.625rem] text-muted-foreground">
                                                            {row.label}
                                                        </p>
                                                    </div>
                                                </div>

                                                {row.member?.phone && (
                                                    <a
                                                        href={`tel:${row.member.phone}`}
                                                        className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-border/70 px-2 py-0.5 text-[0.6875rem] font-semibold text-foreground hover:bg-muted"
                                                        title={`Appeler ${row.member.name}`}
                                                    >
                                                        <Phone className="size-3 text-muted-foreground" />
                                                        Appeler
                                                    </a>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            <div className="pt-2.5 border-t border-border/40 flex items-center justify-between text-xs">
                                <div className="flex items-center gap-2">
                                    <Smartphone className="size-3.5 text-muted-foreground" />
                                    <span className="font-medium text-muted-foreground">
                                        Terminal POS :
                                    </span>
                                    <span className="font-mono font-bold text-foreground">
                                        {session.terminal.serial_number ?? session.terminal.type.toUpperCase()}
                                    </span>
                                </div>
                                <span className="rounded bg-muted px-1.5 py-0.5 text-[0.625rem] font-bold text-muted-foreground uppercase">
                                    {session.terminal.type}
                                </span>
                            </div>

                            <div className="flex items-center justify-between text-xs pt-1 text-muted-foreground">
                                <span className="text-[0.6875rem]">Source Android :</span>
                                <KoraTooltip
                                    side="top"
                                    content={
                                        <>
                                            <span className="block font-black">Source de localisation</span>
                                            Méthode utilisée par le terminal : GPS satellite direct,
                                            réseau cellulaire (approximatif) ou fusion hybride
                                            GPS + réseau.
                                        </>
                                    }
                                >
                                    <span className="font-semibold text-foreground text-[0.6875rem] cursor-help underline decoration-dotted decoration-muted-foreground/40 underline-offset-2">
                                        {pos?.source ? SOURCE_LABELS[pos.source] : 'GPS Android standard'}
                                    </span>
                                </KoraTooltip>
                            </div>
                        </div>
                    </section>

                    {/* Short Trajectory History Timeline */}
                    <section className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="text-xs font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Clock className="size-3.5 text-digital" />
                                Historique récent du trajet ({history.length} points)
                            </h4>
                        </div>

                        {history.length > 0 ? (
                            <div className="grid gap-x-5 rounded-xl border border-border/60 bg-card p-3 shadow-sm sm:grid-cols-2">
                                {history.map((point, index) => {
                                    const timeStr = new Date(point.recorded_at).toLocaleTimeString([], {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit',
                                    });
                                    const isMostRecent = index === 0;

                                    return (
                                        <div
                                            key={point.uuid}
                                            className="flex items-center justify-between text-xs py-1.5 border-b border-border/30 last:border-0"
                                        >
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className={`size-2 rounded-full ${
                                                        isMostRecent ? 'bg-emerald-500' : 'bg-muted-foreground/40'
                                                    }`}
                                                />
                                                <span className="font-mono text-[0.6875rem] font-bold text-foreground">
                                                    {timeStr}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <span className="font-mono font-bold text-[0.6875rem] text-digital">
                                                    {point.speed_kmh ?? 0} km/h
                                                </span>
                                                <span className="font-mono text-[0.625rem] text-muted-foreground">
                                                    {point.latitude.toFixed(4)}, {point.longitude.toFixed(4)}
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed border-border/80 p-3 text-center text-xs text-muted-foreground">
                                Aucun historique supplémentaire enregistré pour cette session.
                            </div>
                        )}
                    </section>
                </div>
            </SheetContent>
        </Sheet>
    );
}
