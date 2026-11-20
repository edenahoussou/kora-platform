import { Gauge, Loader2, Truck, Weight } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    ParcelFieldLabel,
    ParcelRequiredNote,
} from '@/components/parcels/field-label';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type {
    CapacityDeparture,
    LaravelValidationErrors,
} from '@/components/parcels/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    departure: CapacityDeparture | null;
    busy: boolean;
    generalError: string | null;
    validationErrors: LaravelValidationErrors;
    onSubmit: (capacityGrams: number) => Promise<void>;
};

function firstError(
    errors: LaravelValidationErrors,
    key: string,
): string | undefined {
    return errors[key]?.[0];
}

function formatGrams(value: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} g`;
}

export function CapacityDialog({
    open,
    onOpenChange,
    departure,
    busy,
    generalError,
    validationErrors,
    onSubmit,
}: Props) {
    const [capacity, setCapacity] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        // eslint-disable-next-line react-hooks/set-state-in-effect
        setCapacity(
            departure?.capacity_grams === null ||
                departure?.capacity_grams === undefined
                ? ''
                : String(departure.capacity_grams),
        );
    }, [departure, open]);

    const value = /^\d+$/.test(capacity.trim()) ? Number(capacity) : null;
    const canSubmit =
        !busy &&
        value !== null &&
        value >= 0 &&
        (departure?.reserved_grams ?? 0) <= value;

    const submit = async () => {
        if (!canSubmit || value === null) {
            return;
        }

        await onSubmit(value);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[34rem]">
                <DialogHeader>
                    <DialogTitle>Capacité de soute</DialogTitle>
                    <DialogDescription>
                        Définissez le poids maximal autorisé pour ce départ. La
                        capacité ne peut pas être inférieure au poids déjà
                        réservé.
                    </DialogDescription>
                    <ParcelRequiredNote />
                </DialogHeader>

                {departure && (
                    <div className="grid gap-2 rounded-2xl border border-border bg-muted/25 p-4 text-sm">
                        <div className="flex items-center gap-2">
                            <Truck className="size-4 text-gold" />
                            <strong className="text-foreground">
                                {departure.line_code ?? 'Ligne'} ·{' '}
                                {departure.line_name ?? 'Sans nom'}
                            </strong>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {new Intl.DateTimeFormat('fr-FR', {
                                dateStyle: 'long',
                                timeStyle: 'short',
                            }).format(new Date(departure.departure_at))}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Déjà réservé :{' '}
                            {formatGrams(departure.reserved_grams)}
                        </p>
                    </div>
                )}

                <div className="field">
                    <ParcelFieldLabel
                        htmlFor="departure-capacity"
                        icon={Weight}
                        required
                        hint="Poids total autorisé dans la soute, supérieur ou égal au poids déjà réservé."
                    >
                        Capacité allouée (grammes)
                    </ParcelFieldLabel>
                    <input
                        id="departure-capacity"
                        type="number"
                        min={Math.max(0, departure?.reserved_grams ?? 0)}
                        max={10_000_000}
                        step={100}
                        value={capacity}
                        onChange={(event) => setCapacity(event.target.value)}
                        aria-required="true"
                        className="control"
                    />
                    {firstError(validationErrors, 'capacity_grams') && (
                        <p className="field-error-text">
                            {firstError(validationErrors, 'capacity_grams')}
                        </p>
                    )}
                    {!canSubmit && capacity !== '' && (
                        <p className="text-xs text-amber-700">
                            La capacité doit être supérieure ou égale au poids
                            déjà réservé.
                        </p>
                    )}
                    <p className="mt-1 flex items-center gap-1.5 text-[0.6875rem] text-muted-foreground">
                        <Gauge className="size-3 text-gold" />
                        Une capacité non définie bloque l’affectation des colis
                        à ce départ.
                    </p>
                </div>

                {generalError && (
                    <div
                        role="alert"
                        className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    >
                        {generalError}
                    </div>
                )}

                <DialogFooter>
                    <ParcelButton
                        type="button"
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={busy}
                    >
                        Annuler
                    </ParcelButton>
                    <ParcelButton
                        type="button"
                        variant="primary"
                        onClick={() => void submit()}
                        disabled={!canSubmit}
                    >
                        {busy ? (
                            <>
                                <Loader2 className="size-4 animate-spin" />
                                Enregistrement…
                            </>
                        ) : (
                            'Définir la capacité'
                        )}
                    </ParcelButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
