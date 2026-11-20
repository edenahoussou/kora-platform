import {
    AlertTriangle,
    CheckCircle2,
    Coins,
    Download,
    Gauge,
    Package,
    PackageCheck,
    RefreshCw,
    RotateCcw,
    ShieldAlert,
    Truck,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { ParcelKpi } from '@/components/parcels/parcel-kpi';
import { parcelStatusLabel } from '@/components/parcels/status';
import type {
    ParcelSupervision,
    ParcelSupervisionAlert,
    ParcelSupervisionCapacityDeparture,
    ParcelSupervisionCorridorRow,
    ParcelSupervisionIncident,
    ParcelSupervisionResponse,
    ParcelSupervisionStatusRow,
} from '@/components/parcels/types';

function formatDateTime(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('fr-FR').format(value);
}

function formatGrams(value: number | null): string {
    return value === null ? 'Non définie' : `${formatNumber(value)} g`;
}

function responseMessage(payload: unknown, fallback: string): string {
    if (
        payload !== null &&
        typeof payload === 'object' &&
        'message' in payload &&
        typeof payload.message === 'string'
    ) {
        return payload.message;
    }

    return fallback;
}

const severityClass: Record<string, string> = {
    blocking: 'border-red-300/60 bg-red-50 text-red-900',
    warning: 'border-amber-300/60 bg-amber-50 text-amber-900',
};

type Props = {
    refreshKey?: number;
};

export function ParcelSupervisionPanel({ refreshKey = 0 }: Props) {
    const [data, setData] = useState<ParcelSupervision | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch('/parcels/data/supervision', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const payload = (await response
                .json()
                .catch(() => null)) as ParcelSupervisionResponse | null;

            if (!response.ok || payload === null) {
                setError(
                    responseMessage(
                        payload,
                        'La supervision Colis est indisponible.',
                    ),
                );
                setData(null);

                return;
            }

            setData(payload.data);
        } catch {
            setError(
                'Le réseau est instable. La supervision Colis est indisponible.',
            );
            setData(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        void load();
    }, [load, refreshKey]);

    if (loading) {
        return (
            <section className="flex min-h-60 items-center justify-center rounded-[18px] border border-border bg-card">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <RefreshCw className="size-4 animate-spin" />
                    Chargement de la supervision Colis…
                </div>
            </section>
        );
    }

    if (error !== null || data === null) {
        return (
            <section className="rounded-[18px] border border-red-200 bg-red-50 px-5 py-5 text-sm text-red-800">
                <strong className="block">Supervision indisponible</strong>
                <p className="mt-1">{error ?? 'Aucune donnée reçue.'}</p>
                <ParcelButton
                    type="button"
                    variant="ghost"
                    className="mt-4"
                    onClick={() => void load()}
                >
                    <RefreshCw className="size-4" />
                    Réessayer
                </ParcelButton>
            </section>
        );
    }

    const statusColumns: TableColumn<ParcelSupervisionStatusRow>[] = [
        {
            key: 'status',
            header: 'Statut',
            render: (row) => (
                <span className="font-bold">
                    {parcelStatusLabel(row.status)}
                </span>
            ),
        },
        {
            key: 'count',
            header: 'Colis',
            align: 'right',
            render: (row) => formatNumber(row.count),
        },
    ];
    const corridorColumns: TableColumn<ParcelSupervisionCorridorRow>[] = [
        {
            key: 'label',
            header: 'Corridor',
            render: (row) => <span className="font-bold">{row.label}</span>,
        },
        {
            key: 'state',
            header: 'État',
            render: (row) => (
                <span
                    className={
                        row.enabled
                            ? 'text-xs font-bold text-emerald-700'
                            : 'text-xs font-bold text-muted-foreground'
                    }
                >
                    {row.enabled ? 'Actif' : 'Inactif'}
                </span>
            ),
        },
        {
            key: 'count',
            header: 'Colis',
            align: 'right',
            render: (row) => formatNumber(row.count),
        },
    ];
    const incidentColumns: TableColumn<ParcelSupervisionIncident>[] = [
        {
            key: 'reference',
            header: 'Colis',
            render: (row) => (
                <div>
                    <strong className="block text-xs">
                        {row.parcel_reference ?? '—'}
                    </strong>
                    <span className="text-[0.6875rem] text-muted-foreground">
                        {row.kind}
                    </span>
                </div>
            ),
        },
        {
            key: 'description',
            header: 'Incident',
            render: (row) => (
                <span className="line-clamp-2 text-xs">{row.description}</span>
            ),
        },
        {
            key: 'blocking',
            header: 'Blocage',
            render: (row) => (
                <span
                    className={
                        row.blocking
                            ? 'badge badge-danger'
                            : 'badge badge-neutral'
                    }
                >
                    {row.blocking ? 'Bloquant' : 'Suivi'}
                </span>
            ),
        },
        {
            key: 'opened_at',
            header: 'Ouvert le',
            render: (row) => formatDateTime(row.opened_at),
        },
    ];
    const capacityColumns: TableColumn<ParcelSupervisionCapacityDeparture>[] = [
        {
            key: 'departure',
            header: 'Départ',
            render: (row) => (
                <div>
                    <strong className="block text-xs">
                        {row.line_code ?? '—'}
                    </strong>
                    <span className="text-[0.6875rem] text-muted-foreground">
                        {formatDateTime(row.departure_at)}
                    </span>
                </div>
            ),
        },
        {
            key: 'corridor',
            header: 'Corridor',
            render: (row) => row.label,
        },
        {
            key: 'capacity',
            header: 'Capacité',
            align: 'right',
            render: (row) => formatGrams(row.capacity_grams),
        },
        {
            key: 'reserved',
            header: 'Réservé',
            align: 'right',
            render: (row) => formatGrams(row.reserved_grams),
        },
        {
            key: 'available',
            header: 'Disponible',
            align: 'right',
            render: (row) => formatGrams(row.available_grams),
        },
    ];

    return (
        <section className="grid gap-5">
            <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                <ParcelKpi
                    icon={PackageCheck}
                    label="À réceptionner"
                    hint="Arrivés au hub destination"
                    value={data.meta.awaiting_reception}
                    tone="navy"
                    tooltip="Colis au statut arrived, dans votre périmètre, pas encore réceptionnés physiquement."
                />
                <ParcelKpi
                    icon={RotateCcw}
                    label="Retours à traiter"
                    hint="Préparation, transit ou remise"
                    value={data.meta.returns_to_handle}
                    tone="gold"
                    tooltip="Somme des statuts return_pending, return_transit, return_arrived et returned dans le périmètre."
                />
                <ParcelKpi
                    icon={ShieldAlert}
                    label="Incidents ouverts"
                    hint="Dossiers non résolus"
                    value={data.meta.open_incidents}
                    tone="blue"
                    tooltip={`${data.incidents.blocking} incident(s) bloquant(s). Un colis bloqué ne peut pas être payé, affecté, prêt ou remis.`}
                />
                <ParcelKpi
                    icon={Truck}
                    label="Départs sans soute"
                    hint={`Horizon ${data.capacity.horizon_days} jours`}
                    value={data.capacity.without_capacity_count}
                    tone="green"
                    tooltip="Départs programmés éligibles dont le budget de soute n’est pas défini. Ils restent visibles mais bloquent l’affectation au-delà de la capacité."
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                <article className="rounded-[18px] border border-border bg-card px-5 py-5">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2 text-[0.6875rem] font-extrabold tracking-[0.14em] text-muted-foreground uppercase">
                            <AlertTriangle className="size-3.5 text-gold" />
                            Alertes de configuration
                        </div>
                        <span className="text-[0.6875rem] font-bold text-muted-foreground">
                            {data.config_health.alerts.length} alerte
                            {data.config_health.alerts.length > 1 ? 's' : ''}
                        </span>
                    </div>
                    <div className="mt-4 grid max-h-72 gap-2 overflow-y-auto pr-1">
                        {data.config_health.alerts.length === 0 ? (
                            <div className="rounded-xl border border-emerald-300/50 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                <CheckCircle2 className="mr-2 inline size-4" />
                                Aucun blocage de configuration détecté.
                            </div>
                        ) : (
                            data.config_health.alerts.map(
                                (alert: ParcelSupervisionAlert) => (
                                    <div
                                        key={`${alert.code}-${alert.message}`}
                                        className={`rounded-xl border px-4 py-3 text-sm ${severityClass[alert.severity] ?? 'border-border bg-muted/30'}`}
                                    >
                                        <strong className="block text-xs uppercase">
                                            {alert.severity === 'blocking'
                                                ? 'Bloquant'
                                                : 'Attention'}
                                        </strong>
                                        <p className="mt-1 text-xs leading-5">
                                            {alert.message}
                                        </p>
                                    </div>
                                ),
                            )
                        )}
                    </div>
                </article>

                <article className="rounded-[18px] border border-border bg-card px-5 py-5">
                    <div className="flex items-center gap-2 text-[0.6875rem] font-extrabold tracking-[0.14em] text-muted-foreground uppercase">
                        <Gauge className="size-3.5 text-gold" />
                        Capacité des départs
                    </div>
                    <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                        {[
                            [
                                'Départs éligibles',
                                formatNumber(data.capacity.departures_count),
                            ],
                            [
                                'Soute disponible',
                                formatGrams(data.capacity.available_grams),
                            ],
                            [
                                'Sans budget',
                                formatNumber(
                                    data.capacity.without_capacity_count,
                                ),
                            ],
                            [
                                'Saturés',
                                formatNumber(data.capacity.saturated_count),
                            ],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="rounded-xl border border-border bg-muted/20 px-3 py-3"
                            >
                                <dt className="text-[0.625rem] font-bold tracking-[0.1em] text-muted-foreground uppercase">
                                    {label}
                                </dt>
                                <dd className="mt-1 text-lg font-black tracking-[-0.03em]">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <ParcelButton
                        type="button"
                        variant="ghost"
                        className="mt-4 w-full"
                        onClick={() =>
                            window.location.assign(
                                '/parcels/data/supervision/export',
                            )
                        }
                    >
                        <Download className="size-4" />
                        Exporter la synthèse CSV
                    </ParcelButton>
                    <p className="mt-2 text-center text-[0.625rem] text-muted-foreground">
                        Agrégats uniquement · aucune donnée personnelle, code ou
                        payload fournisseur.
                    </p>
                </article>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <KoraTable<ParcelSupervisionStatusRow>
                    title="Colis par statut"
                    subtitle="Volumes du périmètre OPS courant."
                    icon={Package}
                    variant="finance"
                    columns={statusColumns}
                    data={data.by_status.filter((row) => row.count > 0)}
                    rowKey={(row) => row.status}
                    emptyIcon={Package}
                    emptyTitle="Aucun colis"
                    emptyMessage="Aucun colis n’est encore enregistré dans ce périmètre."
                />
                <KoraTable<ParcelSupervisionCorridorRow>
                    title="Colis par corridor"
                    subtitle="Charge commerciale et desserte."
                    icon={Coins}
                    variant="finance"
                    columns={corridorColumns}
                    data={data.by_corridor.filter((row) => row.count > 0)}
                    rowKey={(row) => row.uuid}
                    emptyIcon={Coins}
                    emptyTitle="Aucun corridor utilisé"
                    emptyMessage="Créez un corridor et publiez un tarif pour démarrer."
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <KoraTable<ParcelSupervisionIncident>
                    title="Incidents ouverts"
                    subtitle="Dossiers récents non résolus."
                    icon={ShieldAlert}
                    variant="finance"
                    columns={incidentColumns}
                    data={data.incidents.recent}
                    rowKey={(row) => row.uuid}
                    emptyIcon={ShieldAlert}
                    emptyTitle="Aucun incident"
                    emptyMessage="Tous les dossiers d’incident sont résolus."
                />
                <KoraTable<ParcelSupervisionCapacityDeparture>
                    title="Départs sans budget de soute"
                    subtitle="À compléter avant affectation."
                    icon={Truck}
                    variant="finance"
                    columns={capacityColumns}
                    data={data.capacity.without_capacity}
                    rowKey={(row) => row.uuid}
                    emptyIcon={Truck}
                    emptyTitle="Toutes les soutes sont budgétées"
                    emptyMessage="Les départs éligibles disposent d’un budget de soute."
                />
            </div>
        </section>
    );
}
