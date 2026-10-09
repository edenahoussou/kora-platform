import { Head, router, usePoll } from '@inertiajs/react';
import {
    ArrowRight,
    BusFront,
    ChevronLeft,
    ChevronRight,
    Download,
    Plus,
    Route,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTooltip } from '@/components/kora-tooltip';
import departures from '@/routes/departures';
import programming from '@/routes/programming';
import type { BreadcrumbItem } from '@/types';

type DepartureListItem = {
    uuid: string;
    lineUuid: string | null;
    lineCode: string;
    lineName: string;
    originCity: string | null;
    destinationCity: string | null;
    originPoint: string | null;
    isReturn: boolean;
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
    averageFillRate: number;
    peakLegLabel: string | null;
    peakLoad: number;
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
    punctuality: number | null;
    fillRate: number | null;
    seatsSold: number;
};

type Option = { uuid: string; label: string };

type Filters = {
    q: string;
    date: string;
    city: string;
    physical_stop: string;
    line_uuid: string;
    status: string;
};

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
    cities: string[];
    physicalStops: Option[];
    appliedDate: string;
    filters: Partial<Filters>;
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

function localToday(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function shiftDate(date: string, days: number): string {
    const shifted = new Date(`${date}T00:00:00`);
    shifted.setDate(shifted.getDate() + days);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${shifted.getFullYear()}-${pad(shifted.getMonth() + 1)}-${pad(shifted.getDate())}`;
}

export default function DeparturesPage({
    departures: departureData,
    statuses,
    lines,
    cities,
    physicalStops,
    appliedDate,
    filters: appliedFilters,
    stats,
    permissions,
}: PageProps) {
    // Un « bus parti » déclaré par le convoyeur sur le terminal (MEZ-98)
    // apparaît ici sans recharger la page. Le polling s'arrête tout seul
    // quand l'onglet est masqué.
    usePoll(30000, { only: ['departures', 'stats'] });

    const [query, setQuery] = useState(appliedFilters.q ?? '');
    const [date, setDate] = useState(appliedDate);
    const [city, setCity] = useState(appliedFilters.city || 'all');
    const [physicalStop, setPhysicalStop] = useState(
        appliedFilters.physical_stop || 'all',
    );
    const [lineUuid, setLineUuid] = useState(appliedFilters.line_uuid || 'all');
    const [status, setStatus] = useState(appliedFilters.status || 'all');

    const buildParams = (
        overrides: {
            q?: string;
            date?: string;
            city?: string;
            physicalStop?: string;
            lineUuid?: string;
            status?: string;
            page?: number;
        } = {},
    ): Record<string, string> => {
        const nextQuery = overrides.q ?? query;
        const nextDate = overrides.date ?? date;
        const nextCity = overrides.city ?? city;
        const nextPhysicalStop = overrides.physicalStop ?? physicalStop;
        const nextLineUuid = overrides.lineUuid ?? lineUuid;
        const nextStatus = overrides.status ?? status;

        const params: Record<string, string> = {};

        if (nextQuery.trim() !== '') {
            params.q = nextQuery.trim();
        }

        if (nextDate !== '') {
            params.date = nextDate;
        }

        if (nextCity !== 'all') {
            params.city = nextCity;
        }

        if (nextPhysicalStop !== 'all') {
            params.physical_stop = nextPhysicalStop;
        }

        if (nextLineUuid !== 'all') {
            params.line_uuid = nextLineUuid;
        }

        if (nextStatus !== 'all') {
            params.status = nextStatus;
        }

        if (overrides.page !== undefined && overrides.page > 1) {
            params.page = String(overrides.page);
        }

        return params;
    };

    const visit = (overrides: Parameters<typeof buildParams>[0] = {}) => {
        router.get(departures.index.url(), buildParams(overrides), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const goToDate = (nextDate: string) => {
        if (nextDate === '') {
            return;
        }

        setDate(nextDate);
        visit({ date: nextDate, page: 1 });
    };

    const applyFilters = () => visit({ page: 1 });

    const resetFilters = () => {
        setQuery('');
        setDate(localToday());
        setCity('all');
        setPhysicalStop('all');
        setLineUuid('all');
        setStatus('all');
        router.get(
            departures.index.url(),
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const filterSelect = (
        label: string,
        value: string,
        onChange: (value: string) => void,
        options: [string, string][],
    ) => (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-all outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
            >
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
            <ChevronRight
                className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                strokeWidth={1.7}
            />
        </label>
    );

    const planningLabel = new Intl.DateTimeFormat('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date(`${appliedDate}T00:00:00`));

    const isToday = appliedDate === localToday();
    const hasActiveFilters =
        query.trim() !== '' ||
        city !== 'all' ||
        physicalStop !== 'all' ||
        lineUuid !== 'all' ||
        status !== 'all';

    return (
        <>
            <Head title="Départs" />

            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Exploitation / Départs
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Départs
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Suivez les rotations du jour, l’embarquement et le
                            remplissage de chaque départ.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={() =>
                                router.reload({ only: ['departures', 'stats'] })
                            }
                        >
                            <Download className="size-4" /> Exporter
                        </button>
                        {permissions.canCreate && (
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={() =>
                                    router.get(programming.index.url())
                                }
                            >
                                <Plus className="size-4" /> Programmer un départ
                            </button>
                        )}
                    </div>
                </div>

                {/* OPS-RIBBON — Cadence réseau */}
                <section className="ops-ribbon cash-reveal cash-reveal-delay-1">
                    <div>
                        <span className="section-kicker">Cadence réseau</span>
                        <h2>
                            {stats.total} départ{stats.total > 1 ? 's' : ''}{' '}
                            programmé{stats.total > 1 ? 's' : ''}
                            {isToday ? ' aujourd’hui' : ` le ${planningLabel}`}
                        </h2>
                        <p>
                            {stats.scheduled} programmé
                            {stats.scheduled > 1 ? 's' : ''} · {stats.boarding}{' '}
                            en embarquement · {stats.departed} en route ·{' '}
                            {stats.arrived} arrivé{stats.arrived > 1 ? 's' : ''}
                            {stats.cancelled > 0
                                ? ` · ${stats.cancelled} annulé${stats.cancelled > 1 ? 's' : ''}`
                                : ''}
                        </p>
                    </div>
                    <div className="ops-stats">
                        <div>
                            <small>Ponctualité</small>
                            <strong>
                                {stats.punctuality !== null
                                    ? `${stats.punctuality} %`
                                    : '—'}
                            </strong>
                            <span>Retard ≤ 15 min</span>
                        </div>
                        <KoraTooltip
                            side="bottom"
                            content={
                                <>
                                    <span className="block font-black">
                                        Remplissage pondéré
                                    </span>
                                    Σ(passagers × tronçons parcourus) / Σ(sieges
                                    × tronçons de la ligne) — tous départs du
                                    jour
                                </>
                            }
                        >
                            <div>
                                <small>Remplissage</small>
                                <strong>
                                    {stats.fillRate !== null
                                        ? `${stats.fillRate} %`
                                        : '—'}
                                </strong>
                                <span>Capacité du jour</span>
                            </div>
                        </KoraTooltip>
                        <div>
                            <small>Places vendues</small>
                            <strong>{stats.seatsSold}</strong>
                            <span>Réservations actives</span>
                        </div>
                    </div>
                </section>

                {/* FILTERBAR — Planning du jour */}
                <div className="cash-filterbar ops-filterbar cash-reveal cash-reveal-delay-2">
                    <div className="ops-filter-label">
                        <span>Planning</span>
                        <strong>{planningLabel}</strong>
                    </div>

                    <div className="ops-day-nav">
                        <button
                            type="button"
                            aria-label="Jour précédent"
                            onClick={() => goToDate(shiftDate(appliedDate, -1))}
                            className="grid size-8 place-items-center rounded-lg bg-muted text-foreground transition-colors hover:bg-gold/15"
                        >
                            <ChevronLeft className="size-4" strokeWidth={1.7} />
                        </button>
                        <button
                            type="button"
                            aria-label="Jour suivant"
                            onClick={() => goToDate(shiftDate(appliedDate, 1))}
                            className="grid size-8 place-items-center rounded-lg bg-muted text-foreground transition-colors hover:bg-gold/15"
                        >
                            <ChevronRight
                                className="size-4"
                                strokeWidth={1.7}
                            />
                        </button>
                    </div>

                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters();
                            }
                        }}
                        aria-label="Rechercher un départ, un trajet ou un véhicule"
                        placeholder="Départ, trajet ou véhicule…"
                        className="cash-filter-search"
                    />

                    <label className="cash-filter-field relative block">
                        <span className="sr-only">Date du planning</span>
                        <input
                            type="date"
                            value={date}
                            onChange={(event) => goToDate(event.target.value)}
                            className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-all outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                        />
                    </label>

                    {filterSelect(
                        'Ville de départ',
                        city,
                        (value) => {
                            setCity(value);
                            visit({ city: value, page: 1 });
                        },
                        [
                            ['all', 'Toutes les villes'],
                            ...cities.map(
                                (name) => [name, name] as [string, string],
                            ),
                        ],
                    )}

                    {filterSelect(
                        'Point de départ',
                        physicalStop,
                        (value) => {
                            setPhysicalStop(value);
                            visit({ physicalStop: value, page: 1 });
                        },
                        [
                            ['all', 'Tous les points'],
                            ...physicalStops.map(
                                (stop) =>
                                    [stop.uuid, stop.label] as [string, string],
                            ),
                        ],
                    )}

                    {filterSelect(
                        'Ligne',
                        lineUuid,
                        (value) => {
                            setLineUuid(value);
                            visit({ lineUuid: value, page: 1 });
                        },
                        [
                            ['all', 'Toutes les lignes'],
                            ...lines.map(
                                (line) =>
                                    [line.uuid, line.label] as [string, string],
                            ),
                        ],
                    )}

                    {filterSelect(
                        'Statut',
                        status,
                        (value) => {
                            setStatus(value);
                            visit({ status: value, page: 1 });
                        },
                        [
                            ['all', 'Tous les statuts'],
                            ...Object.entries(statuses).map(
                                ([value, label]) =>
                                    [value, statusLabels[value] ?? label] as [
                                        string,
                                        string,
                                    ],
                            ),
                        ],
                    )}

                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={applyFilters}
                    >
                        <SlidersHorizontal className="size-4" /> Filtrer
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={resetFilters}
                    >
                        Réinitialiser
                    </button>
                </div>

                {/* DEPARTURE-BOARD — ops-trip cards */}
                <section className="departure-board cash-reveal cash-reveal-delay-3">
                    {departureData.data.length > 0 ? (
                        departureData.data.map((departure) => {
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
                                    className={`ops-trip ${departure.status === 'boarding' ? 'boarding' : ''}`}
                                >
                                    <div className="ops-time">
                                        <span>
                                            {departure.departureTime ?? '—'}
                                        </span>
                                        <small>
                                            {departure.originPoint ?? ''}
                                        </small>
                                    </div>

                                    <div className="ops-route">
                                        <div className="ops-route-top">
                                            <KoraStatusBadge
                                                label={
                                                    statusLabels[
                                                        departure.status
                                                    ] ?? departure.status
                                                }
                                                tone={
                                                    statusTones[
                                                        departure.status
                                                    ] ?? 'gold'
                                                }
                                            />
                                            <strong>
                                                {departure.originCity &&
                                                departure.destinationCity
                                                    ? `${departure.originCity} → ${departure.destinationCity}`
                                                    : departure.lineName}
                                            </strong>
                                            {departure.isReturn && (
                                                <KoraStatusBadge
                                                    label="Sens retour"
                                                    tone="muted"
                                                />
                                            )}
                                        </div>
                                        <small className="ops-route-meta">
                                            <span>
                                                <Route className="size-3" />
                                                {departure.lineCode} ·{' '}
                                                {departure.lineName}
                                            </span>
                                            <span>
                                                <BusFront className="size-3" />
                                                {departure.busRegistration}
                                            </span>
                                        </small>
                                        <div className="ops-route-line">
                                            <i />
                                            <b>K</b>
                                            <i />
                                        </div>
                                    </div>

                                    <div className="ops-crew">
                                        <small>Équipage</small>
                                        <strong>{crewLabel}</strong>
                                    </div>

                                    <KoraTooltip
                                        content={
                                            <>
                                                <span className="block font-black">
                                                    Tronçon le plus chargé
                                                </span>
                                                {departure.peakLegLabel ?? '—'}{' '}
                                                : {departure.peakLoad} passager
                                                {departure.peakLoad > 1
                                                    ? 's'
                                                    : ''}{' '}
                                                / {departure.busSeatCount}{' '}
                                                sièges · moyenne ligne{' '}
                                                {departure.averageFillRate}%
                                            </>
                                        }
                                    >
                                        <div className="ops-occupancy">
                                            <div>
                                                <small>Remplissage</small>
                                                <strong>
                                                    {departure.fillRate}%
                                                </strong>
                                            </div>
                                            <span>
                                                <i
                                                    style={{
                                                        width: `${departure.fillRate}%`,
                                                    }}
                                                />
                                            </span>
                                            {departure.peakLegLabel && (
                                                <em className="ops-occupancy-leg">
                                                    Pic ·{' '}
                                                    {departure.peakLegLabel}
                                                </em>
                                            )}
                                        </div>
                                    </KoraTooltip>

                                    <button
                                        type="button"
                                        aria-label="Ouvrir le détail"
                                        className="ops-row-action"
                                    >
                                        <ArrowRight className="size-4" />
                                    </button>
                                </article>
                            );
                        })
                    ) : (
                        <div className="grid place-items-center rounded-2xl border border-dashed border-border/60 bg-card px-6 py-16 text-center text-sm text-muted-foreground">
                            <div>
                                <BusFront className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                                <p className="font-semibold text-foreground">
                                    Aucun départ le {planningLabel}
                                </p>
                                <p className="mt-1 text-xs">
                                    {hasActiveFilters
                                        ? 'Ajustez vos critères de recherche ou changez de jour.'
                                        : 'Aucune rotation n’est programmée ce jour. Naviguez vers un autre jour ou ouvrez la programmation.'}
                                </p>
                                <div className="mt-4 flex items-center justify-center gap-2">
                                    {hasActiveFilters && (
                                        <button
                                            type="button"
                                            onClick={resetFilters}
                                            className="btn btn-ghost text-xs"
                                        >
                                            Réinitialiser les filtres
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() =>
                                            goToDate(shiftDate(appliedDate, -1))
                                        }
                                        className="btn btn-ghost text-xs"
                                    >
                                        <ChevronLeft className="size-3.5" />{' '}
                                        Jour précédent
                                    </button>
                                    {permissions.canCreate && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.get(
                                                    programming.index.url(),
                                                )
                                            }
                                            className="btn btn-primary text-xs"
                                        >
                                            <Plus className="size-3.5" />{' '}
                                            Programmer un départ
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </section>

                {departureData.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-[0.6875rem] text-muted-foreground">
                        <span>
                            {departureData.from ?? 0}–{departureData.to ?? 0}{' '}
                            sur {departureData.total} départs
                        </span>
                        <div className="flex items-center gap-[5px]">
                            <button
                                type="button"
                                onClick={() =>
                                    visit({
                                        page: departureData.current_page - 1,
                                    })
                                }
                                disabled={departureData.current_page === 1}
                                className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-colors hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                            >
                                <ChevronRight
                                    className="size-3.5 rotate-180"
                                    strokeWidth={1.7}
                                />
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    visit({
                                        page: departureData.current_page + 1,
                                    })
                                }
                                disabled={
                                    departureData.current_page ===
                                    departureData.last_page
                                }
                                className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-colors hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                            >
                                <ChevronRight
                                    className="size-3.5"
                                    strokeWidth={1.7}
                                />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

DeparturesPage.layout = {
    breadcrumbs: [
        { title: 'Exploitation', href: '/departures' },
        { title: 'Départs', href: '/departures' },
    ] as BreadcrumbItem[],
};
