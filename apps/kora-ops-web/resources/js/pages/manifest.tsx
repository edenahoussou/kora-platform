import { Head, router, usePoll } from '@inertiajs/react';
import { ClipboardList, Users } from 'lucide-react';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import manifest from '@/routes/manifest';
import type { BreadcrumbItem } from '@/types';

type ManifestDepartureRow = {
    uuid: string;
    lineCode: string | null;
    lineName: string | null;
    busRegistration: string | null;
    departureDate: string | null;
    departureTime: string | null;
    status: string;
    passengerCount: number;
};

type PageProps = {
    departures: {
        data: ManifestDepartureRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
};

const statusLabels: Record<string, string> = {
    scheduled: 'Programmé',
    boarding: 'Embarquement',
    departed: 'En route',
    arrived: 'Arrivé',
    cancelled: 'Annulé',
};

const statusTones: Record<
    string,
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive'
> = {
    scheduled: 'navy',
    boarding: 'gold',
    departed: 'blue',
    arrived: 'navy',
    cancelled: 'destructive',
};

export default function ManifestPage({
    departures: departureData,
    statuses,
}: PageProps) {
    // Statuts des départs à jour quand le convoyeur les fait avancer (MEZ-98).
    usePoll(30000, { only: ['departures'] });

    const filters: FilterConfig[] = useMemo(
        () => [
            { name: 'date', label: 'Date', type: 'date', options: [] },
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
        currentPage: departureData.current_page,
        lastPage: departureData.last_page,
        perPage: departureData.per_page,
        total: departureData.total,
        from: departureData.from,
        to: departureData.to,
        baseUrl: '/manifest',
    };

    const columns: TableColumn<ManifestDepartureRow>[] = [
        {
            key: 'departure',
            header: 'Départ',
            render: (row) => (
                <div>
                    <strong className="block font-bold text-foreground">
                        {row.lineName ?? '—'}
                    </strong>
                    <span className="text-xs text-muted-foreground">
                        {row.lineCode} · {row.busRegistration ?? '—'}
                    </span>
                </div>
            ),
        },
        {
            key: 'when',
            header: 'Date / heure',
            render: (row) => (
                <span className="font-medium text-foreground">
                    {row.departureDate} · {row.departureTime}
                </span>
            ),
        },
        {
            key: 'passengers',
            header: 'Passagers',
            render: (row) => (
                <span className="inline-flex items-center gap-1.5 font-medium text-foreground">
                    <Users className="size-3.5 text-muted-foreground" />
                    {row.passengerCount}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (row) => (
                <KoraStatusBadge
                    label={statusLabels[row.status] ?? row.status}
                    tone={statusTones[row.status] ?? 'muted'}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Manifestes" />

            <div className="mb-6">
                <Heading
                    title="Manifestes"
                    description="Choisissez un départ pour consulter la liste de ses passagers et leurs billets."
                />
            </div>

            <KoraFilterBar
                route="/manifest"
                searchPlaceholder="Rechercher par ligne ou immatriculation…"
                filters={filters}
            />

            <div className="mt-5">
                <KoraTable
                    title="Départs"
                    subtitle={`${departureData.total} départ${departureData.total > 1 ? 's' : ''} trouvé${departureData.total > 1 ? 's' : ''}`}
                    icon={ClipboardList}
                    columns={columns}
                    data={departureData.data}
                    rowKey={(row) => row.uuid}
                    emptyTitle="Aucun départ trouvé"
                    emptyMessage="Ajustez vos filtres pour retrouver le départ recherché."
                    emptyIcon={ClipboardList}
                    pagination={pagination}
                    onRowClick={(row) =>
                        router.get(manifest.show.url({ departure: row.uuid }))
                    }
                />
            </div>
        </>
    );
}

ManifestPage.layout = {
    breadcrumbs: [
        { title: 'Manifestes', href: '/manifest' } satisfies BreadcrumbItem,
    ],
};
