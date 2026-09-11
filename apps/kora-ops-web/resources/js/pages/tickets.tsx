import { Head } from '@inertiajs/react';
import { Ticket as TicketIcon, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import type { ManifestRow } from '@/components/manifest/manifest-types';
import {
    JourneyStatusBadge,
    TicketStatusBadge,
} from '@/components/manifest/status-badges';
import { TicketDetailDialog } from '@/components/manifest/ticket-detail-dialog';

type PageProps = {
    tickets: {
        data: ManifestRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
};

export default function TicketsPage({
    tickets: ticketData,
    statuses,
}: PageProps) {
    const [detailRow, setDetailRow] = useState<ManifestRow | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);

    const filters: FilterConfig[] = useMemo(
        () => [
            {
                name: 'status',
                label: 'Statut',
                placeholder: 'Tous les statuts',
                options: Object.entries(statuses).map(([value, label]) => ({
                    value,
                    label,
                })),
            },
        ],
        [statuses],
    );

    const pagination: PaginationMeta = {
        currentPage: ticketData.current_page,
        lastPage: ticketData.last_page,
        perPage: ticketData.per_page,
        total: ticketData.total,
        from: ticketData.from,
        to: ticketData.to,
        baseUrl: '/tickets',
    };

    const columns: TableColumn<ManifestRow>[] = [
        {
            key: 'ticket',
            header: 'Billet',
            render: (row) => (
                <div>
                    <span className="block font-mono text-xs font-bold text-foreground">
                        {row.ticket?.shortCode ?? '—'}
                    </span>
                    <TicketStatusBadge
                        status={row.ticket?.status ?? null}
                        statusLabel={row.ticket?.statusLabel ?? null}
                    />
                </div>
            ),
        },
        {
            key: 'passenger',
            header: 'Passager',
            render: (row) => (
                <div>
                    <strong className="block font-bold text-foreground">
                        {row.passenger
                            ? `${row.passenger.firstName} ${row.passenger.lastName}`
                            : 'Passager inconnu'}
                    </strong>
                    {row.passenger?.phone && (
                        <span className="text-xs text-muted-foreground">
                            {row.passenger.phone}
                        </span>
                    )}
                    {row.reservation?.isGroupPurchase && (
                        <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-gold/15 px-2 py-0.5 text-[0.5625rem] font-bold text-[var(--kora)]">
                            <Users className="size-2.5" />
                            Groupe
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'departure',
            header: 'Départ',
            render: (row) => (
                <span className="text-foreground">
                    {row.departure
                        ? `${row.departure.lineCode} · ${row.departure.departureDate} ${row.departure.departureTime}`
                        : '—'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut passager',
            render: (row) => <JourneyStatusBadge status={row.journeyStatus} />,
        },
    ];

    return (
        <>
            <Head title="Billets" />

            <div className="mb-6">
                <Heading
                    title="Billets"
                    description="Recherchez, contrôlez et consultez chaque billet selon son canal de vente."
                />
            </div>

            <KoraFilterBar
                route="/tickets"
                searchPlaceholder="Rechercher par référence, nom ou téléphone…"
                filters={filters}
            />

            <div className="mt-5">
                <KoraTable
                    title="Billets"
                    subtitle={`${ticketData.total} billet${ticketData.total > 1 ? 's' : ''} trouvé${ticketData.total > 1 ? 's' : ''}`}
                    icon={TicketIcon}
                    columns={columns}
                    data={ticketData.data}
                    rowKey={(row) => row.ticket?.uuid ?? row.segmentUuid ?? ''}
                    emptyTitle="Aucun billet trouvé"
                    emptyMessage="Ajustez votre recherche pour retrouver le billet."
                    emptyIcon={TicketIcon}
                    pagination={pagination}
                    onRowClick={(row) => {
                        setDetailRow(row);
                        setDialogOpen(true);
                    }}
                />
            </div>

            <TicketDetailDialog
                row={detailRow}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />
        </>
    );
}
