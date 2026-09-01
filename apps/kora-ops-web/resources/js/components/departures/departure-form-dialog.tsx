import { Form } from '@inertiajs/react';
import { Calendar, Check, Clock, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import departures from '@/routes/departures';

export type DepartureRecord = {
    uuid: string;
    lineUuid?: string | null;
    lineCode: string;
    lineName: string;
    busUuid?: string | null;
    busRegistration: string;
    departureDate: string | null;
    departureTime: string | null;
    driverUuid?: string | null;
    driverName: string | null;
    conductorUuid?: string | null;
    conductorName: string | null;
    status: string;
};

type Option = { uuid: string; label: string };
type BusOption = { uuid: string; label: string };

type Props = {
    open: boolean;
    departure?: DepartureRecord | null;
    statuses: Record<string, string>;
    lines: Option[];
    buses: BusOption[];
    drivers: Option[];
    conductors: Option[];
    onOpenChange: (open: boolean) => void;
};

export function DepartureFormDialog({
    open,
    departure,
    lines,
    buses,
    drivers,
    conductors,
    onOpenChange,
}: Props) {
    const editing = Boolean(departure);
    const form = editing
        ? departures.update.form({ departure: departure!.uuid })
        : departures.store.form();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay className="bg-[rgba(0,29,78,0.24)] backdrop-blur-md" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                className="max-w-[42rem] rounded-[1.75rem] border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] [&>button]:hidden"
            >
                <Form
                    key={departure?.uuid ?? 'new'}
                    {...form}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex items-start justify-between gap-5 border-b border-border/70 p-7 pb-6">
                                <div className="flex items-start gap-4">
                                    <span className="grid size-12 shrink-0 place-items-center rounded-[0.9rem] bg-gold text-[var(--kora)]">
                                        <Calendar className="size-5" />
                                    </span>
                                    <div>
                                        <span className="mb-1 block text-[0.6875rem] font-black tracking-[0.15em] text-gold uppercase">
                                            Parcours guidé
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {editing
                                                ? 'Modifier le départ'
                                                : 'Programmer un départ'}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm text-muted-foreground">
                                            Le départ sera créé au statut
                                            PROGRAMMÉ et restera modifiable
                                            jusqu'à sa préparation.
                                        </DialogDescription>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => onOpenChange(false)}
                                    className="grid size-9 place-items-center rounded-[0.7rem] bg-muted"
                                    aria-label="Fermer"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>

                            <div className="grid gap-5 p-7">
                                <FieldSelect
                                    id="departure-line"
                                    name="line_uuid"
                                    label="Ligne *"
                                    value={departure?.lineUuid ?? undefined}
                                    options={lines}
                                    placeholder="Sélectionner une ligne"
                                    error={errors.line_uuid}
                                />

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="departure-date">
                                            Date *
                                        </Label>
                                        <Input
                                            id="departure-date"
                                            name="departure_date"
                                            type="date"
                                            required
                                            defaultValue={
                                                departure?.departureDate ?? ''
                                            }
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.departure_date}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="departure-time">
                                            Heure de départ *
                                        </Label>
                                        <Input
                                            id="departure-time"
                                            name="departure_time"
                                            type="time"
                                            required
                                            defaultValue={
                                                departure?.departureTime ?? ''
                                            }
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.departure_time}
                                        />
                                    </div>
                                </div>

                                <FieldSelect
                                    id="departure-bus"
                                    name="bus_uuid"
                                    label="Bus assigné *"
                                    value={departure?.busUuid ?? undefined}
                                    options={buses.map((bus) => ({
                                        uuid: bus.uuid,
                                        label: bus.label,
                                    }))}
                                    placeholder="Sélectionner un véhicule"
                                    error={errors.bus_uuid}
                                />

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <FieldSelect
                                        id="departure-driver"
                                        name="driver_uuid"
                                        label="Chauffeur"
                                        value={
                                            departure?.driverUuid ?? undefined
                                        }
                                        options={drivers}
                                        placeholder="À affecter"
                                        error={errors.driver_uuid}
                                        required={false}
                                    />
                                    <FieldSelect
                                        id="departure-conductor"
                                        name="conductor_uuid"
                                        label="Convoyeur"
                                        value={
                                            departure?.conductorUuid ??
                                            undefined
                                        }
                                        options={conductors}
                                        placeholder="À affecter"
                                        error={errors.conductor_uuid}
                                        required={false}
                                    />
                                </div>

                                <div className="rounded-[0.875rem] border border-border/50 bg-muted/40 p-4">
                                    <div className="mb-2 flex items-center gap-2">
                                        <Clock className="size-3.5 text-muted-foreground" />
                                        <span className="text-[0.5625rem] font-black tracking-[0.13em] text-muted-foreground uppercase">
                                            Horaires estimés par arrêt
                                        </span>
                                    </div>
                                    <p className="text-[0.625rem] text-muted-foreground">
                                        L'heure d'arrivée est calculée
                                        automatiquement pour chaque arrêt à
                                        partir de la durée estimée de la ligne.
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-col-reverse gap-3 border-t border-border/70 bg-muted/30 p-7 sm:flex-row sm:justify-end">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => onOpenChange(false)}
                                    disabled={processing}
                                >
                                    Annuler
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-gold font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                                >
                                    <Check className="size-4" />
                                    {processing
                                        ? 'Enregistrement…'
                                        : editing
                                          ? 'Enregistrer les changements'
                                          : 'Créer le départ'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function FieldSelect({
    id,
    name,
    label,
    value,
    options,
    placeholder,
    error,
    required = true,
}: {
    id: string;
    name: string;
    label: string;
    value?: string;
    options: Option[];
    placeholder: string;
    error?: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                name={name}
                defaultValue={value ?? ''}
                required={required}
                className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none"
            >
                <option value="">{placeholder}</option>
                {options.map((option) => (
                    <option key={option.uuid} value={option.uuid}>
                        {option.label}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}
