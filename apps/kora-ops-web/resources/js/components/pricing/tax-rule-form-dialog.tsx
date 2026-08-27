import { Form } from '@inertiajs/react';
import { Check, Info, Landmark, X } from 'lucide-react';
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
import taxRules from '@/routes/tax-rules';

export type TaxRuleRecord = {
    uuid: string;
    serviceType: string;
    scope: string;
    taxCode: string;
    taxRate: number;
    startsOn: string | null;
    endsOn: string | null;
    status: string;
    validatedByName: string | null;
};

type Props = {
    open: boolean;
    rule?: TaxRuleRecord | null;
    serviceTypes: Record<string, string>;
    onOpenChange: (open: boolean) => void;
};

const serviceTypeLabels: Record<string, string> = {
    transport: 'Transport (billets)',
    parcel: 'Colis',
    fee: 'Frais',
    other: 'Autre service',
};

export function TaxRuleFormDialog({
    open,
    rule,
    serviceTypes,
    onOpenChange,
}: Props) {
    const editing = Boolean(rule);
    const form = editing
        ? taxRules.update.form({ taxRule: rule!.uuid })
        : taxRules.store.form();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay className="bg-[rgba(0,29,78,0.24)] backdrop-blur-md" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                className="max-w-[36rem] rounded-[1.75rem] border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] [&>button]:hidden"
            >
                <Form
                    key={rule?.uuid ?? 'new'}
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
                                        <Landmark className="size-5" />
                                    </span>
                                    <div>
                                        <span className="mb-1 block text-[0.6875rem] font-black tracking-[0.15em] text-gold uppercase">
                                            Paramètres fiscaux
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {editing
                                                ? 'Modifier le taux'
                                                : 'Nouveau taux fiscal'}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm text-muted-foreground">
                                            Groupe/taux confirmé avec la DGI,
                                            historisé — jamais codé en dur.
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
                                <div className="flex items-start gap-3.5 rounded-[0.875rem] border border-[rgba(246,176,0,0.3)] bg-[rgba(246,176,0,0.06)] p-4">
                                    <Info className="mt-0.5 size-4 shrink-0 text-gold" />
                                    <p className="text-[0.75rem] leading-relaxed text-muted-foreground">
                                        Ce référentiel alimente la facturation
                                        MECeF. Il n'affecte pas le prix affiché
                                        au moment de la recherche/du devis
                                        passager.
                                    </p>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax-service-type">
                                            Type de service *
                                        </Label>
                                        <select
                                            id="tax-service-type"
                                            name="service_type"
                                            defaultValue={
                                                rule?.serviceType ?? 'transport'
                                            }
                                            required
                                            className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none"
                                        >
                                            {Object.keys(serviceTypes).map(
                                                (value) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {serviceTypeLabels[
                                                            value
                                                        ] ?? value}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError
                                            message={errors.service_type}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax-scope">
                                            Portée *
                                        </Label>
                                        <Input
                                            id="tax-scope"
                                            name="scope"
                                            defaultValue={rule?.scope ?? ''}
                                            placeholder="national"
                                            required
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError message={errors.scope} />
                                    </div>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax-code">
                                            Code fiscal *
                                        </Label>
                                        <Input
                                            id="tax-code"
                                            name="tax_code"
                                            defaultValue={rule?.taxCode ?? ''}
                                            placeholder="TVA-STD"
                                            required
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted font-mono text-sm shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError message={errors.tax_code} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax-rate">
                                            Taux (%) *
                                        </Label>
                                        <Input
                                            id="tax-rate"
                                            name="tax_rate"
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            max={100}
                                            defaultValue={rule?.taxRate ?? ''}
                                            required
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError message={errors.tax_rate} />
                                    </div>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax-starts-on">
                                            Effectif à partir du *
                                        </Label>
                                        <Input
                                            id="tax-starts-on"
                                            name="starts_on"
                                            type="date"
                                            defaultValue={rule?.startsOn ?? ''}
                                            required
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.starts_on}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax-ends-on">
                                            Effectif jusqu'au
                                        </Label>
                                        <Input
                                            id="tax-ends-on"
                                            name="ends_on"
                                            type="date"
                                            defaultValue={rule?.endsOn ?? ''}
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError message={errors.ends_on} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="tax-status">Statut *</Label>
                                    <select
                                        id="tax-status"
                                        name="status"
                                        defaultValue={rule?.status ?? 'draft'}
                                        required
                                        className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none"
                                    >
                                        <option value="draft">Brouillon</option>
                                        <option value="active">
                                            Active (confirmé DGI)
                                        </option>
                                        <option value="archived">
                                            Archivé
                                        </option>
                                    </select>
                                    <p className="text-[0.6875rem] leading-relaxed text-muted-foreground">
                                        Passer en "Active" enregistre
                                        automatiquement qui a validé ce taux.
                                        {rule?.validatedByName && (
                                            <>
                                                {' '}
                                                Validé par{' '}
                                                {rule.validatedByName}.
                                            </>
                                        )}
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
                                          : 'Créer le taux'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
