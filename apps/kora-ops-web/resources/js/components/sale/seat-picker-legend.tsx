import { cn } from '@/lib/utils';

const statusLegend = [
    ['available', 'Disponible'],
    ['selected', 'Sélectionnée'],
    ['reserved', 'Réservée'],
    ['paid', 'Payée'],
    ['blocked', 'Bloquée'],
] as const;

type Props = {
    className?: string;
};

export function SeatPickerLegend({ className }: Props) {
    return (
        <div
            className={cn('seat-picker-legend', className)}
            aria-label="Légende des sièges"
        >
            <span className="seat-picker-legend-title">Légende</span>
            <div className="seat-picker-legend-items">
                {statusLegend.map(([status, label]) => (
                    <span key={status}>
                        <i className={status} />
                        {label}
                    </span>
                ))}
            </div>
        </div>
    );
}
