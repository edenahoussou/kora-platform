import {
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Eye,
    Package,
    RefreshCw,
    Search,
    SlidersHorizontal,
    TriangleAlert,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { ParcelDetailDialog } from '@/components/parcels/parcel-detail-dialog';
import {
    parcelStatusLabels,
    parcelStatusLabel,
    parcelStatusTone,
} from '@/components/parcels/status';
import type {
    DepositHub,
    ParcelCorridor,
    ParcelListItem,
    ParcelListMeta,
    ParcelListResponse,
} from '@/components/parcels/types';

type Props = {
    hubs: DepositHub[];
    corridors: ParcelCorridor[];
    canIntake: boolean;
    onCreate: () => void;
    refreshKey?: number;
};

const statusOptions = ['', ...Object.keys(parcelStatusLabels)];

function formatGrams(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return `${new Intl.NumberFormat('fr-FR').format(value)} g`;
}

function formatAmount(value: number, currency: string): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} ${currency}`;
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
    }).format(new Date(value));
}

function corridorLabel(
    corridor: ParcelCorridor,
    hubsByUuid: Map<string, DepositHub>,
): string {
    const origin =
        corridor.origin_hub_uuid === null
            ? '—'
            : (hubsByUuid.get(corridor.origin_hub_uuid)?.name ?? '—');
    const destination =
        corridor.destination_hub_uuid === null
            ? '—'
            : (hubsByUuid.get(corridor.destination_hub_uuid)?.name ?? '—');

    return `${origin} → ${destination}`;
}

export function ParcelListPanel({
    hubs,
    corridors,
    canIntake,
    onCreate,
    refreshKey = 0,
}: Props) {
    const [status, setStatus] = useState('');
    const [reference, setReference] = useState('');
    const [corridorUuid, setCorridorUuid] = useState('');
    const [applied, setApplied] = useState({
        status: '',
        reference: '',
        corridor: '',
    });
    const [page, setPage] = useState(1);
    const [parcels, setParcels] = useState<ParcelListItem[]>([]);
    const [meta, setMeta] = useState<ParcelListMeta | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selected, setSelected] = useState<ParcelListItem | null>(null);

    const hubsByUuid = new Map(hubs.map((hub) => [hub.uuid, hub]));

    const load = useCallback(
        async (
            targetPage: number,
            filters: { status: string; reference: string; corridor: string },
        ) => {
            setLoading(true);
            setError(null);

            const params = new URLSearchParams();

            if (filters.status !== '') {
                params.set('status', filters.status);
            }

            if (filters.reference !== '') {
                params.set('reference', filters.reference);
            }

            if (filters.corridor !== '') {
                params.set('corridor_uuid', filters.corridor);
            }

            if (targetPage > 1) {
                params.set('page', String(targetPage));
            }

            const query = params.toString();

            try {
                const response = await fetch(
                    `/parcels/data${query !== '' ? `?${query}` : ''}`,
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    throw new Error(
                        response.status === 403
                            ? 'Accès à la liste des colis refusé.'
                            : `Impossible de charger les colis (${response.status}).`,
                    );
                }

                const payload = (await response.json()) as ParcelListResponse;
                setParcels(payload.data);
                setMeta(payload.meta);
                setPage(targetPage);
            } catch (reason) {
                setParcels([]);
                setMeta(null);
                setError(
                    reason instanceof Error
                        ? reason.message
                        : 'Erreur de chargement de la liste.',
                );
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    useEffect(() => {
        // Chargement externe déclenché par les filtres/pagination.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        void load(page, applied);
    }, [applied, page, refreshKey, load]);

    const applyFilters = () => {
        setPage(1);
        setApplied({
            status,
            reference: reference.trim().toUpperCase(),
            corridor: corridorUuid,
        });
    };

    const resetFilters = () => {
        setStatus('');
        setReference('');
        setCorridorUuid('');
        setPage(1);
        setApplied({ status: '', reference: '', corridor: '' });
    };

    const goToPage = (target: number) => {
        if (meta === null || target < 1 || target > meta.last_page) {
            return;
        }

        void load(target, applied);
    };

    return (
        <>
            <div className="cash-filterbar">
                <div className="relative min-w-[240px] flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        value={reference}
                        onChange={(event) => setReference(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                applyFilters();
                            }
                        }}
                        placeholder="Référence exacte · KC-20261119-95262"
                        aria-label="Rechercher un colis par référence exacte"
                        className="cash-filter-search w-full"
                        style={{ paddingLeft: '2.25rem' }}
                    />
                </div>
                <label className="cash-filter-field relative block">
                    <span className="sr-only">Statut du colis</span>
                    <select
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                        className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                    >
                        {statusOptions.map((value) => (
                            <option key={value || 'all'} value={value}>
                                {value === ''
                                    ? 'Tous les statuts'
                                    : parcelStatusLabel(value)}
                            </option>
                        ))}
                    </select>
                    <ChevronRight
                        className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                        strokeWidth={1.7}
                    />
                </label>
                <label className="cash-filter-field relative block">
                    <span className="sr-only">Corridor</span>
                    <select
                        value={corridorUuid}
                        onChange={(event) =>
                            setCorridorUuid(event.target.value)
                        }
                        className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                    >
                        <option value="">Tous les corridors</option>
                        {corridors.map((corridor) => (
                            <option key={corridor.uuid} value={corridor.uuid}>
                                {corridorLabel(corridor, hubsByUuid)}
                            </option>
                        ))}
                    </select>
                    <ChevronRight
                        className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                        strokeWidth={1.7}
                    />
                </label>
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

            <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-[1.25rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Liste des colis
                    </h2>
                    <p className="mt-0.5 text-[0.75rem] text-muted-foreground">
                        {meta === null
                            ? 'Chargement du périmètre OPS…'
                            : `${meta.total} colis dans le périmètre OPS`}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <ParcelButton
                        variant="ghost"
                        size="sm"
                        onClick={() => void load(page, applied)}
                        disabled={loading}
                    >
                        <RefreshCw
                            className={`size-3.5 ${loading ? 'animate-spin' : ''}`}
                        />
                        Actualiser
                    </ParcelButton>
                    {canIntake && (
                        <ParcelButton
                            variant="primary"
                            size="sm"
                            onClick={onCreate}
                        >
                            <Package className="size-3.5" />
                            Créer un colis
                        </ParcelButton>
                    )}
                </div>
            </div>

            {error && (
                <div className="mb-3 rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                </div>
            )}

            <section className="cash-table-card">
                {loading ? (
                    <div className="grid min-h-[16rem] place-items-center">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <RefreshCw className="size-4 animate-spin" />
                            Chargement des colis…
                        </div>
                    </div>
                ) : parcels.length === 0 ? (
                    <div className="px-6 py-16 text-center">
                        <Package className="mx-auto size-8 text-muted-foreground/50" />
                        <h3 className="mt-3 font-black">
                            Aucun colis dans ce périmètre
                        </h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Ajustez la référence, le statut ou le corridor.
                        </p>
                    </div>
                ) : (
                    <div className="cash-table-scroll">
                        <table className="w-full table-fixed text-left">
                            <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                <tr>
                                    <th className="w-[26%] px-3 py-3">Colis</th>
                                    <th className="w-[26%] px-3 py-3">
                                        Trajet & client
                                    </th>
                                    <th className="w-[16%] px-3 py-3">
                                        Poids & montant
                                    </th>
                                    <th className="w-[13%] px-3 py-3">
                                        Statut
                                    </th>
                                    <th className="w-[12%] px-2 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {parcels.map((parcel) => (
                                    <tr
                                        key={parcel.uuid}
                                        className="border-b border-black/[0.045] transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-3 py-3.5">
                                            <strong className="block font-mono text-[0.6875rem] font-black tracking-[0.02em] whitespace-nowrap text-foreground">
                                                {parcel.reference}
                                            </strong>
                                            <small className="mt-0.5 line-clamp-1 block text-[0.6875rem] text-muted-foreground">
                                                {parcel.contents || '—'}
                                            </small>
                                        </td>
                                        <td className="px-3 py-3.5">
                                            <span className="block text-[0.6875rem] font-bold text-foreground">
                                                {parcel.origin ?? '—'} →{' '}
                                                {parcel.destination ?? '—'}
                                            </span>
                                            <small className="mt-0.5 line-clamp-1 block text-[0.6875rem] text-muted-foreground">
                                                {parcel.sender_name} →{' '}
                                                {parcel.recipient_name}
                                            </small>
                                            <small className="mt-0.5 block text-[0.625rem] text-muted-foreground/80">
                                                {formatDate(parcel.updated_at)}
                                            </small>
                                        </td>
                                        <td className="px-3 py-3.5">
                                            <span className="block text-[0.6875rem] font-bold text-foreground">
                                                {formatGrams(
                                                    parcel.weight_grams,
                                                )}
                                            </span>
                                            <small className="mt-0.5 block text-[0.625rem] text-muted-foreground">
                                                Déclaré{' '}
                                                {formatGrams(
                                                    parcel.declared_grams,
                                                )}
                                            </small>
                                            {parcel.quote !== null ? (
                                                <small className="mt-0.5 block text-[0.625rem] font-bold text-foreground">
                                                    {formatAmount(
                                                        parcel.quote.total,
                                                        parcel.quote.currency,
                                                    )}
                                                </small>
                                            ) : (
                                                <small className="mt-0.5 block text-[0.625rem] text-muted-foreground">
                                                    Montant à la pesée
                                                </small>
                                            )}
                                        </td>
                                        <td className="px-3 py-3.5">
                                            <div className="flex flex-wrap items-center gap-1">
                                                <KoraStatusBadge
                                                    className="whitespace-nowrap"
                                                    tone={parcelStatusTone(
                                                        parcel.status,
                                                    )}
                                                    label={parcelStatusLabel(
                                                        parcel.status,
                                                    )}
                                                />
                                                {parcel.incident_open && (
                                                    <KoraStatusBadge
                                                        className="whitespace-nowrap"
                                                        tone="destructive"
                                                        icon={
                                                            <TriangleAlert className="size-3" />
                                                        }
                                                        label="Incident"
                                                    />
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-2 py-3.5">
                                            <div className="flex flex-col items-end gap-1">
                                                <ParcelButton
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setSelected(parcel)
                                                    }
                                                >
                                                    <Eye className="size-3.5" />
                                                    Détails
                                                </ParcelButton>
                                                {parcel.tracking_url && (
                                                    <a
                                                        href={
                                                            parcel.tracking_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="btn btn-primary btn-sm"
                                                    >
                                                        <ExternalLink className="size-3.5" />
                                                        Suivi
                                                    </a>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {meta !== null && meta.last_page > 1 && (
                    <div className="flex flex-col gap-3 border-t border-black/[0.045] px-4 py-3 text-[0.6875rem] text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            {meta.from ?? 0}–{meta.to ?? 0} sur {meta.total}{' '}
                            colis
                        </span>
                        <div className="flex items-center gap-2">
                            <ParcelButton
                                variant="ghost"
                                size="sm"
                                onClick={() => goToPage(meta.current_page - 1)}
                                disabled={meta.current_page <= 1}
                            >
                                <ChevronLeft className="size-3.5" />
                                Précédent
                            </ParcelButton>
                            <span className="font-bold text-foreground">
                                Page {meta.current_page} / {meta.last_page}
                            </span>
                            <ParcelButton
                                variant="ghost"
                                size="sm"
                                onClick={() => goToPage(meta.current_page + 1)}
                                disabled={meta.current_page >= meta.last_page}
                            >
                                Suivant
                                <ChevronRight className="size-3.5" />
                            </ParcelButton>
                        </div>
                    </div>
                )}
            </section>

            <ParcelDetailDialog
                parcel={selected}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            />
        </>
    );
}
