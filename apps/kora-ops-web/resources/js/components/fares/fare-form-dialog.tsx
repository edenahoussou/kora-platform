import { Form } from '@inertiajs/react';
import { AlertCircle, Check, Info, Tag, X } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import fares from '@/routes/fares';

export type FareRecord = {
    uuid: string;
    lineCode: string;
    lineName: string;
    originName: string;
    destinationName: string;
    serviceClassCode: string;
    serviceClassName: string;
    baseAmount: number;
    currency: string;
    effectiveFrom: string;
    effectiveUntil: string | null;
    status: string;
};

type StopOption = {
    uuid: string;
    name: string;
    stopOrder: number;
};

type LineOption = {
    uuid: string;
    code: string;
    name: string;
    stops: StopOption[];
};

type ServiceClassOption = {
    uuid: string;
    code: string;
    name: string;
};

type Props = {
    open: boolean;
    fare?: FareRecord | null;
    lines: LineOption[];
    serviceClasses: ServiceClassOption[];
    onOpenChange: (open: boolean) => void;
};

function HelpText({ children }: { children: React.ReactNode }) {
    return (
        <p className="text-[0.6875rem] leading-relaxed text-muted-foreground">
            {children}
        </p>
    );
}

export function FareFormDialog({
    open,
    fare,
    lines,
    serviceClasses,
    onOpenChange,
}: Props) {
    const editing = Boolean(fare);
    const form = editing
        ? fares.update.form({ fare: fare!.uuid })
        : fares.store.form();

    const [selectedLineUuid, setSelectedLineUuid] = useState<string>(
        fare?.lineCode
            ? lines.find((l) => l.code === fare.lineCode)?.uuid ?? ''
            : '',
    );

    const [selectedOriginUuid, setSelectedOriginUuid] = useState<string>(
        fare?.originName
            ? lines
                .flatMap((l) => l.stops)
                .find((s) => s.name === fare.originName)?.uuid ?? ''
            : '',
    );

    const selectedLine = useMemo(
        () => lines.find((l) => l.uuid === selectedLineUuid),
        [lines, selectedLineUuid],
    );

    const availableStops = selectedLine?.stops ?? [];

    const selectedOrigin = useMemo(
        () => availableStops.find((s) => s.uuid === selectedOriginUuid),
        [availableStops, selectedOriginUuid],
    );

    // Destinations possibles : seulement les arrêts après l'origine dans l'itinéraire
    const destinationStops = useMemo(() => {
        if (!selectedOrigin) return availableStops;
        return availableStops.filter(
            (s) => s.stopOrder > selectedOrigin.stopOrder,
        );
    }, [availableStops, selectedOrigin]);

    // Origines possibles : exclure la destination déjà sélectionnée
    const originStops = useMemo(() => availableStops, [availableStops]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay className="bg-[rgba(0,29,78,0.24)] backdrop-blur-md" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                className="!max-w-[52rem] !max-h-[85vh] overflow-y-auto rounded-[1.75rem] border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] [&>button]:hidden"
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
                            {/* En-tête */}
                            <div className="flex items-start justify-between gap-5 border-b border-border/70 p-8 pb-7">
                                <div className="flex items-start gap-4">
                                    <span className="grid size-12 shrink-0 place-items-center rounded-[0.9rem] bg-gold text-[var(--kora)]">
                                        <Tag className="size-5" />
                                    </span>
                                    <div>
                                        <span className="mb-1 block text-[0.6875rem] font-black tracking-[0.15em] text-gold uppercase">
                                            Tarification
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {editing
                                                ? 'Modifier le tarif'
                                                : 'Nouvelle grille'}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm text-muted-foreground">
                                            Définissez le segment, la classe
                                            de service et la période d'effet.
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

                            <div className="grid gap-7 p-8">
                                {/* Alerte informative */}
                                {!editing ? (
                                    <div className="flex items-start gap-3.5 rounded-[0.875rem] border border-[rgba(246,176,0,0.3)] bg-[rgba(246,176,0,0.06)] p-4">
                                        <Info className="mt-0.5 size-4 shrink-0 text-gold" />
                                        <div>
                                            <p className="text-[0.8125rem] font-medium text-foreground">
                                                Le tarif sera créé en
                                                brouillon
                                            </p>
                                            <p className="mt-1 text-[0.75rem] leading-relaxed text-muted-foreground">
                                                Vous devrez l'activer
                                                explicitement pour qu'il
                                                soit vendable. Les billets
                                                déjà émis ne sont jamais
                                                modifiés rétroactivement.
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-start gap-3.5 rounded-[0.875rem] border border-border/60 bg-muted/40 p-4">
                                        <AlertCircle className="mt-0.5 size-4 shrink-0 text-[var(--digital)]" />
                                        <div>
                                            <p className="text-[0.8125rem] font-medium text-foreground">
                                                {fare?.status === 'active'
                                                    ? 'Tarif actif — modification sensible'
                                                    : fare?.status === 'draft'
                                                        ? 'Tarif en brouillon — libre modification'
                                                        : 'Tarif archivé — lecture seule'}
                                            </p>
                                            <p className="mt-1 text-[0.75rem] leading-relaxed text-muted-foreground">
                                                {fare?.status === 'active'
                                                    ? "Modifier le prix ou la période impacte immédiatement les nouvelles recherches. Les billets déjà vendus conservent leur prix d'origine."
                                                    : fare?.status === 'draft'
                                                        ? "Ce tarif n'est pas encore vendable. Vous pouvez ajuster librement le prix et la période."
                                                        : "Ce tarif n'est plus vendable. Créez un nouveau tarif pour le remplacer."}
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {/* Ligne + Service */}
                                <div className="grid gap-7 sm:grid-cols-2">
                                    <div className="grid gap-2.5">
                                        <Label htmlFor="fare-line">
                                            Ligne *
                                        </Label>
                                        <select
                                            id="fare-line"
                                            name="line_id"
                                            value={selectedLineUuid}
                                            onChange={(e) => {
                                                setSelectedLineUuid(
                                                    e.target.value,
                                                );
                                                setSelectedOriginUuid('');
                                            }}
                                            required
                                            disabled={editing}
                                            className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none disabled:opacity-60"
                                        >
                                            <option value="">
                                                Sélectionner une ligne
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
                                            La ligne détermine les arrêts
                                            disponibles pour le segment.
                                        </HelpText>
                                        <InputError
                                            message={errors.line_id}
                                        />
                                    </div>
                                    <div className="grid gap-2.5">
                                        <Label htmlFor="fare-service">
                                            Service *
                                        </Label>
                                        <select
                                            id="fare-service"
                                            name="service_class_id"
                                            defaultValue={
                                                fare?.serviceClassCode
                                                    ? serviceClasses.find(
                                                        (s) =>
                                                            s.code ===
                                                            fare.serviceClassCode,
                                                    )?.uuid ?? ''
                                                    : ''
                                            }
                                            required
                                            disabled={editing}
                                            className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none disabled:opacity-60"
                                        >
                                            <option value="">
                                                Sélectionner un service
                                            </option>
                                            {serviceClasses.map((cls) => (
                                                <option
                                                    key={cls.uuid}
                                                    value={cls.uuid}
                                                >
                                                    {cls.name}
                                                </option>
                                            ))}
                                        </select>
                                        <HelpText>
                                            Chaque classe de service a son
                                            propre prix sur le même segment.
                                        </HelpText>
                                        <InputError
                                            message={errors.service_class_id}
                                        />
                                    </div>
                                </div>

                                {/* Origine + Destination */}
                                <div className="grid gap-7 sm:grid-cols-2">
                                    <div className="grid gap-2.5">
                                        <Label htmlFor="fare-origin">
                                            Origine *
                                        </Label>
                                        <select
                                            id="fare-origin"
                                            name="origin_stop_id"
                                            value={selectedOriginUuid}
                                            onChange={(e) => {
                                                setSelectedOriginUuid(
                                                    e.target.value,
                                                );
                                            }}
                                            required
                                            disabled={
                                                editing ||
                                                availableStops.length === 0
                                            }
                                            className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none disabled:opacity-60"
                                        >
                                            <option value="">
                                                {availableStops.length === 0
                                                    ? "Sélectionner d'abord une ligne"
                                                    : "Sélectionner l'origine"}
                                            </option>
                                            {originStops.map((stop) => (
                                                <option
                                                    key={stop.uuid}
                                                    value={stop.uuid}
                                                >
                                                    {stop.name}
                                                </option>
                                            ))}
                                        </select>
                                        <HelpText>
                                            Point de départ du segment
                                            vendable.
                                        </HelpText>
                                        <InputError
                                            message={errors.origin_stop_id}
                                        />
                                    </div>
                                    <div className="grid gap-2.5">
                                        <Label htmlFor="fare-destination">
                                            Destination *
                                        </Label>
                                        <select
                                            id="fare-destination"
                                            name="destination_stop_id"
                                            defaultValue={
                                                fare?.destinationName
                                                    ? availableStops.find(
                                                        (s) =>
                                                            s.name ===
                                                            fare.destinationName,
                                                    )?.uuid ?? ''
                                                    : ''
                                            }
                                            required
                                            disabled={
                                                editing ||
                                                availableStops.length === 0
                                            }
                                            className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none disabled:opacity-60"
                                        >
                                            <option value="">
                                                {availableStops.length === 0
                                                    ? "Sélectionner d'abord une ligne"
                                                    : selectedOriginUuid === ''
                                                        ? "Sélectionner d'abord l'origine"
                                                        : destinationStops.length === 0
                                                            ? 'Aucune destination disponible'
                                                            : 'Sélectionner la destination'}
                                            </option>
                                            {destinationStops.map((stop) => (
                                                <option
                                                    key={stop.uuid}
                                                    value={stop.uuid}
                                                >
                                                    {stop.name}
                                                </option>
                                            ))}
                                        </select>
                                        <HelpText>
                                            Doit être située après l'origine
                                            dans l'itinéraire de la ligne.
                                        </HelpText>
                                        <InputError
                                            message={
                                                errors.destination_stop_id
                                            }
                                        />
                                    </div>
                                </div>

                                {/* Prix */}
                                <div className="grid gap-2.5">
                                    <Label htmlFor="fare-amount">
                                        Prix (FCFA) *
                                    </Label>
                                    <Input
                                        id="fare-amount"
                                        name="base_amount"
                                        type="number"
                                        min="1"
                                        defaultValue={fare?.baseAmount ?? ''}
                                        required
                                        placeholder="8500"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                    />
                                    <HelpText>
                                        Montant en Francs CFA (XOF), entier,
                                        sans décimale. Taxes incluses.
                                    </HelpText>
                                    <InputError
                                        message={errors.base_amount}
                                    />
                                </div>

                                {/* Période d'effet */}
                                <div className="rounded-[1rem] bg-muted/40 p-6">
                                    <span className="mb-2 block text-[0.5625rem] font-black uppercase tracking-[0.13em] text-[var(--digital)]">
                                        Période d'effet
                                    </span>
                                    <p className="mb-5 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                        Définit la fenêtre de validité du
                                        tarif. En dehors de cette période, le
                                        tarif n'est pas proposé à la vente.
                                    </p>
                                    <div className="grid gap-7 sm:grid-cols-2">
                                        <div className="grid gap-2.5">
                                            <Label htmlFor="fare-from">
                                                Date d'effet (début) *
                                            </Label>
                                            <Input
                                                id="fare-from"
                                                name="effective_from"
                                                type="date"
                                                defaultValue={
                                                    fare?.effectiveFrom ?? ''
                                                }
                                                required
                                                className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                            />
                                            <HelpText>
                                                À partir de cette date, le
                                                tarif est proposé à la vente
                                                (après activation).
                                            </HelpText>
                                            <InputError
                                                message={errors.effective_from}
                                            />
                                        </div>
                                        <div className="grid gap-2.5">
                                            <Label htmlFor="fare-until">
                                                Date de fin (optionnel)
                                            </Label>
                                            <Input
                                                id="fare-until"
                                                name="effective_until"
                                                type="date"
                                                defaultValue={
                                                    fare?.effectiveUntil ?? ''
                                                }
                                                className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                            />
                                            <HelpText>
                                                Laisser vide pour un tarif
                                                permanent (indéterminé).
                                                Sinon, le tarif cesse
                                                automatiquement à cette date.
                                            </HelpText>
                                            <InputError
                                                message={
                                                    errors.effective_until
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Devise (champ caché XOF par défaut) */}
                                <input
                                    type="hidden"
                                    name="currency"
                                    value={fare?.currency ?? 'XOF'}
                                />
                            </div>

                            <div className="flex flex-col-reverse gap-3 border-t border-border/70 bg-muted/30 p-8 sm:flex-row sm:justify-end">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => onOpenChange(false)}
                                    disabled={processing}
                                    className="h-12 rounded-[0.8rem] px-5"
                                >
                                    Annuler
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-12 rounded-[0.8rem] bg-gold px-6 font-medium text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] hover:bg-[var(--kora)] hover:text-white"
                                >
                                    <Check className="size-4" />
                                    {processing
                                        ? 'Enregistrement…'
                                        : editing
                                            ? 'Enregistrer les changements'
                                            : 'Valider et continuer'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
