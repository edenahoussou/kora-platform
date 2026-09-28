import { Head, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowRight,
    CheckCircle2,
    ChevronRight,
    Clock,
    Compass,
    Eye,
    Globe2,
    MapPin,
    MoreHorizontal,
    Pencil,
    Plus,
    RotateCcw,
    Route,
    Search,
    Sparkles,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { LineCreatorStudio } from '@/components/network/preview/line-creator-studio';
import type { MockLine } from '@/components/network/preview/network-preview-data';
import { INITIAL_MOCK_LINES } from '@/components/network/preview/network-preview-data';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
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

export default function NetworkPreviewPage({
    statuses = { active: 'Active', draft: 'Brouillon', archived: 'Archivée' },
    types = {
        nationale: 'Nationale',
        internationale: 'Internationale',
        regionale: 'Régionale',
    },
    permissions = {
        canCreate: true,
        canUpdate: true,
        canDeactivate: true,
        canManageStops: true,
    },
}: PageProps) {
    // Liste des lignes gérées (mock initial + modifications de l'utilisateur)
    const [lines, setLines] = useState<MockLine[]>(INITIAL_MOCK_LINES);

    // Filtres
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedType, setSelectedType] = useState('all');
    const [selectedSens, setSelectedSens] = useState('all');
    const [selectedStatus, setSelectedStatus] = useState('all');

    // Studio de création / édition
    const [studioOpen, setStudioOpen] = useState(false);
    const [editingLine, setEditingLine] = useState<MockLine | null>(null);

    // Ligne sélectionnée pour affichage rapide
    const [selectedLineForView, setSelectedLineForView] = useState<MockLine | null>(null);

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
                const matchesItinerary = line.itinerary.toLowerCase().includes(q);

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
        const totalKm = lines.reduce((acc, l) => acc + l.distanceKm, 0);
        const allStopUuids = new Set<string>();
        lines.forEach((l) => l.stops.forEach((s) => allStopUuids.add(s.name)));
        const bidirectionalRatio = Math.round(
            (lines.filter((l) => l.bidirectional).length / (lines.length || 1)) * 100,
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

    const handleSaveLine = (savedLine: MockLine) => {
        setLines((prev) => {
            const exists = prev.some((l) => l.uuid === savedLine.uuid);

            if (exists) {
                return prev.map((l) => (l.uuid === savedLine.uuid ? savedLine : l));
            }

            return [savedLine, ...prev];
        });
    };

    const toggleLineStatus = (lineUuid: string) => {
        setLines((prev) =>
            prev.map((l) => {
                if (l.uuid !== lineUuid) {
                    return l;
                }

                const nextStatus = l.status === 'active' ? 'archived' : 'active';

                return { ...l, status: nextStatus };
            }),
        );
    };

    const deleteLine = (lineUuid: string) => {
        if (window.confirm('Voulez-vous vraiment supprimer cette ligne de la simulation ?')) {
            setLines((prev) => prev.filter((l) => l.uuid !== lineUuid));

            if (selectedLineForView?.uuid === lineUuid) {
                setSelectedLineForView(null);
            }
        }
    };

    return (
        <>
            <Head title="Réseau & Lignes — Preview V2" />

            <div className="cash-page">
                {/* 1. Page Head standardisé */}
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground flex items-center gap-1.5">
                            <span>KORA OPS</span>
                            <span>/</span>
                            <span>Exploitation</span>
                            <span>/</span>
                            <span className="font-bold text-foreground">Réseau & Lignes</span>
                            <span className="rounded bg-gold/15 px-1.5 py-0.2 text-[0.625rem] font-black text-[var(--kora)]">
                                PREVIEW V2
                            </span>
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Réseau & Lignes commerciales
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Architecturez le réseau de transport, tracez les itinéraires sur carte et gérez les arrêts opérationnels.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            onClick={() => router.get('/network')}
                            className="btn btn-ghost"
                            title="Revenir à l’ancienne vue standard"
                        >
                            ← Retour ancienne vue
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setEditingLine(null);
                                setStudioOpen(true);
                            }}
                            className="btn btn-primary"
                        >
                            <Sparkles className="size-4" /> Studio Carte — Nouvelle ligne
                        </button>
                    </div>
                </div>

                {/* 2. Bandeau d'information Preview */}
                <div className="cash-reveal mb-6 rounded-2xl border border-gold/40 bg-gradient-to-r from-gold/10 via-gold/5 to-transparent p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-sm">
                    <div className="flex items-center gap-3">
                        <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold text-[var(--kora)] shadow">
                            <Compass className="size-5" />
                        </span>
                        <div>
                            <strong className="block text-xs font-black text-foreground">
                                Environnement de prévisualisation UI V2 (Données Mockées)
                            </strong>
                            <p className="text-[0.6875rem] text-muted-foreground">
                                Cette maquette fonctionnelle intègre la nouvelle charte des écrans de caisse et le Studio Cartographique interactif avec calcul kilométrique automatique OSRM.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            setEditingLine(null);
                            setStudioOpen(true);
                        }}
                        className="btn btn-navy text-xs shrink-0"
                    >
                        Tester le Studio Carte
                    </button>
                </div>

                {/* 3. Hero & KPIs métriques */}
                <section className="cash-reveal cash-reveal-delay-1 cash-hero mb-6">
                    <div>
                        <span className="section-kicker text-gold">
                            Réseau d’exploitation actif
                        </span>
                        <h2>
                            {metrics.activeCount} lignes en exploitation active
                        </h2>
                        <p>
                            Maillage interurbain desservant {metrics.stopsCount} gares et haltes régionales sur plus de {metrics.totalKm} km de réseau routier.
                        </p>
                    </div>
                    <div className="cash-hero-current">
                        <span>Couverture totale</span>
                        <strong>{metrics.totalKm.toLocaleString('fr-FR')} km</strong>
                    </div>
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-extrabold text-white">
                        <CheckCircle2 className="size-3.5 text-gold" /> Réseau opérationnel
                    </span>
                </section>

                {/* Grille de cartes KPI légères */}
                <section className="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-6 cash-reveal cash-reveal-delay-2">
                    <div className="rounded-2xl border border-border bg-card p-4 shadow-[0_4px_20px_rgba(0,29,78,0.03)]">
                        <span className="text-[0.625rem] font-bold text-muted-foreground uppercase flex items-center gap-1.5">
                            <Route className="size-3.5 text-gold" /> Lignes actives
                        </span>
                        <strong className="mt-2 block text-2xl font-black text-foreground">
                            {metrics.activeCount} <span className="text-xs font-normal text-muted-foreground">/ {lines.length}</span>
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            Nationales et internationales
                        </span>
                    </div>

                    <div className="rounded-2xl border border-border bg-card p-4 shadow-[0_4px_20px_rgba(0,29,78,0.03)]">
                        <span className="text-[0.625rem] font-bold text-muted-foreground uppercase flex items-center gap-1.5">
                            <MapPin className="size-3.5 text-gold" /> Points de desserte
                        </span>
                        <strong className="mt-2 block text-2xl font-black text-foreground">
                            {metrics.stopsCount}
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            Gares & haltes intermédiaires
                        </span>
                    </div>

                    <div className="rounded-2xl border border-border bg-card p-4 shadow-[0_4px_20px_rgba(0,29,78,0.03)]">
                        <span className="text-[0.625rem] font-bold text-muted-foreground uppercase flex items-center gap-1.5">
                            <ArrowLeftRight className="size-3.5 text-gold" /> Sens Aller-Retour
                        </span>
                        <strong className="mt-2 block text-2xl font-black text-foreground">
                            {metrics.bidirectionalRatio}%
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            Exploitation bidirectionnelle
                        </span>
                    </div>

                    <div className="rounded-2xl border border-border bg-card p-4 shadow-[0_4px_20px_rgba(0,29,78,0.03)]">
                        <span className="text-[0.625rem] font-bold text-muted-foreground uppercase flex items-center gap-1.5">
                            <Globe2 className="size-3.5 text-gold" /> Liaisons internationales
                        </span>
                        <strong className="mt-2 block text-2xl font-black text-foreground">
                            {lines.filter((l) => l.type === 'internationale').length}
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            Corridors transfrontaliers
                        </span>
                    </div>
                </section>

                {/* 4. Barre de titre section & actions */}
                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro mb-4">
                    <div>
                        <span className="section-kicker">Référentiel des lignes</span>
                        <h2>Liaisons commerciales programmées</h2>
                    </div>
                    {permissions.canCreate && (
                        <div className="actions">
                            <button
                                type="button"
                                onClick={() => {
                                    setEditingLine(null);
                                    setStudioOpen(true);
                                }}
                                className="btn btn-navy text-xs"
                            >
                                <Plus className="size-3.5" /> Créer une ligne
                            </button>
                        </div>
                    )}
                </div>

                {/* 5. Barre de filtres standardisée (design cash-filterbar) */}
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

                {/* 6. Tableau de lignes au design de caisse (cash-table-card) */}
                <section className="cash-table-card cash-reveal cash-reveal-delay-4">
                    {filteredLines.length === 0 ? (
                        <div className="px-6 py-16 text-center text-sm text-muted-foreground">
                            <Route className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                            <p className="font-semibold text-foreground">
                                Aucune ligne ne correspond aux critères
                            </p>
                            <p className="mt-1 text-xs">
                                Modifiez vos filtres ou lancez le studio cartographique pour ajouter une liaison.
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
                                        <th className="px-4 py-3">Itinéraire</th>
                                        <th className="px-4 py-3">Sens</th>
                                        <th className="px-4 py-3">Arrêts</th>
                                        <th className="px-4 py-3">Segments</th>
                                        <th className="px-4 py-3">Distance & Durée</th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {paginatedLines.map((line) => (
                                        <tr
                                            key={line.uuid}
                                            onClick={() => setSelectedLineForView(line)}
                                            className="group cursor-pointer border-b border-black/[0.045] transition-colors hover:bg-muted/30"
                                        >
                                            {/* Colonne Ligne */}
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-2.5">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/15 font-black text-xs text-[var(--kora)]">
                                                        {line.code}
                                                    </span>
                                                    <div>
                                                        <span className="font-extrabold text-foreground text-xs block">
                                                            {line.name}
                                                        </span>
                                                        <span className="text-[0.625rem] text-muted-foreground uppercase font-bold">
                                                            {typeLabels[line.type] ?? line.type}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Colonne Itinéraire */}
                                            <td className="px-4 py-3.5 text-xs font-semibold text-foreground">
                                                <div className="flex items-center gap-1.5">
                                                    <span>{line.originCity}</span>
                                                    <ArrowRight className="size-3 text-gold shrink-0" />
                                                    <span>{line.destinationCity}</span>
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
                                                            <ArrowLeftRight className="size-3" /> Aller-Retour
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
                                                    <strong className="text-foreground font-black">
                                                        {line.distanceKm} km
                                                    </strong>
                                                    <span className="text-[0.6875rem] text-muted-foreground block flex items-center gap-1 mt-0.5">
                                                        <Clock className="size-3 text-gold" />
                                                        {Math.floor(line.estimatedDurationMinutes / 60)}h
                                                        {(line.estimatedDurationMinutes % 60).toString().padStart(2, '0')}
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Colonne Statut */}
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={statusLabels[line.status] ?? line.status}
                                                    tone={statusTones[line.status] ?? 'gold'}
                                                />
                                            </td>

                                            {/* Colonne Actions */}
                                            <td
                                                className="px-4 py-3.5 text-right"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setEditingLine(line);
                                                            setStudioOpen(true);
                                                        }}
                                                        className="btn btn-ghost p-1.5 size-8 rounded-lg"
                                                        title="Éditer dans le Studio Carte"
                                                    >
                                                        <Pencil className="size-3.5 text-foreground" />
                                                    </button>

                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
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
                                                                onClick={() => setSelectedLineForView(line)}
                                                            >
                                                                <Eye className="size-4" />
                                                                Aperçu des arrêts
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    setEditingLine(line);
                                                                    setStudioOpen(true);
                                                                }}
                                                            >
                                                                <Pencil className="size-4" />
                                                                Modifier dans le Studio
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => toggleLineStatus(line.uuid)}
                                                            >
                                                                <RotateCcw className="size-4" />
                                                                {line.status === 'active'
                                                                    ? 'Archiver la ligne'
                                                                    : 'Réactiver la ligne'}
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => deleteLine(line.uuid)}
                                                                className="text-rose-500 focus:text-rose-500"
                                                            >
                                                                <Trash2 className="size-4" />
                                                                Supprimer
                                                            </DropdownMenuItem>
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
                        totalPages={Math.ceil(filteredLines.length / pageSize) || 1}
                        total={filteredLines.length}
                        pageSize={pageSize}
                        onPageChange={setCurrentPage}
                    />
                </section>
            </div>

            {/* 7. Drawer d'Aperçu rapide d'une ligne sélectionnée */}
            {selectedLineForView && (
                <div
                    className="fixed inset-0 z-40 flex justify-end bg-black/40 backdrop-blur-sm animate-in fade-in"
                    onClick={() => setSelectedLineForView(null)}
                >
                    <div
                        className="w-full max-w-lg bg-card h-full p-6 shadow-2xl overflow-y-auto flex flex-col justify-between"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div>
                            <div className="flex items-center justify-between pb-4 border-b border-border">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-10 place-items-center rounded-xl bg-gold text-[var(--kora)] font-black text-sm">
                                        {selectedLineForView.code}
                                    </span>
                                    <div>
                                        <h3 className="font-extrabold text-foreground text-base">
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
                                    className="p-1 rounded-lg hover:bg-muted"
                                >
                                    <X className="size-5" />
                                </button>
                            </div>

                            <div className="py-4 space-y-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="p-3 rounded-xl bg-muted/40">
                                        <small className="text-[0.625rem] text-muted-foreground uppercase font-bold">
                                            Distance totale
                                        </small>
                                        <strong className="block text-base font-extrabold text-foreground">
                                            {selectedLineForView.distanceKm} km
                                        </strong>
                                    </div>
                                    <div className="p-3 rounded-xl bg-muted/40">
                                        <small className="text-[0.625rem] text-muted-foreground uppercase font-bold">
                                            Durée estimée
                                        </small>
                                        <strong className="block text-base font-extrabold text-foreground">
                                            {Math.floor(selectedLineForView.estimatedDurationMinutes / 60)}h
                                            {(selectedLineForView.estimatedDurationMinutes % 60).toString().padStart(2, '0')}
                                        </strong>
                                    </div>
                                </div>

                                <div>
                                    <h4 className="text-xs font-bold text-foreground mb-2 uppercase tracking-wider">
                                        Arrêts ordonnés ({selectedLineForView.stops.length})
                                    </h4>
                                    <div className="space-y-2">
                                        {selectedLineForView.stops.map((stop, idx) => (
                                            <div
                                                key={stop.uuid}
                                                className="flex items-center justify-between p-2.5 rounded-xl border border-border/60 bg-muted/20 text-xs"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span className="grid size-6 place-items-center rounded-lg bg-gold text-[var(--kora)] font-black text-[10px]">
                                                        {(idx + 1).toString().padStart(2, '0')}
                                                    </span>
                                                    <div>
                                                        <strong className="block font-bold text-foreground">
                                                            {stop.name}
                                                        </strong>
                                                        <span className="text-[0.625rem] text-muted-foreground">
                                                            {stop.city}, {stop.country}
                                                        </span>
                                                    </div>
                                                </div>
                                                <span className="font-extrabold text-foreground">
                                                    {stop.kmFromOrigin} km
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="pt-4 border-t border-border flex gap-3">
                            <button
                                type="button"
                                onClick={() => {
                                    setEditingLine(selectedLineForView);
                                    setSelectedLineForView(null);
                                    setStudioOpen(true);
                                }}
                                className="btn btn-primary flex-1"
                            >
                                <Pencil className="size-4" /> Éditer dans le Studio Carte
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* 8. Studio Cartographique Interactif Leaflet */}
            <LineCreatorStudio
                open={studioOpen}
                onClose={() => setStudioOpen(false)}
                onSaveLine={handleSaveLine}
                initialLine={editingLine}
            />
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
                    onClick={() => onPageChange(Math.min(totalPages, currentPage + 1))}
                    disabled={currentPage === totalPages}
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                >
                    <ChevronRight className="size-3.5" />
                </button>
            </div>
        </div>
    );
}

NetworkPreviewPage.layout = {
    breadcrumbs: [
        { title: 'Lignes & arrêts', href: '/network' } satisfies BreadcrumbItem,
        {
            title: 'Preview V2',
            href: '/network/preview',
        } satisfies BreadcrumbItem,
    ],
};
