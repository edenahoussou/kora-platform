import { Form } from '@inertiajs/react';
import { Check, Info, TrendingUp, X } from 'lucide-react';
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
import pricingRules from '@/routes/pricing-rules';

export type PricingRuleRecord = {
    uuid: string;
    lineUuid: string | null;
    lineCode: string | null;
    lineName: string | null;
    type: string;
    percentage: number;
    priority: number;
    conditions: {
        fillRateMin: number | null;
        fillRateMax: number | null;
        hoursBeforeDepartureMin: number | null;
        hoursBeforeDepartureMax: number | null;
    };
    startsAt: string | null;
    endsAt: string | null;
    status: string;
};

type LineOption = { uuid: string; code: string; name: string };

type Props = {
    open: boolean;
    rule?: PricingRuleRecord | null;
    lines: LineOption[];
    onOpenChange: (open: boolean) => void;
};

function HelpText({ children }: { children: React.ReactNode }) {
    return (
        <p className="text-[0.6875rem] leading-relaxed text-muted-foreground">
            {children}
        </p>
    );
}

function toDateTimeLocal(iso: string | null): string {
    if (!iso) {
        return '';
    }

    // "2026-09-01T08:00:00.000000Z" -> "2026-09-01T08:00" pour un input datetime-local
    return iso.slice(0, 16);
}

export function PricingRuleFormDialog({
    open,
    rule,
    lines,
    onOpenChange,
}: Props) {
    const editing = Boolean(rule);
    const form = editing
        ? pricingRules.update.form({ pricingRule: rule!.uuid })
        : pricingRules.store.form();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay className="bg-[rgba(0,29,78,0.24)] backdrop-blur-md" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                className="!max-h-[85vh] !max-w-[42rem] overflow-y-auto rounded-[1.75rem] border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] [&>button]:hidden"
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
                                        <TrendingUp className="size-5" />
                                    </span>
                                    <div>
                                        <span className="mb-1 block text-[0.6875rem] font-black tracking-[0.15em] text-gold uppercase">
                                            Tarification dynamique
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {editing
                                                ? 'Modifier la règle'
                                                : 'Nouvelle règle de pricing'}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm text-muted-foreground">
                                            Majoration ou réduction appliquée au
                                            tarif de base selon le remplissage
                                            et le délai avant départ.
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

                            <div className="grid gap-6 p-7">
                                {!editing && (
                                    <div className="flex items-start gap-3.5 rounded-[0.875rem] border border-[rgba(246,176,0,0.3)] bg-[rgba(246,176,0,0.06)] p-4">
                                        <Info className="mt-0.5 size-4 shrink-0 text-gold" />
                                        <div>
                                            <p className="text-[0.8125rem] font-medium text-foreground">
                                                La règle sera créée en brouillon
                                            </p>
                                            <p className="mt-1 text-[0.75rem] leading-relaxed text-muted-foreground">
                                                Activez-la explicitement pour
                                                qu'elle s'applique aux nouvelles
                                                recherches. Aucun effet
                                                rétroactif sur les billets déjà
                                                achetés.
                                            </p>
                                        </div>
                                    </div>
                                )}

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-line">Ligne</Label>
                                        <select
                                            id="rule-line"
                                            name="line_id"
                                            defaultValue={rule?.lineUuid ?? ''}
                                            className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none"
                                        >
                                            <option value="">
                                                Toutes les lignes
                                            </option>
                                            {lines.map((line) => (
                                                <option
                                                    key={line.uuid}
                                                    value={line.uuid}
                                                >
                                                    {line.code} · {line.name}
                                                </option>
                                            ))}
                                        </select>
                                        <HelpText>
                                            Laisser vide pour une règle globale,
                                            toutes lignes.
                                        </HelpText>
                                        <InputError message={errors.line_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-priority">
                                            Priorité *
                                        </Label>
                                        <Input
                                            id="rule-priority"
                                            name="priority"
                                            type="number"
                                            min={1}
                                            max={65535}
                                            defaultValue={rule?.priority ?? 100}
                                            required
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <HelpText>
                                            Plus le nombre est petit, plus la
                                            règle est prioritaire.
                                        </HelpText>
                                        <InputError message={errors.priority} />
                                    </div>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-type">
                                            Type *
                                        </Label>
                                        <select
                                            id="rule-type"
                                            name="type"
                                            defaultValue={
                                                rule?.type ?? 'increase'
                                            }
                                            required
                                            className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none"
                                        >
                                            <option value="increase">
                                                Majoration
                                            </option>
                                            <option value="decrease">
                                                Réduction
                                            </option>
                                        </select>
                                        <InputError message={errors.type} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-percentage">
                                            Pourcentage *
                                        </Label>
                                        <Input
                                            id="rule-percentage"
                                            name="percentage"
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            max={1000}
                                            defaultValue={
                                                rule?.percentage ?? ''
                                            }
                                            required
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.percentage}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-3 rounded-[0.875rem] bg-muted/40 p-4">
                                    <Label className="text-[0.8125rem]">
                                        Conditions (facultatives)
                                    </Label>
                                    <HelpText>
                                        Ex : "remplissage &gt; 80% et départ
                                        dans -24h → majoration". Laisser vide =
                                        condition non évaluée.
                                    </HelpText>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="rule-fill-min"
                                                className="text-xs font-medium"
                                            >
                                                Remplissage min (%)
                                            </Label>
                                            <Input
                                                id="rule-fill-min"
                                                name="conditions[fill_rate_min]"
                                                type="number"
                                                step="0.1"
                                                min={0}
                                                max={100}
                                                defaultValue={
                                                    rule?.conditions
                                                        .fillRateMin ?? ''
                                                }
                                                className="h-11 rounded-[0.75rem] border-0 bg-card shadow-[inset_0_0_0_1px_var(--border)]"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        'conditions.fill_rate_min'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="rule-fill-max"
                                                className="text-xs font-medium"
                                            >
                                                Remplissage max (%)
                                            </Label>
                                            <Input
                                                id="rule-fill-max"
                                                name="conditions[fill_rate_max]"
                                                type="number"
                                                step="0.1"
                                                min={0}
                                                max={100}
                                                defaultValue={
                                                    rule?.conditions
                                                        .fillRateMax ?? ''
                                                }
                                                className="h-11 rounded-[0.75rem] border-0 bg-card shadow-[inset_0_0_0_1px_var(--border)]"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        'conditions.fill_rate_max'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="rule-hours-min"
                                                className="text-xs font-medium"
                                            >
                                                Délai min avant départ (h)
                                            </Label>
                                            <Input
                                                id="rule-hours-min"
                                                name="conditions[hours_before_departure_min]"
                                                type="number"
                                                min={0}
                                                defaultValue={
                                                    rule?.conditions
                                                        .hoursBeforeDepartureMin ??
                                                    ''
                                                }
                                                className="h-11 rounded-[0.75rem] border-0 bg-card shadow-[inset_0_0_0_1px_var(--border)]"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        'conditions.hours_before_departure_min'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="rule-hours-max"
                                                className="text-xs font-medium"
                                            >
                                                Délai max avant départ (h)
                                            </Label>
                                            <Input
                                                id="rule-hours-max"
                                                name="conditions[hours_before_departure_max]"
                                                type="number"
                                                min={0}
                                                defaultValue={
                                                    rule?.conditions
                                                        .hoursBeforeDepartureMax ??
                                                    ''
                                                }
                                                className="h-11 rounded-[0.75rem] border-0 bg-card shadow-[inset_0_0_0_1px_var(--border)]"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        'conditions.hours_before_departure_max'
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-starts-at">
                                            Début de validité
                                        </Label>
                                        <Input
                                            id="rule-starts-at"
                                            name="starts_at"
                                            type="datetime-local"
                                            defaultValue={toDateTimeLocal(
                                                rule?.startsAt ?? null,
                                            )}
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError
                                            message={errors.starts_at}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-ends-at">
                                            Fin de validité
                                        </Label>
                                        <Input
                                            id="rule-ends-at"
                                            name="ends_at"
                                            type="datetime-local"
                                            defaultValue={toDateTimeLocal(
                                                rule?.endsAt ?? null,
                                            )}
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                        />
                                        <InputError message={errors.ends_at} />
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
                                          : 'Créer la règle'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
