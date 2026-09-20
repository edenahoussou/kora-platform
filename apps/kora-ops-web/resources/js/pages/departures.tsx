import { Head, router, usePoll } from '@inertiajs/react';
import { ArrowRight, BusFront, Download, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DepartureFormDialog } from '@/components/departures/departure-form-dialog';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { Button } from '@/components/ui/button';
import departures from '@/routes/departures';
import type { BreadcrumbItem } from '@/types';

type DepartureListItem = {
    uuid: string;
    lineUuid: string | null;
    lineCode: string;
    lineName: string;
    busUuid: string | null;
    busRegistration: string;
    busSeatCount: number;
    departureDate: string | null;
    departureTime: string | null;
    estimatedArrivalAt: string | null;
    driverUuid: string | null;
    driverName: string | null;
    conductorUuid: string | null;
    conductorName: string | null;
    fillRate: number;
    reservationsCount: number;
    status: string;
};

type Stats = {
    total: number;
    scheduled: number;
    boarding: number;
    departed: number;
    arrived: number;
    cancelled: number;
};

type Option = { uuid: string; label: string };
type BusOption = { uuid: string; label: string };

type PageProps = {
    departures: {
        data: DepartureListItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
    lines: Option[];
    buses: BusOption[];
    drivers: Option[];
    conductors: Option[];
    stats: Stats;
    permissions: {
        canCreate: boolean;
        canManage: boolean;
        canCancel: boolean;
    };
};

const statusLabels: Record<string, string> = {
    scheduled: 'Programmé',
    boarding: 'Embarquement',
    departed: 'En route',
    arrived: 'Arrivé',
    cancelled: 'Annulé',
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    scheduled: 'navy',
    boarding: 'gold',
    departed: 'blue',
    arrived: 'navy',
    cancelled: 'muted',
};

export default function DeparturesPage({
    departures: departureData,
    statuses,
    lines,
    buses,
    drivers,
    conductors,
    stats,
    permissions,
}: PageProps) {
    // Un « bus parti » déclaré par le convoyeur sur le terminal (MEZ-98)
    // apparaît ici sans recharger la page. Le polling s'arrête tout seul
    // quand l'onglet est masqué.
    usePoll(30000, { only: ['departures', 'stats'] });

    const [dialogOpen, setDialogOpen] = useState(false);
    const [selected, setSelected] = useState<DepartureListItem | null>(null);

    const filters: FilterConfig[] = useMemo(
        () => [
            {
                name: 'date',
                label: 'Date',
                type: 'date',
                options: [],
            },
            {
                name: 'line_uuid',
                label: 'Ligne',
                placeholder: 'Toutes les lignes',
                options: lines.map((line) => ({
                    value: line.uuid,
                    label: line.label,
                })),
            },
            {
                name: 'status',
                label: 'Statut',
                placeholder: 'Tous les statuts',
                options: Object.entries(statuses).map(([value, label]) => ({
                    value,
                    label: statusLabels[value] ?? label,
                })),
            },
        ],
        [statuses, lines],
    );

    return (
        <>
            <Head title="Gestion des départs" />

            {/* PAGE-HEAD */}
            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Exploitation / Départs
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Gestion des départs
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Planifiez, affectez et supervisez chaque départ.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                    <Button
                        variant="outline"
                        className="h-11 rounded-[0.75rem] border-border/50 px-4 font-medium text-muted-foreground hover:text-foreground"
                        onClick={() => {
                            // Placeholder — export functionality
                            router.reload({ only: ['departures'] });
                        }}
                    >
                        <Download className="size-4" />
                        Exporter
                    </Button>
                    {permissions.canCreate && (
                        <Button
                            onClick={() => {
                                setSelected(null);
                                setDialogOpen(true);
                            }}
                            className="h-11 rounded-[0.75rem] bg-gold px-5 font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                        >
                            <Plus className="size-4" />
                            Programmer un départ
                        </Button>
                    )}
                </div>
            </div>

            {/* OPS-RIBBON — Cadence réseau */}
            <section className="relative mb-5 grid grid-cols-1 gap-6 overflow-hidden rounded-[1.5rem] bg-gradient-to-br from-[var(--kora)] to-[var(--digital)] p-7 text-white shadow-[0_22px_52px_rgba(0,29,78,0.17)] md:grid-cols-[1fr_auto]">
                <div className="relative z-10">
                    <span className="mb-1.5 block text-[0.5625rem] font-black tracking-[0.13em] text-gold uppercase">
                        Cadence réseau
                    </span>
                    <h2 className="text-[1.5625rem] font-extrabold tracking-[-0.03em]">
                        {stats.total} départ{stats.total > 1 ? 's' : ''}{' '}
                        programmé
                        {stats.total > 1 ? 's' : ''}
                    </h2>
                    <p className="mt-1 text-[0.6875rem] text-white/58">
                        {stats.scheduled} programmé
                        {stats.scheduled > 1 ? 's' : ''} · {stats.boarding} en
                        embarquement · {stats.departed} en route ·{' '}
                        {stats.arrived} arrivé
                        {stats.arrived > 1 ? 's' : ''}
                    </p>
                </div>
                <div className="relative z-10 grid grid-cols-3 gap-2.5">
                    <div className="rounded-[0.875rem] border border-white/10 bg-white/[0.06] p-3.5">
                        <small className="block text-[0.5rem] text-white/45">
                            Ponctualité
                        </small>
                        <strong className="mt-1 block text-[1.1875rem] font-extrabold">
                            —
                        </strong>
                        <span className="mt-0.5 block text-[0.5rem] text-gold">
                            Sprint 6
                        </span>
                    </div>
                    <div className="rounded-[0.875rem] border border-white/10 bg-white/[0.06] p-3.5">
                        <small className="block text-[0.5rem] text-white/45">
                            Remplissage
                        </small>
                        <strong className="mt-1 block text-[1.1875rem] font-extrabold">
                            —
                        </strong>
                        <span className="mt-0.5 block text-[0.5rem] text-gold">
                            Sprint 6
                        </span>
                    </div>
                    <div className="rounded-[0.875rem] border border-white/10 bg-white/[0.06] p-3.5">
                        <small className="block text-[0.5rem] text-white/45">
                            Places vendues
                        </small>
                        <strong className="mt-1 block text-[1.1875rem] font-extrabold">
                            —
                        </strong>
                        <span className="mt-0.5 block text-[0.5rem] text-gold">
                            Sprint 6
                        </span>
                    </div>
                </div>
            </section>

            {/* FILTER BAR */}
            <KoraFilterBar
                route="/departures"
                searchPlaceholder="Départ, trajet ou véhicule…"
                filters={filters}
            />

            {/* DEPARTURE BOARD — ops-trip cards */}
            <div className="mt-5 grid gap-2.5">
                {departureData.data.length > 0 ? (
                    departureData.data.map((departure) => {
                        const isBoarding = departure.status === 'boarding';
                        const crewLabel =
                            departure.driverName || departure.conductorName
                                ? [
                                      departure.driverName,
                                      departure.conductorName,
                                  ]
                                      .filter(Boolean)
                                      .join(' · ')
                                : 'À affecter';

                        return (
                            <article
                                key={departure.uuid}
                                tabIndex={0}
                                onClick={() =>
                                    router.get(
                                        departures.show.url({
                                            departure: departure.uuid,
                                        }),
                                    )
                                }
                                className={`group relative grid cursor-pointer grid-cols-[78px_1fr_130px_140px_34px] items-center gap-4 rounded-[1.125rem] border border-border/30 bg-card p-4 shadow-[0_12px_35px_rgba(0,29,78,0.055)] transition-all hover:translate-x-1 hover:shadow-[0_20px_48px_rgba(0,29,78,0.1)] md:grid-cols-[95px_minmax(310px,1.3fr)_minmax(180px,0.7fr)_180px_38px] md:gap-[18px] md:p-[18px_20px] ${
                                    isBoarding
                                        ? 'before:absolute before:top-[18px] before:bottom-[18px] before:left-0 before:w-1 before:rounded-r-[5px] before:bg-gold'
                                        : ''
                                }`}
                            >
                                {/* OPS-TIME */}
                                <div className="pr-2">
                                    <span className="block text-[1.625rem] font-black tracking-tight text-foreground">
                                        {departure.departureTime ?? '—'}
                                    </span>
                                </div>

                                {/* OPS-ROUTE */}
                                <div>
                                    <div className="flex items-center gap-2.5">
                                        <KoraStatusBadge
                                            label={
                                                statusLabels[
                                                    departure.status
                                                ] ?? departure.status
                                            }
                                            tone={
                                                statusTones[departure.status] ??
                                                'gold'
                                            }
                                        />
                                        <strong className="text-[0.75rem] font-bold text-foreground">
                                            {departure.lineName}
                                        </strong>
                                    </div>
                                    <small className="mt-1.5 block text-[0.5625rem] text-muted-foreground">
                                        {departure.lineCode} ·{' '}
                                        {departure.busRegistration}
                                    </small>
                                    <div className="mt-3 flex items-center gap-1">
                                        <span className="h-px flex-1 bg-border/60" />
                                        <span className="grid size-6 place-items-center rounded-[0.5rem] bg-gold text-[0.5625rem] font-black text-[var(--kora)]">
                                            K
                                        </span>
                                        <span className="h-px flex-1 bg-border/60" />
                                    </div>
                                </div>

                                {/* CREW */}
                                <div className="hidden md:block">
                                    <small className="block text-[0.5rem] text-muted-foreground">
                                        Équipage
                                    </small>
                                    <strong className="mt-1 block text-[0.625rem] font-bold text-foreground">
                                        {crewLabel}
                                    </strong>
                                </div>

                                {/* OCCUPANCY */}
                                <div className="hidden md:block">
                                    <div className="flex items-center justify-between">
                                        <small className="text-[0.5rem] text-muted-foreground">
                                            Remplissage
                                        </small>
                                        <strong className="text-[0.625rem] font-bold text-foreground">
                                            {departure.fillRate}%
                                        </strong>
                                    </div>
                                    <span className="mt-2 block h-1.5 overflow-hidden rounded-[5px] bg-muted">
                                        <i
                                            className="block h-full rounded-[5px] bg-gradient-to-r from-[var(--digital)] to-gold"
                                            style={{
                                                width: `${departure.fillRate}%`,
                                            }}
                                        />
                                    </span>
                                </div>

                                {/* ROW-ACTION */}
                                <button
                                    aria-label="Ouvrir le détail"
                                    className="grid size-8 place-items-center rounded-[0.5625rem] border border-border/60 bg-card text-[var(--kora)] transition-colors group-hover:bg-gold/10"
                                >
                                    <ArrowRight className="size-4" />
                                </button>
                            </article>
                        );
                    })
                ) : (
                    <div className="grid min-h-[430px] place-items-center rounded-[1.25rem] border border-dashed border-border/60 bg-card text-center">
                        <div className="max-w-[420px]">
                            <span className="mx-auto grid size-16 place-items-center rounded-[1.25rem] bg-gold/18 text-[1.375rem] font-black text-[var(--kora)]">
                                <BusFront className="size-7" />
                            </span>
                            <h3 className="mt-4 text-[1.3125rem] font-bold text-foreground">
                                Aucun départ programmé
                            </h3>
                            <p className="mt-2 text-[0.75rem] leading-relaxed text-muted-foreground">
                                Ajustez vos filtres ou programmez le premier
                                départ du planning.
                            </p>
                            {permissions.canCreate && (
                                <Button
                                    onClick={() => {
                                        setSelected(null);
                                        setDialogOpen(true);
                                    }}
                                    className="mt-5 h-10 rounded-[0.75rem] bg-gold px-4 font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                                >
                                    <Plus className="size-4" />
                                    Programmer un départ
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* PAGINATION */}
            {departureData.last_page > 1 && (
                <div className="mt-5 flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        {departureData.from ?? 0}–{departureData.to ?? 0} sur{' '}
                        {departureData.total}
                    </span>
                    <div className="flex gap-1">
                        {departureData.current_page > 1 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get(
                                        departures.index.url({
                                            query: {
                                                page:
                                                    departureData.current_page -
                                                    1,
                                            },
                                        }),
                                    )
                                }
                            >
                                Précédent
                            </Button>
                        )}
                        {departureData.current_page <
                            departureData.last_page && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get(
                                        departures.index.url({
                                            query: {
                                                page:
                                                    departureData.current_page +
                                                    1,
                                            },
                                        }),
                                    )
                                }
                            >
                                Suivant
                            </Button>
                        )}
                    </div>
                </div>
            )}

            <DepartureFormDialog
                open={dialogOpen}
                departure={selected}
                statuses={statuses}
                lines={lines}
                buses={buses}
                drivers={drivers}
                conductors={conductors}
                onOpenChange={setDialogOpen}
            />
        </>
    );
}

DeparturesPage.layout = {
    breadcrumbs: [
        { title: 'Départs', href: '/departures' } satisfies BreadcrumbItem,
    ],
};
