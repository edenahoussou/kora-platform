import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowUpRight,
    Eye,
    Globe2,
    KeyRound,
    Package,
    PackageCheck,
    RefreshCw,
    Scale,
    Search,
    SlidersHorizontal,
    Ticket,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { FilterSelect, Pagination } from '@/components/cash/cash-shared';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import {
    parcelStatusLabel,
    parcelStatusTone,
} from '@/components/parcels/status';
import { SalePageHeading } from '@/components/sale/sale-page-heading';
import {
    COUNTER_AGENCY_HUB,
    INITIAL_AGENCY_PARCELS,
    NETWORK_LOOKUP_PARCELS,
} from '@/components/sale-parcels-list/mock-data';
import { NetworkParcelLookupDialog } from '@/components/sale-parcels-list/network-parcel-lookup-dialog';
import { ParcelRegistryDrawer } from '@/components/sale-parcels-list/parcel-registry-drawer';
import { ParcelRegistryHero } from '@/components/sale-parcels-list/parcel-registry-hero';
import { ParcelRegistryKpis } from '@/components/sale-parcels-list/parcel-registry-kpis';
import type {
    CounterParcelRecord,
    CounterParcelScope,
} from '@/components/sale-parcels-list/types';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const PAGE_SIZE = 8;

function formatAmount(amount: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
}

export default function SaleParcelsIndexPage() {
    const [parcels, setParcels] = useState<CounterParcelRecord[]>(
        INITIAL_AGENCY_PARCELS,
    );
    const [scope, setScope] = useState<CounterParcelScope>('all');
    const [networkLookupOpen, setNetworkLookupOpen] = useState(false);

    // Draft filter inputs (applied on "Filtrer" or Enter, matching KORA filterbar behavior)
    const [queryInput, setQueryInput] = useState('');
    const [statusInput, setStatusInput] = useState('all');
    const [cityInput, setCityInput] = useState('all');
    const [channelInput, setChannelInput] = useState('all');

    const [applied, setApplied] = useState({
        query: '',
        status: 'all',
        city: 'all',
        channel: 'all',
    });
    const [page, setPage] = useState(1);
    const [selectedParcel, setSelectedParcel] =
        useState<CounterParcelRecord | null>(null);

    // Distinct partner/corridor cities visible in the agency perimeter
    const cityOptions = useMemo(() => {
        const cities = new Set<string>();

        for (const p of parcels) {
            if (p.originCity !== COUNTER_AGENCY_HUB.city) {
                cities.add(p.originCity);
            }

            if (p.destinationCity !== COUNTER_AGENCY_HUB.city) {
                cities.add(p.destinationCity);
            }
        }

        return [
            ['all', 'Toutes les destinations'],
            ...Array.from(cities)
                .sort((a, b) => a.localeCompare(b, 'fr'))
                .map((city) => [city, city]),
        ];
    }, [parcels]);

    // Scope counts
    const scopeCounts = useMemo(() => {
        return {
            all: parcels.length,
            outbound: parcels.filter((p) => p.direction === 'outbound').length,
            inbound: parcels.filter((p) => p.direction === 'inbound').length,
            preregistered: parcels.filter((p) => p.status === 'preregistered')
                .length,
            incidents: parcels.filter(
                (p) => p.incidentOpen || p.status === 'return_pending',
            ).length,
        };
    }, [parcels]);

    // KPI metrics derived dynamically from current agency state
    const kpiMetrics = useMemo(() => {
        const outboundTodayCount = parcels.filter(
            (p) => p.direction === 'outbound',
        ).length;
        const inboundTodayCount = parcels.filter(
            (p) => p.direction === 'inbound',
        ).length;
        const toProcessCount = parcels.filter((p) =>
            ['preregistered', 'arrived'].includes(p.status),
        ).length;
        const availableForPickupCount = parcels.filter(
            (p) => p.status === 'available',
        ).length;
        const todayRevenueFcfa = parcels
            .filter((p) => p.paid && p.direction === 'outbound')
            .reduce((sum, p) => sum + p.amountFcfa, 0);

        return {
            todayCount: parcels.length,
            outboundTodayCount,
            inboundTodayCount,
            toProcessCount,
            availableForPickupCount,
            todayRevenueFcfa,
        };
    }, [parcels]);

    const filteredParcels = useMemo(() => {
        const q = applied.query.trim().toLowerCase();

        return parcels.filter((parcel) => {
            if (scope === 'outbound' && parcel.direction !== 'outbound') {
                return false;
            }

            if (scope === 'inbound' && parcel.direction !== 'inbound') {
                return false;
            }

            if (
                scope === 'preregistered' &&
                parcel.status !== 'preregistered'
            ) {
                return false;
            }

            if (
                scope === 'incidents' &&
                !parcel.incidentOpen &&
                parcel.status !== 'return_pending'
            ) {
                return false;
            }

            if (applied.status !== 'all' && parcel.status !== applied.status) {
                return false;
            }

            if (
                applied.city !== 'all' &&
                parcel.originCity !== applied.city &&
                parcel.destinationCity !== applied.city
            ) {
                return false;
            }

            if (
                applied.channel !== 'all' &&
                parcel.paymentMethod !== applied.channel
            ) {
                return false;
            }

            if (q !== '') {
                const haystack = [
                    parcel.reference,
                    parcel.contents,
                    parcel.senderName,
                    parcel.senderPhone,
                    parcel.recipientName,
                    parcel.recipientPhone,
                    parcel.originCity,
                    parcel.destinationCity,
                    parcel.departureRef ?? '',
                ]
                    .join(' ')
                    .toLowerCase();

                if (!haystack.includes(q)) {
                    return false;
                }
            }

            return true;
        });
    }, [parcels, scope, applied]);

    const totalPages = Math.max(
        1,
        Math.ceil(filteredParcels.length / PAGE_SIZE),
    );
    const safePage = Math.min(page, totalPages);
    const paginatedParcels = useMemo(() => {
        const start = (safePage - 1) * PAGE_SIZE;

        return filteredParcels.slice(start, start + PAGE_SIZE);
    }, [filteredParcels, safePage]);

    const applyFilters = () => {
        setPage(1);
        setApplied({
            query: queryInput,
            status: statusInput,
            city: cityInput,
            channel: channelInput,
        });
    };

    const resetFilters = () => {
        setScope('all');
        setQueryInput('');
        setStatusInput('all');
        setCityInput('all');
        setChannelInput('all');
        setPage(1);
        setApplied({
            query: '',
            status: 'all',
            city: 'all',
            channel: 'all',
        });
    };

    const networkLookupMatches = useMemo(() => {
        const normalized = applied.query.trim().toLowerCase();

        if (normalized.length < 4) {
            return [];
        }

        const strippedPhone = normalized.replace(/\s+/g, '');

        return NETWORK_LOOKUP_PARCELS.filter(
            (p) =>
                p.reference.toLowerCase().includes(normalized) ||
                p.senderPhone.replace(/\s+/g, '').includes(strippedPhone) ||
                p.recipientPhone.replace(/\s+/g, '').includes(strippedPhone),
        );
    }, [applied.query]);

    const handleMarkReceived = (target: CounterParcelRecord) => {
        const nowLabel = 'Aujourd’hui · À l’instant';
        setParcels((prev) =>
            prev.map((item) => {
                if (item.uuid !== target.uuid) {
                    return item;
                }

                const updated: CounterParcelRecord = {
                    ...item,
                    status: 'available',
                    updatedAt: nowLabel,
                    withdrawDeadline: '28 nov. 2026',
                    movements: [
                        ...item.movements,
                        {
                            id: `mv-${Date.now()}`,
                            label: 'Réceptionné en magasin · Code PIN envoyé au destinataire',
                            location: `${COUNTER_AGENCY_HUB.city} · ${COUNTER_AGENCY_HUB.name}`,
                            actor: 'Agent guichet',
                            timestamp: nowLabel,
                            done: true,
                        },
                    ],
                };

                setSelectedParcel(updated);

                return updated;
            }),
        );
        toast.success(
            `${target.reference} réceptionné et mis à disposition pour ${target.recipientName}.`,
        );
    };

    const handleConfirmRelease = (target: CounterParcelRecord) => {
        const nowLabel = 'Aujourd’hui · À l’instant';
        setParcels((prev) =>
            prev.map((item) => {
                if (item.uuid !== target.uuid) {
                    return item;
                }

                const updated: CounterParcelRecord = {
                    ...item,
                    status: 'withdrawn',
                    updatedAt: nowLabel,
                    movements: [
                        ...item.movements,
                        {
                            id: `mv-${Date.now()}`,
                            label: 'Remis au destinataire après contrôle PIN',
                            location: `${COUNTER_AGENCY_HUB.city} · Guichet Retraits`,
                            actor: 'Agent guichet',
                            timestamp: nowLabel,
                            done: true,
                        },
                    ],
                };

                setSelectedParcel(updated);

                return updated;
            }),
        );
        toast.success(
            `Remise confirmée pour ${target.reference} (${target.recipientName}).`,
        );
    };

    const handlePrintLabel = (target: CounterParcelRecord) => {
        toast.success(
            `Impression du bordereau ${target.reference} lancée sur l’imprimante thermique.`,
        );
    };

    const columns: TableColumn<CounterParcelRecord>[] = [
        {
            key: 'parcel',
            header: 'Colis',
            render: (parcel) => (
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <strong className="font-mono text-[0.725rem] font-black tracking-[0.02em] whitespace-nowrap text-foreground">
                            {parcel.reference}
                        </strong>
                        {parcel.status === 'preregistered' && (
                            <span className="rounded bg-amber-500/15 px-1.5 py-0.5 text-[0.575rem] font-extrabold text-amber-700 uppercase dark:text-amber-300">
                                Pré-enreg.
                            </span>
                        )}
                    </div>
                    <small className="mt-0.5 line-clamp-1 block text-[0.7rem] text-muted-foreground">
                        {parcel.contents}
                    </small>
                </div>
            ),
        },
        {
            key: 'route',
            header: 'Trajet & sens',
            render: (parcel) => (
                <div>
                    <div className="flex items-center gap-1.5">
                        <span
                            className={cn(
                                'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.6rem] font-extrabold uppercase',
                                parcel.direction === 'outbound'
                                    ? 'bg-kora/10 text-kora dark:bg-gold/15 dark:text-gold'
                                    : 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
                            )}
                        >
                            {parcel.direction === 'outbound' ? (
                                <ArrowUpRight className="size-3" />
                            ) : (
                                <ArrowDownLeft className="size-3" />
                            )}
                            {parcel.direction === 'outbound'
                                ? 'Expédié'
                                : 'Arrivée'}
                        </span>
                        <span className="text-[0.725rem] font-bold text-foreground">
                            {parcel.originCity} → {parcel.destinationCity}
                        </span>
                    </div>
                    <small className="mt-0.5 block text-[0.65rem] text-muted-foreground">
                        {parcel.departureRef
                            ? `${parcel.departureRef} · ${parcel.departureLabel}`
                            : parcel.departureLabel}
                    </small>
                </div>
            ),
        },
        {
            key: 'parties',
            header: 'Expéditeur & destinataire',
            render: (parcel) => (
                <div>
                    <span className="block text-[0.725rem] font-semibold text-foreground">
                        {parcel.senderName}{' '}
                        <span className="text-muted-foreground">→</span>{' '}
                        {parcel.recipientName}
                    </span>
                    <small className="mt-0.5 block text-[0.65rem] text-muted-foreground">
                        {parcel.direction === 'inbound'
                            ? `Dest. ${parcel.recipientPhone}`
                            : `Exp. ${parcel.senderPhone}`}
                    </small>
                </div>
            ),
        },
        {
            key: 'weight_amount',
            header: 'Poids & montant',
            render: (parcel) => (
                <div>
                    <span className="block text-[0.725rem] font-extrabold text-foreground">
                        {parcel.weightKg !== null
                            ? `${parcel.weightKg} kg`
                            : `~${parcel.declaredWeightKg} kg (décl.)`}
                    </span>
                    <small className="mt-0.5 block text-[0.65rem] font-bold text-foreground/85">
                        {formatAmount(parcel.amountFcfa)}
                    </small>
                    <small className="block text-[0.625rem] text-muted-foreground">
                        {parcel.paymentChannelLabel}
                    </small>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Dernier mouvement',
            render: (parcel) => (
                <div>
                    <div className="flex flex-wrap items-center gap-1">
                        <KoraStatusBadge
                            className="whitespace-nowrap"
                            tone={parcelStatusTone(parcel.status)}
                            label={parcelStatusLabel(parcel.status)}
                        />
                        {parcel.incidentOpen && (
                            <KoraStatusBadge
                                className="whitespace-nowrap"
                                tone="destructive"
                                icon={<AlertTriangle className="size-3" />}
                                label="Incident"
                            />
                        )}
                    </div>
                    <small className="mt-1 block text-[0.625rem] text-muted-foreground">
                        {parcel.updatedAt}
                    </small>
                </div>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (parcel) => (
                <div
                    className="flex flex-wrap justify-end gap-1.5"
                    onClick={(e) => e.stopPropagation()}
                >
                    {parcel.status === 'preregistered' && (
                        <Link
                            href={`/sale/parcels/new?mode=lookup&ref=${encodeURIComponent(parcel.reference)}`}
                            className="btn btn-primary btn-sm"
                        >
                            <Scale className="size-3.5" />
                            <span>Peser</span>
                        </Link>
                    )}

                    {parcel.status === 'arrived' && (
                        <button
                            type="button"
                            onClick={() => handleMarkReceived(parcel)}
                            className="btn btn-primary btn-sm"
                        >
                            <PackageCheck className="size-3.5" />
                            <span>Réceptionner</span>
                        </button>
                    )}

                    {parcel.status === 'available' && (
                        <button
                            type="button"
                            onClick={() => setSelectedParcel(parcel)}
                            className="btn btn-navy btn-sm"
                        >
                            <KeyRound className="size-3.5" />
                            <span>Remettre</span>
                        </button>
                    )}

                    <button
                        type="button"
                        onClick={() => setSelectedParcel(parcel)}
                        className="btn btn-ghost btn-sm"
                    >
                        <Eye className="size-3.5" />
                        <span>Détails</span>
                    </button>
                </div>
            ),
        },
    ];

    const scopeTabs: Array<{
        key: CounterParcelScope;
        label: string;
        count: number;
    }> = [
        { key: 'all', label: 'Tous (mon agence)', count: scopeCounts.all },
        { key: 'outbound', label: 'Expédiés ici', count: scopeCounts.outbound },
        {
            key: 'inbound',
            label: 'Arrivées & retraits',
            count: scopeCounts.inbound,
        },
        {
            key: 'preregistered',
            label: 'Pré-enregistrés',
            count: scopeCounts.preregistered,
        },
        {
            key: 'incidents',
            label: 'Incidents & retours',
            count: scopeCounts.incidents,
        },
    ];

    return (
        <>
            <Head title="Colis enregistrés · Vente" />

            <div className="cash-page">
                <SalePageHeading
                    eyebrow="KORA OPS / Vente · Colis"
                    title="Colis enregistrés"
                    description="Suivez les expéditions et arrivées de votre agence, finalisez les pré-enregistrements et gérez les retraits."
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={() =>
                                    toast.success(
                                        'Registre des mouvements actualisé.',
                                    )
                                }
                                className="btn btn-ghost btn-sm"
                            >
                                <RefreshCw className="size-3.5" />
                                <span>Actualiser</span>
                            </button>
                            <Link
                                href="/sale"
                                className="btn btn-ghost btn-sm"
                            >
                                <Ticket className="size-3.5 text-gold" />
                                <span>Billetterie Passagers</span>
                            </Link>
                        </div>
                    }
                />

                {/* KORA Hero banner: standard hero height, navy left with subtle micro-animations + 2 CTAs, KORA photo right */}
                <ParcelRegistryHero
                    pendingPreRegCount={scopeCounts.preregistered}
                    todayMovementsCount={parcels.length}
                />

                {/* 4 Colored KPI Cards (matching Dashboard / Cash KPI style) */}
                <ParcelRegistryKpis
                    todayCount={kpiMetrics.todayCount}
                    outboundTodayCount={kpiMetrics.outboundTodayCount}
                    inboundTodayCount={kpiMetrics.inboundTodayCount}
                    toProcessCount={kpiMetrics.toProcessCount}
                    availableForPickupCount={kpiMetrics.availableForPickupCount}
                    todayRevenueFcfa={kpiMetrics.todayRevenueFcfa}
                />

                {/* Scope Tabs */}
                <div
                    role="tablist"
                    aria-label="Filtrer par périmètre opérationnel"
                    className="mb-3.5 flex flex-wrap items-center gap-2"
                >
                    {scopeTabs.map((tab) => {
                        const isActive = scope === tab.key;

                        return (
                            <button
                                key={tab.key}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                onClick={() => {
                                    setScope(tab.key);
                                    setPage(1);
                                }}
                                className={cn(
                                    'inline-flex cursor-pointer items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-bold transition-all',
                                    isActive
                                        ? 'border-kora bg-kora text-white shadow-sm dark:border-gold dark:bg-gold dark:text-kora'
                                        : 'border-border bg-card text-muted-foreground hover:border-gold/50 hover:text-foreground',
                                )}
                            >
                                <span>{tab.label}</span>
                                <span
                                    className={cn(
                                        'inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[0.625rem] font-black',
                                        isActive
                                            ? 'bg-gold text-kora dark:bg-kora dark:text-gold'
                                            : 'bg-muted text-foreground',
                                    )}
                                >
                                    {tab.count}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Caisse-style Filterbar */}
                <div className="cash-filterbar">
                    <div className="relative min-w-[230px] flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            value={queryInput}
                            onChange={(event) =>
                                setQueryInput(event.target.value)
                            }
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    applyFilters();
                                }
                            }}
                            placeholder="Référence (agence ou autre agence), client, téléphone…"
                            aria-label="Rechercher un colis dans l'agence ou par référence réseau"
                            className="cash-filter-search w-full"
                            style={{ paddingLeft: '2.25rem' }}
                        />
                    </div>

                    <FilterSelect
                        label="Statut"
                        value={statusInput}
                        onChange={setStatusInput}
                        options={[
                            ['all', 'Tous les statuts'],
                            ['preregistered', 'Pré-enregistré'],
                            ['paid', 'Payé'],
                            ['ready', 'Prêt au départ'],
                            ['loaded', 'Chargé'],
                            ['in_transit', 'En transit'],
                            ['arrived', 'Arrivé'],
                            ['available', 'Disponible au retrait'],
                            ['withdrawn', 'Retiré'],
                            ['return_pending', 'Retour à charger'],
                        ]}
                    />

                    <FilterSelect
                        label="Corridor"
                        value={cityInput}
                        onChange={setCityInput}
                        options={cityOptions}
                    />

                    <FilterSelect
                        label="Paiement"
                        value={channelInput}
                        onChange={setChannelInput}
                        options={[
                            ['all', 'Tous les paiements'],
                            ['cash', 'Espèces'],
                            ['mobile_money', 'Mobile Money'],
                            ['company_account', 'Compte Entreprise'],
                            ['pending', 'À encaisser'],
                        ]}
                    />

                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={applyFilters}
                    >
                        <SlidersHorizontal className="size-4" />
                        <span>Filtrer</span>
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={resetFilters}
                    >
                        Réinitialiser
                    </button>
                </div>

                {/* Cross-agency network lookup results (only surfaced when searching an exact network ref/phone) */}
                {networkLookupMatches.length > 0 && (
                    <div className="mb-3.5 space-y-2">
                        {networkLookupMatches.map((match) => (
                            <div
                                key={match.uuid}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-500/35 bg-amber-500/10 px-4 py-3 text-xs text-foreground"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300">
                                        <Globe2 className="size-4" />
                                    </span>
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono font-black">
                                                {match.reference}
                                            </span>
                                            <KoraStatusBadge
                                                tone={parcelStatusTone(
                                                    match.status,
                                                )}
                                                label={parcelStatusLabel(
                                                    match.status,
                                                )}
                                            />
                                            <span className="rounded bg-card px-1.5 py-0.5 text-[0.625rem] font-bold text-muted-foreground">
                                                Hors périmètre{' '}
                                                {COUNTER_AGENCY_HUB.city} ·
                                                Lecture seule
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-muted-foreground">
                                            {match.originCity} →{' '}
                                            {match.destinationCity} ·{' '}
                                            {match.departureLabel} (
                                            {match.senderName} →{' '}
                                            {match.recipientName})
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setSelectedParcel(match)}
                                    className="btn btn-navy btn-sm"
                                >
                                    <Eye className="size-3.5" />
                                    <span>Consulter le suivi</span>
                                </button>
                            </div>
                        ))}
                    </div>
                )}

                {/* Caisse-style Table */}
                <KoraTable<CounterParcelRecord>
                    variant="cash"
                    title="Derniers mouvements des colis"
                    subtitle={`${filteredParcels.length} colis · Périmètre ${COUNTER_AGENCY_HUB.city} (${COUNTER_AGENCY_HUB.name})`}
                    icon={Package}
                    columns={columns}
                    data={paginatedParcels}
                    rowKey={(parcel) => parcel.uuid}
                    onRowClick={(parcel) => setSelectedParcel(parcel)}
                    emptyTitle="Aucun colis dans cette vue"
                    emptyMessage="Ajustez vos filtres, ou saisissez une référence exacte (ex. KC-20261125-7741) pour consulter un colis hors agence."
                    emptyIcon={Package}
                    actions={
                        <button
                            type="button"
                            onClick={() => setNetworkLookupOpen(true)}
                            className="btn btn-ghost btn-sm"
                        >
                            <Globe2 className="size-3.5 text-gold" />
                            <span>Suivi hors agence</span>
                        </button>
                    }
                    footer={
                        totalPages > 1 ? (
                            <Pagination
                                currentPage={safePage}
                                totalPages={totalPages}
                                total={filteredParcels.length}
                                pageSize={PAGE_SIZE}
                                onPageChange={setPage}
                            />
                        ) : undefined
                    }
                />
            </div>

            <NetworkParcelLookupDialog
                open={networkLookupOpen}
                onOpenChange={setNetworkLookupOpen}
                agencyParcels={parcels}
                onSelectParcel={(parcel) => setSelectedParcel(parcel)}
            />

            <ParcelRegistryDrawer
                parcel={selectedParcel}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedParcel(null);
                    }
                }}
                onMarkReceived={handleMarkReceived}
                onConfirmRelease={(parcel) => handleConfirmRelease(parcel)}
                onPrintLabel={handlePrintLabel}
            />
        </>
    );
}

SaleParcelsIndexPage.layout = {
    breadcrumbs: [
        { title: 'Vente', href: '/sale' },
        { title: 'Colis', href: '/sale/parcels' },
    ] as BreadcrumbItem[],
};
