import {
    ArrowUpDown,
    Bus,
    Crosshair,
    Gauge,
    MapPin,
    Navigation,
    Smartphone,
    User,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { FleetTrackingSession } from '@/types/tracking';
import { FleetTrackingBadge, formatAge } from './fleet-tracking-badge';

type SortField =
    'bus' | 'line' | 'conductor' | 'speed' | 'accuracy' | 'age' | 'state';

type SortDirection = 'asc' | 'desc';

type Props = {
    sessions: FleetTrackingSession[];
    onSelectSession: (session: FleetTrackingSession) => void;
    onSwitchToMap: (session?: FleetTrackingSession) => void;
    className?: string;
};

export function FleetTrackingTable({
    sessions,
    onSelectSession,
    onSwitchToMap,
    className,
}: Props) {
    const [sortField, setSortField] = useState<SortField>('age');
    const [sortDirection, setSortDirection] = useState<SortDirection>('asc');

    const handleSort = (field: SortField) => {
        if (sortField === field) {
            setSortDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    };

    const sortedSessions = useMemo(() => {
        return [...sessions].sort((a, b) => {
            let res = 0;

            switch (sortField) {
                case 'bus':
                    res = a.bus.registration_number.localeCompare(
                        b.bus.registration_number,
                    );
                    break;
                case 'line':
                    res = (a.departure.line_code ?? '').localeCompare(
                        b.departure.line_code ?? '',
                    );
                    break;
                case 'conductor':
                    res = a.conductor.name.localeCompare(b.conductor.name);
                    break;
                case 'speed':
                    res =
                        (a.position?.speed_kmh ?? -1) -
                        (b.position?.speed_kmh ?? -1);
                    break;
                case 'accuracy':
                    res =
                        (a.position?.accuracy_meters ?? 999) -
                        (b.position?.accuracy_meters ?? 999);
                    break;
                case 'age':
                    res =
                        (a.position?.age_seconds ?? 99999) -
                        (b.position?.age_seconds ?? 99999);
                    break;
                case 'state':
                    res = a.state.localeCompare(b.state);
                    break;
            }

            return sortDirection === 'asc' ? res : -res;
        });
    }, [sessions, sortField, sortDirection]);

    return (
        <div
            className={cn(
                'overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm',
                className,
            )}
        >
            <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                    <thead className="border-b border-border/60 bg-muted/40 text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-3.5">
                                <button
                                    type="button"
                                    onClick={() => handleSort('bus')}
                                    className="flex items-center gap-1.5 hover:text-foreground"
                                >
                                    Bus & Immatriculation
                                    <ArrowUpDown className="size-3" />
                                </button>
                            </th>
                            <th className="px-4 py-3.5">
                                <button
                                    type="button"
                                    onClick={() => handleSort('line')}
                                    className="flex items-center gap-1.5 hover:text-foreground"
                                >
                                    Ligne & Départ
                                    <ArrowUpDown className="size-3" />
                                </button>
                            </th>
                            <th className="px-4 py-3.5">
                                <button
                                    type="button"
                                    onClick={() => handleSort('conductor')}
                                    className="flex items-center gap-1.5 hover:text-foreground"
                                >
                                    Convoyeur & Terminal
                                    <ArrowUpDown className="size-3" />
                                </button>
                            </th>
                            <th className="px-4 py-3.5">
                                <button
                                    type="button"
                                    onClick={() => handleSort('state')}
                                    className="flex items-center gap-1.5 hover:text-foreground"
                                >
                                    État Signal
                                    <ArrowUpDown className="size-3" />
                                </button>
                            </th>
                            <th className="px-4 py-3.5">Dernière Position</th>
                            <th className="px-4 py-3.5">
                                <button
                                    type="button"
                                    onClick={() => handleSort('speed')}
                                    className="flex items-center gap-1.5 hover:text-foreground"
                                >
                                    Vitesse / Cap
                                    <ArrowUpDown className="size-3" />
                                </button>
                            </th>
                            <th className="px-4 py-3.5">
                                <button
                                    type="button"
                                    onClick={() => handleSort('accuracy')}
                                    className="flex items-center gap-1.5 hover:text-foreground"
                                >
                                    Précision
                                    <ArrowUpDown className="size-3" />
                                </button>
                            </th>
                            <th className="px-4 py-3.5">
                                <button
                                    type="button"
                                    onClick={() => handleSort('age')}
                                    className="flex items-center gap-1.5 hover:text-foreground"
                                >
                                    Fraîcheur
                                    <ArrowUpDown className="size-3" />
                                </button>
                            </th>
                            <th className="px-4 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-border/40 font-medium text-foreground">
                        {sortedSessions.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={9}
                                    className="py-12 text-center text-muted-foreground"
                                >
                                    <div className="flex flex-col items-center justify-center gap-2">
                                        <Bus className="size-8 text-muted-foreground/40" />
                                        <span className="text-xs font-bold text-foreground">
                                            Aucun véhicule en circulation trouvé
                                        </span>
                                        <span className="text-[0.6875rem]">
                                            Ajustez vos filtres de recherche.
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            sortedSessions.map((session) => {
                                const pos = session.position;
                                const speed = pos?.speed_kmh;
                                const heading = pos?.heading_degrees;

                                return (
                                    <tr
                                        key={session.session_uuid}
                                        className="group transition-colors hover:bg-muted/30"
                                    >
                                        {/* Bus */}
                                        <td className="px-4 py-3.5">
                                            <div className="flex items-center gap-2">
                                                <span className="rounded-md border border-border/50 bg-muted px-2 py-0.5 font-mono text-xs font-black tracking-tight text-foreground">
                                                    {
                                                        session.bus
                                                            .registration_number
                                                    }
                                                </span>
                                                <span className="max-w-[120px] truncate text-[0.625rem] text-muted-foreground">
                                                    {session.bus.model ??
                                                        'Coach'}
                                                </span>
                                            </div>
                                        </td>

                                        {/* Line & Departure */}
                                        <td className="px-4 py-3.5">
                                            <div>
                                                <span className="text-xs font-black tracking-wider text-primary uppercase dark:text-gold">
                                                    {
                                                        session.departure
                                                            .line_code
                                                    }
                                                </span>
                                                {(session.departure.origin ||
                                                    session.departure
                                                        .destination) && (
                                                    <p className="max-w-[160px] truncate text-[0.625rem] text-muted-foreground">
                                                        {
                                                            session.departure
                                                                .origin
                                                        }{' '}
                                                        ➔{' '}
                                                        {
                                                            session.departure
                                                                .destination
                                                        }
                                                    </p>
                                                )}
                                                <span className="text-[0.5625rem] text-muted-foreground/75">
                                                    Départ :{' '}
                                                    {session.departure.time}
                                                </span>
                                            </div>
                                        </td>

                                        {/* Conductor & Terminal */}
                                        <td className="px-4 py-3.5">
                                            <div>
                                                <div className="flex items-center gap-1 text-xs font-semibold text-foreground">
                                                    <User className="size-3 text-muted-foreground" />
                                                    {session.conductor.name}
                                                </div>
                                                <div className="flex items-center gap-1 text-[0.625rem] text-muted-foreground">
                                                    <Smartphone className="size-2.5" />
                                                    <span className="font-mono">
                                                        {session.terminal
                                                            .serial_number ??
                                                            session.terminal
                                                                .type}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        {/* State */}
                                        <td className="px-4 py-3.5">
                                            <FleetTrackingBadge
                                                state={session.state}
                                                ageSeconds={pos?.age_seconds}
                                                size="sm"
                                            />
                                        </td>

                                        {/* Last Position */}
                                        <td className="px-4 py-3.5">
                                            {pos ? (
                                                <div className="flex items-center gap-1 font-mono text-[0.6875rem] text-foreground">
                                                    <MapPin className="size-3 shrink-0 text-primary" />
                                                    <span>
                                                        {pos.latitude.toFixed(
                                                            4,
                                                        )}
                                                        ,{' '}
                                                        {pos.longitude.toFixed(
                                                            4,
                                                        )}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-[0.625rem] text-muted-foreground/60 italic">
                                                    Indisponible
                                                </span>
                                            )}
                                        </td>

                                        {/* Speed & Heading */}
                                        <td className="px-4 py-3.5">
                                            {speed !== null &&
                                            speed !== undefined ? (
                                                <div className="flex items-center gap-1.5 font-mono font-bold text-foreground">
                                                    <Gauge className="size-3 shrink-0 text-digital" />
                                                    <span>
                                                        {Math.round(speed)} km/h
                                                    </span>
                                                    {heading !== null &&
                                                        heading !==
                                                            undefined && (
                                                            <span
                                                                title={`Cap : ${heading}°`}
                                                            >
                                                                <Navigation
                                                                    className="size-3 text-muted-foreground"
                                                                    style={{
                                                                        transform: `rotate(${heading}deg)`,
                                                                    }}
                                                                />
                                                            </span>
                                                        )}
                                                </div>
                                            ) : (
                                                <span className="text-[0.625rem] text-muted-foreground/60 italic">
                                                    À l'arrêt
                                                </span>
                                            )}
                                        </td>

                                        {/* Accuracy */}
                                        <td className="px-4 py-3.5">
                                            {pos?.accuracy_meters !== null &&
                                            pos?.accuracy_meters !==
                                                undefined ? (
                                                <div className="flex items-center gap-1">
                                                    <span className="font-mono text-xs font-bold text-foreground">
                                                        ± {pos.accuracy_meters}m
                                                    </span>
                                                    <span
                                                        className={`rounded px-1 text-[0.5625rem] font-extrabold uppercase ${
                                                            pos.quality ===
                                                            'accurate'
                                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                                : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                                        }`}
                                                    >
                                                        {pos.quality ===
                                                        'accurate'
                                                            ? 'Opt.'
                                                            : 'Dég.'}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-[0.625rem] text-muted-foreground/60">
                                                    —
                                                </span>
                                            )}
                                        </td>

                                        {/* Age */}
                                        <td className="px-4 py-3.5">
                                            <span className="font-mono text-xs font-bold text-foreground">
                                                {formatAge(pos?.age_seconds)}
                                            </span>
                                            {pos?.recorded_at && (
                                                <p className="text-[0.5625rem] text-muted-foreground">
                                                    {new Date(
                                                        pos.recorded_at,
                                                    ).toLocaleTimeString([], {
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                        second: '2-digit',
                                                    })}
                                                </p>
                                            )}
                                        </td>

                                        {/* Actions */}
                                        <td className="px-4 py-3.5 text-right">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        onSwitchToMap(session)
                                                    }
                                                    className="h-7 gap-1 border-border/70 px-2 text-[0.6875rem] font-bold hover:bg-muted"
                                                    title="Afficher ce bus sur la carte"
                                                >
                                                    <Crosshair className="size-3 text-gold" />
                                                    Carte
                                                </Button>

                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        onSelectSession(session)
                                                    }
                                                    className="h-7 px-2 text-[0.6875rem] font-bold text-primary hover:bg-primary/10"
                                                    title="Ouvrir la fiche télémétrie complète"
                                                >
                                                    Détails
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
