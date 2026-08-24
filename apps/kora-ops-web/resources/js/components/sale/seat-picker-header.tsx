import { BusFront, CalendarDays, Clock3, Hash, Tag, Users } from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { DialogDescription, DialogTitle } from '@/components/ui/dialog';
import type { SaleDeparture } from './sale-types';
import { formatDuration, formatShortDate, formatTime } from './sale-utils';

type Props = {
    departure: SaleDeparture;
};

export function SeatPickerHeader({ departure }: Props) {
    const serviceClass =
        departure.farePreview?.service_class_name ??
        departure.farePreview?.service_class_code ??
        'Service non renseigné';
    const vehicleModel =
        departure.bus.model ?? departure.bus.brand ?? 'Modèle non renseigné';

    return (
        <header className="seat-dialog-header">
            <div className="seat-dialog-header-copy">
                <div className="seat-dialog-heading-line">
                    <DialogTitle id="seat-picker-title">
                        Choisir les places
                    </DialogTitle>
                    <KoraStatusBadge
                        label={serviceClass}
                        tone="gold"
                        className="seat-dialog-service-badge"
                    />
                </div>
                <DialogDescription id="seat-picker-description">
                    Sélectionnez une place pour chaque voyageur avant de
                    continuer.
                </DialogDescription>
                <div className="seat-dialog-meta">
                    <span>
                        <Hash className="size-3.5" />
                        Ligne <strong>{departure.line.code}</strong>
                    </span>
                    <span>
                        <BusFront className="size-3.5" />
                        Véhicule <strong>{vehicleModel}</strong>
                    </span>
                    <span>
                        <Tag className="size-3.5" />
                        Immat.{' '}
                        <strong>{departure.bus.registrationNumber}</strong>
                    </span>
                    <span>
                        <Users className="size-3.5" />
                        <strong>{departure.bus.seatCount}</strong> places
                    </span>
                    <span>
                        <CalendarDays className="size-3.5" />
                        {formatShortDate(departure.departureDate)}
                    </span>
                    <span>
                        <Clock3 className="size-3.5" />
                        {formatTime(departure.departureTime)} ·{' '}
                        {formatDuration(
                            departure.line.estimatedDurationMinutes,
                        )}
                    </span>
                </div>
            </div>
        </header>
    );
}
