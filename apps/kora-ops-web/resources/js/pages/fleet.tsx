import { Head, router } from '@inertiajs/react';
import {
    BusFront,
    Edit3,
    MoreHorizontal,
    Plus,
    RotateCcw,
    Wrench,
    Archive,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { BusFormDialog } from '@/components/buses/bus-form-dialog';
import type { BusRecord } from '@/components/buses/bus-form-dialog';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import fleet from '@/routes/fleet';
import type { BreadcrumbItem } from '@/types';

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
    agencies: { id: number; name: string }[];
    models: { id: number; label: string }[];
    compositions: { id: number; label: string; seatCount: number }[];
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
const statusStyles: Record<string, string> = {
    active: 'bg-emerald-500/10 text-emerald-700',
    draft: 'bg-gold/15 text-[var(--kora)]',
    maintenance: 'bg-orange-500/10 text-orange-700',
    retired: 'bg-muted text-muted-foreground',
};

export default function FleetPage({
    buses,
    statuses,
    agencies,
    models,
    compositions,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<BusRecord | null>(null);
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
                name: 'agency_id',
                label: 'Agence',
                placeholder: 'Toutes les agences',
                options: agencies.map((agency) => ({
                    value: String(agency.id),
                    label: agency.name,
                })),
            },
        ],
        [statuses, agencies],
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
                        <span className="text-xs text-muted-foreground">
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
                    <span className="text-xs text-muted-foreground">
                        {bus.seatCount} places
                    </span>
                </div>
            ),
        },
        {
            key: 'agency',
            header: 'Agence',
            render: (bus) => (
                <span className="font-medium text-foreground">
                    {bus.agencyName ?? 'Non rattaché'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (bus) => (
                <span
                    className={`inline-flex rounded-full px-2.5 py-1 text-[0.6875rem] font-bold ${statusStyles[bus.status] ?? statusStyles.draft}`}
                >
                    {statusLabels[bus.status] ?? bus.status}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (bus) =>
                (permissions.canUpdate || permissions.canDeactivate) && (
                    <div className="flex justify-end">
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
                        Flotte
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Gérez les bus, leur composition et leur rattachement
                        agence.
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
                        Nouveau bus
                    </Button>
                )}
            </div>
            <KoraFilterBar
                route="/fleet"
                searchPlaceholder="Rechercher par immatriculation, modèle ou agence…"
                filters={filters}
            />
            <KoraTable
                title="Référentiel des bus"
                subtitle={`${buses.total} bus au total`}
                icon={BusFront}
                columns={columns}
                data={buses.data}
                rowKey={(bus) => bus.uuid}
                emptyTitle="Aucun bus trouvé"
                emptyMessage="Ajustez vos filtres ou créez le premier bus du référentiel."
                emptyIcon={BusFront}
                pagination={pagination}
            />
            <BusFormDialog
                open={open}
                bus={selected}
                statuses={statuses}
                agencies={agencies}
                models={models}
                compositions={compositions}
                onOpenChange={setOpen}
            />
        </>
    );
}
FleetPage.layout = {
    breadcrumbs: [{ title: 'Flotte', href: '/fleet' } satisfies BreadcrumbItem],
};
