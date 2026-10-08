import { Head, router } from '@inertiajs/react';
import {
    Archive,
    ArrowLeft,
    BusFront,
    CheckCircle,
    CheckCircle2,
    Edit3,
    Layers,
    MoreHorizontal,
    Plus,
    Sparkles,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { AmenityFormDialog } from '@/components/fleet/amenity-form-dialog';
import type { AmenityRecord } from '@/components/fleet/amenity-form-dialog';
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

type PageProps = {
    amenities: {
        data: AmenityRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
        canDeactivate: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    archived: 'Archivée',
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    active: 'navy',
    archived: 'muted',
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

export default function AmenitiesPage({
    amenities,
    statuses,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<AmenityRecord | null>(null);

    const stats = useMemo(() => {
        const data = amenities.data;
        const active = data.filter((a) => a.status === 'active').length;
        const archived = data.filter((a) => a.status === 'archived').length;
        const equippedBuses = data.reduce(
            (acc, a) => acc + (a.busesCount || 0),
            0,
        );

        return {
            total: amenities.total,
            active,
            archived,
            equippedBuses,
        };
    }, [amenities]);

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
        currentPage: amenities.current_page,
        lastPage: amenities.last_page,
        perPage: amenities.per_page,
        total: amenities.total,
        from: amenities.from,
        to: amenities.to,
        baseUrl: '/amenities',
    };

    const changeStatus = (amenity: AmenityRecord, status: string) => {
        router.patch(
            amenitiesRoutes.status.url({ amenity: amenity.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const columns: TableColumn<AmenityRecord>[] = [
        {
            key: 'amenity',
            header: 'Commodité',
            render: (amenity) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-gold/12 text-[var(--kora)]">
                        <Sparkles className="size-4" />
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {amenity.label}
                        </strong>
                        <span className="font-mono text-xs text-muted-foreground">
                            {amenity.code}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'buses',
            header: 'Bus rattachés',
            render: (amenity) => (
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-muted/80 px-2.5 py-1 text-xs font-semibold text-foreground ring-1 ring-border">
                        <BusFront className="size-3.5 text-gold" />
                        {amenity.busesCount} bus
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (amenity) => (
                <KoraStatusBadge
                    label={statusLabels[amenity.status] ?? amenity.status}
                    tone={statusTones[amenity.status] ?? 'gold'}
                />
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (amenity) => (
                <div
                    className="flex justify-end"
                    onClick={(e) => e.stopPropagation()}
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Actions pour ${amenity.label}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {permissions.canUpdate && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setSelected(amenity);
                                        setOpen(true);
                                    }}
                                >
                                    <Edit3 className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                            )}
                            {permissions.canDeactivate &&
                                amenity.status === 'active' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(amenity, 'archived')
                                        }
                                    >
                                        <Archive className="size-4" />
                                        Archiver
                                    </DropdownMenuItem>
                                )}
                            {permissions.canDeactivate &&
                                amenity.status === 'archived' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(amenity, 'active')
                                        }
                                    >
                                        <CheckCircle className="size-4" />
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
            <Head title="Commodités" />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Flotte / Commodités
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Commodités de la flotte
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Gérez le catalogue réutilisable des équipements
                        rattachables aux bus du réseau KORA.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        onClick={() => router.get(fleet.index.url())}
                        className="btn btn-ghost"
                    >
                        <ArrowLeft className="size-3.5" />
                        Flotte de bus
                    </button>
                    <button
                        type="button"
                        onClick={() => router.get(modelsRoutes.index.url())}
                        className="btn btn-ghost"
                    >
                        <Layers className="size-3.5 text-gold" />
                        Modèles
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
                            Nouvelle commodité
                        </button>
                    )}
                </div>
            </div>

            <section className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <KpiCard
                    icon={Sparkles}
                    label="Commodités"
                    hint="Catalogue total"
                    value={stats.total}
                    tone="navy"
                />
                <KpiCard
                    icon={CheckCircle2}
                    label="Actives"
                    hint="Disponibles pour bus"
                    value={stats.active}
                    tone="green"
                />
                <KpiCard
                    icon={Archive}
                    label="Archivées"
                    hint="Hors catalogue actif"
                    value={stats.archived}
                    tone="muted"
                />
                <KpiCard
                    icon={BusFront}
                    label="Affectations"
                    hint="Bus équipés"
                    value={stats.equippedBuses}
                    tone="gold"
                />
            </section>

            <KoraFilterBar
                route="/amenities"
                searchPlaceholder="Rechercher par code ou libellé…"
                filters={filters}
            />

            <KoraTable
                title="Catalogue des commodités"
                subtitle={`${amenities.total} commodité${amenities.total > 1 ? 's' : ''} au total`}
                icon={Sparkles}
                columns={columns}
                data={amenities.data}
                rowKey={(amenity) => amenity.uuid}
                emptyTitle="Aucune commodité trouvée"
                emptyMessage="Ajustez vos filtres ou créez la première commodité du référentiel."
                emptyIcon={Sparkles}
                pagination={pagination}
            />

            <AmenityFormDialog
                open={open}
                amenity={selected}
                onOpenChange={setOpen}
            />
        </>
    );
}

AmenitiesPage.layout = {
    breadcrumbs: [
        { title: 'Flotte', href: '/fleet' } satisfies BreadcrumbItem,
        {
            title: 'Commodités',
            href: '/amenities',
        } satisfies BreadcrumbItem,
    ],
};
