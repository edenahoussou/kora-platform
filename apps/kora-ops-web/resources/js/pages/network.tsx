import { Head, router } from '@inertiajs/react';
import {
    Archive,
    Edit3,
    Eye,
    MapPin,
    MoreHorizontal,
    Plus,
    RotateCcw,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import type { LineRecord } from '@/components/lines/line-form-dialog';
import { LineFormDialog } from '@/components/lines/line-form-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import network from '@/routes/network';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    lines: {
        data: LineRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
    types: Record<string, string>;
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
        canDeactivate: boolean;
        canManageStops: boolean;
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
const typeLabels: Record<string, string> = {
    nationale: 'Nationale',
    internationale: 'Internationale',
    regionale: 'Régionale',
};

export default function NetworkPage({
    lines,
    statuses,
    types,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<LineRecord | null>(null);

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
                name: 'type',
                label: 'Type',
                placeholder: 'Tout le réseau',
                options: Object.entries(types).map(([value, label]) => ({
                    value,
                    label: typeLabels[value] ?? label,
                })),
            },
        ],
        [statuses, types],
    );

    const pagination: PaginationMeta = {
        currentPage: lines.current_page,
        lastPage: lines.last_page,
        perPage: lines.per_page,
        total: lines.total,
        from: lines.from,
        to: lines.to,
        baseUrl: '/network',
    };

    const changeStatus = (line: LineRecord, status: string) => {
        if (
            window.confirm(
                'Voulez-vous vraiment modifier le statut de cette ligne ?',
            )
        ) {
            router.patch(
                network.status.url({ line: line.uuid }),
                { status },
                { preserveScroll: true },
            );
        }
    };

    const columns: TableColumn<LineRecord>[] = [
        {
            key: 'line',
            header: 'Ligne',
            render: (line) => (
                <div>
                    <span className="cell-main block font-bold text-foreground">
                        {line.code} {typeLabels[line.type] ?? ''}
                    </span>
                    <span className="cell-sub block text-xs text-muted-foreground">
                        {line.name}
                    </span>
                </div>
            ),
        },
        {
            key: 'itinerary',
            header: 'Itinéraire',
            render: (line) => (
                <span className="font-medium text-foreground">
                    {line.itinerary}
                </span>
            ),
        },
        {
            key: 'stops',
            header: 'Arrêts',
            render: (line) => (
                <span className="font-medium text-foreground">
                    {line.activeStopsCount}
                </span>
            ),
        },
        {
            key: 'segments',
            header: 'Segments',
            render: (line) => (
                <span className="font-medium text-foreground">
                    {line.segmentsCount}
                </span>
            ),
        },
        {
            key: 'distance',
            header: 'Distance',
            render: (line) => (
                <span className="font-medium text-foreground">
                    {line.distanceKm
                        ? `${line.distanceKm.toLocaleString('fr-FR')} km`
                        : '—'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (line) => (
                <KoraStatusBadge
                    label={statusLabels[line.status] ?? line.status}
                    tone={statusTones[line.status] ?? 'gold'}
                />
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (line) => (
                <div className="flex justify-end">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Actions pour ${line.code}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() =>
                                    router.get(
                                        network.show.url({ line: line.uuid }),
                                    )
                                }
                            >
                                <Eye className="size-4" />
                                Voir le détail
                            </DropdownMenuItem>
                            {permissions.canUpdate && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setSelected(line);
                                        setOpen(true);
                                    }}
                                >
                                    <Edit3 className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                            )}
                            {permissions.canDeactivate &&
                                line.status === 'active' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(line, 'archived')
                                        }
                                    >
                                        <Archive className="size-4" />
                                        Archiver
                                    </DropdownMenuItem>
                                )}
                            {permissions.canDeactivate &&
                                line.status !== 'active' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(line, 'active')
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
            <Head title="Lignes & arrêts" />
            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Exploitation / Lignes & arrêts
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Lignes & arrêts
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Structurez le réseau et ses points de desserte.
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
                        Nouvelle ligne
                    </Button>
                )}
            </div>
            <KoraFilterBar
                route="/network"
                searchPlaceholder="Rechercher par code ou nom de ligne…"
                filters={filters}
            />
            <KoraTable
                title="Référentiel des lignes"
                subtitle={`${lines.total} lignes au total`}
                icon={MapPin}
                columns={columns}
                data={lines.data}
                rowKey={(line) => line.uuid}
                emptyTitle="Aucune ligne trouvée"
                emptyMessage="Ajustez vos filtres ou créez la première ligne du réseau."
                emptyIcon={MapPin}
                pagination={pagination}
            />
            <LineFormDialog
                open={open}
                line={selected}
                statuses={statuses}
                onOpenChange={setOpen}
            />
        </>
    );
}

NetworkPage.layout = {
    breadcrumbs: [
        { title: 'Lignes & arrêts', href: '/network' } satisfies BreadcrumbItem,
    ],
};
