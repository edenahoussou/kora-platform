import { Link } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    Check,
    Clock,
    Copy,
    Crosshair,
    FileText,
    Gauge,
    MapPin,
    Navigation,
    Phone,
    Radio,
    ShieldCheck,
    Smartphone,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { FleetTrackingSession, TrackingPositionSource } from '@/types/tracking';
import { FleetTrackingBadge, formatAge } from './fleet-tracking-badge';

type Props = {
    session: FleetTrackingSession | null;
    open: boolean;
    onClose: () => void;
    onCenterOnMap: (session: FleetTrackingSession) => void;
};

const SOURCE_LABELS: Record<TrackingPositionSource, string> = {
    gps: 'GPS Satellite Direct',
    network: 'Réseau Cellulaire',
    fused: 'Fused Location (Hybride Android)',
    unknown: 'Inconnue / Dégradée',
};

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
}: Props) {
    const [copiedCoords, setCopiedCoords] = useState(false);

    if (!session) {
        return null;
    }

    const pos = session.position;
    const history = session.recent_positions ?? [];

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
                className="w-full sm:max-w-md md:max-w-lg overflow-y-auto p-0 flex flex-col bg-background border-l border-border/60 shadow-2xl z-[500]"
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
                            <div className="grid grid-cols-2 gap-2.5">
                                {/* Speed Gauge Card */}
                                <div className="rounded-xl border border-border/60 bg-card p-3.5 shadow-sm space-y-1">
                                    <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1">
                                        <Gauge className="size-3 text-digital" />
                                        Vitesse instantanée
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

                                {/* Heading Card */}
                                <div className="rounded-xl border border-border/60 bg-card p-3.5 shadow-sm space-y-1">
                                    <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1">
                                        <Navigation className="size-3 text-gold" />
                                        Cap & Orientation
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

                                {/* Precision / Accuracy Card */}
                                <div className="rounded-xl border border-border/60 bg-card p-3.5 shadow-sm space-y-1">
                                    <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1">
                                        <Radio className="size-3 text-emerald-500" />
                                        Précision signal
                                    </span>
                                    <div className="flex items-baseline gap-1.5">
                                        <span className="text-lg font-black font-mono text-foreground">
                                            {pos.accuracy_meters !== null
                                                ? `± ${pos.accuracy_meters} m`
                                                : 'Inconnue'}
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
                                        Rayon d'incertitude satellite
                                    </p>
                                </div>

                                {/* Freshness Card */}
                                <div className="rounded-xl border border-border/60 bg-card p-3.5 shadow-sm space-y-1">
                                    <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1">
                                        <Clock className="size-3 text-amber-500" />
                                        Fraîcheur point
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

                    {/* Terminal & Crew Section */}
                    <section className="space-y-3">
                        <h4 className="text-xs font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <ShieldCheck className="size-3.5 text-primary" />
                            Équipage & Terminal de bord
                        </h4>

                        <div className="rounded-xl border border-border/60 bg-card p-3.5 space-y-3 shadow-sm">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <User className="size-4" />
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
                                <span className="font-semibold text-foreground text-[0.6875rem]">
                                    {pos?.source ? SOURCE_LABELS[pos.source] : 'GPS Android standard'}
                                </span>
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
                            <div className="space-y-2 rounded-xl border border-border/60 bg-card p-3 shadow-sm">
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
