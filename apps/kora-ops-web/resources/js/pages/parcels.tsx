import { Head, usePage } from '@inertiajs/react';
import {
    Archive,
    ArrowRight,
    Building2,
    CheckCircle2,
    CircleAlert,
    Coins,
    Gauge,
    Loader2,
    MapPin,
    Package,
    PackagePlus,
    Plus,
    RefreshCw,
    Route,
    Settings2,
    Store,
    Truck,
    Wallet,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import { CapacityDialog } from '@/components/parcels/capacity-dialog';
import { CorridorDialog } from '@/components/parcels/corridor-dialog';
import { DepositHubDialog } from '@/components/parcels/deposit-hub-dialog';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { ParcelFinancePanel } from '@/components/parcels/parcel-finance-panel';
import { ParcelIntakeDialog } from '@/components/parcels/parcel-intake-dialog';
import { ParcelKpi } from '@/components/parcels/parcel-kpi';
import { ParcelListPanel } from '@/components/parcels/parcel-list-panel';
import { ParcelSupervisionPanel } from '@/components/parcels/parcel-supervision-panel';
import { TariffDialog } from '@/components/parcels/tariff-dialog';
import type {
    CapacityConfigurationResponse,
    CapacityDeparture,
    CorridorPayload,
    DepositHub,
    DepositHubPayload,
    LaravelValidationErrors,
    ParcelConfiguration,
    ParcelConfigurationResponse,
    ParcelCorridor,
    ParcelIntakePayload,
    ParcelIntakeResponse,
    ParcelIntakeResult,
    ParcelQuote,
    TariffPayload,
} from '@/components/parcels/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { configuration as configurationRoute } from '@/routes/parcels/data';
import parcelHubs from '@/routes/parcels/data/hubs';
import type { BreadcrumbItem } from '@/types';

type TabKey =
    'overview' | 'parcels' | 'hubs' | 'corridors' | 'capacity' | 'finance';

const TABS: Array<{
    key: TabKey;
    label: string;
    description: string;
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    requires?: 'corridors' | 'configure';
}> = [
    {
        key: 'overview',
        label: 'Vue d’ensemble',
        description: 'Supervision et alertes',
        icon: Gauge,
    },
    {
        key: 'parcels',
        label: 'Colis',
        description: 'Liste, suivi et dossiers',
        icon: Package,
    },
    {
        key: 'hubs',
        label: 'Lieux de dépôt',
        description: 'Agences et points partenaires',
        icon: MapPin,
    },
    {
        key: 'corridors',
        label: 'Corridors & tarifs',
        description: 'Desserte et grilles',
        icon: Route,
        requires: 'corridors',
    },
    {
        key: 'capacity',
        label: 'Capacité des départs',
        description: 'Soute disponible',
        icon: Truck,
        requires: 'configure',
    },
    {
        key: 'finance',
        label: 'Files financières',
        description: 'Encaissements, remboursements, factures',
        icon: Coins,
    },
];

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

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatGrams(value: number | null): string {
    if (value === null) {
        return 'Non définie';
    }

    return `${new Intl.NumberFormat('fr-FR').format(value)} g`;
}

function formatAmount(value: number, currency: string): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} ${currency}`;
}

const DEPARTURE_STATUS_LABELS: Record<string, string> = {
    scheduled: 'Programmé',
    boarding: 'Embarquement',
    departed: 'Parti',
    arrived: 'Arrivé',
    cancelled: 'Annulé',
};

function departureStatusLabel(status: string): string {
    return DEPARTURE_STATUS_LABELS[status] ?? status;
}

export default function ParcelsPage() {
    const { url } = usePage();
    const [activeTab, setActiveTab] = useState<TabKey>('overview');
    const [configuration, setConfiguration] =
        useState<ParcelConfiguration | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [forbidden, setForbidden] = useState(false);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const [formErrors, setFormErrors] = useState<LaravelValidationErrors>({});

    const [archiveTarget, setArchiveTarget] = useState<DepositHub | null>(null);
    const [archiving, setArchiving] = useState(false);
    const [archiveError, setArchiveError] = useState<string | null>(null);

    const [corridorOpen, setCorridorOpen] = useState(false);
    const [corridorTarget, setCorridorTarget] = useState<ParcelCorridor | null>(
        null,
    );
    const [savingCorridor, setSavingCorridor] = useState(false);
    const [corridorError, setCorridorError] = useState<string | null>(null);
    const [corridorErrors, setCorridorErrors] =
        useState<LaravelValidationErrors>({});

    const [tariffOpen, setTariffOpen] = useState(false);
    const [tariffTarget, setTariffTarget] = useState<ParcelCorridor | null>(
        null,
    );
    const [publishingTariff, setPublishingTariff] = useState(false);
    const [tariffError, setTariffError] = useState<string | null>(null);
    const [tariffErrors, setTariffErrors] = useState<LaravelValidationErrors>(
        {},
    );

    const [capacityRows, setCapacityRows] = useState<CapacityDeparture[]>([]);
    const [capacityHorizon, setCapacityHorizon] = useState(30);
    const [capacityLoading, setCapacityLoading] = useState(false);
    const [capacityError, setCapacityError] = useState<string | null>(null);
    const [capacityTarget, setCapacityTarget] =
        useState<CapacityDeparture | null>(null);
    const [savingCapacity, setSavingCapacity] = useState(false);
    const [capacityFormError, setCapacityFormError] = useState<string | null>(
        null,
    );
    const [capacityFormErrors, setCapacityFormErrors] =
        useState<LaravelValidationErrors>({});

    const [intakeOpen, setIntakeOpen] = useState(false);
    const [creatingParcel, setCreatingParcel] = useState(false);
    const [intakeError, setIntakeError] = useState<string | null>(null);
    const [intakeErrors, setIntakeErrors] = useState<LaravelValidationErrors>(
        {},
    );
    const [parcelListRefresh, setParcelListRefresh] = useState(0);

    const loadConfiguration = async () => {
        setLoading(true);
        setLoadError(null);
        setForbidden(false);

        try {
            const response = await fetch(configurationRoute.url(), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const payload = (await response
                .json()
                .catch(() => null)) as ParcelConfigurationResponse | null;

            if (!response.ok || payload === null) {
                setForbidden(response.status === 403);
                setLoadError(
                    responseMessage(
                        payload,
                        'Impossible de charger la configuration Colis.',
                    ),
                );

                return;
            }

            setConfiguration(payload.data);
        } catch {
            setLoadError(
                'Le réseau est instable. La configuration Colis n’a pas pu être chargée.',
            );
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        // Initial server-state hydration; failures are stored for the UI.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        void loadConfiguration();
    }, []);

    const loadCapacity = useCallback(async () => {
        setCapacityLoading(true);
        setCapacityError(null);

        try {
            const response = await fetch('/parcels/data/capacity', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const payload = (await response
                .json()
                .catch(() => null)) as CapacityConfigurationResponse | null;

            if (!response.ok || payload === null) {
                setCapacityError(
                    responseMessage(
                        payload,
                        'Impossible de charger la capacité des départs.',
                    ),
                );

                return;
            }

            setCapacityRows(payload.data.departures);
            setCapacityHorizon(payload.data.horizon_days);
        } catch {
            setCapacityError(
                'Le réseau est instable. La capacité des départs n’a pas pu être chargée.',
            );
        } finally {
            setCapacityLoading(false);
        }
    }, []);

    useEffect(() => {
        if (activeTab === 'capacity' && configuration !== null) {
            // Hydration of a server-side projection when the tab opens.
            // eslint-disable-next-line react-hooks/set-state-in-effect
            void loadCapacity();
        }
    }, [activeTab, configuration, loadCapacity]);

    const urlParams = useMemo(() => {
        const query = url.includes('?') ? url.slice(url.indexOf('?') + 1) : '';

        return new URLSearchParams(query);
    }, [url]);

    const search = (urlParams.get('q') ?? '').trim().toLowerCase();
    const typeFilter = urlParams.get('type') ?? '';
    const stateFilter = urlParams.get('state') ?? '';

    const hubs = useMemo(() => configuration?.hubs ?? [], [configuration]);
    const stops = useMemo(
        () => configuration?.options.stops ?? [],
        [configuration],
    );

    const stopsByUuid = useMemo(() => {
        return new Map(stops.map((stop) => [stop.uuid, stop]));
    }, [stops]);

    const partnerPointsByUuid = useMemo(() => {
        return new Map(
            (configuration?.options.partner_points ?? []).map((point) => [
                point.uuid,
                point,
            ]),
        );
    }, [configuration?.options.partner_points]);

    const internalAgenciesByUuid = useMemo(() => {
        return new Map(
            (configuration?.options.internal_agencies ?? []).map((agency) => [
                agency.uuid,
                agency,
            ]),
        );
    }, [configuration?.options.internal_agencies]);

    const hubsByUuid = useMemo(
        () => new Map(hubs.map((hub) => [hub.uuid, hub])),
        [hubs],
    );

    const linkedAgencyUuids = useMemo(
        () =>
            hubs
                .map((hub) => hub.agency_uuid)
                .filter((uuid): uuid is string => uuid !== null),
        [hubs],
    );

    const linkedPartnerPointUuids = useMemo(
        () =>
            hubs
                .map((hub) => hub.kora_point_uuid)
                .filter((uuid): uuid is string => uuid !== null),
        [hubs],
    );

    const filteredHubs = useMemo(() => {
        return hubs.filter((hub) => {
            if (search !== '') {
                const stop = hub.stop_uuid
                    ? stopsByUuid.get(hub.stop_uuid)
                    : undefined;
                const partnerPoint = hub.kora_point_uuid
                    ? partnerPointsByUuid.get(hub.kora_point_uuid)
                    : undefined;
                const haystack = [
                    hub.name,
                    hub.city,
                    stop?.name,
                    stop?.city,
                    stop?.line_code,
                    partnerPoint?.agency_name,
                    partnerPoint?.address,
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                if (!haystack.includes(search)) {
                    return false;
                }
            }

            if (typeFilter !== '' && hub.kind !== typeFilter) {
                return false;
            }

            if (stateFilter === 'active' && (!hub.enabled || hub.archived_at)) {
                return false;
            }

            if (
                stateFilter === 'inactive' &&
                (hub.enabled || hub.archived_at)
            ) {
                return false;
            }

            if (stateFilter === 'archived' && !hub.archived_at) {
                return false;
            }

            return true;
        });
    }, [
        hubs,
        partnerPointsByUuid,
        search,
        stateFilter,
        stopsByUuid,
        typeFilter,
    ]);

    const activeHubs = hubs.filter(
        (hub) => hub.enabled && hub.archived_at === null,
    ).length;
    const partnerHubs = hubs.filter(
        (hub) => hub.kind === 'partner_point' && hub.archived_at === null,
    ).length;
    const archivedHubs = hubs.filter((hub) => hub.archived_at !== null).length;
    const canConfigure = configuration?.permissions.canConfigure === true;
    const canPublishTariffs =
        configuration?.permissions.canPublishTariffs === true;
    const canIntake = configuration?.permissions.canIntake === true;
    const canManageCorridors = canConfigure || canPublishTariffs;
    const visibleTabs = TABS.filter((tab) => {
        if (tab.requires === 'configure') {
            return canConfigure;
        }

        if (tab.requires === 'corridors') {
            return canManageCorridors;
        }

        return true;
    });

    const corridors = configuration?.corridors ?? [];
    const activeCorridors = corridors.filter((corridor) => corridor.enabled);
    const corridorsWithoutTariff = activeCorridors.filter(
        (corridor) => corridor.tariffs.length === 0,
    );

    const corridorLabel = (hubUuid: string | null): string => {
        if (hubUuid === null) {
            return '—';
        }

        const hub = hubsByUuid.get(hubUuid);

        return hub?.name ?? hub?.city ?? 'Lieu inconnu';
    };

    const corridorLine = (corridor: ParcelCorridor): string => {
        const origin = corridor.origin_hub_uuid
            ? hubsByUuid.get(corridor.origin_hub_uuid)
            : undefined;
        const stop = origin?.stop_uuid
            ? stopsByUuid.get(origin.stop_uuid)
            : undefined;

        return stop?.line_code ?? 'Ligne non renseignée';
    };

    const filterConfigs: FilterConfig[] = [
        {
            name: 'type',
            label: 'Type de lieu',
            placeholder: 'Tous les lieux',
            options: [
                { value: 'agency', label: 'Agences internes' },
                { value: 'partner_point', label: 'Points partenaires' },
            ],
        },
        {
            name: 'state',
            label: 'Statut',
            placeholder: 'Tous les statuts',
            options: [
                { value: 'active', label: 'Actifs' },
                { value: 'inactive', label: 'Inactifs' },
                { value: 'archived', label: 'Archivés' },
            ],
        },
    ];

    const saveHub = async (payload: DepositHubPayload) => {
        setSaving(true);
        setFormError(null);
        setFormErrors({});

        try {
            const response = await fetch(parcelHubs.update.url(), {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(payload),
            });
            const body = await response.json().catch(() => null);

            if (!response.ok) {
                setFormErrors(validationErrors(body));
                setFormError(
                    responseMessage(
                        body,
                        'Le lieu de dépôt n’a pas été enregistré.',
                    ),
                );

                return;
            }

            setDialogOpen(false);
            toast.success('Lieu de dépôt enregistré.');
            await loadConfiguration();
        } catch {
            setFormError(
                'Le réseau est instable. Le lieu de dépôt n’a pas été enregistré.',
            );
        } finally {
            setSaving(false);
        }
    };

    const archiveHub = async () => {
        if (archiveTarget === null) {
            return;
        }

        setArchiving(true);
        setArchiveError(null);

        try {
            const response = await fetch(
                parcelHubs.archive.url({ hub: archiveTarget.uuid }),
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                },
            );
            const body = await response.json().catch(() => null);

            if (!response.ok) {
                setArchiveError(
                    responseMessage(
                        body,
                        'Le lieu de dépôt n’a pas été archivé.',
                    ),
                );

                return;
            }

            setArchiveTarget(null);
            toast.success('Lieu de dépôt archivé. L’historique est conservé.');
            await loadConfiguration();
        } catch {
            setArchiveError(
                'Le réseau est instable. Le lieu de dépôt n’a pas été archivé.',
            );
        } finally {
            setArchiving(false);
        }
    };

    const saveCorridor = async (payload: CorridorPayload) => {
        setSavingCorridor(true);
        setCorridorError(null);
        setCorridorErrors({});

        try {
            const response = await fetch('/parcels/data/corridors', {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(payload),
            });
            const body = await response.json().catch(() => null);

            if (!response.ok) {
                setCorridorErrors(validationErrors(body));
                setCorridorError(
                    responseMessage(
                        body,
                        'Le corridor n’a pas été enregistré.',
                    ),
                );

                return;
            }

            setCorridorOpen(false);
            setCorridorTarget(null);
            toast.success('Corridor enregistré.');
            await loadConfiguration();
            await loadCapacity();
        } catch {
            setCorridorError(
                'Le réseau est instable. Le corridor n’a pas été enregistré.',
            );
        } finally {
            setSavingCorridor(false);
        }
    };

    const publishTariff = async (payload: TariffPayload) => {
        if (tariffTarget === null) {
            return;
        }

        setPublishingTariff(true);
        setTariffError(null);
        setTariffErrors({});

        try {
            const response = await fetch(
                `/parcels/data/corridors/${tariffTarget.uuid}/tariffs`,
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
            const body = await response.json().catch(() => null);

            if (!response.ok) {
                setTariffErrors(validationErrors(body));
                setTariffError(
                    responseMessage(body, 'Le tarif n’a pas été publié.'),
                );

                return;
            }

            setTariffOpen(false);
            setTariffTarget(null);
            toast.success('Nouveau tarif publié.');
            await loadConfiguration();
        } catch {
            setTariffError(
                'Le réseau est instable. Le tarif n’a pas été publié.',
            );
        } finally {
            setPublishingTariff(false);
        }
    };

    const saveCapacity = async (capacityGrams: number) => {
        if (capacityTarget === null) {
            return;
        }

        setSavingCapacity(true);
        setCapacityFormError(null);
        setCapacityFormErrors({});

        try {
            const response = await fetch(
                `/parcels/data/departures/${capacityTarget.uuid}/capacity`,
                {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({ capacity_grams: capacityGrams }),
                },
            );
            const body = await response.json().catch(() => null);

            if (!response.ok) {
                setCapacityFormErrors(validationErrors(body));
                setCapacityFormError(
                    responseMessage(
                        body,
                        'La capacité du départ n’a pas été enregistrée.',
                    ),
                );

                return;
            }

            setCapacityTarget(null);
            toast.success('Capacité de soute enregistrée.');
            await loadCapacity();
        } catch {
            setCapacityFormError(
                'Le réseau est instable. La capacité n’a pas été enregistrée.',
            );
        } finally {
            setSavingCapacity(false);
        }
    };

    const createParcel = async (
        payload: ParcelIntakePayload,
    ): Promise<ParcelIntakeResult | null> => {
        setCreatingParcel(true);
        setIntakeError(null);
        setIntakeErrors({});

        try {
            const response = await fetch('/parcels/data/intake', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(payload),
            });
            const body = (await response
                .json()
                .catch(() => null)) as ParcelIntakeResponse | null;

            if (!response.ok || body === null) {
                setIntakeErrors(validationErrors(body));
                setIntakeError(
                    responseMessage(
                        body,
                        'Le colis n’a pas été créé. Vérifiez les données et la capacité du départ.',
                    ),
                );

                return null;
            }

            toast.success(`Colis ${body.data.reference} créé.`);
            setParcelListRefresh((current) => current + 1);
            await loadCapacity();

            return body.data;
        } catch {
            setIntakeError(
                'Le réseau est instable. Le colis n’a pas été créé.',
            );

            return null;
        } finally {
            setCreatingParcel(false);
        }
    };

    const quoteParcel = useCallback(
        async (payload: {
            deposit_hub_uuid: string;
            destination_hub_uuid: string;
            weight_grams: number;
        }): Promise<ParcelQuote | null> => {
            try {
                const response = await fetch('/parcels/data/quote', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify(payload),
                });
                const body = (await response.json().catch(() => null)) as {
                    data?: ParcelQuote;
                } | null;

                if (!response.ok || body?.data === undefined) {
                    return null;
                }

                return body.data;
            } catch {
                return null;
            }
        },
        [],
    );

    const hubColumns: TableColumn<DepositHub>[] = [
        {
            key: 'hub',
            header: 'Lieu',
            render: (hub) => {
                const stop = hub.stop_uuid
                    ? stopsByUuid.get(hub.stop_uuid)
                    : undefined;
                const partnerPoint = hub.kora_point_uuid
                    ? partnerPointsByUuid.get(hub.kora_point_uuid)
                    : undefined;

                return (
                    <div className="flex items-start gap-3">
                        <span
                            className={
                                hub.kind === 'partner_point'
                                    ? 'grid size-9 shrink-0 place-items-center rounded-xl bg-gold/15 text-gold'
                                    : 'grid size-9 shrink-0 place-items-center rounded-xl bg-kora/10 text-kora'
                            }
                        >
                            {hub.kind === 'partner_point' ? (
                                <Store className="size-4" />
                            ) : (
                                <Building2 className="size-4" />
                            )}
                        </span>
                        <span className="min-w-0">
                            <strong className="block truncate text-sm text-foreground">
                                {hub.name ?? 'Lieu sans nom'}
                            </strong>
                            <small className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                                {hub.city ?? '—'}
                                {partnerPoint?.address
                                    ? ` · ${partnerPoint.address}`
                                    : ''}
                                {stop?.line_code ? ` · ${stop.line_code}` : ''}
                            </small>
                        </span>
                    </div>
                );
            },
        },
        {
            key: 'kind',
            header: 'Type',
            render: (hub) => (
                <KoraStatusBadge
                    tone={hub.kind === 'partner_point' ? 'gold' : 'navy'}
                    label={
                        hub.kind === 'partner_point'
                            ? 'Point partenaire'
                            : 'Agence interne'
                    }
                />
            ),
        },
        {
            key: 'entity',
            header: 'Entité commerciale',
            render: (hub) => {
                const partnerPoint = hub.kora_point_uuid
                    ? partnerPointsByUuid.get(hub.kora_point_uuid)
                    : undefined;
                const agency = hub.agency_uuid
                    ? internalAgenciesByUuid.get(hub.agency_uuid)
                    : undefined;

                return (
                    <span className="block">
                        <strong className="block text-xs font-semibold text-foreground">
                            {partnerPoint?.agency_name ?? agency?.name ?? '—'}
                        </strong>
                        <small className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                            {partnerPoint
                                ? (partnerPoint.responsible_name ??
                                  'Responsable non renseigné')
                                : (agency?.country ?? '—')}
                        </small>
                    </span>
                );
            },
        },
        {
            key: 'stop',
            header: 'Arrêt de chargement',
            render: (hub) => {
                const stop = hub.stop_uuid
                    ? stopsByUuid.get(hub.stop_uuid)
                    : undefined;

                if (!stop) {
                    return <span className="text-muted-foreground">—</span>;
                }

                return (
                    <span className="block">
                        <strong className="block text-xs font-semibold text-foreground">
                            {stop.name}
                        </strong>
                        <small className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                            {stop.line_code ?? '—'} · {stop.city ?? '—'}
                        </small>
                    </span>
                );
            },
        },
        {
            key: 'corridors',
            header: 'Corridors',
            align: 'center',
            render: (hub) => {
                const count = (configuration?.corridors ?? []).filter(
                    (corridor) =>
                        corridor.origin_hub_uuid === hub.uuid ||
                        corridor.destination_hub_uuid === hub.uuid,
                ).length;

                return (
                    <span className="inline-flex min-w-8 items-center justify-center rounded-lg bg-muted px-2 py-1 text-xs font-bold text-foreground">
                        {count}
                    </span>
                );
            },
        },
        {
            key: 'status',
            header: 'Statut',
            render: (hub) => {
                if (hub.archived_at) {
                    return (
                        <KoraStatusBadge
                            tone="muted"
                            label={`Archivé · ${formatDate(hub.archived_at)}`}
                        />
                    );
                }

                return hub.enabled ? (
                    <KoraStatusBadge tone="emerald" label="Actif" />
                ) : (
                    <KoraStatusBadge tone="muted" label="Inactif" />
                );
            },
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (hub) => {
                if (hub.archived_at) {
                    return (
                        <span className="text-[0.6875rem] text-muted-foreground">
                            Historique conservé
                        </span>
                    );
                }

                if (!canConfigure) {
                    return null;
                }

                return (
                    <ParcelButton
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setArchiveError(null);
                            setArchiveTarget(hub);
                        }}
                        className="text-muted-foreground hover:text-destructive"
                    >
                        <Archive className="size-3.5" />
                        Archiver
                    </ParcelButton>
                );
            },
        },
    ];

    const corridorColumns: TableColumn<ParcelCorridor>[] = [
        {
            key: 'route',
            header: 'Trajet',
            render: (corridor) => (
                <div className="flex items-start gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-kora/10 text-kora">
                        <Route className="size-4" />
                    </span>
                    <span className="min-w-0">
                        <strong className="flex flex-wrap items-center gap-1.5 text-sm text-foreground">
                            {corridorLabel(corridor.origin_hub_uuid)}
                            <ArrowRight className="size-3.5 text-gold" />
                            {corridorLabel(corridor.destination_hub_uuid)}
                        </strong>
                        <small className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                            {corridorLine(corridor)} ·{' '}
                            {formatDate(
                                corridor.tariffs[0]?.created_at ?? null,
                            )}
                        </small>
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (corridor) =>
                corridor.enabled ? (
                    <KoraStatusBadge tone="emerald" label="Actif" />
                ) : (
                    <KoraStatusBadge tone="muted" label="Inactif" />
                ),
        },
        {
            key: 'wallet',
            header: 'Paiement guichet',
            render: (corridor) =>
                corridor.wallet_cash_enabled ? (
                    <KoraStatusBadge tone="gold" label="Wallet + espèces" />
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Non autorisé
                    </span>
                ),
        },
        {
            key: 'tariff',
            header: 'Tarif courant',
            render: (corridor) => {
                const latest = corridor.tariffs[0];

                if (latest === undefined) {
                    return <KoraStatusBadge tone="amber" label="Aucun tarif" />;
                }

                return (
                    <span className="block">
                        <strong className="block text-xs font-bold text-foreground">
                            {formatAmount(
                                latest.base_amount,
                                configuration?.currency ?? '',
                            )}{' '}
                            +{' '}
                            {formatAmount(
                                latest.step_amount,
                                configuration?.currency ?? '',
                            )}{' '}
                            / {formatGrams(latest.step_grams)}
                        </strong>
                        <small className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                            V{latest.version} ·{' '}
                            {latest.deposit_kind === 'partner_point'
                                ? 'point partenaire'
                                : 'agence'}{' '}
                            · Max {formatGrams(latest.maximum_grams)} · retour{' '}
                            {formatAmount(
                                latest.return_amount,
                                configuration?.currency ?? '',
                            )}
                        </small>
                    </span>
                );
            },
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (corridor) => (
                <div className="flex justify-end gap-2">
                    {canConfigure && (
                        <ParcelButton
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                setCorridorError(null);
                                setCorridorErrors({});
                                setCorridorTarget(corridor);
                                setCorridorOpen(true);
                            }}
                        >
                            <Settings2 className="size-3.5" />
                            Configurer
                        </ParcelButton>
                    )}
                    {canPublishTariffs && (
                        <ParcelButton
                            type="button"
                            variant="primary"
                            onClick={() => {
                                setTariffError(null);
                                setTariffErrors({});
                                setTariffTarget(corridor);
                                setTariffOpen(true);
                            }}
                        >
                            <Coins className="size-3.5" />
                            Tarif
                        </ParcelButton>
                    )}
                </div>
            ),
        },
    ];

    const capacityColumns: TableColumn<CapacityDeparture>[] = [
        {
            key: 'departure',
            header: 'Départ',
            render: (departure) => (
                <div className="flex items-start gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/15 text-gold">
                        <Truck className="size-4" />
                    </span>
                    <span className="min-w-0">
                        <strong className="block text-sm text-foreground">
                            {departure.line_code ?? 'Ligne'} ·{' '}
                            {departure.line_name ?? 'Sans nom'}
                        </strong>
                        <small className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                            {formatDateTime(departure.departure_at)}
                        </small>
                    </span>
                </div>
            ),
        },
        {
            key: 'corridors',
            header: 'Corridors desservis',
            render: (departure) => (
                <span className="block text-xs text-muted-foreground">
                    {departure.corridor_uuids.length} corridor
                    {departure.corridor_uuids.length > 1 ? 's' : ''}
                </span>
            ),
        },
        {
            key: 'capacity',
            header: 'Capacité soute',
            render: (departure) =>
                departure.capacity_grams === null ? (
                    <KoraStatusBadge tone="amber" label="Non définie" />
                ) : (
                    <span className="block">
                        <strong className="block text-xs font-bold text-foreground">
                            {formatGrams(departure.capacity_grams)}
                        </strong>
                        <small className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                            Réservé {formatGrams(departure.reserved_grams)}
                        </small>
                    </span>
                ),
        },
        {
            key: 'available',
            header: 'Disponible',
            render: (departure) =>
                departure.available_grams === null ? (
                    <span className="text-xs text-muted-foreground">—</span>
                ) : (
                    <KoraStatusBadge
                        tone={
                            departure.available_grams === 0
                                ? 'destructive'
                                : 'emerald'
                        }
                        label={formatGrams(departure.available_grams)}
                    />
                ),
        },
        {
            key: 'status',
            header: 'Statut départ',
            render: (departure) => (
                <KoraStatusBadge
                    tone="blue"
                    label={departureStatusLabel(departure.status)}
                />
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (departure) => (
                <ParcelButton
                    type="button"
                    variant="primary"
                    onClick={() => {
                        setCapacityFormError(null);
                        setCapacityFormErrors({});
                        setCapacityTarget(departure);
                    }}
                >
                    <Gauge className="size-3.5" />
                    Définir
                </ParcelButton>
            ),
        },
    ];

    return (
        <>
            <Head title="Gestion des colis" />

            <div className="cash-page">
                <div className="kora-page-head mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 flex items-center gap-1.5 text-[0.6875rem] text-muted-foreground">
                            <span>KORA OPS</span>
                            <span>/</span>
                            <span>Colis</span>
                            <span>/</span>
                            <span className="font-bold text-foreground">
                                Liste & configuration
                            </span>
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Gestion des colis
                        </h1>
                        <p className="mt-1.5 max-w-2xl text-[0.8125rem] leading-6 text-muted-foreground">
                            Suivez les colis du périmètre OPS, puis configurez
                            les lieux de dépôt, les corridors et la capacité de
                            soute. Chaque colis reste rattaché à un trajet réel.
                        </p>
                    </div>

                    {activeTab !== 'parcels' && (canIntake || canConfigure) && (
                        <div className="flex flex-wrap items-center gap-2">
                            {canIntake && (
                                <ParcelButton
                                    type="button"
                                    variant="primary"
                                    onClick={() => {
                                        setIntakeError(null);
                                        setIntakeErrors({});
                                        setIntakeOpen(true);
                                    }}
                                >
                                    <PackagePlus className="size-4" />
                                    Créer un colis
                                </ParcelButton>
                            )}
                            {canConfigure && (
                                <ParcelButton
                                    type="button"
                                    variant={canIntake ? 'ghost' : 'primary'}
                                    onClick={() => {
                                        setFormError(null);
                                        setFormErrors({});
                                        setDialogOpen(true);
                                    }}
                                >
                                    <Plus className="size-4" />
                                    Ajouter un lieu de dépôt
                                </ParcelButton>
                            )}
                        </div>
                    )}
                </div>

                {loading ? (
                    <div className="grid min-h-[16rem] place-items-center rounded-[18px] border border-border bg-card">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            Chargement de la configuration Colis…
                        </div>
                    </div>
                ) : forbidden ? (
                    <div className="rounded-[18px] border border-amber-300/70 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                        <div className="flex items-center gap-2 font-bold">
                            <CircleAlert className="size-4" />
                            Périmètre Colis non autorisé
                        </div>
                        <p className="mt-1.5">
                            Votre compte ne dispose pas de `parcels.network`.
                            Contactez l’administration pour obtenir l’accès à la
                            configuration Colis.
                        </p>
                    </div>
                ) : loadError ? (
                    <div className="rounded-[18px] border border-destructive/30 bg-destructive/10 px-5 py-4 text-sm text-destructive">
                        {loadError}
                    </div>
                ) : (
                    <>
                        {activeTab !== 'parcels' && (
                            <section className="relative mb-5 overflow-hidden rounded-[26px] bg-kora px-6 py-6 text-white shadow-[0_24px_55px_rgba(0,29,78,0.18)]">
                                <div className="absolute -top-24 -right-20 size-72 rounded-full border border-white/10" />
                                <div className="absolute right-12 -bottom-28 size-80 rounded-full border border-white/10" />
                                <div className="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                                    <div className="max-w-2xl">
                                        <span className="text-[0.6875rem] font-black tracking-[0.18em] text-gold uppercase">
                                            Réseau de dépôt Colis
                                        </span>
                                        <h2 className="mt-3 text-[clamp(1.7rem,3vw,2.6rem)] leading-[1.02] font-extrabold tracking-[-0.055em]">
                                            Chaque colis part
                                            <br />
                                            <em className="text-gold not-italic">
                                                d’un lieu réel.
                                            </em>
                                        </h2>
                                        <p className="mt-3 max-w-xl text-sm leading-6 text-white/65">
                                            Le lieu de dépôt porte l’attribution
                                            commerciale. L’arrêt porte la
                                            desserte logistique. Les deux ne se
                                            confondent jamais.
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-3 gap-2 sm:min-w-[26rem]">
                                        {[
                                            ['Actifs', activeHubs],
                                            ['Partenaires', partnerHubs],
                                            ['Archivés', archivedHubs],
                                        ].map(([label, value]) => (
                                            <div
                                                key={label}
                                                className="rounded-2xl border border-white/10 bg-white/10 px-4 py-3"
                                            >
                                                <strong className="block text-2xl font-black tracking-[-0.04em] text-white">
                                                    {value}
                                                </strong>
                                                <span className="mt-1 block text-[0.625rem] font-bold tracking-[0.12em] text-white/55 uppercase">
                                                    {label}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </section>
                        )}

                        <nav
                            className={`mb-5 grid gap-2 rounded-[18px] border border-border bg-card p-2 sm:grid-cols-2 ${
                                visibleTabs.length >= 6
                                    ? 'lg:grid-cols-6'
                                    : visibleTabs.length === 5
                                      ? 'lg:grid-cols-5'
                                      : visibleTabs.length === 4
                                        ? 'lg:grid-cols-4'
                                        : visibleTabs.length === 3
                                          ? 'lg:grid-cols-3'
                                          : 'lg:grid-cols-2'
                            }`}
                            aria-label="Sections Colis"
                        >
                            {visibleTabs.map((tab) => {
                                const Icon = tab.icon;
                                const active = activeTab === tab.key;

                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => setActiveTab(tab.key)}
                                        className={
                                            active
                                                ? 'flex items-center gap-3 rounded-[13px] bg-kora px-4 py-3 text-left text-white shadow-sm'
                                                : 'flex items-center gap-3 rounded-[13px] px-4 py-3 text-left text-foreground transition hover:bg-muted'
                                        }
                                    >
                                        <span
                                            className={
                                                active
                                                    ? 'grid size-8 shrink-0 place-items-center rounded-lg bg-white/10 text-gold'
                                                    : 'grid size-8 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground'
                                            }
                                        >
                                            <Icon className="size-4" />
                                        </span>
                                        <span className="min-w-0">
                                            <strong className="block text-xs font-bold">
                                                {tab.label}
                                            </strong>
                                            <small
                                                className={
                                                    active
                                                        ? 'mt-0.5 block text-[0.625rem] text-white/55'
                                                        : 'mt-0.5 block text-[0.625rem] text-muted-foreground'
                                                }
                                            >
                                                {tab.description}
                                            </small>
                                        </span>
                                    </button>
                                );
                            })}
                        </nav>

                        {activeTab === 'parcels' ? (
                            <ParcelListPanel
                                hubs={hubs}
                                corridors={corridors}
                                canIntake={canIntake}
                                refreshKey={parcelListRefresh}
                                onCreate={() => {
                                    setIntakeError(null);
                                    setIntakeErrors({});
                                    setIntakeOpen(true);
                                }}
                            />
                        ) : activeTab === 'overview' ? (
                            <ParcelSupervisionPanel />
                        ) : activeTab === 'corridors' ? (
                            <section className="grid gap-5">
                                <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                                    <ParcelKpi
                                        icon={Route}
                                        label="Corridors actifs"
                                        hint="Desserte ouverte à la vente"
                                        value={activeCorridors.length}
                                        tone="navy"
                                        tooltip="Corridors enabled = true dans le périmètre Colis. Les corridors désactivés sont exclus du compteur."
                                    />
                                    <ParcelKpi
                                        icon={Coins}
                                        label="Avec tarif"
                                        hint="Grille publiée disponible"
                                        value={
                                            activeCorridors.length -
                                            corridorsWithoutTariff.length
                                        }
                                        tone="green"
                                        tooltip="Corridors actifs disposant d’au moins un tarif publié. Calcul : corridors actifs − corridors sans tarif."
                                    />
                                    <ParcelKpi
                                        icon={Wallet}
                                        label="Wallet + espèces"
                                        hint="Encaissement guichet autorisé"
                                        value={
                                            activeCorridors.filter(
                                                (corridor) =>
                                                    corridor.wallet_cash_enabled,
                                            ).length
                                        }
                                        tone="gold"
                                        tooltip="Corridors dont wallet_cash_enabled = true. Ce paramètre autorise l’encaissement Wallet et espèces au guichet."
                                    />
                                </div>

                                <KoraTable<ParcelCorridor>
                                    title="Corridors & tarifs"
                                    variant="finance"
                                    subtitle="Desserte structurée, activation contrôlée et publication de tarifs versionnés."
                                    icon={Route}
                                    columns={corridorColumns}
                                    data={corridors}
                                    rowKey={(corridor) => corridor.uuid}
                                    emptyIcon={Route}
                                    emptyTitle="Aucun corridor"
                                    emptyMessage="Créez un corridor entre deux lieux de dépôt actifs, puis publiez un tarif."
                                    actions={
                                        <div className="flex items-center gap-3">
                                            <span className="hidden text-[0.6875rem] font-bold text-muted-foreground sm:block">
                                                {corridors.length} corridor
                                                {corridors.length > 1
                                                    ? 's'
                                                    : ''}
                                            </span>
                                            {canConfigure && (
                                                <ParcelButton
                                                    type="button"
                                                    variant="primary"
                                                    onClick={() => {
                                                        setCorridorError(null);
                                                        setCorridorErrors({});
                                                        setCorridorTarget(null);
                                                        setCorridorOpen(true);
                                                    }}
                                                >
                                                    <Plus className="size-4" />
                                                    Nouveau corridor
                                                </ParcelButton>
                                            )}
                                        </div>
                                    }
                                />

                                {!canPublishTariffs && (
                                    <div className="rounded-2xl border border-border bg-muted/30 px-4 py-3 text-xs leading-5 text-muted-foreground">
                                        Votre rôle peut configurer les corridors
                                        mais pas publier de tarif. Demandez
                                        `parcels.tariffs` pour modifier les
                                        grilles.
                                    </div>
                                )}
                            </section>
                        ) : activeTab === 'capacity' ? (
                            <section className="grid gap-5">
                                <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                                    <ParcelKpi
                                        icon={Truck}
                                        label="Départs dans l’horizon"
                                        hint={`Fenêtre de ${capacityHorizon} jours`}
                                        value={capacityRows.length}
                                        tone="blue"
                                        tooltip={`Départs programmés desservant les corridors actifs dans les ${capacityHorizon} prochains jours.`}
                                    />
                                    <ParcelKpi
                                        icon={Gauge}
                                        label="Capacité définie"
                                        hint="Soute chiffrée"
                                        value={
                                            capacityRows.filter(
                                                (row) =>
                                                    row.capacity_grams !== null,
                                            ).length
                                        }
                                        tone="green"
                                        tooltip="Départs pour lesquels capacity_grams est renseignée. Les autres ne peuvent pas recevoir d’affectation."
                                    />
                                    <ParcelKpi
                                        icon={CircleAlert}
                                        label="Sans capacité"
                                        hint="À configurer avant affectation"
                                        value={
                                            capacityRows.filter(
                                                (row) =>
                                                    row.capacity_grams === null,
                                            ).length
                                        }
                                        tone="gold"
                                        tooltip="Départs de l’horizon dont capacity_grams est null. Calcul : total des départs − départs avec capacité définie."
                                    />
                                </div>

                                {capacityError ? (
                                    <div className="rounded-[18px] border border-destructive/30 bg-destructive/10 px-5 py-4 text-sm text-destructive">
                                        {capacityError}
                                    </div>
                                ) : capacityLoading ? (
                                    <div className="grid min-h-[14rem] place-items-center rounded-[18px] border border-border bg-card">
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Loader2 className="size-4 animate-spin" />
                                            Chargement des départs…
                                        </div>
                                    </div>
                                ) : (
                                    <KoraTable<CapacityDeparture>
                                        title="Capacité des départs"
                                        variant="finance"
                                        subtitle={`Départs programmés des corridors actifs sur ${capacityHorizon} jours.`}
                                        icon={Truck}
                                        columns={capacityColumns}
                                        data={capacityRows}
                                        rowKey={(departure) => departure.uuid}
                                        emptyIcon={Truck}
                                        emptyTitle="Aucun départ à traiter"
                                        emptyMessage="Aucun départ programmé ne dessert les corridors actifs dans l’horizon de routage."
                                        actions={
                                            <ParcelButton
                                                type="button"
                                                variant="ghost"
                                                onClick={() =>
                                                    void loadCapacity()
                                                }
                                                disabled={capacityLoading}
                                            >
                                                <RefreshCw className="size-3.5" />
                                                Actualiser
                                            </ParcelButton>
                                        }
                                    />
                                )}

                                <div className="flex items-start gap-3 rounded-2xl border border-gold/30 bg-gold/10 px-4 py-3 text-xs leading-5 text-foreground/75">
                                    <Gauge className="mt-0.5 size-4 shrink-0 text-gold" />
                                    <p>
                                        La capacité est vérifiée à l’intake mais
                                        reste non réservée dans cette phase.
                                        Aucune limite n’est déduite tant que
                                        l’affectation n’est pas confirmée.
                                    </p>
                                </div>
                            </section>
                        ) : activeTab === 'finance' ? (
                            <ParcelFinancePanel />
                        ) : (
                            <section className="grid gap-5">
                                <KoraFilterBar
                                    route="/parcels"
                                    searchPlaceholder="Rechercher un lieu, une ville, une ligne…"
                                    filters={filterConfigs}
                                    label={{
                                        caption: 'Lieux configurés',
                                        value: `${filteredHubs.length} / ${hubs.length}`,
                                    }}
                                />

                                <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                                    <ParcelKpi
                                        icon={Building2}
                                        label="Agences internes"
                                        hint="Hubs portés par KORA"
                                        value={
                                            hubs.filter(
                                                (hub) =>
                                                    hub.kind === 'agency' &&
                                                    hub.archived_at === null,
                                            ).length
                                        }
                                        tone="navy"
                                        tooltip="Hubs kind = agency non archivés. Les agences déjà rattachées restent comptées ici, mais sont retirées du formulaire d’ajout."
                                    />
                                    <ParcelKpi
                                        icon={Store}
                                        label="Points partenaires"
                                        hint="Hubs portés par un partenaire"
                                        value={partnerHubs}
                                        tone="green"
                                        tooltip="Hubs kind = partner_point non archivés, actifs ou non."
                                    />
                                    <ParcelKpi
                                        icon={CheckCircle2}
                                        label="Prêts à recevoir"
                                        hint="Lieux activés"
                                        value={activeHubs}
                                        tone="gold"
                                        tooltip="Hubs enabled = true et archived_at vide. Seuls ces lieux peuvent être choisis à l’intake."
                                    />
                                </div>

                                <KoraTable<DepositHub>
                                    title="Lieux de dépôt"
                                    variant="finance"
                                    subtitle="Agences internes et points partenaires reliés à un arrêt de ligne."
                                    icon={MapPin}
                                    columns={hubColumns}
                                    data={filteredHubs}
                                    rowKey={(hub) => hub.uuid}
                                    emptyIcon={MapPin}
                                    emptyTitle="Aucun lieu de dépôt"
                                    emptyMessage="Ajustez les filtres ou ajoutez un lieu relié à un arrêt actif."
                                    actions={
                                        <span className="hidden text-[0.6875rem] font-bold text-muted-foreground sm:block">
                                            {filteredHubs.length} lieu
                                            {filteredHubs.length > 1 ? 'x' : ''}
                                        </span>
                                    }
                                />

                                <div className="flex items-start gap-3 rounded-2xl border border-gold/30 bg-gold/10 px-4 py-3 text-xs leading-5 text-foreground/75">
                                    <ArrowRight className="mt-0.5 size-4 shrink-0 text-gold" />
                                    <p>
                                        Un point partenaire n’est proposé que si
                                        son arrêt est desservi dans le bon ordre
                                        par un départ éligible. Aucun transfert
                                        implicite vers une agence n’est créé.
                                    </p>
                                </div>
                            </section>
                        )}
                    </>
                )}
            </div>

            <DepositHubDialog
                open={dialogOpen}
                onOpenChange={(open) => {
                    if (!saving) {
                        setDialogOpen(open);
                    }
                }}
                internalAgencies={
                    configuration?.options.internal_agencies ?? []
                }
                partnerPoints={configuration?.options.partner_points ?? []}
                stops={configuration?.options.stops ?? []}
                linkedAgencyUuids={linkedAgencyUuids}
                linkedPartnerPointUuids={linkedPartnerPointUuids}
                busy={saving}
                generalError={formError}
                validationErrors={formErrors}
                onSubmit={saveHub}
            />

            <CorridorDialog
                open={corridorOpen}
                onOpenChange={(open) => {
                    if (!savingCorridor) {
                        setCorridorOpen(open);

                        if (!open) {
                            setCorridorError(null);
                            setCorridorErrors({});
                        }
                    }
                }}
                corridor={corridorTarget}
                hubs={hubs}
                stops={stops}
                busy={savingCorridor}
                generalError={corridorError}
                validationErrors={corridorErrors}
                onSubmit={saveCorridor}
            />

            <TariffDialog
                open={tariffOpen}
                onOpenChange={(open) => {
                    if (!publishingTariff) {
                        setTariffOpen(open);

                        if (!open) {
                            setTariffError(null);
                            setTariffErrors({});
                        }
                    }
                }}
                corridor={tariffTarget}
                currency={configuration?.currency ?? ''}
                depositKind={tariffTarget?.origin_hub_kind ?? 'agency'}
                busy={publishingTariff}
                generalError={tariffError}
                validationErrors={tariffErrors}
                onSubmit={publishTariff}
            />

            <CapacityDialog
                open={capacityTarget !== null}
                onOpenChange={(open) => {
                    if (!open && !savingCapacity) {
                        setCapacityTarget(null);
                        setCapacityFormError(null);
                        setCapacityFormErrors({});
                    }
                }}
                departure={capacityTarget}
                busy={savingCapacity}
                generalError={capacityFormError}
                validationErrors={capacityFormErrors}
                onSubmit={saveCapacity}
            />

            <ParcelIntakeDialog
                open={intakeOpen}
                onOpenChange={(open) => {
                    if (!creatingParcel) {
                        setIntakeOpen(open);

                        if (!open) {
                            setIntakeError(null);
                            setIntakeErrors({});
                        }
                    }
                }}
                hubs={hubs}
                corridors={corridors}
                stops={stops}
                busy={creatingParcel}
                generalError={intakeError}
                validationErrors={intakeErrors}
                onCreate={createParcel}
                onQuote={quoteParcel}
            />

            <Dialog
                open={archiveTarget !== null}
                onOpenChange={(open) => {
                    if (!open && !archiving) {
                        setArchiveTarget(null);
                        setArchiveError(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Archiver ce lieu de dépôt ?</DialogTitle>
                        <DialogDescription>
                            Le lieu ne sera plus proposé au guichet. Les colis,
                            corridors et l’historique existants restent
                            conservés.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="rounded-xl border border-border bg-muted/30 px-4 py-3 text-sm">
                        <strong className="block text-foreground">
                            {archiveTarget?.name ?? 'Lieu sans nom'}
                        </strong>
                        <small className="mt-0.5 block text-muted-foreground">
                            {archiveTarget?.kind === 'partner_point'
                                ? 'Point partenaire'
                                : 'Agence interne'}
                            {archiveTarget?.city
                                ? ` · ${archiveTarget.city}`
                                : ''}
                        </small>
                    </div>

                    {archiveError && (
                        <div
                            role="alert"
                            className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        >
                            {archiveError}
                        </div>
                    )}

                    <DialogFooter>
                        <ParcelButton
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                setArchiveTarget(null);
                                setArchiveError(null);
                            }}
                            disabled={archiving}
                        >
                            Annuler
                        </ParcelButton>
                        <ParcelButton
                            type="button"
                            variant="destructive"
                            onClick={() => void archiveHub()}
                            disabled={archiving}
                        >
                            {archiving ? (
                                <>
                                    <Loader2 className="size-4 animate-spin" />
                                    Archivage…
                                </>
                            ) : (
                                'Archiver le lieu'
                            )}
                        </ParcelButton>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ParcelsPage.layout = {
    breadcrumbs: [
        {
            title: 'Colis',
            href: '/parcels',
        } satisfies BreadcrumbItem,
    ],
};
