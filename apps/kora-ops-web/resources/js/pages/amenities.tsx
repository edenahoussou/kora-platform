import { Head, router } from '@inertiajs/react';
import {
    Archive,
    ArrowLeft,
    CheckCircle,
    Edit3,
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
import amenitiesRoutes from '@/routes/amenities';
import fleet from '@/routes/fleet';
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

export default function FleetAmenitiesPage({
    amenities,
    statuses,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<AmenityRecord | null>(null);

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
                <span className="font-medium text-foreground">
                    {amenity.busesCount}
                </span>
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
                    <div className="kora-breadcrumb mb-2 flex items-center gap-2 text-[0.6875rem] text-muted-foreground">
                        <button
                            onClick={() => router.get(fleet.index.url())}
                            className="inline-flex items-center gap-1 hover:text-foreground"
                        >
                            <ArrowLeft className="size-3" />
                            Flotte
                        </button>
                        <span>/ Commodités</span>
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Commodités
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Le référentiel des équipements réutilisables (Wi-Fi,
                        climatisation…) rattachables à n'importe quel bus.
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
                        Nouvelle commodité
                    </Button>
                )}
            </div>
            <KoraFilterBar
                route="/amenities"
                searchPlaceholder="Rechercher par code ou libellé…"
                filters={filters}
            />
            <KoraTable
                title="Référentiel des commodités"
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

FleetAmenitiesPage.layout = {
    breadcrumbs: [
        { title: 'Flotte', href: '/fleet' } satisfies BreadcrumbItem,
        {
            title: 'Commodités',
            href: '/amenities',
        } satisfies BreadcrumbItem,
    ],
};
