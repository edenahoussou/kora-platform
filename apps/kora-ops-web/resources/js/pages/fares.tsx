import { Head, router } from '@inertiajs/react';
import {
    Archive,
    CheckCircle,
    Edit3,
    MoreHorizontal,
    Plus,
    Tag,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { FareFormDialog } from '@/components/fares/fare-form-dialog';
import type { FareRecord } from '@/components/fares/fare-form-dialog';
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
import fares from '@/routes/fares';
import type { BreadcrumbItem } from '@/types';

type ServiceClassOption = {
    uuid: string;
    code: string;
    name: string;
};

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
    serviceClasses: ServiceClassOption[];
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

function formatMoney(amount: number, currency: string): string {
    const formatted = new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    }).format(amount);

    return `${formatted} ${currency}`;
}

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

export default function FaresPage({
    fares,
    statuses,
    serviceClasses,
    lines,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<FareRecord | null>(null);

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
            {
                name: 'service',
                label: 'Service',
                placeholder: 'Tous les services',
                options: serviceClasses.map((cls) => ({
                    value: cls.code,
                    label: cls.name,
                })),
            },
        ],
        [statuses, serviceClasses, lines],
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
            fares.status.url({ fare: fare.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const columns: TableColumn<FareRecord>[] = [
        {
            key: 'line',
            header: 'Ligne',
            render: (fare) => (
                <div>
                    <span className="cell-main block font-bold text-foreground">
                        {fare.lineCode}
                    </span>
                    <span className="cell-sub block text-xs text-muted-foreground">
                        {fare.lineName}
                    </span>
                </div>
            ),
        },
        {
            key: 'segment',
            header: 'Segment O/D',
            render: (fare) => (
                <span className="font-medium text-foreground">
                    {fare.originName} → {fare.destinationName}
                </span>
            ),
        },
        {
            key: 'service',
            header: 'Service',
            render: (fare) => (
                <span className="font-medium text-foreground">
                    {fare.serviceClassName}
                </span>
            ),
        },
        {
            key: 'price',
            header: 'Prix',
            render: (fare) => (
                <span className="font-bold text-foreground">
                    {formatMoney(fare.baseAmount, fare.currency)}
                </span>
            ),
        },
        {
            key: 'period',
            header: 'Période',
            render: (fare) => (
                <span className="text-xs text-muted-foreground">
                    {formatDate(fare.effectiveFrom)}
                    {fare.effectiveUntil
                        ? ` → ${formatDate(fare.effectiveUntil)}`
                        : ' → indéterminée'}
                </span>
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
                <div className="flex justify-end">
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
                                        <CheckCircle className="size-4" />
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
                    <Button
                        onClick={() => {
                            setSelected(null);
                            setOpen(true);
                        }}
                        className="h-11 rounded-[0.75rem] bg-gold px-5 font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                    >
                        <Plus className="size-4" />
                        Nouvelle grille
                    </Button>
                )}
            </div>
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
                emptyTitle="Aucun tarif trouvé"
                emptyMessage="Ajustez vos filtres ou créez la première grille tarifaire."
                emptyIcon={Tag}
                pagination={pagination}
            />
            <FareFormDialog
                open={open}
                fare={selected}
                lines={lines}
                serviceClasses={serviceClasses}
                onOpenChange={setOpen}
            />
        </>
    );
}

FaresPage.layout = {
    breadcrumbs: [
        { title: 'Tarifs', href: '/fares' } satisfies BreadcrumbItem,
    ],
};
