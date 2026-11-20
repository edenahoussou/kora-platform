import {
    CircleDollarSign,
    Coins,
    Loader2,
    Package,
    RotateCcw,
    Ruler,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    ParcelFieldLabel,
    ParcelRequiredNote,
} from '@/components/parcels/field-label';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type {
    DepositHubKind,
    LaravelValidationErrors,
    ParcelCorridor,
    TariffPayload,
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
    corridor: ParcelCorridor | null;
    currency: string;
    depositKind: DepositHubKind;
    busy: boolean;
    generalError: string | null;
    validationErrors: LaravelValidationErrors;
    onSubmit: (payload: TariffPayload) => Promise<void>;
};

type FormState = {
    base_amount: string;
    step_amount: string;
    step_grams: string;
    maximum_grams: string;
    return_amount: string;
};

function firstError(
    errors: LaravelValidationErrors,
    key: string,
): string | undefined {
    return errors[key]?.[0];
}

function integer(value: string): number | null {
    if (!/^\d+$/.test(value.trim())) {
        return null;
    }

    return Number(value);
}

export function TariffDialog({
    open,
    onOpenChange,
    corridor,
    currency,
    depositKind,
    busy,
    generalError,
    validationErrors,
    onSubmit,
}: Props) {
    const [form, setForm] = useState<FormState>({
        base_amount: '',
        step_amount: '',
        step_grams: '5000',
        maximum_grams: '30000',
        return_amount: '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        const latest = corridor?.tariffs[0];

        // eslint-disable-next-line react-hooks/set-state-in-effect
        setForm({
            base_amount: latest ? String(latest.base_amount) : '',
            step_amount: latest ? String(latest.step_amount) : '',
            step_grams: String(latest?.step_grams ?? 5000),
            maximum_grams: String(latest?.maximum_grams ?? 30000),
            return_amount: latest ? String(latest.return_amount) : '',
        });
    }, [corridor, open]);

    const values = {
        base_amount: integer(form.base_amount),
        step_amount: integer(form.step_amount),
        step_grams: integer(form.step_grams),
        maximum_grams: integer(form.maximum_grams),
        return_amount: integer(form.return_amount),
    };

    const canSubmit =
        !busy &&
        values.base_amount !== null &&
        values.base_amount >= 1 &&
        values.base_amount <= 10_000_000 &&
        values.step_amount !== null &&
        values.step_amount >= 0 &&
        values.step_amount <= 1_000_000 &&
        values.step_grams !== null &&
        values.step_grams >= 100 &&
        values.step_grams <= 30_000 &&
        values.maximum_grams !== null &&
        values.maximum_grams >= values.step_grams &&
        values.maximum_grams <= 30_000 &&
        values.return_amount !== null &&
        values.return_amount >= 1 &&
        values.return_amount <= 10_000_000;

    const update = (key: keyof FormState, value: string) => {
        setForm((current) => ({ ...current, [key]: value }));
    };

    const submit = async () => {
        if (
            !canSubmit ||
            values.base_amount === null ||
            values.step_amount === null ||
            values.step_grams === null ||
            values.maximum_grams === null ||
            values.return_amount === null
        ) {
            return;
        }

        await onSubmit({
            deposit_kind: depositKind,
            base_amount: values.base_amount,
            step_amount: values.step_amount,
            step_grams: values.step_grams,
            maximum_grams: values.maximum_grams,
            return_amount: values.return_amount,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-[40rem]">
                <DialogHeader>
                    <DialogTitle>Publier un tarif</DialogTitle>
                    <DialogDescription>
                        Grille{' '}
                        {depositKind === 'partner_point'
                            ? 'point partenaire'
                            : 'agence'}{' '}
                        · tarif versionné : la publication crée une nouvelle
                        version sans réécrire l’historique. Montants exprimés en{' '}
                        {currency}.
                    </DialogDescription>
                    <ParcelRequiredNote />
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="field">
                        <ParcelFieldLabel
                            htmlFor="tariff-base"
                            icon={Coins}
                            required
                            hint="Montant minimum facturé pour un colis, quel que soit son poids."
                        >
                            Montant de base
                        </ParcelFieldLabel>
                        <input
                            id="tariff-base"
                            type="number"
                            min={1}
                            max={10_000_000}
                            step={1}
                            value={form.base_amount}
                            onChange={(event) =>
                                update('base_amount', event.target.value)
                            }
                            aria-required="true"
                            className="control"
                        />
                        {firstError(validationErrors, 'base_amount') && (
                            <p className="field-error-text">
                                {firstError(validationErrors, 'base_amount')}
                            </p>
                        )}
                    </div>

                    <div className="field">
                        <ParcelFieldLabel
                            htmlFor="tariff-return"
                            icon={RotateCcw}
                            required
                            hint="Frais appliqués si le colis revient à l’expéditeur."
                        >
                            Montant retour
                        </ParcelFieldLabel>
                        <input
                            id="tariff-return"
                            type="number"
                            min={1}
                            max={10_000_000}
                            step={1}
                            value={form.return_amount}
                            onChange={(event) =>
                                update('return_amount', event.target.value)
                            }
                            aria-required="true"
                            className="control"
                        />
                        {firstError(validationErrors, 'return_amount') && (
                            <p className="field-error-text">
                                {firstError(validationErrors, 'return_amount')}
                            </p>
                        )}
                    </div>

                    <div className="field">
                        <ParcelFieldLabel
                            htmlFor="tariff-step-grams"
                            icon={Ruler}
                            required
                            hint="Tranche de poids utilisée pour calculer les dépassements."
                        >
                            Pas de poids (grammes)
                        </ParcelFieldLabel>
                        <input
                            id="tariff-step-grams"
                            type="number"
                            min={100}
                            max={30_000}
                            step={100}
                            value={form.step_grams}
                            onChange={(event) =>
                                update('step_grams', event.target.value)
                            }
                            aria-required="true"
                            className="control"
                        />
                        {firstError(validationErrors, 'step_grams') && (
                            <p className="field-error-text">
                                {firstError(validationErrors, 'step_grams')}
                            </p>
                        )}
                    </div>

                    <div className="field">
                        <ParcelFieldLabel
                            htmlFor="tariff-step-amount"
                            icon={CircleDollarSign}
                            required
                            hint="Supplément ajouté à chaque tranche de poids dépassée."
                        >
                            Montant par pas
                        </ParcelFieldLabel>
                        <input
                            id="tariff-step-amount"
                            type="number"
                            min={0}
                            max={1_000_000}
                            step={1}
                            value={form.step_amount}
                            onChange={(event) =>
                                update('step_amount', event.target.value)
                            }
                            aria-required="true"
                            className="control"
                        />
                        {firstError(validationErrors, 'step_amount') && (
                            <p className="field-error-text">
                                {firstError(validationErrors, 'step_amount')}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-1.5 sm:col-span-2">
                        <ParcelFieldLabel
                            htmlFor="tariff-maximum"
                            icon={Package}
                            required
                            hint="Poids maximal accepté par ce tarif avant refus ou pesée complémentaire."
                        >
                            Poids maximal couvert (grammes)
                        </ParcelFieldLabel>
                        <input
                            id="tariff-maximum"
                            type="number"
                            min={100}
                            max={30_000}
                            step={100}
                            value={form.maximum_grams}
                            onChange={(event) =>
                                update('maximum_grams', event.target.value)
                            }
                            aria-required="true"
                            className="control"
                        />
                        {firstError(validationErrors, 'maximum_grams') && (
                            <p className="field-error-text">
                                {firstError(validationErrors, 'maximum_grams')}
                            </p>
                        )}
                        <p className="text-[0.6875rem] text-muted-foreground">
                            Plafond actuel du tarif : 30 000 g. La limite
                            stricte de pesée relève de la Task 3.
                        </p>
                    </div>
                </div>

                {!canSubmit && (
                    <div className="rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">
                        Vérifiez que le poids maximal est supérieur au pas de
                        poids et que tous les montants sont positifs.
                    </div>
                )}

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
                                Publication…
                            </>
                        ) : (
                            'Publier le tarif'
                        )}
                    </ParcelButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
