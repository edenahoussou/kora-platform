import { Head, router } from '@inertiajs/react';
import {
    ChevronRight,
    Eye,
    Globe,
    IdCard,
    Mail,
    MapPin,
    Phone,
    RotateCcw,
    Search,
    Smartphone,
    Ticket as TicketIcon,
    Trash2,
    Users as UsersIcon,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { PassengerDetailDrawer } from '@/components/passengers/passenger-detail-drawer';
import { cn } from '@/lib/utils';
import type {
    PaginatedPassengers,
    PassengerFilters,
    PassengerPermissions,
    PassengerSummary,
} from '@/types/passengers';

type PendingErasure = {
    uuid: string;
    name: string | null;
    phone: string | null;
    email: string | null;
    requested_at: string | null;
};

type Props = {
    passengers: PaginatedPassengers;
    filters: PassengerFilters;
    permissions?: PassengerPermissions;
    pendingErasures?: PendingErasure[];
};

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

export default function PassengersPage({
    passengers = {
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0,
    },
    filters = {},
    permissions = { canExport: false, canErase: false },
    pendingErasures = [],
}: Props) {
    const { current_page: currentPage, last_page: lastPage } = passengers;
    const firstNearbyPage = Math.max(
        1,
        Math.min(currentPage - 1, lastPage - 2),
    );
    const pageNumbers = Array.from(
        new Set([
            1,
            firstNearbyPage,
            firstNearbyPage + 1,
            firstNearbyPage + 2,
            lastPage,
        ]),
    )
        .filter((page) => page >= 1 && page <= lastPage)
        .sort((a, b) => a - b);
    const [query, setQuery] = useState(filters.q ?? '');
    const [selectedStatus, setSelectedStatus] = useState(
        filters.status ?? 'all',
    );
    const [selectedFrequency, setSelectedFrequency] = useState(
        filters.frequency ?? 'all',
    );

    const [drawerOpen, setDrawerOpen] = useState(false);
    const [selectedPassenger, setSelectedPassenger] =
        useState<PassengerSummary | null>(null);

    const searchTimer = useRef<number | null>(null);

    const visit = (newParams: Record<string, string | number | null>) => {
        const params: Record<string, string | number> = {};

        const q = newParams.q !== undefined ? newParams.q : query;
        const status =
            newParams.status !== undefined ? newParams.status : selectedStatus;
        const frequency =
            newParams.frequency !== undefined
                ? newParams.frequency
                : selectedFrequency;
        const page = newParams.page !== undefined ? newParams.page : 1;

        if (q) {
            params.q = String(q);
        }

        if (status && status !== 'all') {
            params.status = String(status);
        }

        if (frequency && frequency !== 'all') {
            params.frequency = String(frequency);
        }

        if (Number(page) > 1) {
            params.page = Number(page);
        }

        router.get('/passengers', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleQueryChange = (val: string) => {
        setQuery(val);

        if (searchTimer.current !== null) {
            window.clearTimeout(searchTimer.current);
        }

        searchTimer.current = window.setTimeout(() => {
            visit({ q: val, page: 1 });
        }, 350);
    };

    useEffect(() => {
        return () => {
            if (searchTimer.current !== null) {
                window.clearTimeout(searchTimer.current);
            }
        };
    }, []);

    const resetFilters = () => {
        setQuery('');
        setSelectedStatus('all');
        setSelectedFrequency('all');
        router.get(
            '/passengers',
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const openDetail = (passenger: PassengerSummary) => {
        setSelectedPassenger(passenger);
        setDrawerOpen(true);
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
                {options.map(([optVal, optLabel]) => (
                    <option key={optVal} value={optVal}>
                        {optLabel}
                    </option>
                ))}
            </select>
            <ChevronRight
                className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                strokeWidth={1.7}
            />
        </label>
    );

    return (
        <>
            <Head title="Annuaire des Passagers" />

            <div className="cash-page">
                {/* En-tête de la page (sans cartes KPI) */}
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Réseau commercial & Vente / Passagers
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Fiches Voyageurs & Passagers
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Consultez les dossiers passagers, coordonnées,
                            pièces d’identité, volumes d’achat et historiques de
                            trajets.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="rounded-xl border border-black/[0.06] bg-card px-3.5 py-2 text-xs font-bold text-muted-foreground shadow-2xs dark:border-white/[0.06]">
                            <strong className="text-foreground">
                                {passengers.total}
                            </strong>{' '}
                            passager{passengers.total > 1 ? 's' : ''} au total
                        </span>
                    </div>
                </div>

                {/* Demandes de suppression de compte en attente */}
                {permissions.canErase && pendingErasures.length > 0 && (
                    <div className="cash-reveal mb-6 rounded-2xl border border-red-200/70 bg-red-50/60 p-4 dark:border-red-400/20 dark:bg-red-400/10">
                        <div className="flex items-center gap-2 text-[0.8125rem] font-bold text-red-700 dark:text-red-300">
                            <Trash2 className="size-4" />
                            {pendingErasures.length} demande
                            {pendingErasures.length > 1 ? 's' : ''} de
                            suppression de compte en attente
                        </div>
                        <ul className="mt-3 grid gap-2">
                            {pendingErasures.map((erasure) => (
                                <li
                                    key={erasure.uuid}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-card px-3.5 py-2.5 ring-1 ring-black/[0.045] dark:ring-white/[0.06]"
                                >
                                    <span className="text-xs text-muted-foreground">
                                        <strong className="text-foreground">
                                            {erasure.phone ??
                                                erasure.name ??
                                                'Contact inconnu'}
                                        </strong>
                                        {erasure.name &&
                                            erasure.phone &&
                                            ` · ${erasure.name}`}
                                        {erasure.requested_at &&
                                            ` · reçue le ${new Date(erasure.requested_at).toLocaleDateString('fr-FR')}`}
                                    </span>
                                    {erasure.phone && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleQueryChange(
                                                    erasure.phone ?? '',
                                                )
                                            }
                                            className="rounded-lg bg-red-600/10 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-600/20 dark:text-red-300"
                                        >
                                            Traiter
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Barre de filtres */}
                <div className="cash-filterbar cash-reveal cash-reveal-delay-1 mb-6">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(e) => handleQueryChange(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                visit({ page: 1 });
                            }
                        }}
                        aria-label="Rechercher par nom, téléphone, email ou pièce d'identité"
                        placeholder="Rechercher un voyageur par nom, téléphone, pièce d'identité…"
                        className="cash-filter-search"
                    />

                    {filterSelect(
                        'Statut',
                        selectedStatus,
                        (value) => {
                            setSelectedStatus(value);
                            visit({ status: value, page: 1 });
                        },
                        [
                            ['all', 'Tous les statuts'],
                            ['active', 'Actif (ayant voyagé)'],
                            ['app', 'Compte KORA App'],
                            ['to_verify', 'À compléter / Vérifier'],
                        ],
                    )}

                    {filterSelect(
                        'Fréquence',
                        selectedFrequency,
                        (value) => {
                            setSelectedFrequency(value);
                            visit({ frequency: value, page: 1 });
                        },
                        [
                            ['all', 'Tous les volumes'],
                            ['frequent', 'Habitués (5+ trajets)'],
                            ['occasional', 'Occasionnels (2–4 trajets)'],
                            ['new', 'Nouveaux (1 trajet)'],
                        ],
                    )}

                    {(query ||
                        selectedStatus !== 'all' ||
                        selectedFrequency !== 'all') && (
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="btn btn-ghost flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                            title="Réinitialiser tous les filtres"
                        >
                            <RotateCcw className="size-3.5" />
                            Réinitialiser
                        </button>
                    )}
                </div>

                {/* Tableau principal exploitant toute la largeur */}
                <section className="cash-table-card cash-reveal cash-reveal-delay-2">
                    {passengers.data.length === 0 ? (
                        <div className="px-6 py-16 text-center text-sm text-muted-foreground">
                            <UsersIcon className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                            <p className="font-semibold text-foreground">
                                Aucun passager trouvé
                            </p>
                            <p className="mt-1 text-xs">
                                Modifiez vos termes de recherche ou
                                réinitialisez les filtres.
                            </p>
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="btn btn-ghost mt-4 text-xs font-bold"
                            >
                                Réinitialiser les critères
                            </button>
                        </div>
                    ) : (
                        <>
                            <div className="cash-table-scroll overflow-x-auto">
                                <table className="w-full min-w-[1050px] text-left">
                                    <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Voyageur
                                            </th>
                                            <th className="px-4 py-3">
                                                Coordonnées
                                            </th>
                                            <th className="px-4 py-3">
                                                Pièce & Nationalité
                                            </th>
                                            <th className="px-4 py-3">
                                                Dernier départ
                                            </th>
                                            <th className="px-4 py-3 text-center">
                                                Billets
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Dépenses
                                            </th>
                                            <th className="px-4 py-3">
                                                Statut
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Fiche
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {passengers.data.map((p) => {
                                            return (
                                                <tr
                                                    key={p.phone}
                                                    onClick={() =>
                                                        openDetail(p)
                                                    }
                                                    className="group cursor-pointer border-b border-black/[0.045] transition-colors hover:bg-muted/30 dark:border-white/[0.045]"
                                                >
                                                    {/* Voyageur */}
                                                    <td className="px-4 py-3.5">
                                                        <div className="flex items-center gap-3">
                                                            <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/15 text-xs font-black text-gold">
                                                                {getInitials(
                                                                    p.fullName,
                                                                )}
                                                            </span>
                                                            <div className="min-w-0">
                                                                <span className="block truncate font-bold text-foreground">
                                                                    {p.fullName}
                                                                </span>
                                                                <div className="mt-0.5 flex items-center gap-1.5 text-[0.6875rem]">
                                                                    {p.hasAccount ? (
                                                                        <span className="inline-flex items-center gap-1 font-medium text-digital">
                                                                            <Smartphone className="size-3" />
                                                                            KORA
                                                                            App
                                                                        </span>
                                                                    ) : (
                                                                        <span className="inline-flex items-center gap-1 font-medium text-muted-foreground">
                                                                            <TicketIcon className="size-3" />
                                                                            Guichet
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    {/* Coordonnées */}
                                                    <td className="px-4 py-3.5">
                                                        <div className="space-y-0.5">
                                                            <div className="flex items-center gap-1.5 font-mono text-xs font-semibold text-foreground">
                                                                <Phone className="size-3 text-muted-foreground" />
                                                                {p.phone}
                                                            </div>
                                                            {p.email ? (
                                                                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                                    <Mail className="size-3" />
                                                                    <span className="max-w-[180px] truncate">
                                                                        {
                                                                            p.email
                                                                        }
                                                                    </span>
                                                                </div>
                                                            ) : (
                                                                <span className="text-[0.6875rem] text-muted-foreground/60">
                                                                    Email non
                                                                    renseigné
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>

                                                    {/* Pièce & Nationalité */}
                                                    <td className="px-4 py-3.5">
                                                        <div className="space-y-0.5">
                                                            <div className="flex items-center gap-1.5 text-xs font-medium text-foreground">
                                                                <IdCard className="size-3 text-muted-foreground" />
                                                                <span>
                                                                    {
                                                                        p.documentTypeLabel
                                                                    }
                                                                </span>
                                                            </div>
                                                            <div className="flex items-center gap-2 text-[0.6875rem] text-muted-foreground">
                                                                {p.documentNumber ? (
                                                                    <span className="font-mono font-medium">
                                                                        {
                                                                            p.documentNumber
                                                                        }
                                                                    </span>
                                                                ) : (
                                                                    <span>
                                                                        N° non
                                                                        renseigné
                                                                    </span>
                                                                )}
                                                                {p.nationality && (
                                                                    <span className="inline-flex items-center gap-1 rounded bg-muted px-1 py-0.5 font-bold">
                                                                        <Globe className="size-2.5" />
                                                                        {
                                                                            p.nationality
                                                                        }
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </td>

                                                    {/* Dernier départ */}
                                                    <td className="px-4 py-3.5">
                                                        {p.lastRoute ? (
                                                            <div>
                                                                <div className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                                                    <MapPin className="size-3 text-gold" />
                                                                    <span>
                                                                        {
                                                                            p.lastRoute
                                                                        }
                                                                    </span>
                                                                </div>
                                                                <span className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                                                                    {p.lastSeenRelative ??
                                                                        p.lastSeenAt}
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">
                                                                Aucun trajet
                                                            </span>
                                                        )}
                                                    </td>

                                                    {/* Billets */}
                                                    <td className="px-4 py-3.5 text-center">
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center justify-center rounded-lg px-2.5 py-1 text-xs font-black',
                                                                p.ticketsCount >=
                                                                    5
                                                                    ? 'bg-gold/15 text-gold'
                                                                    : 'bg-muted text-foreground',
                                                            )}
                                                        >
                                                            {p.ticketsCount}
                                                        </span>
                                                    </td>

                                                    {/* Dépenses */}
                                                    <td className="px-4 py-3.5 text-right">
                                                        <span className="font-mono text-xs font-bold text-foreground">
                                                            {p.totalSpendLabel}
                                                        </span>
                                                    </td>

                                                    {/* Statut */}
                                                    <td className="px-4 py-3.5">
                                                        <KoraStatusBadge
                                                            label={
                                                                p.statusLabel
                                                            }
                                                            tone={
                                                                p.status ===
                                                                'active'
                                                                    ? 'blue'
                                                                    : 'gold'
                                                            }
                                                        />
                                                    </td>

                                                    {/* Action */}
                                                    <td className="px-4 py-3.5 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                openDetail(p);
                                                            }}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-black/[0.06] bg-card px-2.5 py-1.5 text-xs font-bold text-muted-foreground shadow-2xs transition hover:border-gold/40 hover:bg-gold/10 hover:text-gold dark:border-white/[0.06]"
                                                            title="Voir la fiche détaillée"
                                                        >
                                                            <Eye className="size-3.5" />
                                                            Détail
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            <div className="flex flex-col items-center justify-between gap-4 border-t border-black/[0.05] p-4 text-xs text-muted-foreground sm:flex-row dark:border-white/[0.05]">
                                <span>
                                    Affichage de {passengers.from} à{' '}
                                    {passengers.to} sur {passengers.total}{' '}
                                    passagers
                                </span>

                                {lastPage > 1 && (
                                    <nav
                                        aria-label="Pagination des voyageurs"
                                        className="flex flex-wrap items-center gap-1.5"
                                    >
                                        {pageNumbers.map((page, index) => (
                                            <span
                                                key={page}
                                                className="flex items-center gap-1.5"
                                            >
                                                {index > 0 &&
                                                    page -
                                                        pageNumbers[index - 1] >
                                                        1 && (
                                                        <span aria-hidden="true">
                                                            …
                                                        </span>
                                                    )}
                                                <button
                                                    type="button"
                                                    aria-current={
                                                        page === currentPage
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    aria-label={`Page ${page}`}
                                                    className={cn(
                                                        'grid size-8 place-items-center rounded-lg font-bold',
                                                        page === currentPage
                                                            ? 'bg-kora text-white'
                                                            : 'cursor-pointer bg-muted text-foreground hover:bg-gold/20',
                                                    )}
                                                    onClick={() =>
                                                        page !== currentPage &&
                                                        visit({ page })
                                                    }
                                                >
                                                    {page}
                                                </button>
                                            </span>
                                        ))}
                                    </nav>
                                )}
                            </div>
                        </>
                    )}
                </section>
            </div>

            {/* Tiroir latéral de détail du passager */}
            <PassengerDetailDrawer
                open={drawerOpen}
                passenger={selectedPassenger}
                onClose={() => setDrawerOpen(false)}
                canExport={permissions.canExport}
                canErase={permissions.canErase}
            />
        </>
    );
}
