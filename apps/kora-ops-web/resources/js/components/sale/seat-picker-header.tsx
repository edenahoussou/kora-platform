import { BusFront, CalendarDays, Clock3, Hash, Tag, Users } from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { DialogDescription, DialogTitle } from '@/components/ui/dialog';
import type { SaleItineraryLeg } from './sale-types';
import { formatDuration, formatIsoDate, formatIsoTime, formatShortDate } from './sale-utils';

type Props = {
    leg: SaleItineraryLeg;
    /** Shown next to the title (e.g. "Aller"/"Retour") when there is more than one leg. */
    directionLabel?: string;
    /** Overrides the service-class badge, e.g. with a "2/4 places" progress indicator. */
    progressBadge?: string;
};

export function SeatPickerHeader({ leg, directionLabel, progressBadge }: Props) {
    const serviceClass =
        leg.serviceClassName ?? leg.serviceClassCode ?? 'Service non renseigné';
    const vehicleModel = leg.bus.model ?? leg.bus.brand ?? 'Modèle non renseigné';
    const title = directionLabel
        ? `Choisir les places · ${directionLabel}`
        : 'Choisir les places';

    return (
        <header className="seat-dialog-header">
            <div className="seat-dialog-header-copy">
                <div className="seat-dialog-heading-line">
                    <DialogTitle id="seat-picker-title">{title}</DialogTitle>
                    <KoraStatusBadge
                        label={progressBadge ?? serviceClass}
                        tone="gold"
                        className="seat-dialog-service-badge"
                    />
                </div>
                <DialogDescription id="seat-picker-description">
                    {directionLabel
                        ? 'Attribuez une place à chaque voyageur sur chaque jambe du trajet.'
                        : 'Sélectionnez une place pour chaque voyageur avant de continuer.'}
                </DialogDescription>
                <div className="seat-dialog-meta">
                    <span>
                        <Hash className="size-3.5" />
                        Ligne <strong>{leg.line.code}</strong>
                    </span>
                    <span>
                        <BusFront className="size-3.5" />
                        Véhicule <strong>{vehicleModel}</strong>
                    </span>
                    <span>
                        <Tag className="size-3.5" />
                        Immat.{' '}
                        <strong>{leg.bus.registrationNumber}</strong>
                    </span>
                    <span>
                        <Users className="size-3.5" />
                        <strong>{leg.bus.seatCount}</strong> places
                    </span>
                    <span>
                        <CalendarDays className="size-3.5" />
                        {formatShortDate(formatIsoDate(leg.departureAt))}
                    </span>
                    <span>
                        <Clock3 className="size-3.5" />
                        {formatIsoTime(leg.departureAt)} ·{' '}
                        {formatDuration(leg.line.estimatedDurationMinutes ?? null)}
                    </span>
                </div>
            </div>
        </header>
    );
}
