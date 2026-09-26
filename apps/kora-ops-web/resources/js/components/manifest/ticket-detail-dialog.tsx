import {
    CircleAlert,
    MapPin,
    Phone,
    Ticket as TicketIcon,
    Users,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { show as showManifestReservation } from '@/routes/manifest/reservations';
import type { ManifestRow } from './manifest-types';
import { JourneyStatusBadge, TicketStatusBadge } from './status-badges';

type TicketDetailDialogProps = {
    row: ManifestRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function TicketDetailDialog({
    row,
    open,
    onOpenChange,
}: TicketDetailDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-[30rem] rounded-[1.5rem] border-border/50 p-0">
                {row === null ? (
                    <div className="grid place-items-center gap-2 p-10 text-center text-muted-foreground">
                        <CircleAlert className="size-6" />
                        <p className="text-xs font-semibold">
                            Données indisponibles pour ce passager.
                        </p>
                    </div>
                ) : (
                    <>
                        <DialogHeader className="border-b border-border/70 p-6 pb-5">
                            <DialogTitle className="flex items-center gap-2 text-base font-bold">
                                <TicketIcon className="size-4 text-[var(--kora)]" />
                                {row.passenger
                                    ? `${row.passenger.firstName} ${row.passenger.lastName}`
                                    : 'Passager inconnu'}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="grid gap-5 p-6">
                            <div className="flex flex-wrap items-center gap-2">
                                <JourneyStatusBadge
                                    status={row.journeyStatus}
                                />
                                <TicketStatusBadge
                                    status={row.ticket?.status ?? null}
                                    statusLabel={
                                        row.ticket?.statusLabel ?? null
                                    }
                                />
                            </div>

                            {row.passenger?.phone && (
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Phone className="size-3.5" />
                                    {row.passenger.phone}
                                </div>
                            )}

                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Référence billet
                                    </dt>
                                    <dd className="mt-0.5 font-mono font-bold text-foreground">
                                        {row.ticket?.shortCode ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Siège
                                    </dt>
                                    <dd className="mt-0.5 font-bold text-foreground">
                                        {row.seatCode ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Nationalité
                                    </dt>
                                    <dd className="mt-0.5 font-bold text-foreground">
                                        {row.passenger?.nationality
                                            ? row.passenger.nationality.toUpperCase()
                                            : '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Pièce d'identité
                                    </dt>
                                    <dd className="mt-0.5 font-bold text-foreground">
                                        {row.passenger?.documentTypeLabel
                                            ? `${row.passenger.documentTypeLabel} ${row.passenger.documentNumber ?? ''}`.trim()
                                            : '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Naissance
                                    </dt>
                                    <dd className="mt-0.5 font-bold text-foreground">
                                        {row.passenger?.birthDate ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Réservation
                                    </dt>
                                    <dd className="mt-0.5 font-mono font-bold text-foreground">
                                        {row.reservation?.reference ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Émis le
                                    </dt>
                                    <dd className="mt-0.5 font-bold text-foreground">
                                        {row.ticket?.issuedAt
                                            ? new Date(
                                                row.ticket.issuedAt,
                                            ).toLocaleString('fr-FR')
                                            : '—'}
                                    </dd>
                                </div>
                                {row.departure && (
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Départ
                                        </dt>
                                        <dd className="mt-0.5 font-bold text-foreground">
                                            {row.departure.lineCode} ·{' '}
                                            {row.departure.departureDate} à{' '}
                                            {row.departure.departureTime}
                                        </dd>
                                    </div>
                                )}
                            </dl>

                            {(row.originStop || row.destinationStop) && (
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <MapPin className="size-3.5" />
                                    {row.originStop ?? '—'} →{' '}
                                    {row.destinationStop ?? '—'}
                                </div>
                            )}

                            {row.reservation?.isGroupPurchase && (
                                <a
                                    href={showManifestReservation.url({
                                        reservation: row.reservation.uuid,
                                    })}
                                    className="flex items-center gap-2 rounded-[0.75rem] bg-gold/12 p-3 text-xs font-semibold text-[var(--kora)] hover:bg-gold/20"
                                >
                                    <Users className="size-3.5" />
                                    Achat groupé — voir les autres passagers de
                                    cette réservation
                                </a>
                            )}
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
