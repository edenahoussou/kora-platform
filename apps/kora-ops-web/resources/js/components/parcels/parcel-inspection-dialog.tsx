import {
    Ban,
    Banknote,
    CircleAlert,
    Loader2,
    Scale,
    ShieldAlert,
    Weight,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    ParcelFieldLabel,
    ParcelRequiredNote,
} from '@/components/parcels/field-label';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type {
    LaravelValidationErrors,
    ParcelListItem,
} from '@/components/parcels/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ParcelInspectionCategory =
    | 'allowed'
    | 'cash'
    | 'flammable'
    | 'chemical'
    | 'undeclared_medicine'
    | 'weapon'
    | 'refused';

export type ParcelInspectionPayload = {
    weight_grams?: number;
    category: ParcelInspectionCategory;
    cash_value?: number;
    reason?: string;
};

type Props = {
    parcel: ParcelListItem | null;
    onOpenChange: (open: boolean) => void;
    onSubmit: (payload: ParcelInspectionPayload) => Promise<{
        ok: boolean;
        message?: string;
        errors?: LaravelValidationErrors;
    }>;
};

const categories: {
    value: ParcelInspectionCategory;
    label: string;
    forbidden: boolean;
}[] = [
    { value: 'allowed', label: 'Contenu conforme', forbidden: false },
    { value: 'cash', label: 'Espèces déclarées', forbidden: false },
    { value: 'flammable', label: 'Liquide inflammable', forbidden: true },
    { value: 'chemical', label: 'Produit chimique', forbidden: true },
    {
        value: 'undeclared_medicine',
        label: 'Médicament non déclaré',
        forbidden: true,
    },
    { value: 'weapon', label: 'Arme', forbidden: true },
    { value: 'refused', label: 'Autre motif de refus', forbidden: true },
];

function fieldError(
    errors: LaravelValidationErrors,
    key: string,
): string | undefined {
    return errors[key]?.[0];
}

export function ParcelInspectionDialog({
    parcel,
    onOpenChange,
    onSubmit,
}: Props) {
    const [category, setCategory] =
        useState<ParcelInspectionCategory>('allowed');
    const [weight, setWeight] = useState('');
    const [cashValue, setCashValue] = useState('');
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [errors, setErrors] = useState<LaravelValidationErrors>({});

    useEffect(() => {
        if (parcel === null) {
            return;
        }

        // eslint-disable-next-line react-hooks/set-state-in-effect
        setCategory('allowed');
        setWeight(
            parcel.declared_grams > 0 ? String(parcel.declared_grams) : '',
        );
        setCashValue('');
        setReason('');
        setError(null);
        setErrors({});
    }, [parcel]);

    const selected = categories.find((item) => item.value === category);
    const forbidden = selected?.forbidden ?? false;
    const needsWeight = !forbidden;

    const submit = async () => {
        if (parcel === null || busy) {
            return;
        }

        setBusy(true);
        setError(null);
        setErrors({});

        const grams = Number(weight);

        if (needsWeight && (!Number.isInteger(grams) || grams < 1)) {
            setErrors({
                weight_grams: ['Indiquez un poids entier supérieur à 0 g.'],
            });
            setBusy(false);

            return;
        }

        const result: {
            ok: boolean;
            message?: string;
            errors?: LaravelValidationErrors;
        } = await onSubmit({
            ...(needsWeight ? { weight_grams: grams } : {}),
            category,
            ...(category === 'cash'
                ? { cash_value: Number(cashValue || 0) }
                : {}),
            ...(reason.trim() !== '' ? { reason: reason.trim() } : {}),
        }).catch(() => ({
            ok: false,
            message:
                'Le réseau est instable. La pesée n’a pas été enregistrée.',
        }));

        setBusy(false);

        if (result.ok) {
            onOpenChange(false);

            return;
        }

        setErrors(result.errors ?? {});
        setError(result.message ?? 'La pesée n’a pas été enregistrée.');
    };

    return (
        <Dialog open={parcel !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-[38rem]">
                <DialogHeader>
                    <DialogTitle>Inspecter et peser le colis</DialogTitle>
                    <DialogDescription>
                        {parcel !== null
                            ? `${parcel.reference} · ${parcel.contents || 'Contenu non renseigné'}`
                            : ''}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="rounded-xl border border-border bg-muted/30 px-3 py-2.5 text-xs leading-relaxed text-muted-foreground">
                        <CircleAlert className="mr-1.5 inline size-3.5 text-gold" />
                        Une pesée valide remplace l’ancien devis. Un refus
                        bloque le paiement, l’affectation et la mise à prêt.
                    </div>

                    <div>
                        <ParcelFieldLabel
                            htmlFor="inspection-category"
                            icon={ShieldAlert}
                            required
                            hint="Sélectionnez la nature réellement constatée à l’ouverture."
                        >
                            Catégorie du contenu
                        </ParcelFieldLabel>
                        <select
                            id="inspection-category"
                            className="control mt-1"
                            value={category}
                            onChange={(event) =>
                                setCategory(
                                    event.target
                                        .value as ParcelInspectionCategory,
                                )
                            }
                        >
                            {categories.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                        {fieldError(errors, 'category') && (
                            <p className="mt-1 text-xs text-destructive">
                                {fieldError(errors, 'category')}
                            </p>
                        )}
                    </div>

                    {category === 'cash' && (
                        <div>
                            <ParcelFieldLabel
                                htmlFor="inspection-cash"
                                icon={Banknote}
                                required
                                hint="Montant déclaré. Au-delà du seuil configuré, le colis est refusé et audité."
                            >
                                Valeur des espèces (FCFA)
                            </ParcelFieldLabel>
                            <input
                                id="inspection-cash"
                                type="number"
                                min={0}
                                className="control mt-1"
                                value={cashValue}
                                onChange={(event) =>
                                    setCashValue(event.target.value)
                                }
                            />
                            {fieldError(errors, 'cash_value') && (
                                <p className="mt-1 text-xs text-destructive">
                                    {fieldError(errors, 'cash_value')}
                                </p>
                            )}
                        </div>
                    )}

                    {needsWeight && (
                        <div>
                            <ParcelFieldLabel
                                htmlFor="inspection-weight"
                                icon={Weight}
                                required
                                hint="Poids réel lu sur la balance, en grammes. Plafond opérationnel 30 000 g."
                            >
                                Poids pesé (g)
                            </ParcelFieldLabel>
                            <input
                                id="inspection-weight"
                                type="number"
                                min={1}
                                step={1}
                                className="control mt-1"
                                value={weight}
                                onChange={(event) =>
                                    setWeight(event.target.value)
                                }
                            />
                            {fieldError(errors, 'weight_grams') && (
                                <p className="mt-1 text-xs text-destructive">
                                    {fieldError(errors, 'weight_grams')}
                                </p>
                            )}
                        </div>
                    )}

                    {(forbidden || reason !== '') && (
                        <div>
                            <ParcelFieldLabel
                                htmlFor="inspection-reason"
                                icon={Ban}
                                required={forbidden}
                                hint="Motif constaté. Le refus et son auteur sont conservés dans l’historique."
                            >
                                Motif du refus
                            </ParcelFieldLabel>
                            <textarea
                                id="inspection-reason"
                                className="control mt-1 min-h-20"
                                maxLength={500}
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                                placeholder="Précisez ce qui a été constaté."
                            />
                            {fieldError(errors, 'reason') && (
                                <p className="mt-1 text-xs text-destructive">
                                    {fieldError(errors, 'reason')}
                                </p>
                            )}
                        </div>
                    )}

                    {error !== null && (
                        <p className="rounded-xl border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                            {error}
                        </p>
                    )}
                </div>

                <DialogFooter className="gap-2">
                    <ParcelRequiredNote />
                    <ParcelButton
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={busy}
                    >
                        Annuler
                    </ParcelButton>
                    <ParcelButton
                        variant={forbidden ? 'danger' : 'primary'}
                        onClick={() => void submit()}
                        disabled={busy}
                    >
                        {busy ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : forbidden ? (
                            <Ban className="size-3.5" />
                        ) : (
                            <Scale className="size-3.5" />
                        )}
                        {forbidden
                            ? 'Enregistrer le refus'
                            : 'Valider la pesée'}
                    </ParcelButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
