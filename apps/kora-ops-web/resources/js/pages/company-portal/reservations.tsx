import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    ChevronRight,
    Download,
    History,
    MapPin,
    MoveRight,
    Search,
    SlidersHorizontal,
    Ticket,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { formatFcfa } from '@/components/company-accounts/company-account-types';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import companyPortal from '@/routes/company-portal';

type ReservationRow = {
    uuid: string;
    reference: string;
    createdAt: string | null;
    status: string;
    statusLabel: string;
    purchaseType: string;
    passengerCount: number;
    passengers: string[];
    totalAmount: number;
    currency: string;
    itinerary: {
        label: string;
        travelDate: string | null;
        roundTrip: boolean;
    };
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    account: {
        uuid: string;
        name: string;
        reference: string;
    };
    reservations: Paginated<ReservationRow>;
    filters: { q: string; status: string; month: string };
    statuses: Record<string, string>;
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    held: 'gold',
    assistance_pending: 'gold',
    confirmed: 'navy',
    paid: 'navy',
    ticketed: 'blue',
    boarded: 'blue',
    cancelled: 'muted',
    expired: 'muted',
    unavailable: 'muted',
};

function formatDate(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: [string, string][];
}) {
    return (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
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
}

function Pagination({
    currentPage,
    totalPages,
    total,
    from,
    to,
    onPageChange,
}: {
    currentPage: number;
    totalPages: number;
    total: number;
    from: number | null;
    to: number | null;
    onPageChange: (page: number) => void;
}) {
    if (total === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between border-t border-black/[0.045] px-4 py-3 text-xs text-muted-foreground">
            <span>
                {from}–{to} sur {total}
            </span>
            <div className="flex items-center gap-1.5">
                <button
                    type="button"
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage <= 1}
                    aria-label="Page précédente"
                >
                    <ChevronRight
                        className="size-3.5 rotate-180"
                        strokeWidth={1.7}
                    />
                </button>
                <span className="px-2 font-bold text-foreground">
                    {currentPage} / {totalPages}
                </span>
                <button
                    type="button"
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage >= totalPages}
                    aria-label="Page suivante"
                >
                    <ChevronRight className="size-3.5" strokeWidth={1.7} />
                </button>
            </div>
        </div>
    );
}

export default function CompanyPortalReservations({
    account,
    reservations,
    filters,
    statuses,
}: Props) {
    const { url: currentUrl } = usePage();
    const initialFilters = useMemo(() => {
        const params = currentUrl.includes('?')
            ? new URLSearchParams(currentUrl.slice(currentUrl.indexOf('?') + 1))
            : new URLSearchParams();

        return {
            q: params.get('q') ?? filters.q ?? '',
            status: params.get('status') ?? filters.status ?? 'all',
            month: params.get('month') ?? filters.month ?? '',
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const [query, setQuery] = useState(initialFilters.q);
    const [status, setStatus] = useState(initialFilters.status);
    const [month, setMonth] = useState(initialFilters.month);

    const buildParams = (
        overrides: {
            q?: string;
            status?: string;
            month?: string;
            page?: number;
        } = {},
    ): Record<string, string> => {
        const nextQuery = overrides.q ?? query;
        const nextStatus = overrides.status ?? status;
        const nextMonth = overrides.month ?? month;
        const params: Record<string, string> = {};

        if (nextQuery.trim() !== '') {
            params.q = nextQuery.trim();
        }

        if (nextStatus !== 'all') {
            params.status = nextStatus;
        }

        if (nextMonth !== '') {
            params.month = nextMonth;
        }

        if (overrides.page !== undefined && overrides.page > 1) {
            params.page = String(overrides.page);
        }

        return params;
    };

    const visit = (
        overrides: {
            q?: string;
            status?: string;
            month?: string;
            page?: number;
        } = {},
    ) => {
        router.get(
            companyPortal.reservations.index.url(),
            buildParams(overrides),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const exportUrl = () => {
        const params = buildParams({ page: 1 });
        delete params.page;
        const search = new URLSearchParams(params).toString();
        const base = companyPortal.reservations.export.url();

        return search === '' ? base : `${base}?${search}`;
    };

    const statusOptions: [string, string][] = [
        ['all', 'Tous les statuts'],
        ...Object.entries(statuses).map(([value, label]): [string, string] => [
            value,
            label,
        ]),
    ];

    return (
        <>
            <Head title="Historique des achats" />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Espace entreprise / Historique des achats
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Historique des achats
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            {account.name} — vos réservations, voyageurs et
                            billets émis.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={companyPortal.index.url()}
                            className="btn btn-ghost"
                        >
                            <ArrowLeft className="size-4" /> Retour
                        </Link>
                        <a href={exportUrl()} className="btn btn-navy" download>
                            <Download className="size-4" /> Exporter (CSV)
                        </a>
                    </div>
                </div>

                <div className="cash-filterbar cash-reveal cash-reveal-delay-1">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                visit({ page: 1 });
                            }
                        }}
                        aria-label="Rechercher une réservation"
                        placeholder="Référence ou nom de voyageur…"
                        className="cash-filter-search"
                    />
                    <FilterSelect
                        label="Statut"
                        value={status}
                        onChange={(value) => {
                            setStatus(value);
                            visit({ status: value, page: 1 });
                        }}
                        options={statusOptions}
                    />
                    <label className="cash-filter-field relative block">
                        <span className="sr-only">Période</span>
                        <input
                            type="month"
                            value={month}
                            onChange={(event) => {
                                setMonth(event.target.value);
                                visit({ month: event.target.value, page: 1 });
                            }}
                            aria-label="Filtrer par mois d’achat"
                            className="h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                        />
                    </label>
                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={() => visit({ page: 1 })}
                    >
                        <SlidersHorizontal className="size-4" /> Rechercher
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={() => {
                            setQuery('');
                            setStatus('all');
                            setMonth('');
                            router.get(
                                companyPortal.reservations.index.url(),
                                {},
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    replace: true,
                                },
                            );
                        }}
                    >
                        Réinitialiser
                    </button>
                </div>

                <section className="cash-table-card cash-reveal cash-reveal-delay-2">
                    {reservations.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-gold/10 text-gold">
                                <History className="size-5" strokeWidth={1.6} />
                            </span>
                            <p className="mt-4 text-sm font-bold text-foreground">
                                Aucune réservation trouvée
                            </p>
                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                Vos achats de billets apparaîtront ici dès la
                                première réservation réglée par crédit
                                entreprise.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="cash-table-scroll overflow-x-auto">
                                <table className="w-full min-w-[860px] text-left">
                                    <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Réservation
                                            </th>
                                            <th className="px-4 py-3">
                                                Itinéraire
                                            </th>
                                            <th className="px-4 py-3">
                                                Voyageurs
                                            </th>
                                            <th className="px-4 py-3">
                                                Montant
                                            </th>
                                            <th className="px-4 py-3">
                                                Statut
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Détail
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {reservations.data.map(
                                            (reservation) => (
                                                <tr
                                                    key={reservation.uuid}
                                                    className="group border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                                >
                                                    <td className="px-4 py-3.5">
                                                        <p className="text-xs font-black text-foreground">
                                                            {
                                                                reservation.reference
                                                            }
                                                        </p>
                                                        <p className="mt-0.5 flex items-center gap-1 text-[0.625rem] text-muted-foreground">
                                                            <CalendarDays className="size-3" />
                                                            acheté le{' '}
                                                            {formatDate(
                                                                reservation.createdAt,
                                                            )}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <p className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                                            <MapPin className="size-3 text-gold" />
                                                            {
                                                                reservation
                                                                    .itinerary
                                                                    .label
                                                            }
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {reservation
                                                                .itinerary
                                                                .travelDate !==
                                                            null
                                                                ? `voyage le ${formatDate(reservation.itinerary.travelDate)}`
                                                                : 'date de voyage non planifiée'}
                                                            {reservation
                                                                .itinerary
                                                                .roundTrip &&
                                                                ' · aller-retour'}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <p className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                                            <Users className="size-3" />
                                                            {
                                                                reservation.passengerCount
                                                            }{' '}
                                                            voyageur
                                                            {reservation.passengerCount >
                                                            1
                                                                ? 's'
                                                                : ''}
                                                        </p>
                                                        <p className="mt-0.5 max-w-44 truncate text-[0.625rem] text-muted-foreground">
                                                            {reservation
                                                                .passengers
                                                                .length > 0
                                                                ? reservation.passengers.join(
                                                                      ', ',
                                                                  ) +
                                                                  (reservation.passengerCount >
                                                                  reservation
                                                                      .passengers
                                                                      .length
                                                                      ? '…'
                                                                      : '')
                                                                : '—'}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <p className="text-xs font-black text-foreground">
                                                            {formatFcfa(
                                                                reservation.totalAmount,
                                                            )}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {reservation.purchaseType ===
                                                            'group'
                                                                ? 'achat groupé'
                                                                : 'achat individuel'}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <KoraStatusBadge
                                                            label={
                                                                reservation.statusLabel
                                                            }
                                                            tone={
                                                                statusTones[
                                                                    reservation
                                                                        .status
                                                                ] ?? 'muted'
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3.5 text-right">
                                                        <Link
                                                            href={companyPortal.reservations.show.url(
                                                                {
                                                                    reservation:
                                                                        reservation.uuid,
                                                                },
                                                            )}
                                                            className="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[0.6875rem] font-bold text-[var(--kora)] transition-[background-color] duration-500 hover:bg-gold/15"
                                                        >
                                                            <Ticket className="size-3.5" />
                                                            Voir
                                                            <MoveRight className="size-3" />
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination
                                currentPage={reservations.current_page}
                                totalPages={reservations.last_page}
                                total={reservations.total}
                                from={reservations.from}
                                to={reservations.to}
                                onPageChange={(page) => visit({ page })}
                            />
                        </>
                    )}
                </section>
            </div>
        </>
    );
}
