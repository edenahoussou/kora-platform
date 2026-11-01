import { Head, router } from '@inertiajs/react';
import {
    Archive,
    ArrowRight,
    Calendar,
    CheckCircle2,
    Clock,
    Edit3,
    Eye,
    MoreHorizontal,
    Plus,
    Route,
    Tag,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { FareFormDialog } from '@/components/fares/fare-form-dialog';
import type { FareRecord } from '@/components/fares/fare-form-dialog';
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
import faresRoutes from '@/routes/fares';
import type { BreadcrumbItem } from '@/types';

type StopOption = {
    uuid: string;
    name: string;
    stopOrder: number;
};

type LineOption = {
    uuid: string;
    code: string;
    name: string;
    stops: StopOption[];
};

type PageProps = {
    fares: {
        data: FareRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
    lines: LineOption[];
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
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

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

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

export default function FaresPage({
    fares,
    statuses,
    lines,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<FareRecord | null>(null);

    const stats = useMemo(() => {
        const data = fares.data;
        const active = data.filter((f) => f.status === 'active').length;
        const draft = data.filter((f) => f.status === 'draft').length;

        return {
            total: fares.total,
            active,
            draft,
            linesCount: lines.length,
        };
    }, [fares, lines]);

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
            {
                name: 'line',
                label: 'Ligne',
                placeholder: 'Tout le réseau',
                options: lines.map((line) => ({
                    value: line.uuid,
                    label: `${line.code} · ${line.name}`,
                })),
            },
        ],
        [statuses, lines],
    );

    const pagination: PaginationMeta = {
        currentPage: fares.current_page,
        lastPage: fares.last_page,
        perPage: fares.per_page,
        total: fares.total,
        from: fares.from,
        to: fares.to,
        baseUrl: '/fares',
    };

    const changeStatus = (fare: FareRecord, status: string) => {
        router.patch(
            faresRoutes.status.url({ fare: fare.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const columns: TableColumn<FareRecord>[] = [
        {
            key: 'line',
            header: 'Ligne',
            render: (fare) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-gold/12 font-mono text-[0.6875rem] font-black text-[var(--kora)]">
                        {fare.lineCode.slice(0, 4)}
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {fare.lineCode}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {fare.lineName}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'segment',
            header: 'Segment O/D',
            render: (fare) => (
                <div className="flex items-center gap-1.5 font-medium text-foreground">
                    <span className="text-foreground">{fare.originName}</span>
                    <ArrowRight className="size-3 text-muted-foreground" />
                    <span className="text-foreground">
                        {fare.destinationName}
                    </span>
                </div>
            ),
        },
        {
            key: 'price',
            header: 'Prix unitaire',
            render: (fare) => (
                <div className="flex items-baseline gap-1">
                    <span className="font-mono text-sm font-black text-foreground">
                        {new Intl.NumberFormat('fr-FR', {
                            maximumFractionDigits: 0,
                        }).format(fare.baseAmount)}
                    </span>
                    <span className="text-[10px] font-bold text-muted-foreground">
                        {fare.currency}
                    </span>
                </div>
            ),
        },
        {
            key: 'period',
            header: 'Période d\u2019effet',
            render: (fare) => (
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Calendar className="size-3 text-muted-foreground" />
                    <span>
                        {formatDate(fare.effectiveFrom)}
                        {fare.effectiveUntil
                            ? ` → ${formatDate(fare.effectiveUntil)}`
                            : ' → indéterminée'}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (fare) => (
                <KoraStatusBadge
                    label={statusLabels[fare.status] ?? fare.status}
                    tone={statusTones[fare.status] ?? 'gold'}
                />
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (fare) => (
                <div
                    className="flex justify-end"
                    onClick={(e) => e.stopPropagation()}
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Actions pour ${fare.lineCode} ${fare.originName} → ${fare.destinationName}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() =>
                                    router.get(
                                        faresRoutes.show.url({
                                            fare: fare.uuid,
                                        }),
                                    )
                                }
                            >
                                <Eye className="size-4" />
                                Voir le détail
                            </DropdownMenuItem>
                            {permissions.canUpdate && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setSelected(fare);
                                        setOpen(true);
                                    }}
                                >
                                    <Edit3 className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                            )}
                            {permissions.canUpdate &&
                                fare.status === 'draft' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(fare, 'active')
                                        }
                                    >
                                        <CheckCircle2 className="size-4" />
                                        Activer
                                    </DropdownMenuItem>
                                )}
                            {permissions.canUpdate &&
                                fare.status === 'active' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(fare, 'archived')
                                        }
                                    >
                                        <Archive className="size-4" />
                                        Archiver
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
            <Head title="Tarifs" />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Exploitation / Tarifs
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Tarifs
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Administrez les grilles tarifaires et leurs périodes
                        d'application.
                    </p>
                </div>
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
                        Nouvelle grille
                    </button>
                )}
            </div>

            <section className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <KpiCard
                    icon={Tag}
                    label="Grilles"
                    hint="Référentiel tarifaire"
                    value={stats.total}
                    tone="navy"
                />
                <KpiCard
                    icon={CheckCircle2}
                    label="Actives"
                    hint="En exploitation"
                    value={stats.active}
                    tone="green"
                />
                <KpiCard
                    icon={Clock}
                    label="En préparation"
                    hint="Brouillons"
                    value={stats.draft}
                    tone="gold"
                />
                <KpiCard
                    icon={Route}
                    label="Lignes"
                    hint="Réseau couvert"
                    value={stats.linesCount}
                    tone="blue"
                />
            </section>

            <KoraFilterBar
                route="/fares"
                searchPlaceholder="Rechercher par ligne, arrêt ou ville…"
                filters={filters}
            />

            <KoraTable
                title="Grilles tarifaires"
                subtitle={`${fares.total} tarif${fares.total > 1 ? 's' : ''} au total`}
                icon={Tag}
                columns={columns}
                data={fares.data}
                rowKey={(fare) => fare.uuid}
                onRowClick={(fare) =>
                    router.get(faresRoutes.show.url({ fare: fare.uuid }))
                }
                emptyTitle="Aucun tarif trouvé"
                emptyMessage="Ajustez vos filtres ou créez la première grille tarifaire."
                emptyIcon={Tag}
                pagination={pagination}
            />

            <FareFormDialog
                open={open}
                fare={selected}
                lines={lines}
                onOpenChange={setOpen}
            />
        </>
    );
}

FaresPage.layout = {
    breadcrumbs: [{ title: 'Tarifs', href: '/fares' } satisfies BreadcrumbItem],
};
