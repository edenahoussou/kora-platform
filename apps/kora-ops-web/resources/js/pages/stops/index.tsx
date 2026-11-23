import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronRight,
    Loader2,
    Lock,
    MapPin,
    MapPinned,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type { PhysicalStopItem } from '@/components/stops/stop-editor-modal';
import { StopEditorModal } from '@/components/stops/stop-editor-modal';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type PaginatedStops = {
    data: PhysicalStopItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type StopsPageProps = {
    stops: PaginatedStops;
    cities: string[];
    filters: {
        q?: string;
        city?: string;
        status?: string;
    };
    mapSettings: {
        provider: string;
        osmTileUrl: string;
        osmAttribution: string;
        googleApiKey: string | null;
    };
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
        canDelete: boolean;
    };
};

export default function StopsPage({
    stops,
    cities,
    filters,
    mapSettings,
    permissions,
}: StopsPageProps) {
    const [searchQuery, setSearchQuery] = useState(filters.q ?? '');
    const [selectedCity, setSelectedCity] = useState(filters.city ?? 'all');
    const [selectedStatus, setSelectedStatus] = useState(filters.status ?? 'all');

    // Modals
    const [editorOpen, setEditorOpen] = useState(false);
    const [editingStop, setEditingStop] = useState<PhysicalStopItem | null>(null);

    const [stopPendingDeletion, setStopPendingDeletion] =
        useState<PhysicalStopItem | null>(null);
    const [deletePassword, setDeletePassword] = useState('');
    const [deleteError, setDeleteError] = useState('');
    const [deleteSubmitting, setDeleteSubmitting] = useState(false);

    // Apply filters
    const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);

        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

        searchTimeoutRef.current = setTimeout(() => {
            applyFilters({ q: value });
        }, 450);
    };

    const applyFilters = (patch: Record<string, string>) => {
        const nextFilters: Record<string, string> = {
            q: searchQuery,
            city: selectedCity === 'all' ? '' : selectedCity,
            status: selectedStatus === 'all' ? '' : selectedStatus,
            ...patch,
        };

        const cleaned = Object.fromEntries(
            Object.entries(nextFilters).filter(([, v]) => v !== ''),
        );

        router.get('/stops', cleaned, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

        setSearchQuery('');
        setSelectedCity('all');
        setSelectedStatus('all');
        router.get('/stops', {}, { preserveScroll: true });
    };

    const confirmDelete = () => {
        if (!stopPendingDeletion) {
            return;
        }

        setDeleteSubmitting(true);
        setDeleteError('');

        router.delete(`/stops/${stopPendingDeletion.uuid}`, {
            data: { password: deletePassword },
            preserveScroll: true,
            onError: (errs) => {
                setDeleteSubmitting(false);

                if (errs.password) {
                    setDeleteError(errs.password);
                } else if (errs.stop) {
                    setDeleteError(errs.stop);
                }
            },
            onSuccess: () => {
                setDeleteSubmitting(false);
                setStopPendingDeletion(null);
                setDeletePassword('');
                setDeleteError('');
            },
        });
    };

    // Metrics
    const metrics = useMemo(() => {
        const total = stops.total;
        const attached = stops.data.filter((s) => s.stopsCount > 0).length;
        const unattached = stops.data.filter((s) => s.stopsCount === 0).length;

        return {
            total,
            citiesCount: cities.length,
            attached,
            unattached,
        };
    }, [stops, cities]);

    return (
        <>
            <Head title="Points d’arrêt physiques" />

            <div className="cash-page">
                {/* 1. Page Head */}
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 flex items-center gap-1.5 text-[0.6875rem] text-muted-foreground">
                            <span>KORA OPS</span>
                            <span>/</span>
                            <span>Exploitation</span>
                            <span>/</span>
                            <span className="font-bold text-foreground">
                                Points d’arrêt
                            </span>
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Points d’arrêt physiques
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Gérez les gares, carrefours et lieux physiques de montée et descente.
                            Chaque point porte ses coordonnées GPS exactes partagées par toutes les lignes.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        {permissions.canCreate && (
                            <button
                                type="button"
                                onClick={() => {
                                    setEditingStop(null);
                                    setEditorOpen(true);
                                }}
                                className="btn btn-primary"
                            >
                                <Plus className="size-4" /> Nouveau point d’arrêt
                            </button>
                        )}
                    </div>
                </div>

                {/* 2. Hero & KPIs */}
                <section className="cash-reveal cash-reveal-delay-1 cash-hero mb-6">
                    <div>
                        <span className="section-kicker text-gold">
                            Référentiel cartographique
                        </span>
                        <h2>{metrics.total} points d’arrêt enregistrés</h2>
                    </div>
                    <div className="cash-kpi-strip grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div className="cash-kpi-item">
                            <span className="kpi-label text-gold">Villes couvertes</span>
                            <strong className="kpi-value text-gold">
                                {metrics.citiesCount}
                            </strong>
                        </div>
                        <div className="cash-kpi-item">
                            <span className="kpi-label text-gold">Rattachés aux lignes</span>
                            <strong className="kpi-value text-gold">
                                {metrics.attached}
                            </strong>
                        </div>
                        <div className="cash-kpi-item">
                            <span className="kpi-label text-gold">Isolés / En attente</span>
                            <strong className="kpi-value text-gold">
                                {metrics.unattached}
                            </strong>
                        </div>
                    </div>
                </section>

                {/* 3. Filtres au-dessus du tableau */}
                <div className="cash-filter-card cash-reveal cash-reveal-delay-3 mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-card p-4">
                    <div className="relative min-w-[240px] flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => handleSearchChange(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    applyFilters({ q: searchQuery });
                                }
                            }}
                            placeholder="Rechercher par code, nom ou ville…"
                            className="h-11 w-full rounded-xl bg-muted/55 pl-9 pr-3 text-xs font-bold text-foreground placeholder:text-muted-foreground focus:bg-card focus:outline-hidden focus:ring-2 focus:ring-gold/35"
                        />
                    </div>

                    <label className="cash-filter-field relative block">
                        <span className="sr-only">Ville</span>
                        <select
                            value={selectedCity}
                            onChange={(e) => {
                                setSelectedCity(e.target.value);
                                applyFilters({
                                    city: e.target.value === 'all' ? '' : e.target.value,
                                });
                            }}
                            className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] focus:bg-card focus:outline-hidden focus:ring-2 focus:ring-gold/35"
                        >
                            <option value="all">Toutes les villes</option>
                            {cities.map((city) => (
                                <option key={city} value={city}>
                                    {city}
                                </option>
                            ))}
                        </select>
                        <ChevronRight className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground" />
                    </label>

                    <label className="cash-filter-field relative block">
                        <span className="sr-only">Statut</span>
                        <select
                            value={selectedStatus}
                            onChange={(e) => {
                                setSelectedStatus(e.target.value);
                                applyFilters({
                                    status: e.target.value === 'all' ? '' : e.target.value,
                                });
                            }}
                            className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] focus:bg-card focus:outline-hidden focus:ring-2 focus:ring-gold/35"
                        >
                            <option value="all">Tous les statuts</option>
                            <option value="active">Actif</option>
                            <option value="inactive">Inactif</option>
                            <option value="archived">Archivé</option>
                        </select>
                        <ChevronRight className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground" />
                    </label>

                    <button
                        type="button"
                        onClick={resetFilters}
                        className="btn btn-ghost cash-filter-reset"
                    >
                        Réinitialiser
                    </button>
                </div>

                {/* 4. Tableau des points d'arrêt */}
                <section className="cash-table-card cash-reveal cash-reveal-delay-4">
                    {stops.data.length === 0 ? (
                        <div className="px-6 py-16 text-center text-sm text-muted-foreground">
                            <MapPin className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                            <p className="font-semibold text-foreground">
                                Aucun point d’arrêt trouvé
                            </p>
                            <p className="mt-1 text-xs">
                                Modifiez vos critères de recherche ou créez un nouveau point d’arrêt.
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
                            <table className="w-full min-w-[850px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Code</th>
                                        <th className="px-4 py-3">Point d’arrêt</th>
                                        <th className="px-4 py-3">Ville & Pays</th>
                                        <th className="px-4 py-3">Coordonnées GPS</th>
                                        <th className="px-4 py-3">Lignes connectées</th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stops.data.map((stop) => (
                                        <tr
                                            key={stop.uuid}
                                            className="border-b border-black/[0.045] transition-colors hover:bg-muted/30"
                                        >
                                            {/* Code */}
                                            <td className="px-4 py-3.5 font-mono text-xs font-bold text-foreground">
                                                {stop.code || 'Non renseigné'}
                                            </td>

                                            {/* Nom */}
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-2">
                                                    <span className="grid size-7 shrink-0 place-items-center rounded-lg bg-gold/15 text-kora dark:text-gold">
                                                        <MapPin className="size-3.5" />
                                                    </span>
                                                    <span className="text-xs font-extrabold text-foreground">
                                                        {stop.name}
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Ville & Pays */}
                                            <td className="px-4 py-3.5 text-xs text-foreground">
                                                <div className="flex items-center gap-1.5 font-semibold">
                                                    <span>{stop.city}</span>
                                                    <span className="rounded-md bg-muted px-1.5 py-0.5 text-[0.625rem] font-extrabold text-muted-foreground">
                                                        {stop.country}
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Coordonnées GPS */}
                                            <td className="px-4 py-3.5 font-mono text-xs">
                                                {stop.latitude !== null && stop.longitude !== null ? (
                                                    <span className="text-foreground">
                                                        {stop.latitude.toFixed(5)}, {stop.longitude.toFixed(5)}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground italic">
                                                        Non renseigné
                                                    </span>
                                                )}
                                            </td>

                                            {/* Lignes connectées */}
                                            <td className="px-4 py-3.5 text-xs">
                                                {stop.lines.length > 0 ? (
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        {stop.lines.map((l) => (
                                                            <span
                                                                key={l.uuid}
                                                                className="rounded-md bg-gold px-1.5 py-0.5 text-[0.625rem] font-black text-[var(--kora)]"
                                                                title={l.name}
                                                            >
                                                                {l.code}
                                                            </span>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground italic">
                                                        Aucune ligne
                                                    </span>
                                                )}
                                            </td>

                                            {/* Statut */}
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={
                                                        stop.status === 'active'
                                                            ? 'Actif'
                                                            : stop.status === 'archived'
                                                              ? 'Archivé'
                                                              : 'Inactif'
                                                    }
                                                    tone={
                                                        stop.status === 'active'
                                                            ? 'navy'
                                                            : 'muted'
                                                    }
                                                />
                                            </td>

                                            {/* Actions */}
                                            <td className="px-4 py-3.5 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {permissions.canUpdate && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setEditingStop(stop);
                                                                setEditorOpen(true);
                                                            }}
                                                            className="grid size-9 cursor-pointer place-items-center rounded-lg border border-border bg-card text-foreground shadow-xs transition-colors hover:border-gold hover:bg-gold/10"
                                                            title="Modifier ou repositionner sur la carte"
                                                        >
                                                            <MapPinned className="size-4" />
                                                        </button>
                                                    )}
                                                    {permissions.canDelete && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setStopPendingDeletion(stop);
                                                                setDeletePassword('');
                                                                setDeleteError('');
                                                            }}
                                                            className="grid size-9 cursor-pointer place-items-center rounded-lg border border-destructive/40 bg-card text-destructive shadow-xs transition-colors hover:border-destructive hover:bg-destructive hover:text-white"
                                                            title="Supprimer le point d’arrêt"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {stops.total > 0 && (
                        <div className="flex flex-col gap-4 px-4 py-3 text-[0.6875rem] text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                            <span>
                                {stops.from ?? 0}–{stops.to ?? 0} sur {stops.total} points d’arrêt
                            </span>
                            <div className="flex items-center gap-[5px]">
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.get(
                                            '/stops',
                                            { ...filters, page: Math.max(1, stops.current_page - 1) },
                                            { preserveScroll: true },
                                        )
                                    }
                                    disabled={stops.current_page === 1}
                                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                >
                                    <ChevronRight className="size-3.5 rotate-180" />
                                </button>
                                <span className="px-2 font-bold text-foreground">
                                    Page {stops.current_page} / {stops.last_page}
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.get(
                                            '/stops',
                                            { ...filters, page: Math.min(stops.last_page, stops.current_page + 1) },
                                            { preserveScroll: true },
                                        )
                                    }
                                    disabled={stops.current_page === stops.last_page}
                                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                >
                                    <ChevronRight className="size-3.5" />
                                </button>
                            </div>
                        </div>
                    )}
                </section>
            </div>

            {/* Modal d'édition / création avec carte Leaflet */}
            <StopEditorModal
                open={editorOpen}
                stop={editingStop}
                cities={cities}
                onClose={() => {
                    setEditorOpen(false);
                    setEditingStop(null);
                }}
                mapSettings={mapSettings}
            />

            {/* Modal de suppression */}
            {stopPendingDeletion && (
                <Dialog
                    open={true}
                    onOpenChange={(v) => {
                        if (!v && !deleteSubmitting) {
                            setStopPendingDeletion(null);
                            setDeletePassword('');
                            setDeleteError('');
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-destructive">
                                <Trash2 className="size-5" />
                                Supprimer le point d’arrêt
                            </DialogTitle>
                            <DialogDescription>
                                Êtes-vous sûr de vouloir supprimer définitivement ce point d’arrêt ? La
                                suppression nécessite la saisie de votre mot de passe pour des raisons de
                                sécurité.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-3 py-2 text-xs">
                            <div className="rounded-xl border border-destructive/20 bg-destructive/10 p-3.5 text-foreground">
                                <div className="font-bold">
                                    {stopPendingDeletion.name} ({stopPendingDeletion.city},{' '}
                                    {stopPendingDeletion.country})
                                </div>
                                <div className="mt-1 font-mono text-[0.6875rem] text-muted-foreground">
                                    Code : {stopPendingDeletion.code}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label
                                    htmlFor="delete-stop-password"
                                    className="flex items-center gap-1.5 font-bold text-foreground"
                                >
                                    <Lock className="size-3.5 text-muted-foreground" />
                                    Confirmez avec votre mot de passe
                                    <span className="text-destructive">*</span>
                                </label>
                                <input
                                    id="delete-stop-password"
                                    type="password"
                                    required
                                    autoComplete="current-password"
                                    autoFocus
                                    disabled={deleteSubmitting}
                                    value={deletePassword}
                                    onChange={(e) => {
                                        setDeletePassword(e.target.value);
                                        setDeleteError('');
                                    }}
                                    placeholder="Votre mot de passe utilisateur…"
                                    className="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground shadow-xs focus:border-gold focus:outline-hidden"
                                />
                            </div>

                            {deleteError && (
                                <div className="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-destructive">
                                    <AlertTriangle className="size-4 shrink-0 mt-0.5" />
                                    <p className="font-semibold leading-relaxed">
                                        {deleteError}
                                    </p>
                                </div>
                            )}
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={deleteSubmitting}
                                onClick={() => {
                                    setStopPendingDeletion(null);
                                    setDeletePassword('');
                                    setDeleteError('');
                                }}
                            >
                                Annuler
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={
                                    deleteSubmitting || !deletePassword.trim()
                                }
                                onClick={confirmDelete}
                                className="cursor-pointer gap-2"
                            >
                                {deleteSubmitting && (
                                    <Loader2 className="size-4 animate-spin" />
                                )}
                                {deleteSubmitting ? 'Suppression en cours…' : 'Confirmer la suppression'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
