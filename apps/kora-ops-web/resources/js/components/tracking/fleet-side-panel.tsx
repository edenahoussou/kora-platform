import {
    Bus,
    ChevronLeft,
    ChevronRight,
    Gauge,
    Navigation,
    Search,
    TriangleAlert,
    User,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { FleetTrackingSession } from '@/types/tracking';
import { FleetTrackingBadge, countActiveIncidents } from './fleet-tracking-badge';

type Props = {
    sessions: FleetTrackingSession[];
    selectedSessionUuid: string | null;
    onSelectSession: (session: FleetTrackingSession) => void;
    collapsed: boolean;
    onToggleCollapse: () => void;
    className?: string;
};

export function FleetSidePanel({
    sessions,
    selectedSessionUuid,
    onSelectSession,
    collapsed,
    onToggleCollapse,
    className,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [stateFilter, setStateFilter] = useState<string>('all');

    const filteredSessions = useMemo(() => {
        return sessions.filter((session) => {
            if (stateFilter !== 'all' && session.state !== stateFilter) {
                return false;
            }

            if (!searchQuery.trim()) {
                return true;
            }

            const q = searchQuery.toLowerCase();
            const busMatch = session.bus.registration_number.toLowerCase().includes(q);
            const lineMatch =
                session.departure.line_code?.toLowerCase().includes(q) ||
                session.departure.line_name?.toLowerCase().includes(q) ||
                session.departure.origin?.toLowerCase().includes(q) ||
                session.departure.destination?.toLowerCase().includes(q);
            const condMatch = session.conductor.name.toLowerCase().includes(q);
            const termMatch = session.terminal.serial_number?.toLowerCase().includes(q);

            return busMatch || lineMatch || condMatch || termMatch;
        });
    }, [sessions, searchQuery, stateFilter]);

    if (collapsed) {
        return (
            <div className="flex flex-col items-center justify-between border-r border-border/60 bg-card p-2 w-14 shrink-0 transition-all duration-300">
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={onToggleCollapse}
                    className="size-10 rounded-xl hover:bg-muted"
                    title="Déplier la liste de flotte"
                >
                    <ChevronRight className="size-5 text-muted-foreground" />
                </Button>

                <div className="my-auto flex flex-col items-center gap-3">
                    <Bus className="size-5 text-primary opacity-60" />
                    <span className="rotate-90 whitespace-nowrap text-[0.625rem] font-black uppercase tracking-widest text-muted-foreground">
                        {sessions.length} bus actifs
                    </span>
                </div>

                <div className="flex flex-col gap-1.5 items-center">
                    <span className="size-2 rounded-full bg-emerald-500" title="En direct" />
                    <span className="size-2 rounded-full bg-amber-500" title="Ancien" />
                </div>
            </div>
        );
    }

    return (
        <aside
            className={cn(
                'flex flex-col border-r border-border/60 bg-card w-80 md:w-96 lg:w-[26rem] xl:w-[28rem] shrink-0 h-full overflow-hidden transition-all duration-300',
                className,
            )}
        >
            {/* Header / Search Area */}
            <div className="p-3.5 border-b border-border/50 space-y-2.5 bg-muted/20">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Bus className="size-4" />
                        </div>
                        <div>
                            <h3 className="text-xs font-black uppercase tracking-wider text-foreground">
                                Véhicules en route
                            </h3>
                            <p className="text-[0.625rem] text-muted-foreground">
                                {filteredSessions.length} sur {sessions.length} bus
                            </p>
                        </div>
                    </div>

                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={onToggleCollapse}
                        className="size-8 rounded-lg text-muted-foreground hover:text-foreground"
                        title="Replier le volet"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                </div>

                {/* Search Bar */}
                <div className="relative">
                    <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Bus, ligne, convoyeur, P501…"
                        className="h-8 pl-8 pr-2 text-xs rounded-lg bg-background border-border/60 focus-visible:ring-1 focus-visible:ring-primary"
                    />
                </div>

                {/* State Filter Chips */}
                <div className="flex items-center gap-1 overflow-x-auto pb-0.5 text-[0.625rem]">
                    {[
                        { key: 'all', label: 'Tous' },
                        { key: 'live', label: 'En direct' },
                        { key: 'stale', label: 'Ancien' },
                        { key: 'gps_unavailable', label: 'Sans GPS' },
                    ].map((f) => (
                        <button
                            key={f.key}
                            type="button"
                            onClick={() => setStateFilter(f.key)}
                            className={cn(
                                'rounded-full px-2.5 py-0.5 font-bold transition-all whitespace-nowrap',
                                stateFilter === f.key
                                    ? 'bg-primary text-white shadow-sm'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80',
                            )}
                        >
                            {f.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* List of Vehicles */}
            <div className="flex-1 overflow-y-auto p-3 space-y-2.5">
                {filteredSessions.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-center px-4">
                        <Bus className="size-8 text-muted-foreground/40 mb-2" />
                        <p className="text-xs font-bold text-foreground">
                            Aucun bus correspondant
                        </p>
                        <p className="text-[0.6875rem] text-muted-foreground mt-1">
                            Ajustez vos termes de recherche ou réinitialisez les filtres.
                        </p>
                    </div>
                ) : (
                    filteredSessions.map((session) => {
                        const isSelected = session.session_uuid === selectedSessionUuid;
                        const speed = session.position?.speed_kmh;
                        const heading = session.position?.heading_degrees;
                        const activeIncidents = countActiveIncidents(session);

                        return (
                            <button
                                key={session.session_uuid}
                                type="button"
                                onClick={() => onSelectSession(session)}
                                className={cn(
                                    'w-full text-left rounded-xl p-3.5 border transition-all duration-200 cursor-pointer',
                                    isSelected
                                        ? 'border-gold bg-gold/5 shadow-md ring-1 ring-gold dark:bg-gold/10'
                                        : 'border-border/60 bg-card hover:border-border hover:bg-muted/30 hover:shadow-sm',
                                )}
                            >
                                {/* Top Row: Bus & State */}
                                <div className="flex items-center justify-between gap-2 mb-1.5">
                                    <div className="flex items-center gap-1.5">
                                        <span className="font-mono text-xs font-black tracking-tight text-foreground bg-muted px-2 py-0.5 rounded-md border border-border/50">
                                            {session.bus.registration_number}
                                        </span>
                                        {session.departure.line_code && (
                                            <span className="text-[0.625rem] font-extrabold text-primary dark:text-gold uppercase tracking-wider">
                                                {session.departure.line_code}
                                            </span>
                                        )}
                                        {activeIncidents > 0 && (
                                            <span
                                                className="inline-flex items-center gap-0.5 rounded-full bg-rose-500/15 border border-rose-500/30 px-1.5 py-0.5 text-[0.5625rem] font-extrabold text-rose-700 dark:text-rose-400"
                                                title={`${activeIncidents} incident(s) actif(s) déclaré(s)`}
                                            >
                                                <TriangleAlert className="size-2.5" />
                                                {activeIncidents}
                                            </span>
                                        )}
                                    </div>
                                    <FleetTrackingBadge
                                        state={session.state}
                                        ageSeconds={session.position?.age_seconds}
                                        showPulse={false}
                                        size="sm"
                                    />
                                </div>

                                {/* Line Route */}
                                {(session.departure.origin || session.departure.destination) && (
                                    <p className="text-[0.6875rem] font-medium text-muted-foreground truncate mb-2">
                                        {session.departure.origin} ➔ {session.departure.destination}
                                    </p>
                                )}

                                {/* Bottom Telemetry Row */}
                                <div className="flex items-center justify-between pt-2 border-t border-border/40 text-[0.625rem] text-muted-foreground">
                                    <div className="flex items-center gap-1">
                                        <User className="size-3 text-muted-foreground/70" />
                                        <span className="truncate max-w-[150px]">
                                            {session.conductor.name}
                                        </span>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {speed !== null && speed !== undefined ? (
                                            <span className="flex items-center gap-1 font-mono font-bold text-foreground">
                                                <Gauge className="size-3 text-digital" />
                                                {Math.round(speed)} km/h
                                            </span>
                                        ) : (
                                            <span className="italic text-muted-foreground/60">
                                                À l'arrêt
                                            </span>
                                        )}

                                        {heading !== null && heading !== undefined && (
                                            <span title={`Cap : ${heading}°`}>
                                                <Navigation
                                                    className="size-3 text-muted-foreground"
                                                    style={{
                                                        transform: `rotate(${heading}deg)`,
                                                    }}
                                                />
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </button>
                        );
                    })
                )}
            </div>
        </aside>
    );
}
