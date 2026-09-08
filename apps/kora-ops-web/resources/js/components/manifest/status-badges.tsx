import { KoraStatusBadge } from '@/components/kora-status-badge';
import type { JourneyStatus } from './manifest-types';

type Tone = 'navy' | 'gold' | 'blue' | 'muted' | 'destructive';

const journeyStatusTones: Record<string, Tone> = {
    hold_pending: 'gold',
    hold_expired: 'muted',
    payment_pending: 'gold',
    payment_confirmed: 'blue',
    ticket_issued: 'navy',
    boarded: 'navy',
    absent: 'destructive',
    cancelled: 'destructive',
};

export function JourneyStatusBadge({
    status,
}: {
    status: JourneyStatus | null;
}) {
    if (!status) {
        return <KoraStatusBadge label="Statut indisponible" tone="muted" />;
    }

    return (
        <KoraStatusBadge
            label={status.label}
            tone={journeyStatusTones[status.value] ?? 'muted'}
        />
    );
}

const ticketStatusTones: Record<string, Tone> = {
    issued: 'navy',
    used: 'blue',
    cancelled: 'destructive',
    refunded: 'muted',
};

export function TicketStatusBadge({
    status,
    statusLabel,
}: {
    status: string | null;
    statusLabel: string | null;
}) {
    if (!status || !statusLabel) {
        return <KoraStatusBadge label="Billet non émis" tone="muted" />;
    }

    return (
        <KoraStatusBadge
            label={statusLabel}
            tone={ticketStatusTones[status] ?? 'muted'}
        />
    );
}
