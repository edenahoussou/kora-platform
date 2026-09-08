import { Head } from '@inertiajs/react';
import {
    ArrowLeft,
    Ticket as TicketIcon,
    UserCheck,
    UserX,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import type { ManifestRow } from '@/components/manifest/manifest-types';
import {
    JourneyStatusBadge,
    TicketStatusBadge,
} from '@/components/manifest/status-badges';
import { TicketDetailDialog } from '@/components/manifest/ticket-detail-dialog';
import manifest from '@/routes/manifest';
import type { BreadcrumbItem } from '@/types';

type DepartureRef = {
    uuid: string;
    lineCode: string | null;
    lineName: string | null;
    busRegistration: string | null;
    departureDate: string | null;
    departureTime: string | null;
    status: string;
};

type Summary = {
    total: number;
    boarded: number;
    absent: number;
    ticketIssued: number;
    paymentPending: number;
    holdExpired: number;
    cancelled: number;
};

type PageProps = {
    departure: DepartureRef;
    passengers: ManifestRow[];
    summary: Summary;
    permissions: { canViewTickets: boolean };
};

export default function ManifestDetailPage({
    departure,
    passengers,
    summary,
    permissions,
}: PageProps) {
    const [detailRow, setDetailRow] = useState<ManifestRow | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);

    const columns: TableColumn<ManifestRow>[] = [
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
                    <div>
                        <TicketStatusBadge
                            status={row.ticket?.status ?? null}
                            statusLabel={row.ticket?.statusLabel ?? null}
                        />
                        {row.ticket && (
                            <span className="mt-1 block font-mono text-[0.625rem] text-muted-foreground">
                                {row.ticket.shortCode}
                            </span>
                        )}
                    </div>
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
            <Head title={`Manifeste — ${departure.lineName ?? ''}`} />

            <div className="kora-page-head mb-6">
                <a
                    href={manifest.index.url()}
                    className="mb-2 inline-flex items-center gap-1 text-[0.6875rem] text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3" />
                    Manifestes
                </a>
                <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                    {departure.lineName ?? 'Départ'}
                </h1>
                <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                    {departure.lineCode} · {departure.busRegistration ?? '—'} ·{' '}
                    {departure.departureDate} à {departure.departureTime}
                </p>
            </div>

            <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <SummaryCard
                    icon={Users}
                    label="Passagers"
                    value={summary.total}
                    tone="total"
                />
                <SummaryCard
                    icon={UserCheck}
                    label="Embarqués"
                    value={summary.boarded}
                    tone="boarded"
                />
                <SummaryCard
                    icon={UserX}
                    label="Absents"
                    value={summary.absent}
                    tone="absent"
                />
                <SummaryCard
                    icon={TicketIcon}
                    label="Billets émis"
                    value={summary.ticketIssued}
                    tone="tickets"
                />
                <SummaryCard
                    icon={Users}
                    label="Paiement en attente"
                    value={summary.paymentPending}
                    tone="pending"
                />
            </div>

            <KoraTable
                title="Passagers"
                subtitle={`${passengers.length} passager${passengers.length > 1 ? 's' : ''} sur ce départ`}
                icon={Users}
                columns={columns}
                data={passengers}
                rowKey={(row) => row.segmentUuid ?? row.passenger?.uuid ?? ''}
                emptyTitle="Aucun passager sur ce départ"
                emptyMessage="Aucune réservation n'est encore associée à ce départ."
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

type SummaryTone = 'total' | 'boarded' | 'absent' | 'tickets' | 'pending';

function SummaryCard({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: typeof Users;
    label: string;
    value: number;
    tone: SummaryTone;
}) {
    return (
        <div className={`manifest-summary-card manifest-summary-card-${tone}`}>
            <span className="manifest-summary-icon">
                <Icon className="size-4" />
            </span>
            <strong className="manifest-summary-value">{value}</strong>
            <span className="manifest-summary-label">{label}</span>
        </div>
    );
}

ManifestDetailPage.layout = {
    breadcrumbs: [
        { title: 'Manifestes', href: '/manifest' } satisfies BreadcrumbItem,
    ],
};
