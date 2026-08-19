import { Form } from '@inertiajs/react';
import { Check, MapPin, X } from 'lucide-react';
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
import network from '@/routes/network';

export type LineRecord = {
    uuid: string;
    code: string;
    name: string;
    type: string;
    stopsCount: number;
    activeStopsCount: number;
    distanceKm: number | null;
    estimatedDurationMinutes: number | null;
    status: string;
    countriesCrossed: string[] | null;
};

type Option = { id: string; label: string };

type Props = {
    open: boolean;
    line?: LineRecord | null;
    statuses: Record<string, string>;
    onOpenChange: (open: boolean) => void;
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    draft: 'Brouillon',
    archived: 'Archivée',
};

const typeLabels: Record<string, string> = {
    nationale: 'Nationale',
    internationale: 'Internationale',
    regionale: 'Régionale',
};

export function LineFormDialog({
    open,
    line,
    statuses,
    onOpenChange,
}: Props) {
    const editing = Boolean(line);
    const form = editing
        ? network.update.form({ line: line!.uuid })
        : network.store.form();

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
                                        <MapPin className="size-5" />
                                    </span>
                                    <div>
                                        <span className="mb-1 block text-[0.6875rem] font-black tracking-[0.15em] text-gold uppercase">
                                            Réseau bus
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {editing
                                                ? 'Modifier la ligne'
                                                : 'Nouvelle ligne'}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm text-muted-foreground">
                                            Identifiez la ligne et son
                                            itinéraire principal.
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
                                    <Label htmlFor="line-name">
                                        Nom de la ligne *
                                    </Label>
                                    <Input
                                        id="line-name"
                                        name="name"
                                        defaultValue={line?.name ?? ''}
                                        required
                                        placeholder="Ligne nationale"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="line-code">
                                            Code *
                                        </Label>
                                        <Input
                                            id="line-code"
                                            name="code"
                                            defaultValue={line?.code ?? ''}
                                            required
                                            placeholder="L5"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted uppercase shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.code}
                                        />
                                    </div>
                                    <FieldSelect
                                        id="line-type"
                                        name="type"
                                        label="Type *"
                                        value={line?.type}
                                        options={Object.entries(typeLabels).map(
                                            ([id, label]) => ({
                                                id,
                                                label,
                                            }),
                                        )}
                                        placeholder="Sélectionner un type"
                                        error={errors.type}
                                        defaultValue={
                                            line?.type ?? 'nationale'
                                        }
                                    />
                                </div>
                                <FieldSelect
                                    id="line-status"
                                    name="status"
                                    label="Statut *"
                                    value={line?.status}
                                    options={Object.entries(statuses).map(
                                        ([id, label]) => ({
                                            id,
                                            label:
                                                statusLabels[id] ?? label,
                                        }),
                                    )}
                                    placeholder="Sélectionner un statut"
                                    error={errors.status}
                                    defaultValue={line?.status ?? 'draft'}
                                />
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="line-distance">
                                            Distance (km)
                                        </Label>
                                        <Input
                                            id="line-distance"
                                            name="distance_km"
                                            type="number"
                                            min="0"
                                            defaultValue={
                                                line?.distanceKm ?? ''
                                            }
                                            placeholder="420"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.distance_km}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="line-duration">
                                            Durée estimée (min)
                                        </Label>
                                        <Input
                                            id="line-duration"
                                            name="estimated_duration_minutes"
                                            type="number"
                                            min="0"
                                            defaultValue={
                                                line?.estimatedDurationMinutes ??
                                                ''
                                            }
                                            placeholder="360"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={
                                                errors.estimated_duration_minutes
                                            }
                                        />
                                    </div>
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
                                            : 'Créer la ligne'}
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
    value?: string | null;
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
