import { Head } from '@inertiajs/react';
import { ArrowLeft, Users } from 'lucide-react';
import { useState } from 'react';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import type { ManifestRow } from '@/components/manifest/manifest-types';
import {
    JourneyStatusBadge,
    TicketStatusBadge,
} from '@/components/manifest/status-badges';
import { TicketDetailDialog } from '@/components/manifest/ticket-detail-dialog';
import departures from '@/routes/departures';
import type { BreadcrumbItem } from '@/types';

type ReservationRef = {
    uuid: string;
    status: string;
    channel: string | null;
    isGroupPurchase: boolean;
    passengerCount: number;
};

type PageProps = {
    reservation: ReservationRef;
    passengers: ManifestRow[];
    permissions: { canViewTickets: boolean };
};

export default function ManifestReservationPage({
    reservation,
    passengers,
    permissions,
}: PageProps) {
    const [detailRow, setDetailRow] = useState<ManifestRow | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);

    const columns: TableColumn<ManifestRow>[] = [
        {
            key: 'passenger',
            header: 'Passager',
            render: (row) => (
                <strong className="block font-bold text-foreground">
                    {row.passenger
                        ? `${row.passenger.firstName} ${row.passenger.lastName}`
                        : 'Passager inconnu'}
                </strong>
            ),
        },
        {
            key: 'trip',
            header: 'Trajet',
            render: (row) => (
                <div>
                    <span className="block font-medium text-foreground">
                        {row.departure?.lineCode ?? '—'}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {row.originStop ?? '—'} → {row.destinationStop ?? '—'}
                    </span>
                </div>
            ),
        },
        {
            key: 'seat',
            header: 'Siège',
            render: (row) => (
                <span className="font-mono font-bold text-foreground">
                    {row.seatCode ?? '—'}
                </span>
            ),
        },
        {
            key: 'ticket',
            header: 'Billet',
            render: (row) =>
                permissions.canViewTickets ? (
                    <TicketStatusBadge
                        status={row.ticket?.status ?? null}
                        statusLabel={row.ticket?.statusLabel ?? null}
                    />
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
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
            <Head title="Réservation" />

            <div className="kora-page-head mb-6">
                <a
                    href={departures.index.url()}
                    className="mb-2 inline-flex items-center gap-1 text-[0.6875rem] text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3" />
                    Départs
                </a>
                <h1 className="flex items-center gap-2 text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                    Réservation
                    {reservation.isGroupPurchase && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-gold/15 px-2.5 py-1 text-[0.625rem] font-bold text-[var(--kora)]">
                            <Users className="size-3" />
                            Achat groupé — {reservation.passengerCount}{' '}
                            passagers
                        </span>
                    )}
                </h1>
                <p className="mt-1.5 font-mono text-[0.8125rem] text-muted-foreground">
                    {reservation.uuid}
                </p>
            </div>

            <KoraTable
                title="Passagers de la réservation"
                subtitle={`${passengers.length} passager${passengers.length > 1 ? 's' : ''}`}
                icon={Users}
                columns={columns}
                data={passengers}
                rowKey={(row) => row.segmentUuid ?? row.passenger?.uuid ?? ''}
                emptyTitle="Aucun passager"
                emptyMessage="Cette réservation n'a pas de passager associé."
                emptyIcon={Users}
                onRowClick={(row) => {
                    setDetailRow(row);
                    setDialogOpen(true);
                }}
            />

            <TicketDetailDialog
                row={detailRow}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />
        </>
    );
}

ManifestReservationPage.layout = {
    breadcrumbs: [
        { title: 'Départs', href: '/departures' } satisfies BreadcrumbItem,
    ],
};
