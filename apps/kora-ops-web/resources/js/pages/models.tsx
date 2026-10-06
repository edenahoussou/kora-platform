import { Head, router } from '@inertiajs/react';
import {
    Archive,
    Armchair,
    ArrowLeft,
    BusFront,
    CheckCircle2,
    Edit3,
    Layers,
    MoreHorizontal,
    Plus,
    RotateCcw,
    Sparkles,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { ModelFormDialog } from '@/components/fleet/model-form-dialog';
import type { ModelRecord } from '@/components/fleet/model-form-dialog';
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
    models: {
        data: ModelRecord[];
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
    active: 'Actif',
    archived: 'Archivé',
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

export default function ModelsPage({
    models,
    statuses,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<ModelRecord | null>(null);

    const stats = useMemo(() => {
        const data = models.data;
        const active = data.filter((m) => m.status === 'active').length;
        const archived = data.filter((m) => m.status === 'archived').length;
        const attachedBuses = data.reduce(
            (acc, m) => acc + (m.busesCount || 0),
            0,
        );

        return {
            total: models.total,
            active,
            archived,
            attachedBuses,
        };
    }, [models]);

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
        currentPage: models.current_page,
        lastPage: models.last_page,
        perPage: models.per_page,
        total: models.total,
        from: models.from,
        to: models.to,
        baseUrl: '/models',
    };

    const changeStatus = (model: ModelRecord, status: string) => {
        router.patch(
            modelsRoutes.status.url({ model: model.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const columns: TableColumn<ModelRecord>[] = [
        {
            key: 'model',
            header: 'Modèle',
            render: (model) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-gold/12 text-[var(--kora)]">
                        <Layers className="size-4" />
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {model.brand} {model.name}
                        </strong>
                        <span className="text-xs font-semibold text-muted-foreground">
                            {model.brand}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'seats',
            header: 'Gabarit',
            render: (model) => (
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-muted/80 px-2.5 py-1 text-xs font-semibold text-foreground ring-1 ring-border">
                        <Armchair className="size-3.5 text-gold" />
                        {model.defaultSeatCount} places
                    </span>
                </div>
            ),
        },
        {
            key: 'buses',
            header: 'Bus rattachés',
            render: (model) => (
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-muted/80 px-2.5 py-1 text-xs font-semibold text-foreground ring-1 ring-border">
                        <BusFront className="size-3.5 text-gold" />
                        {model.busesCount} bus
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (model) => (
                <KoraStatusBadge
                    label={statusLabels[model.status] ?? model.status}
                    tone={statusTones[model.status] ?? 'gold'}
                />
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (model) => (
                <div
                    className="flex justify-end"
                    onClick={(e) => e.stopPropagation()}
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Actions pour ${model.brand} ${model.name}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {permissions.canUpdate && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setSelected(model);
                                        setOpen(true);
                                    }}
                                >
                                    <Edit3 className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                            )}
                            {permissions.canDeactivate &&
                                model.status === 'active' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(model, 'archived')
                                        }
                                    >
                                        <Archive className="size-4" />
                                        Archiver
                                    </DropdownMenuItem>
                                )}
                            {permissions.canDeactivate &&
                                model.status === 'archived' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(model, 'active')
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
            <Head title="Modèles de bus" />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Ressources / Modèles
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Modèles de bus
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Gérez le référentiel des marques et modèles de véhicules
                        exploités par le réseau KORA.
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
                            Nouveau modèle
                        </button>
                    )}
                </div>
            </div>

            <section className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <KpiCard
                    icon={Layers}
                    label="Modèles"
                    hint="Référentiel total"
                    value={stats.total}
                    tone="navy"
                />
                <KpiCard
                    icon={CheckCircle2}
                    label="Actifs"
                    hint="Sélectionnables"
                    value={stats.active}
                    tone="green"
                />
                <KpiCard
                    icon={Archive}
                    label="Archivés"
                    hint="Hors référentiel actif"
                    value={stats.archived}
                    tone="muted"
                />
                <KpiCard
                    icon={BusFront}
                    label="Affectations"
                    hint="Bus rattachés"
                    value={stats.attachedBuses}
                    tone="gold"
                />
            </section>

            <KoraFilterBar
                route="/models"
                searchPlaceholder="Rechercher par marque ou modèle…"
                filters={filters}
            />

            <KoraTable
                title="Référentiel des modèles"
                subtitle={`${models.total} modèle${models.total > 1 ? 's' : ''} au total`}
                icon={Layers}
                columns={columns}
                data={models.data}
                rowKey={(model) => model.uuid}
                emptyTitle="Aucun modèle trouvé"
                emptyMessage="Ajustez vos filtres ou créez le premier modèle du référentiel."
                emptyIcon={Layers}
                pagination={pagination}
            />

            <ModelFormDialog
                open={open}
                model={selected}
                onOpenChange={setOpen}
            />
        </>
    );
}

ModelsPage.layout = {
    breadcrumbs: [
        { title: 'Flotte', href: '/fleet' } satisfies BreadcrumbItem,
        {
            title: 'Modèles',
            href: '/models',
        } satisfies BreadcrumbItem,
    ],
};
