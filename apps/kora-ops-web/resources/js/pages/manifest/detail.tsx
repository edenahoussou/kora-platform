import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleAlert,
    FileText,
    Globe2,
    MoveDown,
    MoveUp,
    Printer,
    ShieldCheck,
    Users,
    UserX,
} from 'lucide-react';
import { useState } from 'react';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import type { ManifestRow } from '@/components/manifest/manifest-types';
import { JourneyStatusBadge } from '@/components/manifest/status-badges';
import { TicketDetailDialog } from '@/components/manifest/ticket-detail-dialog';
import { PanelAlert } from '@/components/panel-alert';
import departures from '@/routes/departures';
import manifest from '@/routes/manifest';
import type { BreadcrumbItem } from '@/types';

type DepartureRef = {
    uuid: string;
    lineCode: string | null;
    lineName: string | null;
    lineType: string | null;
    lineTypeLabel: string | null;
    originCity: string | null;
    destinationCity: string | null;
    isReturn: boolean;
    busRegistration: string | null;
    seatCount: number | null;
    departureDate: string | null;
    departureTime: string | null;
    status: string;
    statusLabel: string | null;
};

type Summary = {
    total: number;
    boarded: number;
    absent: number;
    ticketIssued: number;
    paymentPending: number;
    holdExpired: number;
    cancelled: number;
    incompleteIdentities: number;
};

type PageProps = {
    departure: DepartureRef;
    passengers: ManifestRow[];
    summary: Summary;
    permissions: { canViewTickets: boolean };
};

function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    return new Date(`${date}T00:00:00`).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function ManifestDetailPage({
    departure,
    passengers,
    summary,
    permissions,
}: PageProps) {
    const [detailRow, setDetailRow] = useState<ManifestRow | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);

    const isInternational = departure.lineType === 'internationale';
    const identitiesComplete = summary.total - summary.incompleteIdentities;
    const routeLabel = `${departure.originCity ?? ''} → ${departure.destinationCity ?? ''}`;

    const columns: TableColumn<ManifestRow>[] = [
        {
            key: 'seat',
            header: 'Siège',
            render: (row) => (
                <span className="inline-flex min-w-8 items-center justify-center rounded-[0.4rem] border border-border/70 bg-mist px-2 py-1 font-mono text-[0.75rem] font-bold text-foreground">
                    {row.seatCode ?? '—'}
                </span>
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
                    <span className="text-xs text-muted-foreground">
                        {row.passenger?.phone ?? '—'}
                        {row.reservation?.reference &&
                            ` · ${row.reservation.reference}`}
                    </span>
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
            key: 'nationality',
            header: 'Nationalité',
            render: (row) => (
                <span className="text-xs font-semibold text-foreground">
                    {row.passenger?.nationality
                        ? row.passenger.nationality.toUpperCase()
                        : '—'}
                </span>
            ),
        },
        {
            key: 'document',
            header: 'Pièce d’identité',
            render: (row) =>
                row.passenger?.documentNumber ? (
                    <div>
                        <span className="block text-xs font-semibold text-foreground">
                            {row.passenger.documentTypeLabel ?? 'Document'}
                        </span>
                        <span className="font-mono text-[0.625rem] text-muted-foreground">
                            {row.passenger.documentNumber}
                        </span>
                    </div>
                ) : (
                    <span className="text-xs font-semibold text-destructive">
                        —
                    </span>
                ),
        },
        {
            key: 'origin',
            header: 'Montée',
            render: (row) => (
                <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                    <MoveUp className="size-3.5 text-[var(--digital)]" />
                    {row.originStop ?? '—'}
                </span>
            ),
        },
        {
            key: 'destination',
            header: 'Descente',
            render: (row) => (
                <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                    <MoveDown className="size-3.5 text-[var(--kora)]" />
                    {row.destinationStop ?? '—'}
                </span>
            ),
        },
        {
            key: 'ticket',
            header: 'Billet',
            render: (row) =>
                permissions.canViewTickets ? (
                    row.ticket ? (
                        <span className="font-mono text-[0.625rem] text-muted-foreground">
                            {row.ticket.shortCode}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    )
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
        {
            key: 'status',
            header: 'Embarquement',
            render: (row) => <JourneyStatusBadge status={row.journeyStatus} />,
        },
    ];

    return (
        <>
            <Head
                title={`Manifeste — ${departure.originCity ?? departure.lineName} → ${departure.destinationCity ?? ''}`}
            />

            {/* Page-head — navigation + impression */}
            <div className="cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Exploitation / Départs / Manifeste /{' '}
                        {departure.lineCode} · {departure.departureTime}
                    </div>
                    <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                        Manifeste {routeLabel}
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                        {departure.lineCode} · {departure.lineName} ·{' '}
                        {departure.isReturn ? 'Sens retour · ' : ''}
                        {formatDate(departure.departureDate)} ·{' '}
                        {departure.busRegistration}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                    <Link
                        href={departures.show.url({
                            departure: departure.uuid,
                        })}
                        className="btn btn-navy"
                    >
                        <ArrowLeft className="size-4" />
                        Retour au départ
                    </Link>
                    <a
                        href={manifest.print.url({
                            departure: departure.uuid,
                        })}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="btn btn-primary"
                    >
                        <Printer className="size-4" />
                        Imprimer le manifeste
                    </a>
                </div>
            </div>

            {/* Hero — identité du départ, façon maquette */}
            <section className="ops-ribbon cash-reveal mb-4">
                <div>
                    <span className="section-kicker">
                        {departure.lineTypeLabel ?? 'Ligne'} ·{' '}
                        {departure.lineCode}
                    </span>
                    <h2>{routeLabel}</h2>
                    <p>
                        Départ du {formatDate(departure.departureDate)} à{' '}
                        {departure.departureTime} · Bus{' '}
                        {departure.busRegistration ?? '—'}
                        {departure.seatCount
                            ? ` · ${departure.seatCount} sièges`
                            : ''}
                    </p>
                </div>
                <div className="ops-stats">
                    <div>
                        <small>Passagers</small>
                        <strong>{summary.total}</strong>
                        <span>
                            {isInternational
                                ? `${identitiesComplete} identité${identitiesComplete > 1 ? 's' : ''} complète${identitiesComplete > 1 ? 's' : ''}`
                                : 'au manifeste'}
                        </span>
                    </div>
                    <div>
                        <small>Embarqués</small>
                        <strong>{summary.boarded}</strong>
                        <span>contrôlés à bord</span>
                    </div>
                    <div>
                        <small>Statut</small>
                        <strong className="text-[1.1rem] leading-tight">
                            {departure.statusLabel ?? departure.status}
                        </strong>
                        <span>départ</span>
                    </div>
                </div>
            </section>

            {/* Alertes contextuelles */}
            <div className="mb-5 space-y-3">
                {isInternational && (
                    <PanelAlert tone="info" icon={Globe2}>
                        <strong>Contrôle frontière.</strong> Le manifeste d’une
                        ligne internationale doit mentionner pour chaque
                        voyageur : nom, nationalité, numéro de pièce, montée,
                        descente et siège — toute information manquante bloque
                        la présentation au point de passage.
                    </PanelAlert>
                )}
                {summary.incompleteIdentities > 0 && (
                    <PanelAlert tone="warning" icon={CircleAlert}>
                        <strong>
                            {summary.incompleteIdentities} identité
                            {summary.incompleteIdentities > 1 ? 's' : ''}{' '}
                            incomplète
                            {summary.incompleteIdentities > 1 ? 's' : ''}.
                        </strong>{' '}
                        {isInternational
                            ? 'Régularisez nom, nationalité ou pièce avant le départ — ces passagers bloquent le manifeste frontière.'
                            : 'Complétez l’identité de ces passagers pour fiabiliser le manifeste.'}
                    </PanelAlert>
                )}
                {summary.absent > 0 && (
                    <PanelAlert tone="danger" icon={UserX}>
                        <strong>
                            {summary.absent} absent
                            {summary.absent > 1 ? 's' : ''}
                        </strong>{' '}
                        déclaré{summary.absent > 1 ? 's' : ''} no-show — sièges
                        libérés pour revente sur le manifeste imprimé.
                    </PanelAlert>
                )}
                {summary.total > 0 &&
                    summary.boarded ===
                        summary.total - summary.absent - summary.cancelled &&
                    summary.boarded > 0 &&
                    ['departed', 'arrived'].includes(departure.status) && (
                        <PanelAlert tone="success" icon={ShieldCheck}>
                            Tous les passagers attendus sont embarqués — le
                            manifeste est prêt pour la présentation aux
                            autorités.
                        </PanelAlert>
                    )}
            </div>

            {/* Table manifeste — maquette : siège, passager, nationalité,
                pièce, montée, descente, embarquement */}
            <KoraTable
                title="Liste des voyageurs"
                subtitle={`${passengers.length} passager${passengers.length > 1 ? 's' : ''} — montée et descente par voyageur`}
                icon={FileText}
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

ManifestDetailPage.layout = {
    breadcrumbs: [
        { title: 'Départs', href: '/departures' } satisfies BreadcrumbItem,
    ],
};
