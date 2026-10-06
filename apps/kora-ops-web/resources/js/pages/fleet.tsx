import { Head, router } from '@inertiajs/react';
import {
    Archive,
    Armchair,
    BusFront,
    CheckCircle2,
    Edit3,
    Eye,
    Layers,
    MoreHorizontal,
    Plus,
    RotateCcw,
    Sparkles,
    Wrench,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { BusFormDialog } from '@/components/buses/bus-form-dialog';
import type { BusRecord } from '@/components/buses/bus-form-dialog';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import amenitiesRoutes from '@/routes/amenities';
import fleet from '@/routes/fleet';
import modelsRoutes from '@/routes/models';
import type { BreadcrumbItem } from '@/types';

type AmenityOption = {
    id: number;
    code: string;
    label: string;
    icon: string | null;
};

type PageProps = {
    buses: {
        data: BusRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
    models: { id: number; label: string }[];
    compositions: { id: number; label: string; seatCount: number }[];
    amenities: AmenityOption[];
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
        canDeactivate: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Actif',
    draft: 'Brouillon',
    maintenance: 'En maintenance',
    retired: 'Retiré',
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    active: 'navy',
    draft: 'gold',
    maintenance: 'gold',
    retired: 'muted',
};

function KpiCard({
    icon: Icon,
    label,
    hint,
    value,
    tone = 'navy',
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    label: string;
    hint: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted' | 'green' | 'rose';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        rose: 'bg-destructive/10 text-destructive',
    };

    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', toneStyles[tone])}>
                        <Icon className="size-4" strokeWidth={1.8} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{hint}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Total</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

export default function FleetPage({
    buses,
    statuses,
    models,
    compositions,
    amenities,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<BusRecord | null>(null);

    const stats = useMemo(() => {
        const data = buses.data;
        const active = data.filter((b) => b.status === 'active').length;
        const maintenance = data.filter(
            (b) => b.status === 'maintenance',
        ).length;
        const totalSeats = data.reduce(
            (acc, b) => acc + (b.seatCount || 0),
            0,
        );

        return {
            total: buses.total,
            active,
            maintenance,
            totalSeats,
        };
    }, [buses]);

    const filters: FilterConfig[] = useMemo(
        () => [
            {
                name: 'status',
                label: 'Statut',
                placeholder: 'Tous les statuts',
                options: Object.entries(statuses).map(([value, label]) => ({
                    value,
                    label: statusLabels[value] ?? label,
                })),
            },
        ],
        [statuses],
    );

    const pagination: PaginationMeta = {
        currentPage: buses.current_page,
        lastPage: buses.last_page,
        perPage: buses.per_page,
        total: buses.total,
        from: buses.from,
        to: buses.to,
        baseUrl: '/fleet',
    };

    const changeStatus = (bus: BusRecord, status: string) => {
        if (
            window.confirm(
                'Voulez-vous vraiment modifier le statut de ce bus ?',
            )
        ) {
            router.patch(
                fleet.status.url({ bus: bus.uuid }),
                { status },
                { preserveScroll: true },
            );
        }
    };

    const columns: TableColumn<BusRecord>[] = [
        {
            key: 'bus',
            header: 'Bus',
            render: (bus) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-gold/12 text-[var(--kora)]">
                        <BusFront className="size-4" />
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {bus.registrationNumber}
                        </strong>
                        <span className="text-xs font-semibold text-muted-foreground">
                            {bus.modelName ?? 'Modèle non renseigné'}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'composition',
            header: 'Composition',
            render: (bus) => (
                <div>
                    <span className="block font-medium text-foreground">
                        {bus.compositionName ?? '—'}
                    </span>
                    <span className="text-xs font-semibold text-muted-foreground">
                        {bus.seatCount} places
                    </span>
                </div>
            ),
        },
        {
            key: 'amenities',
            header: 'Équipements',
            render: (bus) => {
                const labelsList = bus.amenityLabels ?? [];

                if (labelsList.length === 0) {
                    return (
                        <span className="text-xs text-muted-foreground">—</span>
                    );
                }

                const shown = labelsList.slice(0, 3);
                const rest = labelsList.length - shown.length;

                return (
                    <div className="flex flex-wrap gap-1.5">
                        {shown.map((label) => (
                            <span
                                key={label}
                                className="inline-flex items-center gap-1 rounded-full bg-muted/80 px-2 py-0.5 text-[0.6875rem] font-medium text-foreground ring-1 ring-border"
                            >
                                <Sparkles className="size-2.5 text-gold" />
                                {label}
                            </span>
                        ))}
                        {rest > 0 && (
                            <span className="inline-flex items-center rounded-full bg-gold/15 px-2 py-0.5 font-mono text-[0.6875rem] font-bold text-[var(--kora)] dark:text-gold">
                                +{rest}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'status',
            header: 'Statut',
            render: (bus) => (
                <KoraStatusBadge
                    label={statusLabels[bus.status] ?? bus.status}
                    tone={statusTones[bus.status] ?? 'gold'}
                />
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (bus) => (
                <div
                    className="flex justify-end"
                    onClick={(e) => e.stopPropagation()}
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Actions pour ${bus.registrationNumber}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() =>
                                    router.get(
                                        fleet.show.url({ bus: bus.uuid }),
                                    )
                                }
                            >
                                <Eye className="size-4" />
                                Voir le détail
                            </DropdownMenuItem>
                            {permissions.canUpdate && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setSelected(bus);
                                        setOpen(true);
                                    }}
                                >
                                    <Edit3 className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                            )}
                            {permissions.canDeactivate &&
                                bus.status === 'active' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(bus, 'maintenance')
                                        }
                                    >
                                        <Wrench className="size-4" />
                                        Mettre en maintenance
                                    </DropdownMenuItem>
                                )}
                            {permissions.canDeactivate &&
                                bus.status !== 'retired' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(bus, 'retired')
                                        }
                                    >
                                        <Archive className="size-4" />
                                        Retirer
                                    </DropdownMenuItem>
                                )}
                            {permissions.canDeactivate &&
                                bus.status !== 'active' &&
                                bus.status !== 'retired' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(bus, 'active')
                                        }
                                    >
                                        <RotateCcw className="size-4" />
                                        Réactiver
                                    </DropdownMenuItem>
                                )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Flotte" />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Ressources / Flotte
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Flotte de bus
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Gérez les véhicules du parc KORA, leur gabarit et leurs
                        équipements à bord.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        onClick={() => router.get(modelsRoutes.index.url())}
                        className="btn btn-ghost"
                    >
                        <Layers className="size-3.5 text-gold" />
                        Modèles
                    </button>
                    <button
                        type="button"
                        onClick={() => router.get(amenitiesRoutes.index.url())}
                        className="btn btn-ghost"
                    >
                        <Sparkles className="size-3.5 text-gold" />
                        Commodités
                    </button>

                    {permissions.canCreate && (
                        <button
                            type="button"
                            onClick={() => {
                                setSelected(null);
                                setOpen(true);
                            }}
                            className="btn btn-primary"
                        >
                            <Plus className="size-3.5" />
                            Nouveau bus
                        </button>
                    )}
                </div>
            </div>

            <section className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <KpiCard
                    icon={BusFront}
                    label="Véhicules"
                    hint="Parc total"
                    value={stats.total}
                    tone="navy"
                />
                <KpiCard
                    icon={CheckCircle2}
                    label="Actifs"
                    hint="En exploitation"
                    value={stats.active}
                    tone="green"
                />
                <KpiCard
                    icon={Wrench}
                    label="Maintenance"
                    hint="Immobilisés"
                    value={stats.maintenance}
                    tone="gold"
                />
                <KpiCard
                    icon={Armchair}
                    label="Places assises"
                    hint="Capacité cumulée"
                    value={stats.totalSeats}
                    tone="blue"
                />
            </section>

            <KoraFilterBar
                route="/fleet"
                searchPlaceholder="Rechercher par immatriculation ou modèle…"
                filters={filters}
            />

            <KoraTable
                title="Référentiel des bus"
                subtitle={`${buses.total} bus au total`}
                icon={BusFront}
                columns={columns}
                data={buses.data}
                rowKey={(bus) => bus.uuid}
                onRowClick={(bus) =>
                    router.get(fleet.show.url({ bus: bus.uuid }))
                }
                emptyTitle="Aucun bus trouvé"
                emptyMessage="Ajustez vos filtres ou créez le premier bus du référentiel."
                emptyIcon={BusFront}
                pagination={pagination}
            />

            <BusFormDialog
                open={open}
                bus={selected}
                statuses={statuses}
                models={models}
                compositions={compositions}
                amenities={amenities}
                onOpenChange={setOpen}
            />
        </>
    );
}

FleetPage.layout = {
    breadcrumbs: [
        { title: 'Flotte', href: '/fleet' } satisfies BreadcrumbItem,
    ],
};
