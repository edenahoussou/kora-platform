import { Head, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowRight,
    CheckCircle2,
    ChevronRight,
    Clock,
    Eye,
    Globe2,
    MapPin,
    MoreHorizontal,
    Pencil,
    Plus,
    RotateCcw,
    Route,
    Search,
    X,
} from 'lucide-react';
import { lazy, Suspense, useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type {
    LineRecord,
    PhysicalStationRecord,
} from '@/components/network/types';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import network from '@/routes/network';
import type { BreadcrumbItem } from '@/types';

// Leaflet accède à `window` au chargement : import dynamique obligatoire pour le SSR.
const LineCreatorStudio = lazy(() =>
    import('@/components/network/line-creator-studio').then((module) => ({
        default: module.LineCreatorStudio,
    })),
);

type PageProps = {
    lines: LineRecord[];
    physicalStations: PhysicalStationRecord[];
    statuses: Record<string, string>;
    types: Record<string, string>;
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
        canDeactivate: boolean;
        canManageStops: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    draft: 'Brouillon',
    archived: 'Archivée',
};

const statusTones: Record<string, 'navy' | 'gold' | 'muted'> = {
    active: 'navy',
    draft: 'gold',
    archived: 'muted',
};

const typeLabels: Record<string, string> = {
    nationale: 'Nationale',
    internationale: 'Internationale',
    regionale: 'Régionale',
};

export default function NetworkPage({
    lines,
    physicalStations,
    statuses,
    types,
    permissions,
}: PageProps) {
    // Filtres
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedType, setSelectedType] = useState('all');
    const [selectedSens, setSelectedSens] = useState('all');
    const [selectedStatus, setSelectedStatus] = useState('all');

    // Studio de création / édition
    const [studioOpen, setStudioOpen] = useState(false);
    const [editingLine, setEditingLine] = useState<LineRecord | null>(null);

    // Ligne sélectionnée pour affichage rapide
    const [selectedLineForView, setSelectedLineForView] =
        useState<LineRecord | null>(null);
    const [drawerTab, setDrawerTab] = useState<'stops' | 'fares'>('stops');

    // Pagination
    const [currentPage, setCurrentPage] = useState(1);
    const pageSize = 10;

    // Filtrage dynamique
    const filteredLines = useMemo(() => {
        return lines.filter((line) => {
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const matchesCode = line.code.toLowerCase().includes(q);
                const matchesName = line.name.toLowerCase().includes(q);
                const matchesItinerary = line.itinerary
                    .toLowerCase()
                    .includes(q);

                if (!matchesCode && !matchesName && !matchesItinerary) {
                    return false;
                }
            }

            if (selectedType !== 'all' && line.type !== selectedType) {
                return false;
            }

            if (selectedSens === 'bidirectional' && !line.bidirectional) {
                return false;
            }

            if (selectedSens === 'oneway' && line.bidirectional) {
                return false;
            }

            if (selectedStatus !== 'all' && line.status !== selectedStatus) {
                return false;
            }

            return true;
        });
    }, [lines, searchQuery, selectedType, selectedSens, selectedStatus]);

    // Données paginées
    const paginatedLines = useMemo(() => {
        const start = (currentPage - 1) * pageSize;

        return filteredLines.slice(start, start + pageSize);
    }, [filteredLines, currentPage, pageSize]);

    // Métriques globales du réseau
    const metrics = useMemo(() => {
        const activeLines = lines.filter((l) => l.status === 'active');
        const totalKm = lines.reduce((acc, l) => acc + (l.distanceKm ?? 0), 0);
        const allStopUuids = new Set<string>();
        lines.forEach((l) =>
            l.stops.forEach((s) =>
                allStopUuids.add(s.physicalStopUuid ?? s.name),
            ),
        );
        const bidirectionalRatio = Math.round(
            (lines.filter((l) => l.bidirectional).length /
                (lines.length || 1)) *
                100,
        );

        return {
            activeCount: activeLines.length,
            totalKm,
            stopsCount: allStopUuids.size,
            bidirectionalRatio,
        };
    }, [lines]);

    const resetFilters = () => {
        setSearchQuery('');
        setSelectedType('all');
        setSelectedSens('all');
        setSelectedStatus('all');
        setCurrentPage(1);
    };

    const toggleLineStatus = (line: LineRecord) => {
        const nextStatus = line.status === 'active' ? 'archived' : 'active';

        router.patch(
            network.status.url({ line: line.uuid }),
            { status: nextStatus },
            { preserveScroll: true },
        );
    };

    const openLinePreview = (line: LineRecord) => {
        setDrawerTab('stops');
        setSelectedLineForView(line);
    };

    return (
        <>
            <Head title="Réseau & Lignes commerciales" />

            <div className="cash-page">
                {/* 1. Page Head standardisé */}
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 flex items-center gap-1.5 text-[0.6875rem] text-muted-foreground">
                            <span>KORA OPS</span>
                            <span>/</span>
                            <span>Exploitation</span>
                            <span>/</span>
                            <span className="font-bold text-foreground">
                                Réseau & Lignes
                            </span>
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Réseau & Lignes commerciales
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Architecturez le réseau de transport, tracez les
                            itinéraires sur carte et gérez les arrêts
                            opérationnels.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        {permissions.canCreate && (
                            <button
                                type="button"
                                onClick={() => {
                                    setEditingLine(null);
                                    setStudioOpen(true);
                                }}
                                className="btn btn-primary"
                            >
                                <Plus className="size-4" /> Créer une ligne
                            </button>
                        )}
                    </div>
                </div>

                {/* 2. Hero & KPIs métriques */}
                <section className="cash-reveal cash-reveal-delay-1 cash-hero mb-6">
                    <div>
                        <span className="section-kicker text-gold">
                            Réseau d’exploitation actif
                        </span>
                        <h2>
                            {metrics.activeCount} lignes en exploitation active
                        </h2>
                        <p>
                            Maillage interurbain desservant {metrics.stopsCount}{' '}
                            gares et haltes régionales sur plus de{' '}
                            {metrics.totalKm} km de réseau routier.
                        </p>
                    </div>
                    <div className="cash-hero-current">
                        <span>Couverture totale</span>
                        <strong>
                            {metrics.totalKm.toLocaleString('fr-FR')} km
                        </strong>
                    </div>
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-extrabold text-white">
                        <CheckCircle2 className="size-3.5 text-gold" /> Réseau
                        opérationnel
                    </span>
                </section>

                {/* Grille de cartes KPI légères */}
                <section className="cash-reveal cash-reveal-delay-2 mb-6 grid grid-cols-2 gap-3.5 lg:grid-cols-4">
                    <div className="rounded-2xl border border-border bg-card p-4 shadow-[0_4px_20px_rgba(0,29,78,0.03)]">
                        <span className="flex items-center gap-1.5 text-[0.625rem] font-bold text-muted-foreground uppercase">
                            <Route className="size-3.5 text-gold" /> Lignes
                            actives
                        </span>
                        <strong className="mt-2 block text-2xl font-black text-foreground">
                            {metrics.activeCount}{' '}
                            <span className="text-xs font-normal text-muted-foreground">
                                / {lines.length}
                            </span>
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            Nationales et internationales
                        </span>
                    </div>

                    <div className="rounded-2xl border border-border bg-card p-4 shadow-[0_4px_20px_rgba(0,29,78,0.03)]">
                        <span className="flex items-center gap-1.5 text-[0.625rem] font-bold text-muted-foreground uppercase">
                            <MapPin className="size-3.5 text-gold" /> Points de
                            desserte
                        </span>
                        <strong className="mt-2 block text-2xl font-black text-foreground">
                            {metrics.stopsCount}
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            Gares & haltes intermédiaires
                        </span>
                    </div>

                    <div className="rounded-2xl border border-border bg-card p-4 shadow-[0_4px_20px_rgba(0,29,78,0.03)]">
                        <span className="flex items-center gap-1.5 text-[0.625rem] font-bold text-muted-foreground uppercase">
                            <ArrowLeftRight className="size-3.5 text-gold" />{' '}
                            Sens Aller-Retour
                        </span>
                        <strong className="mt-2 block text-2xl font-black text-foreground">
                            {metrics.bidirectionalRatio}%
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            Exploitation bidirectionnelle
                        </span>
                    </div>

                    <div className="rounded-2xl border border-border bg-card p-4 shadow-[0_4px_20px_rgba(0,29,78,0.03)]">
                        <span className="flex items-center gap-1.5 text-[0.625rem] font-bold text-muted-foreground uppercase">
                            <Globe2 className="size-3.5 text-gold" /> Liaisons
                            internationales
                        </span>
                        <strong className="mt-2 block text-2xl font-black text-foreground">
                            {
                                lines.filter((l) => l.type === 'internationale')
                                    .length
                            }
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            Corridors transfrontaliers
                        </span>
                    </div>
                </section>

                {/* 3. Barre de titre section */}
                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro mb-4">
                    <div>
                        <span className="section-kicker">
                            Référentiel des lignes
                        </span>
                        <h2>Liaisons commerciales programmées</h2>
                    </div>
                </div>

                {/* 4. Barre de filtres standardisée (design cash-filterbar) */}
                <div className="cash-filterbar cash-reveal cash-reveal-delay-3">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Rechercher par code (L1), nom ou ville..."
                        className="cash-filter-search"
                        aria-label="Rechercher une ligne"
                    />

                    <FilterSelect
                        label="Type de ligne"
                        value={selectedType}
                        onChange={setSelectedType}
                        options={[
                            ['all', 'Tous les types'],
                            ...Object.entries(types).map(([val, label]) => [
                                val,
                                typeLabels[val] ?? label,
                            ]),
                        ]}
                    />

                    <FilterSelect
                        label="Sens d’exploitation"
                        value={selectedSens}
                        onChange={setSelectedSens}
                        options={[
                            ['all', 'Tous les sens'],
                            ['bidirectional', 'Aller-Retour'],
                            ['oneway', 'Sens unique'],
                        ]}
                    />

                    <FilterSelect
                        label="Statut"
                        value={selectedStatus}
                        onChange={setSelectedStatus}
                        options={[
                            ['all', 'Tous les statuts'],
                            ...Object.entries(statuses).map(([val, label]) => [
                                val,
                                statusLabels[val] ?? label,
                            ]),
                        ]}
                    />

                    <button
                        type="button"
                        onClick={resetFilters}
                        className="btn btn-ghost cash-filter-reset"
                    >
                        Réinitialiser
                    </button>
                </div>

                {/* 5. Tableau de lignes au design de caisse (cash-table-card) */}
                <section className="cash-table-card cash-reveal cash-reveal-delay-4">
                    {filteredLines.length === 0 ? (
                        <div className="px-6 py-16 text-center text-sm text-muted-foreground">
                            <Route className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                            <p className="font-semibold text-foreground">
                                Aucune ligne ne correspond aux critères
                            </p>
                            <p className="mt-1 text-xs">
                                Modifiez vos filtres ou lancez le studio
                                cartographique pour ajouter une liaison.
                            </p>
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="btn btn-ghost mt-4 text-xs"
                            >
                                Réinitialiser les filtres
                            </button>
                        </div>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[980px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Ligne</th>
                                        <th className="px-4 py-3">
                                            Itinéraire
                                        </th>
                                        <th className="px-4 py-3">Sens</th>
                                        <th className="px-4 py-3">Arrêts</th>
                                        <th className="px-4 py-3">Segments</th>
                                        <th className="px-4 py-3">
                                            Distance & Durée
                                        </th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {paginatedLines.map((line) => (
                                        <tr
                                            key={line.uuid}
                                            onClick={() =>
                                                openLinePreview(line)
                                            }
                                            className="group cursor-pointer border-b border-black/[0.045] transition-colors hover:bg-muted/30"
                                        >
                                            {/* Colonne Ligne */}
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-2.5">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold text-xs font-black text-[var(--kora)]">
                                                        {line.code}
                                                    </span>
                                                    <div>
                                                        <span className="block text-xs font-extrabold text-foreground">
                                                            {line.name}
                                                        </span>
                                                        <span className="text-[0.625rem] font-bold text-muted-foreground uppercase">
                                                            {typeLabels[
                                                                line.type
                                                            ] ?? line.type}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Colonne Itinéraire */}
                                            <td className="px-4 py-3.5 text-xs font-semibold text-foreground">
                                                <div className="flex items-center gap-1.5">
                                                    <span>
                                                        {line.originCity}
                                                    </span>
                                                    <ArrowRight className="size-3 shrink-0 text-gold" />
                                                    <span>
                                                        {line.destinationCity}
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Colonne Sens */}
                                            <td className="px-4 py-3.5">
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[0.6875rem] font-bold',
                                                        line.bidirectional
                                                            ? 'bg-digital/10 text-digital'
                                                            : 'bg-muted text-muted-foreground',
                                                    )}
                                                >
                                                    {line.bidirectional ? (
                                                        <>
                                                            <ArrowLeftRight className="size-3" />{' '}
                                                            Aller-Retour
                                                        </>
                                                    ) : (
                                                        'Sens unique'
                                                    )}
                                                </span>
                                            </td>

                                            {/* Colonne Arrêts */}
                                            <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                                                <span className="inline-flex items-center gap-1">
                                                    <MapPin className="size-3 text-muted-foreground" />
                                                    {line.activeStopsCount}
                                                </span>
                                            </td>

                                            {/* Colonne Segments */}
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                <span className="font-semibold text-foreground">
                                                    {line.segmentsCount}
                                                </span>{' '}
                                                portions
                                            </td>

                                            {/* Colonne Distance & Durée */}
                                            <td className="px-4 py-3.5">
                                                <div className="text-xs">
                                                    <strong className="font-black text-foreground">
                                                        {line.distanceKm !==
                                                        null
                                                            ? `${line.distanceKm} km`
                                                            : '—'}
                                                    </strong>
                                                    <span className="mt-0.5 block flex items-center gap-1 text-[0.6875rem] text-muted-foreground">
                                                        <Clock className="size-3 text-gold" />
                                                        {line.estimatedDurationMinutes !==
                                                        null
                                                            ? `${Math.floor(line.estimatedDurationMinutes / 60)}h${(line.estimatedDurationMinutes % 60).toString().padStart(2, '0')}`
                                                            : '—'}
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Colonne Statut */}
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={
                                                        statusLabels[
                                                            line.status
                                                        ] ?? line.status
                                                    }
                                                    tone={
                                                        statusTones[
                                                            line.status
                                                        ] ?? 'gold'
                                                    }
                                                />
                                            </td>

                                            {/* Colonne Actions */}
                                            <td
                                                className="px-4 py-3.5 text-right"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {permissions.canUpdate && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setEditingLine(
                                                                    line,
                                                                );
                                                                setStudioOpen(
                                                                    true,
                                                                );
                                                            }}
                                                            className="btn btn-ghost size-8 rounded-lg p-1.5"
                                                            title="Éditer dans le Studio Carte"
                                                        >
                                                            <Pencil className="size-3.5 text-foreground" />
                                                        </button>
                                                    )}

                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8"
                                                            >
                                                                <MoreHorizontal className="size-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    openLinePreview(
                                                                        line,
                                                                    )
                                                                }
                                                            >
                                                                <Eye className="size-4" />
                                                                Aperçu de la
                                                                ligne
                                                            </DropdownMenuItem>
                                                            {permissions.canUpdate && (
                                                                <DropdownMenuItem
                                                                    onClick={() => {
                                                                        setEditingLine(
                                                                            line,
                                                                        );
                                                                        setStudioOpen(
                                                                            true,
                                                                        );
                                                                    }}
                                                                >
                                                                    <Pencil className="size-4" />
                                                                    Modifier
                                                                    dans le
                                                                    Studio
                                                                </DropdownMenuItem>
                                                            )}
                                                            {permissions.canDeactivate && (
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        toggleLineStatus(
                                                                            line,
                                                                        )
                                                                    }
                                                                >
                                                                    <RotateCcw className="size-4" />
                                                                    {line.status ===
                                                                    'active'
                                                                        ? 'Archiver la ligne'
                                                                        : 'Réactiver la ligne'}
                                                                </DropdownMenuItem>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    <Pagination
                        currentPage={currentPage}
                        totalPages={
                            Math.ceil(filteredLines.length / pageSize) || 1
                        }
                        total={filteredLines.length}
                        pageSize={pageSize}
                        onPageChange={setCurrentPage}
                    />
                </section>
            </div>

            {/* 6. Drawer d'Aperçu rapide d'une ligne sélectionnée */}
            {selectedLineForView && (
                <div
                    className="fixed inset-0 z-40 flex animate-in justify-end bg-black/40 backdrop-blur-sm fade-in"
                    onClick={() => setSelectedLineForView(null)}
                >
                    <div
                        className="flex h-full w-full max-w-lg flex-col justify-between overflow-y-auto bg-card p-6 shadow-2xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div>
                            <div className="flex items-center justify-between border-b border-border pb-4">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-10 place-items-center rounded-xl bg-gold text-sm font-black text-[var(--kora)]">
                                        {selectedLineForView.code}
                                    </span>
                                    <div>
                                        <h3 className="text-base font-extrabold text-foreground">
                                            {selectedLineForView.name}
                                        </h3>
                                        <span className="text-xs text-muted-foreground">
                                            {selectedLineForView.itinerary}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setSelectedLineForView(null)}
                                    className="rounded-lg p-1 hover:bg-muted"
                                >
                                    <X className="size-5" />
                                </button>
                            </div>

                            <div className="space-y-4 py-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="rounded-xl bg-muted/40 p-3">
                                        <small className="text-[0.625rem] font-bold text-muted-foreground uppercase">
                                            Distance totale
                                        </small>
                                        <strong className="block text-base font-extrabold text-foreground">
                                            {selectedLineForView.distanceKm !==
                                            null
                                                ? `${selectedLineForView.distanceKm} km`
                                                : '—'}
                                        </strong>
                                    </div>
                                    <div className="rounded-xl bg-muted/40 p-3">
                                        <small className="text-[0.625rem] font-bold text-muted-foreground uppercase">
                                            Durée estimée
                                        </small>
                                        <strong className="block text-base font-extrabold text-foreground">
                                            {selectedLineForView.estimatedDurationMinutes !==
                                            null
                                                ? `${Math.floor(selectedLineForView.estimatedDurationMinutes / 60)}h${(selectedLineForView.estimatedDurationMinutes % 60).toString().padStart(2, '0')}`
                                                : '—'}
                                        </strong>
                                    </div>
                                </div>

                                {/* Onglets : Arrêts ordonnés / Tarifs O-D */}
                                <div className="flex gap-1.5 rounded-xl bg-muted/50 p-1">
                                    <button
                                        type="button"
                                        onClick={() => setDrawerTab('stops')}
                                        className={cn(
                                            'flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-all',
                                            drawerTab === 'stops'
                                                ? 'bg-card text-foreground shadow-sm'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        Arrêts (
                                        {selectedLineForView.stops.length})
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setDrawerTab('fares')}
                                        className={cn(
                                            'flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-all',
                                            drawerTab === 'fares'
                                                ? 'bg-card text-foreground shadow-sm'
                                                : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        Tarifs (
                                        {selectedLineForView.fares.length})
                                    </button>
                                </div>

                                {drawerTab === 'stops' && (
                                    <div className="space-y-2">
                                        {selectedLineForView.stops.map(
                                            (stop, idx) => (
                                                <div
                                                    key={stop.uuid}
                                                    className="flex items-center justify-between rounded-xl border border-border/60 bg-muted/20 p-2.5 text-xs"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span className="grid size-6 place-items-center rounded-lg bg-gold text-[10px] font-black text-[var(--kora)]">
                                                            {(idx + 1)
                                                                .toString()
                                                                .padStart(
                                                                    2,
                                                                    '0',
                                                                )}
                                                        </span>
                                                        <div>
                                                            <strong className="block font-bold text-foreground">
                                                                {stop.name}
                                                            </strong>
                                                            <span className="text-[0.625rem] text-muted-foreground">
                                                                {stop.city},{' '}
                                                                {stop.country}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <span className="font-extrabold text-foreground">
                                                        {stop.kmFromOrigin} km
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}

                                {drawerTab === 'fares' && (
                                    <div className="space-y-2">
                                        {selectedLineForView.fares.length ===
                                        0 ? (
                                            <div className="rounded-xl border border-dashed border-border p-6 text-center">
                                                <p className="text-xs font-bold text-foreground">
                                                    Aucun tarif défini
                                                </p>
                                                <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                                    Les tarifs
                                                    origine-destination seront
                                                    configurés dans l'écran
                                                    Tarifs.
                                                </p>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.get('/fares')
                                                    }
                                                    className="btn btn-ghost mt-3 text-xs"
                                                >
                                                    Gérer les tarifs
                                                </button>
                                            </div>
                                        ) : (
                                            selectedLineForView.fares.map(
                                                (fare) => (
                                                    <div
                                                        key={fare.uuid}
                                                        className="flex items-center justify-between rounded-xl border border-border/60 bg-muted/20 p-2.5 text-xs"
                                                    >
                                                        <div>
                                                            <div className="flex items-center gap-1.5 font-bold text-foreground">
                                                                <span>
                                                                    {
                                                                        fare.originName
                                                                    }
                                                                </span>
                                                                <ArrowRight className="size-3 text-gold" />
                                                                <span>
                                                                    {
                                                                        fare.destinationName
                                                                    }
                                                                </span>
                                                            </div>
                                                            <span className="text-[0.625rem] font-bold text-muted-foreground uppercase">
                                                                {
                                                                    fare.serviceClassName
                                                                }
                                                            </span>
                                                        </div>
                                                        <span className="font-black text-foreground">
                                                            {fare.baseAmount.toLocaleString(
                                                                'fr-FR',
                                                            )}{' '}
                                                            <small className="text-[0.625rem] font-bold text-muted-foreground">
                                                                {fare.currency ===
                                                                'XOF'
                                                                    ? 'FCFA'
                                                                    : fare.currency}
                                                            </small>
                                                        </span>
                                                    </div>
                                                ),
                                            )
                                        )}
                                        {selectedLineForView.fares.length >
                                            0 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.get('/fares')
                                                }
                                                className="w-full pt-1 text-center text-[0.6875rem] font-semibold text-gold hover:underline"
                                            >
                                                Gérer la grille tarifaire
                                                complète →
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        {permissions.canUpdate && (
                            <div className="flex gap-3 border-t border-border pt-4">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setEditingLine(selectedLineForView);
                                        setSelectedLineForView(null);
                                        setStudioOpen(true);
                                    }}
                                    className="btn btn-primary flex-1"
                                >
                                    <Pencil className="size-4" /> Éditer dans le
                                    Studio Carte
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* 7. Studio Cartographique Interactif Leaflet */}
            {studioOpen && (
                <Suspense fallback={null}>
                    <LineCreatorStudio
                        open={studioOpen}
                        onClose={() => setStudioOpen(false)}
                        physicalStations={physicalStations}
                        initialLine={editingLine}
                    />
                </Suspense>
            )}
        </>
    );
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
    options: string[][];
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
            <ChevronRight className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground" />
        </label>
    );
}

function Pagination({
    currentPage,
    totalPages,
    total,
    pageSize,
    onPageChange,
}: {
    currentPage: number;
    totalPages: number;
    total: number;
    pageSize: number;
    onPageChange: (page: number) => void;
}) {
    const from = total === 0 ? 0 : (currentPage - 1) * pageSize + 1;
    const to = Math.min(currentPage * pageSize, total);

    return (
        <div className="flex flex-col gap-4 px-4 py-3 text-[0.6875rem] text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <span>
                {from}–{to} sur {total} lignes
            </span>
            <div className="flex items-center gap-[5px]">
                <button
                    type="button"
                    onClick={() => onPageChange(Math.max(1, currentPage - 1))}
                    disabled={currentPage === 1}
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                >
                    <ChevronRight className="size-3.5 rotate-180" />
                </button>
                <span className="px-2 font-bold text-foreground">
                    Page {currentPage} / {totalPages}
                </span>
                <button
                    type="button"
                    onClick={() =>
                        onPageChange(Math.min(totalPages, currentPage + 1))
                    }
                    disabled={currentPage === totalPages}
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                >
                    <ChevronRight className="size-3.5" />
                </button>
            </div>
        </div>
    );
}

NetworkPage.layout = {
    breadcrumbs: [
        {
            title: 'Lignes & arrêts',
            href: '/network',
        } satisfies BreadcrumbItem,
    ],
};
