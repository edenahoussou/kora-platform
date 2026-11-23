import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CreditCard,
    Ellipsis,
    ExternalLink,
    Eye,
    FileText,
    KeyRound,
    Loader2,
    Package,
    PackageCheck,
    PackageOpen,
    ReceiptText,
    RefreshCw,
    Scale,
    Search,
    SlidersHorizontal,
    Tag,
    TriangleAlert,
    Truck,
    Undo2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import { ParcelAssignmentDialog } from '@/components/parcels/parcel-assignment-dialog';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { ParcelDetailDialog } from '@/components/parcels/parcel-detail-dialog';
import { ParcelHandoffDialog } from '@/components/parcels/parcel-handoff-dialog';
import type { ParcelHandoffMode } from '@/components/parcels/parcel-handoff-dialog';
import { ParcelInspectionDialog } from '@/components/parcels/parcel-inspection-dialog';
import type { ParcelInspectionPayload } from '@/components/parcels/parcel-inspection-dialog';
import { ParcelKpi } from '@/components/parcels/parcel-kpi';
import { ParcelPaymentDialog } from '@/components/parcels/parcel-payment-dialog';
import { ParcelReceiptDialog } from '@/components/parcels/parcel-receipt-dialog';
import {
    parcelStatusLabels,
    parcelStatusLabel,
    parcelStatusTone,
} from '@/components/parcels/status';
import type {
    DepositHub,
    LaravelValidationErrors,
    ParcelCorridor,
    ParcelIndicatorsResponse,
    ParcelListItem,
    ParcelListKpis,
    ParcelListMeta,
    ParcelListResponse,
} from '@/components/parcels/types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Props = {
    hubs: DepositHub[];
    corridors: ParcelCorridor[];
    canIntake: boolean;
    onCreate: () => void;
    refreshKey?: number;
};

const statusOptions = ['', ...Object.keys(parcelStatusLabels)];

function xsrfToken(): string {
    const value = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';

    return decodeURIComponent(value);
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

function validationErrors(payload: unknown): LaravelValidationErrors {
    if (
        payload !== null &&
        typeof payload === 'object' &&
        'errors' in payload &&
        payload.errors !== null &&
        typeof payload.errors === 'object'
    ) {
        return payload.errors as LaravelValidationErrors;
    }

    return {};
}

function formatGrams(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return `${new Intl.NumberFormat('fr-FR').format(value)} g`;
}

function formatAmount(value: number, currency: string): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} ${currency}`;
}

function formatNumber(value: number | undefined): string {
    return value === undefined
        ? '—'
        : new Intl.NumberFormat('fr-FR').format(value);
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
    const [kpis, setKpis] = useState<ParcelListKpis | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selected, setSelected] = useState<ParcelListItem | null>(null);
    const [inspecting, setInspecting] = useState<ParcelListItem | null>(null);
    const [paying, setPaying] = useState<ParcelListItem | null>(null);
    const [receipting, setReceipting] = useState<ParcelListItem | null>(null);
    const [assigning, setAssigning] = useState<ParcelListItem | null>(null);
    const [handoff, setHandoff] = useState<{
        parcel: ParcelListItem;
        mode: ParcelHandoffMode;
    } | null>(null);
    const [readyingUuid, setReadyingUuid] = useState<string | null>(null);
    const [returningFeeUuid, setReturningFeeUuid] = useState<string | null>(
        null,
    );

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

    const loadKpis = useCallback(async () => {
        try {
            const response = await fetch('/parcels/data/indicators', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as ParcelIndicatorsResponse;
            setKpis(payload.meta);
        } catch {
            // Le tableau reste utilisable si les indicateurs sont indisponibles.
        }
    }, []);

    useEffect(() => {
        // Chargement externe déclenché par les filtres/pagination.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        void load(page, applied);
        void loadKpis();
    }, [applied, page, refreshKey, load, loadKpis]);

    const refreshAll = () => {
        void load(page, applied);
        void loadKpis();
    };

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

    const markReady = async (parcel: ParcelListItem) => {
        setReadyingUuid(parcel.uuid);

        try {
            const response = await fetch(`/parcels/data/${parcel.uuid}/ready`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });
            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(
                    responseMessage(
                        payload,
                        'Impossible de marquer le colis prêt au départ.',
                    ),
                );
            }

            toast.success(`${parcel.reference} est prêt au départ.`);
            refreshAll();
        } catch (reason) {
            toast.error(
                reason instanceof Error
                    ? reason.message
                    : 'Erreur lors de la préparation du colis.',
            );
        } finally {
            setReadyingUuid(null);
        }
    };

    const prepareReturnFee = async (parcel: ParcelListItem) => {
        setReturningFeeUuid(parcel.uuid);

        try {
            const response = await fetch(
                `/parcels/data/${parcel.uuid}/return-charge`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                },
            );
            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(
                    responseMessage(
                        payload,
                        'Les frais de retour n’ont pas pu être préparés.',
                    ),
                );
            }

            toast.success(
                `Frais de retour préparés pour ${parcel.reference}. Encaissez-les avant remise.`,
            );
            refreshAll();
        } catch (reason) {
            toast.error(
                reason instanceof Error
                    ? reason.message
                    : 'Erreur lors de la préparation des frais de retour.',
            );
        } finally {
            setReturningFeeUuid(null);
        }
    };

    const submitInspection = async (
        payload: ParcelInspectionPayload,
    ): Promise<{
        ok: boolean;
        message?: string;
        errors?: LaravelValidationErrors;
    }> => {
        if (inspecting === null) {
            return { ok: false, message: 'Colis introuvable.' };
        }

        try {
            const response = await fetch(
                `/parcels/data/${inspecting.uuid}/inspect`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify(payload),
                },
            );
            const body = (await response.json().catch(() => null)) as {
                data?: ParcelListItem;
            } | null;

            if (!response.ok || body === null) {
                return {
                    ok: false,
                    message: responseMessage(
                        body,
                        'La pesée n’a pas été enregistrée.',
                    ),
                    errors: validationErrors(body),
                };
            }

            toast.success(
                body.data?.status === 'refused'
                    ? `Colis ${inspecting.reference} refusé et audité.`
                    : `Colis ${inspecting.reference} pesé, devis mis à jour.`,
            );
            setInspecting(null);
            refreshAll();

            return { ok: true };
        } catch {
            return {
                ok: false,
                message:
                    'Le réseau est instable. La pesée n’a pas été enregistrée.',
            };
        }
    };

    const renderPrimaryAction = (parcel: ParcelListItem): ReactNode => {
        if (parcel.can_receive) {
            return (
                <ParcelButton
                    variant="success"
                    size="sm"
                    onClick={() => setHandoff({ parcel, mode: 'receive' })}
                >
                    <PackageCheck className="size-3.5" />
                    {parcel.status === 'return_arrived'
                        ? 'Recevoir retour'
                        : 'Réceptionner'}
                </ParcelButton>
            );
        }

        if (parcel.can_release) {
            return (
                <ParcelButton
                    variant="primary"
                    size="sm"
                    onClick={() => setHandoff({ parcel, mode: 'release' })}
                >
                    <KeyRound className="size-3.5" />
                    {parcel.status === 'returned'
                        ? 'Remettre retour'
                        : 'Remettre'}
                </ParcelButton>
            );
        }

        if (parcel.can_pay_return) {
            return (
                <ParcelButton
                    variant="success"
                    size="sm"
                    onClick={() => setPaying(parcel)}
                >
                    <CreditCard className="size-3.5" />
                    Encaisser retour
                </ParcelButton>
            );
        }

        if (parcel.can_return_charge) {
            return (
                <ParcelButton
                    variant="navy"
                    size="sm"
                    disabled={returningFeeUuid === parcel.uuid}
                    onClick={() => void prepareReturnFee(parcel)}
                >
                    {returningFeeUuid === parcel.uuid ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                        <Undo2 className="size-3.5" />
                    )}
                    Frais retour
                </ParcelButton>
            );
        }

        if (parcel.can_ready) {
            return (
                <ParcelButton
                    variant="success"
                    size="sm"
                    disabled={readyingUuid === parcel.uuid}
                    onClick={() => void markReady(parcel)}
                >
                    {readyingUuid === parcel.uuid ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                        <CheckCircle2 className="size-3.5" />
                    )}
                    Prêt
                </ParcelButton>
            );
        }

        if (parcel.can_assign) {
            return (
                <ParcelButton
                    variant="navy"
                    size="sm"
                    onClick={() => setAssigning(parcel)}
                >
                    <Truck className="size-3.5" />
                    {parcel.status === 'assigned' ? 'Réaffecter' : 'Affecter'}
                </ParcelButton>
            );
        }

        if (canIntake && parcel.can_pay) {
            return (
                <ParcelButton
                    variant="success"
                    size="sm"
                    onClick={() => setPaying(parcel)}
                >
                    <CreditCard className="size-3.5" />
                    Encaisser
                </ParcelButton>
            );
        }

        if (canIntake && ['preregistered', 'weighed'].includes(parcel.status)) {
            return (
                <ParcelButton
                    variant="navy"
                    size="sm"
                    onClick={() => setInspecting(parcel)}
                >
                    <Scale className="size-3.5" />
                    Peser
                </ParcelButton>
            );
        }

        return null;
    };

    const columns: TableColumn<ParcelListItem>[] = [
        {
            key: 'parcel',
            header: 'Colis',
            render: (parcel) => (
                <div>
                    <strong className="block font-mono text-[0.6875rem] font-black tracking-[0.02em] whitespace-nowrap text-foreground">
                        {parcel.reference}
                    </strong>
                    <small className="mt-0.5 line-clamp-1 block text-[0.6875rem] text-muted-foreground">
                        {parcel.contents || '—'}
                    </small>
                </div>
            ),
        },
        {
            key: 'route',
            header: 'Trajet & client',
            render: (parcel) => (
                <div>
                    <span className="block text-[0.6875rem] font-bold text-foreground">
                        {parcel.origin ?? '—'} → {parcel.destination ?? '—'}
                    </span>
                    <small className="mt-0.5 line-clamp-1 block text-[0.6875rem] text-muted-foreground">
                        {parcel.sender_name} → {parcel.recipient_name}
                    </small>
                    <small className="mt-0.5 block text-[0.625rem] text-muted-foreground/80">
                        {formatDate(parcel.updated_at)}
                    </small>
                </div>
            ),
        },
        {
            key: 'weight',
            header: 'Poids & montant',
            render: (parcel) => (
                <div>
                    <span className="block text-[0.6875rem] font-bold text-foreground">
                        {formatGrams(parcel.weight_grams)}
                    </span>
                    <small className="mt-0.5 block text-[0.625rem] text-muted-foreground">
                        Déclaré {formatGrams(parcel.declared_grams)}
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
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (parcel) => (
                <div>
                    <div className="flex flex-wrap items-center gap-1">
                        <KoraStatusBadge
                            className="whitespace-nowrap"
                            tone={parcelStatusTone(parcel.status)}
                            label={parcelStatusLabel(parcel.status)}
                        />
                        {parcel.incident_open && (
                            <KoraStatusBadge
                                className="whitespace-nowrap"
                                tone="destructive"
                                icon={<TriangleAlert className="size-3" />}
                                label="Incident"
                            />
                        )}
                    </div>
                    {parcel.status === 'available' &&
                        parcel.withdraw_by !== null && (
                            <small className="mt-1 block text-[0.625rem] text-muted-foreground">
                                Retrait avant {formatDate(parcel.withdraw_by)}
                            </small>
                        )}
                </div>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (parcel) => {
                const hasMenu =
                    parcel.can_reissue === true ||
                    parcel.can_print_handoff_proof === true ||
                    parcel.can_receipt === true ||
                    parcel.can_label === true ||
                    (parcel.can_manifest === true &&
                        parcel.departure_uuid !== null) ||
                    (parcel.can_assign === true &&
                        parcel.status === 'assigned') ||
                    parcel.tracking_url !== null;

                return (
                    <div className="flex flex-wrap justify-end gap-1.5">
                        {renderPrimaryAction(parcel)}
                        <ParcelButton
                            variant="ghost"
                            size="sm"
                            onClick={() => setSelected(parcel)}
                        >
                            <Eye className="size-3.5" />
                            Détails
                        </ParcelButton>
                        {hasMenu && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <ParcelButton
                                        variant="ghost"
                                        size="sm"
                                        aria-label={`Autres actions pour ${parcel.reference}`}
                                    >
                                        <Ellipsis className="size-3.5" />
                                    </ParcelButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {parcel.can_assign &&
                                        parcel.status === 'assigned' && (
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    setAssigning(parcel)
                                                }
                                            >
                                                <Truck className="size-4" />
                                                Réaffecter
                                            </DropdownMenuItem>
                                        )}
                                    {parcel.can_reissue && (
                                        <DropdownMenuItem
                                            onClick={() =>
                                                setHandoff({
                                                    parcel,
                                                    mode: 'reissue',
                                                })
                                            }
                                        >
                                            <KeyRound className="size-4" />
                                            Renvoyer le code
                                        </DropdownMenuItem>
                                    )}
                                    {parcel.can_manifest &&
                                        parcel.departure_uuid !== null && (
                                            <DropdownMenuItem asChild>
                                                <a
                                                    href={`/parcels/manifest/${parcel.departure_uuid}/print`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <FileText className="size-4" />
                                                    Manifeste
                                                </a>
                                            </DropdownMenuItem>
                                        )}
                                    {parcel.can_label && (
                                        <DropdownMenuItem asChild>
                                            <a
                                                href={`/parcels/${parcel.uuid}/label`}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Tag className="size-4" />
                                                Bordereau d’envoi
                                            </a>
                                        </DropdownMenuItem>
                                    )}
                                    {parcel.can_receipt && (
                                        <DropdownMenuItem
                                            onClick={() =>
                                                setReceipting(parcel)
                                            }
                                        >
                                            <ReceiptText className="size-4" />
                                            Reçu Colis
                                        </DropdownMenuItem>
                                    )}
                                    {parcel.can_print_handoff_proof && (
                                        <DropdownMenuItem asChild>
                                            <a
                                                href={`/parcels/handoff/${parcel.uuid}/print`}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileText className="size-4" />
                                                Preuve de remise
                                            </a>
                                        </DropdownMenuItem>
                                    )}
                                    {parcel.tracking_url && (
                                        <>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem asChild>
                                                <a
                                                    href={parcel.tracking_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <ExternalLink className="size-4" />
                                                    Suivi client
                                                </a>
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                );
            },
        },
    ];

    const paginationFooter =
        meta !== null && meta.last_page > 1 ? (
            <div className="flex flex-col gap-3 border-t border-black/[0.045] px-4 py-3 text-[0.6875rem] text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>
                    {meta.from ?? 0}–{meta.to ?? 0} sur {meta.total} colis
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
        ) : null;

    return (
        <>
            <div className="cash-kpis grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <ParcelKpi
                    icon={Package}
                    tone="navy"
                    label="Colis du périmètre"
                    hint={`${formatNumber(kpis?.withdrawn_today)} remis aujourd’hui`}
                    value={formatNumber(kpis?.total)}
                    tooltip={
                        <span>
                            Tous les colis visibles par votre agence ou par le
                            périmètre réseau, tous statuts confondus.
                        </span>
                    }
                />
                <ParcelKpi
                    icon={PackageCheck}
                    tone="gold"
                    label="À réceptionner"
                    hint="Arrivées et retours"
                    value={formatNumber(kpis?.awaiting_reception)}
                    tooltip={
                        <span>
                            Colis arrivés au lieu de destination ou retours
                            arrivés, dont la réception physique reste à
                            confirmer par un agent autorisé.
                        </span>
                    }
                />
                <ParcelKpi
                    icon={PackageOpen}
                    tone="green"
                    label="Disponibles au retrait"
                    hint="Prêts pour remise"
                    value={formatNumber(kpis?.available)}
                    tooltip={
                        <span>
                            Colis réceptionnés à destination, avant leur date
                            limite de retrait et encore en custody KORA.
                        </span>
                    }
                />
                <ParcelKpi
                    icon={Undo2}
                    tone="blue"
                    label="Retours à traiter"
                    hint="Chargement, réception, remise"
                    value={formatNumber(kpis?.returns_to_handle)}
                    tooltip={
                        <span>
                            Retours à charger, retours en transit, retours
                            arrivés et retours réceptionnés qui n’ont pas encore
                            été remis à l’expéditeur.
                        </span>
                    }
                />
            </div>

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

            {error && (
                <div className="mb-3 rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                </div>
            )}

            {loading ? (
                <section className="cash-table-card">
                    <div className="grid min-h-[16rem] place-items-center">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <RefreshCw className="size-4 animate-spin" />
                            Chargement des colis…
                        </div>
                    </div>
                </section>
            ) : (
                <KoraTable<ParcelListItem>
                    variant="finance"
                    title="Liste des colis"
                    subtitle={
                        meta === null
                            ? 'Périmètre OPS indisponible'
                            : `${meta.total} colis dans le périmètre OPS`
                    }
                    icon={Package}
                    columns={columns}
                    data={parcels}
                    rowKey={(parcel) => parcel.uuid}
                    emptyTitle="Aucun colis dans ce périmètre"
                    emptyMessage="Ajustez la référence, le statut ou le corridor."
                    emptyIcon={Package}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <ParcelButton
                                variant="ghost"
                                size="sm"
                                onClick={refreshAll}
                            >
                                <RefreshCw className="size-3.5" />
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
                    }
                    footer={paginationFooter}
                />
            )}

            <ParcelInspectionDialog
                parcel={inspecting}
                onOpenChange={(open) => {
                    if (!open) {
                        setInspecting(null);
                    }
                }}
                onSubmit={submitInspection}
            />

            <ParcelPaymentDialog
                open={paying !== null}
                parcel={paying}
                onOpenChange={(open) => {
                    if (!open) {
                        setPaying(null);
                    }
                }}
                onPaid={() => {
                    const paid = paying;

                    setPaying(null);
                    refreshAll();

                    if (paid !== null) {
                        setReceipting(paid);
                    }
                }}
            />

            <ParcelReceiptDialog
                open={receipting !== null}
                parcel={receipting}
                onOpenChange={(open) => {
                    if (!open) {
                        setReceipting(null);
                    }
                }}
            />

            <ParcelHandoffDialog
                key={`${handoff?.parcel.uuid ?? 'closed'}-${handoff?.mode ?? ''}`}
                open={handoff !== null}
                parcel={handoff?.parcel ?? null}
                mode={handoff?.mode ?? 'release'}
                onOpenChange={(open) => {
                    if (!open) {
                        setHandoff(null);
                    }
                }}
                onDone={(message) => {
                    const completed = handoff;

                    toast.success(message);
                    setHandoff(null);
                    refreshAll();

                    if (completed?.mode === 'release') {
                        window.open(
                            `/parcels/handoff/${completed.parcel.uuid}/print`,
                            '_blank',
                            'noopener,noreferrer',
                        );
                    }
                }}
                onRefresh={refreshAll}
            />

            <ParcelDetailDialog
                parcel={selected}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            />

            <ParcelAssignmentDialog
                open={assigning !== null}
                parcel={assigning}
                onOpenChange={(open) => {
                    if (!open) {
                        setAssigning(null);
                    }
                }}
                onAssigned={() => {
                    setAssigning(null);
                    refreshAll();
                }}
            />
        </>
    );
}
