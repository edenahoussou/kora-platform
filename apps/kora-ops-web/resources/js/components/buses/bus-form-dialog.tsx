import { Form } from '@inertiajs/react';
import { BusFront, Check, X } from 'lucide-react';
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
import fleet from '@/routes/fleet';

export type BusRecord = {
    uuid: string;
    registrationNumber: string;
    modelId: number | null;
    modelName: string | null;
    compositionId: number | null;
    compositionName: string | null;
    seatCount: number;
    amenityIds: number[];
    amenityLabels?: string[];
    status: string;
};
type Option = { id: number | string; label: string };
type AmenityOption = {
    id: number;
    code: string;
    label: string;
    icon: string | null;
};
type Props = {
    open: boolean;
    bus?: BusRecord | null;
    statuses: Record<string, string>;
    models: Option[];
    compositions: (Option & { seatCount: number })[];
    amenities: AmenityOption[];
    onOpenChange: (open: boolean) => void;
};

const labels: Record<string, string> = {
    active: 'Actif',
    draft: 'Brouillon',
    maintenance: 'En maintenance',
    retired: 'Retiré',
};

export function BusFormDialog({
    open,
    bus,
    statuses,
    models,
    compositions,
    amenities,
    onOpenChange,
}: Props) {
    const editing = Boolean(bus);
    const form = editing
        ? fleet.update.form({ bus: bus!.uuid })
        : fleet.store.form();

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
                    key={bus?.uuid ?? 'new'}
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
                                        <BusFront className="size-5" />
                                    </span>
                                    <div>
                                        <span className="mb-1 block text-[0.6875rem] font-black tracking-[0.15em] text-gold uppercase">
                                            Référentiel flotte
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {editing
                                                ? 'Modifier le bus'
                                                : 'Nouveau bus'}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm text-muted-foreground">
                                            Identifiez le véhicule et sa
                                            composition.
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
                                <div className="grid gap-2">
                                    <Label htmlFor="registration-number">
                                        Immatriculation *
                                    </Label>
                                    <Input
                                        id="registration-number"
                                        name="registration_number"
                                        defaultValue={
                                            bus?.registrationNumber ?? ''
                                        }
                                        required
                                        placeholder="BJ 9254 RB"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted uppercase shadow-[inset_0_0_0_1px_var(--border)]"
                                    />
                                    <InputError
                                        message={errors.registration_number}
                                    />
                                </div>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <FieldSelect
                                        id="bus-model"
                                        name="bus_model_id"
                                        label="Modèle *"
                                        value={bus?.modelId}
                                        options={models}
                                        placeholder="Sélectionner un modèle"
                                        error={errors.bus_model_id}
                                    />
                                    <FieldSelect
                                        id="bus-composition"
                                        name="composition_id"
                                        label="Composition *"
                                        value={bus?.compositionId}
                                        options={compositions.map((item) => ({
                                            id: item.id,
                                            label: `${item.label} · ${item.seatCount} places`,
                                        }))}
                                        placeholder="Sélectionner un gabarit"
                                        error={errors.composition_id}
                                    />
                                </div>
                                {amenities.length > 0 && (
                                    <div className="grid gap-2">
                                        <Label>Équipements</Label>
                                        <div className="grid grid-cols-2 gap-2.5 rounded-[0.875rem] bg-muted p-3.5 shadow-[inset_0_0_0_1px_var(--border)] sm:grid-cols-3">
                                            {amenities.map((amenity) => (
                                                <label
                                                    key={amenity.id}
                                                    htmlFor={`amenity-${amenity.id}`}
                                                    className="flex cursor-pointer items-center gap-2 rounded-[0.6rem] px-2 py-1.5 text-sm text-foreground hover:bg-card"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        id={`amenity-${amenity.id}`}
                                                        name="amenity_ids[]"
                                                        value={amenity.id}
                                                        defaultChecked={bus?.amenityIds?.includes(
                                                            amenity.id,
                                                        )}
                                                        className="size-4 rounded border-border/60 accent-[var(--kora)]"
                                                    />
                                                    {amenity.label}
                                                </label>
                                            ))}
                                        </div>
                                        <InputError
                                            message={errors.amenity_ids}
                                        />
                                    </div>
                                )}
                                <FieldSelect
                                    id="bus-status"
                                    name="status"
                                    label="Statut *"
                                    value={bus?.status}
                                    options={Object.entries(statuses).map(
                                        ([id, label]) => ({
                                            id,
                                            label: labels[id] ?? label,
                                        }),
                                    )}
                                    placeholder="Sélectionner un statut"
                                    error={errors.status}
                                    defaultValue={bus?.status ?? 'draft'}
                                />
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
                                          : 'Créer le bus'}
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
    defaultValue,
}: {
    id: string;
    name: string;
    label: string;
    value?: number | string | null;
    options: Option[];
    placeholder: string;
    error?: string;
    defaultValue?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                name={name}
                defaultValue={value ?? defaultValue ?? ''}
                required
                className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none"
            >
                <option value="">{placeholder}</option>
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.label}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}
