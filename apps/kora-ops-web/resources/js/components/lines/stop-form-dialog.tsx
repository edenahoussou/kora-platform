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

export type StopRecord = {
    uuid: string;
    name: string;
    city: string;
    country: string;
    stopOrder: number;
    kmFromOrigin: number;
    latitude: number | null;
    longitude: number | null;
    status: string;
};

type Props = {
    open: boolean;
    lineUuid: string;
    stop?: StopRecord | null;
    nextOrder: number;
    onOpenChange: (open: boolean) => void;
};

export function StopFormDialog({
    open,
    lineUuid,
    stop,
    nextOrder,
    onOpenChange,
}: Props) {
    const editing = Boolean(stop);
    const form = editing
        ? network.stops.update.form({
              line: lineUuid,
              stop: stop!.uuid,
          })
        : network.stops.store.form({ line: lineUuid });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay className="bg-[rgba(0,29,78,0.24)] backdrop-blur-md" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                className="max-w-[40rem] rounded-[1.75rem] border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] [&>button]:hidden"
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
                                            Itinéraire ordonné
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {editing
                                                ? 'Modifier l’arrêt'
                                                : 'Nouvel arrêt'}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm text-muted-foreground">
                                            Le déplacement d’un arrêt conserve
                                            les billets déjà émis.
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
                                    <Label htmlFor="stop-name">
                                        Nom de l’arrêt *
                                    </Label>
                                    <Input
                                        id="stop-name"
                                        name="name"
                                        defaultValue={stop?.name ?? ''}
                                        required
                                        placeholder="Gare de Cotonou"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="stop-city">
                                            Ville *
                                        </Label>
                                        <Input
                                            id="stop-city"
                                            name="city"
                                            defaultValue={stop?.city ?? ''}
                                            required
                                            placeholder="Cotonou"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.city}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="stop-country">
                                            Pays *
                                        </Label>
                                        <Input
                                            id="stop-country"
                                            name="country"
                                            defaultValue={stop?.country ?? 'BJ'}
                                            required
                                            maxLength={2}
                                            placeholder="BJ"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted uppercase shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.country}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="stop-order">
                                            Ordre *
                                        </Label>
                                        <Input
                                            id="stop-order"
                                            name="stop_order"
                                            type="number"
                                            min="1"
                                            defaultValue={
                                                stop?.stopOrder ?? nextOrder
                                            }
                                            required
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.stop_order}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="stop-km">
                                            Distance depuis l’origine (km)
                                        </Label>
                                        <Input
                                            id="stop-km"
                                            name="km_from_origin"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            defaultValue={stop?.kmFromOrigin ?? 0}
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.km_from_origin}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="stop-lat">
                                            Latitude
                                        </Label>
                                        <Input
                                            id="stop-lat"
                                            name="latitude"
                                            type="number"
                                            step="0.0000001"
                                            defaultValue={stop?.latitude ?? ''}
                                            placeholder="6.3703"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.latitude}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="stop-lng">
                                            Longitude
                                        </Label>
                                        <Input
                                            id="stop-lng"
                                            name="longitude"
                                            type="number"
                                            step="0.0000001"
                                            defaultValue={stop?.longitude ?? ''}
                                            placeholder="2.3912"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.longitude}
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
                                          : 'Ajouter l’arrêt'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
