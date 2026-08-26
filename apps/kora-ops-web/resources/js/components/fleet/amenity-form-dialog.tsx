import { Form } from '@inertiajs/react';
import { Check, Sparkles, X } from 'lucide-react';
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
import amenitiesRoutes from '@/routes/amenities';

export type AmenityRecord = {
    uuid: string;
    code: string;
    label: string;
    icon: string | null;
    busesCount: number;
    status: string;
    createdAt: string | null;
    updatedAt: string | null;
};

type Props = {
    open: boolean;
    amenity?: AmenityRecord | null;
    onOpenChange: (open: boolean) => void;
};

export function AmenityFormDialog({ open, amenity, onOpenChange }: Props) {
    const editing = Boolean(amenity);
    const form = editing
        ? amenitiesRoutes.update.form({ amenity: amenity!.uuid })
        : amenitiesRoutes.store.form();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay className="bg-[rgba(0,29,78,0.24)] backdrop-blur-md" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                className="max-w-[32rem] rounded-[1.75rem] border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] [&>button]:hidden"
            >
                <Form
                    key={amenity?.uuid ?? 'new'}
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
                                        <Sparkles className="size-5" />
                                    </span>
                                    <div>
                                        <span className="mb-1 block text-[0.6875rem] font-black tracking-[0.15em] text-gold uppercase">
                                            Référentiel flotte
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {editing
                                                ? 'Modifier la commodité'
                                                : 'Nouvelle commodité'}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm text-muted-foreground">
                                            Un équipement réutilisable,
                                            rattachable à n'importe quel bus.
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
                                    <Label htmlFor="amenity-code">Code *</Label>
                                    <Input
                                        id="amenity-code"
                                        name="code"
                                        defaultValue={amenity?.code ?? ''}
                                        required
                                        placeholder="wifi"
                                        pattern="[a-z0-9_\-]+"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted font-mono text-sm lowercase shadow-[inset_0_0_0_1px_var(--border)]"
                                    />
                                    <p className="text-[0.6875rem] leading-relaxed text-muted-foreground">
                                        Identifiant technique unique
                                        (minuscules, chiffres, tirets/
                                        underscores). Utilisé par l'app mobile,
                                        ne peut plus changer une fois en
                                        service.
                                    </p>
                                    <InputError message={errors.code} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="amenity-label">
                                        Libellé affiché *
                                    </Label>
                                    <Input
                                        id="amenity-label"
                                        name="label"
                                        defaultValue={amenity?.label ?? ''}
                                        required
                                        placeholder="Wi-Fi à bord"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                    />
                                    <InputError message={errors.label} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="amenity-icon">
                                        Icône (optionnel)
                                    </Label>
                                    <Input
                                        id="amenity-icon"
                                        name="icon"
                                        defaultValue={amenity?.icon ?? ''}
                                        placeholder="wifi"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted font-mono text-sm shadow-[inset_0_0_0_1px_var(--border)]"
                                    />
                                    <p className="text-[0.6875rem] leading-relaxed text-muted-foreground">
                                        Nom d'icône utilisé par l'app mobile
                                        pour afficher le badge.
                                    </p>
                                    <InputError message={errors.icon} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="amenity-status">
                                        Statut *
                                    </Label>
                                    <select
                                        id="amenity-status"
                                        name="status"
                                        defaultValue={
                                            amenity?.status ?? 'active'
                                        }
                                        required
                                        className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none"
                                    >
                                        <option value="active">Active</option>
                                        <option value="archived">
                                            Archivée
                                        </option>
                                    </select>
                                    <p className="text-[0.6875rem] leading-relaxed text-muted-foreground">
                                        Une commodité archivée disparaît de
                                        l'app mobile mais reste visible dans
                                        l'historique des bus déjà rattachés.
                                    </p>
                                    <InputError message={errors.status} />
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
                                          : 'Créer la commodité'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
