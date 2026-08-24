import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Dialog, DialogClose, DialogContent } from '@/components/ui/dialog';
import type { SaleDeparture } from './sale-types';
import { formatShortDate } from './sale-utils';
import { SeatPickerHeader } from './seat-picker-header';

type Props = {
    departure: SaleDeparture;
    children: ReactNode;
    onClose: () => void;
};

export function SeatPickerDialog({ departure, children, onClose }: Props) {
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className="seat-dialog-content p-0"
                showClose={false}
                aria-describedby="seat-picker-description"
            >
                <SeatPickerHeader departure={departure} />
                <DialogClose asChild>
                    <button
                        type="button"
                        className="seat-dialog-close"
                        aria-label={`Fermer la sélection du ${formatShortDate(departure.departureDate)}`}
                    >
                        <X className="size-4" />
                    </button>
                </DialogClose>
                {children}
            </DialogContent>
        </Dialog>
    );
}
