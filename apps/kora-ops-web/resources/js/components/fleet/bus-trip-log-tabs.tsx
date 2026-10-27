import { Link } from '@inertiajs/react';
import { AlertTriangle, Camera, Fuel, Gauge } from 'lucide-react';

/**
 * Onglets « Carburant » et « Kilométrage » de la fiche bus — alimentés par
 * le carnet de bord saisi sur KORA OPS Mobile (chauffeur et convoyeur).
 * Les pleins restent des dépenses « en attente » tant qu'ils ne sont pas
 * validés dans Finance > Dépenses.
 */

export type BusMileageTrip = {
    departureUuid: string | null;
    departureLabel: string | null;
    departureAt: string | null;
    startKm: number | null;
    startAt: string | null;
    startBy: string | null;
    endKm: number | null;
    endAt: string | null;
    endBy: string | null;
    distanceKm: number | null;
    anomaly: string | null;
};

export type BusFuelItem = {
    uuid: string;
    reference: string;
    occurredAt: string;
    liters: number | null;
    unitPrice: number | null;
    amount: number | null;
    station: string | null;
    odometerKm: number | null;
    status: string;
    statusLabel: string;
    submittedBy: string | null;
    departureLabel: string | null;
    hasEvidence: boolean;
    url: string;
};

export type BusTripLog = {
    canViewAmounts: boolean;
    mileage: {
        stats: {
            currentKm: number | null;
            lastReadingAt: string | null;
            lastReadingBy: string | null;
            totalDistanceKm: number;
            tripsCount: number;
            averageTripKm: number | null;
            anomalies: number;
        };
        items: BusMileageTrip[];
    };
    fuel: {
        stats: {
            lastFillAt: string | null;
            lastStation: string | null;
            lastLiters: number | null;
            lastAmount: number | null;
            totalLiters: number;
            totalAmount: number | null;
            pendingCount: number;
            averageConsumption: number | null;
        };
        items: BusFuelItem[];
    };
};

const numberFormat = new Intl.NumberFormat('fr-FR');

function km(value: number | null): string {
    return value === null ? '—' : `${numberFormat.format(value)} km`;
}

function money(value: number | null): string {
    return value === null ? '—' : `${numberFormat.format(value)} XOF`;
}

function liters(value: number | null): string {
    return value === null
        ? '—'
        : `${value.toLocaleString('fr-FR', { maximumFractionDigits: 2 })} L`;
}

function dateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function TabHeader({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="mb-4 border-b border-border/60 pb-4">
            <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                Carnet de bord — KORA OPS Mobile
            </span>
            <h3 className="text-[1.1875rem] font-bold text-foreground">
                {title}
            </h3>
            <p className="mt-1 text-[0.625rem] text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function StatGrid({ rows }: { rows: { label: string; value: string }[] }) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {rows.map((row) => (
                <div
                    key={row.label}
                    className="rounded-[0.75rem] bg-muted p-3.5"
                >
                    <small className="block text-[0.5625rem] text-muted-foreground">
                        {row.label}
                    </small>
                    <strong className="mt-1.5 block text-[0.75rem] text-foreground">
                        {row.value}
                    </strong>
                </div>
            ))}
        </div>
    );
}

function EmptyState({
    icon: Icon,
    title,
    description,
}: {
    icon: React.ComponentType<{ className?: string }>;
    title: string;
    description: string;
}) {
    return (
        <div className="rounded-[1rem] border border-dashed border-border/60 bg-muted/20 p-8 text-center">
            <span className="mx-auto grid size-12 place-items-center rounded-[0.9rem] bg-muted text-muted-foreground">
                <Icon className="size-5" />
            </span>
            <h4 className="mt-3 text-sm font-bold text-foreground">{title}</h4>
            <p className="mt-1 text-xs text-muted-foreground">{description}</p>
        </div>
    );
}

const statusClasses: Record<string, string> = {
    pending:
        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    approved:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
};

export function BusFuelTab({ tripLog }: { tripLog: BusTripLog }) {
    const { stats, items } = tripLog.fuel;

    return (
        <div className="space-y-4">
            <TabHeader
                title="Historique carburant"
                description="Pleins déclarés par l'équipage (litres, prix, station, photo du ticket). Validation dans Finance › Dépenses."
            />

            <StatGrid
                rows={[
                    {
                        label: 'Dernier plein',
                        value: dateTime(stats.lastFillAt),
                    },
                    { label: 'Station', value: stats.lastStation ?? '—' },
                    {
                        label: 'Litres (30 derniers)',
                        value: liters(stats.totalLiters),
                    },
                    tripLog.canViewAmounts
                        ? {
                              label: 'Montant (30 derniers)',
                              value: money(stats.totalAmount),
                          }
                        : {
                              label: 'Consommation moy.',
                              value:
                                  stats.averageConsumption === null
                                      ? '—'
                                      : `${stats.averageConsumption.toLocaleString('fr-FR')} L/100 km`,
                          },
                ]}
            />

            {tripLog.canViewAmounts && (
                <p className="text-[0.6875rem] text-muted-foreground">
                    Consommation moyenne estimée :{' '}
                    <strong className="text-foreground">
                        {stats.averageConsumption === null
                            ? '—'
                            : `${stats.averageConsumption.toLocaleString('fr-FR')} L/100 km`}
                    </strong>
                    {stats.pendingCount > 0 && (
                        <>
                            {' '}
                            · {stats.pendingCount} plein(s) en attente de
                            validation
                        </>
                    )}
                </p>
            )}

            {items.length === 0 ? (
                <EmptyState
                    icon={Fuel}
                    title="Aucun plein déclaré"
                    description="Les pleins saisis par le chauffeur ou le convoyeur sur KORA OPS Mobile apparaîtront ici."
                />
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                <th className="pr-4 pb-3 font-medium">Date</th>
                                <th className="pr-4 pb-3 font-medium">
                                    Station
                                </th>
                                <th className="pr-4 pb-3 text-right font-medium">
                                    Litres
                                </th>
                                {tripLog.canViewAmounts && (
                                    <>
                                        <th className="pr-4 pb-3 text-right font-medium">
                                            Prix/L
                                        </th>
                                        <th className="pr-4 pb-3 text-right font-medium">
                                            Montant
                                        </th>
                                    </>
                                )}
                                <th className="pr-4 pb-3 text-right font-medium">
                                    Compteur
                                </th>
                                <th className="pr-4 pb-3 font-medium">
                                    Saisi par
                                </th>
                                <th className="pb-3 font-medium">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr
                                    key={item.uuid}
                                    className="border-b border-border/30 hover:bg-muted/30"
                                >
                                    <td className="py-3 pr-4">
                                        <Link
                                            href={item.url}
                                            className="block font-bold text-foreground hover:underline"
                                        >
                                            {dateTime(item.occurredAt)}
                                        </Link>
                                        <span className="text-xs text-muted-foreground">
                                            {item.departureLabel ??
                                                item.reference}
                                        </span>
                                    </td>
                                    <td className="py-3 pr-4 text-foreground">
                                        <span className="inline-flex items-center gap-1.5">
                                            {item.station ?? '—'}
                                            {item.hasEvidence && (
                                                <Camera
                                                    className="size-3.5 text-muted-foreground"
                                                    aria-label="Photo du ticket"
                                                />
                                            )}
                                        </span>
                                    </td>
                                    <td className="py-3 pr-4 text-right font-medium tabular-nums">
                                        {liters(item.liters)}
                                    </td>
                                    {tripLog.canViewAmounts && (
                                        <>
                                            <td className="py-3 pr-4 text-right tabular-nums">
                                                {money(item.unitPrice)}
                                            </td>
                                            <td className="py-3 pr-4 text-right font-bold tabular-nums">
                                                {money(item.amount)}
                                            </td>
                                        </>
                                    )}
                                    <td className="py-3 pr-4 text-right tabular-nums">
                                        {km(item.odometerKm)}
                                    </td>
                                    <td className="py-3 pr-4 text-xs text-muted-foreground">
                                        {item.submittedBy ?? '—'}
                                    </td>
                                    <td className="py-3">
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-1 text-[0.6875rem] font-bold ${statusClasses[item.status] ?? 'bg-muted text-foreground'}`}
                                        >
                                            {item.statusLabel}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

export function BusMileageTab({ tripLog }: { tripLog: BusTripLog }) {
    const { stats, items } = tripLog.mileage;

    return (
        <div className="space-y-4">
            <TabHeader
                title="Relevés kilométriques"
                description="Compteur relevé par l'équipage au départ et à l'arrivée de chaque voyage."
            />

            <StatGrid
                rows={[
                    { label: 'Kilométrage actuel', value: km(stats.currentKm) },
                    {
                        label: 'Dernier relevé',
                        value: stats.lastReadingAt
                            ? `${dateTime(stats.lastReadingAt)}${stats.lastReadingBy ? ` · ${stats.lastReadingBy}` : ''}`
                            : '—',
                    },
                    {
                        label: `Distance (${stats.tripsCount} voyage${stats.tripsCount > 1 ? 's' : ''})`,
                        value: km(stats.totalDistanceKm),
                    },
                    {
                        label: 'Moyenne par voyage',
                        value: km(stats.averageTripKm),
                    },
                ]}
            />

            {stats.anomalies > 0 && (
                <p className="flex items-center gap-2 rounded-[0.75rem] bg-amber-100 px-3.5 py-2.5 text-xs font-medium text-amber-900 dark:bg-amber-500/15 dark:text-amber-200">
                    <AlertTriangle className="size-4 shrink-0" />
                    {stats.anomalies} relevé(s) incohérent(s) avec le voyage
                    précédent — à vérifier avec l'équipage.
                </p>
            )}

            {items.length === 0 ? (
                <EmptyState
                    icon={Gauge}
                    title="Aucun relevé"
                    description="Le kilométrage saisi au départ et à l'arrivée sur KORA OPS Mobile apparaîtra ici."
                />
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                <th className="pr-4 pb-3 font-medium">
                                    Voyage
                                </th>
                                <th className="pr-4 pb-3 text-right font-medium">
                                    Départ
                                </th>
                                <th className="pr-4 pb-3 text-right font-medium">
                                    Arrivée
                                </th>
                                <th className="pr-4 pb-3 text-right font-medium">
                                    Distance
                                </th>
                                <th className="pb-3 font-medium">Relevé par</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((trip, index) => (
                                <tr
                                    key={trip.departureUuid ?? index}
                                    className="border-b border-border/30 hover:bg-muted/30"
                                >
                                    <td className="py-3 pr-4">
                                        <span className="block font-bold text-foreground">
                                            {trip.departureLabel ?? '—'}
                                        </span>
                                        {trip.anomaly && (
                                            <span className="mt-0.5 flex items-center gap-1 text-[0.6875rem] font-medium text-amber-700 dark:text-amber-300">
                                                <AlertTriangle className="size-3" />
                                                {trip.anomaly}
                                            </span>
                                        )}
                                    </td>
                                    <td className="py-3 pr-4 text-right tabular-nums">
                                        <span className="block font-medium text-foreground">
                                            {km(trip.startKm)}
                                        </span>
                                        <span className="text-[0.6875rem] text-muted-foreground">
                                            {dateTime(trip.startAt)}
                                        </span>
                                    </td>
                                    <td className="py-3 pr-4 text-right tabular-nums">
                                        <span className="block font-medium text-foreground">
                                            {km(trip.endKm)}
                                        </span>
                                        <span className="text-[0.6875rem] text-muted-foreground">
                                            {dateTime(trip.endAt)}
                                        </span>
                                    </td>
                                    <td className="py-3 pr-4 text-right font-bold tabular-nums">
                                        {km(trip.distanceKm)}
                                    </td>
                                    <td className="py-3 text-xs text-muted-foreground">
                                        {[trip.startBy, trip.endBy]
                                            .filter(
                                                (
                                                    name,
                                                    i,
                                                    all,
                                                ): name is string =>
                                                    !!name &&
                                                    all.indexOf(name) === i,
                                            )
                                            .join(' · ') || '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
