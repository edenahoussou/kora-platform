import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Dialog, DialogClose, DialogContent } from '@/components/ui/dialog';

type Props = {
    header: ReactNode;
    children: ReactNode;
    onClose: () => void;
    closeLabel?: string;
};

export function SeatPickerDialog({
    header,
    children,
    onClose,
    closeLabel = 'Fermer la sélection des places',
}: Props) {
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
                {header}
                <DialogClose asChild>
                    <button
                        type="button"
                        className="seat-dialog-close"
                        aria-label={closeLabel}
                    >
                        <X className="size-4" />
                    </button>
                </DialogClose>
                {children}
            </DialogContent>
        </Dialog>
    );
}
