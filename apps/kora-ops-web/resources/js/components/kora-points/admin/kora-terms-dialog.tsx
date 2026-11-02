import { Form } from '@inertiajs/react';
import { Coins, X } from 'lucide-react';
import { useState } from 'react';
import koraPoints from '@/routes/kora-points';
import organizationRoutes from '@/routes/kora-points/organizations';

type TermsValues = {
    commission_basis_points?: number | null;
    ceiling_amount?: number | null;
    settlement_calendar?: {
        frequency?: string;
    } | null;
};

type Props = {
    open: boolean;
    target:
        | { type: 'point'; uuid: string; label: string }
        | { type: 'organization'; uuid: string; label: string }
        | null;
    values?: TermsValues | null;
    onOpenChange: (open: boolean) => void;
};

export function KoraTermsDialog({ open, target, values, onOpenChange }: Props) {
    const [frequency, setFrequency] = useState(
        values?.settlement_calendar?.frequency ?? '',
    );
    const [commissionRate, setCommissionRate] = useState(
        values?.commission_basis_points != null
            ? String(values.commission_basis_points / 100)
            : '',
    );

    if (!open || !target) {
        return null;
    }

    const isOrganization = target.type === 'organization';
    const form = isOrganization
        ? organizationRoutes.terms.form({ organization: target.uuid })
        : koraPoints.terms.form({ point: target.uuid });
    const title = isOrganization
        ? 'Conditions cadres de l’organisation'
        : 'Surcharge des conditions du point';
    const description = isOrganization
        ? `Définissez les conditions financières par défaut héritées par les points de « ${target.label} ».`
        : `Personnalisez les conditions du point « ${target.label} ». Les champs vides restent hérités.`;

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) =>
                e.target === e.currentTarget && onOpenChange(false)
            }
        >
            <Form
                {...form}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() => onOpenChange(false)}
                className="cash-modal cash-opening-modal w-[34rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="kora-terms-modal-title"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    <Coins className="size-5" />
                                </span>
                                <div>
                                    <span className="section-kicker">
                                        Réseau commercial · Conditions
                                    </span>
                                    <h3 id="kora-terms-modal-title">{title}</h3>
                                    <p>{description}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => onOpenChange(false)}
                                className="cash-modal-close"
                                aria-label="Fermer"
                                disabled={processing}
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="cash-opening-list">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label className="field">
                                    <span>Commission (%)</span>
                                    <input
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        value={commissionRate}
                                        onChange={(e) =>
                                            setCommissionRate(e.target.value)
                                        }
                                        placeholder="ex : 3,5"
                                    />
                                    <input
                                        type="hidden"
                                        name="commission_basis_points"
                                        value={
                                            commissionRate === ''
                                                ? ''
                                                : String(
                                                      Math.round(
                                                          Number(
                                                              commissionRate,
                                                          ) * 100,
                                                      ),
                                                  )
                                        }
                                    />
                                    {errors.commission_basis_points && (
                                        <small className="text-destructive">
                                            {errors.commission_basis_points}
                                        </small>
                                    )}
                                </label>

                                <label className="field">
                                    <span>Plafond d'encours (FCFA)</span>
                                    <input
                                        type="number"
                                        name="ceiling_amount"
                                        min={0}
                                        defaultValue={
                                            values?.ceiling_amount ?? ''
                                        }
                                        placeholder="1 500 000"
                                    />
                                    {errors.ceiling_amount && (
                                        <small className="text-destructive">
                                            {errors.ceiling_amount}
                                        </small>
                                    )}
                                </label>

                                <label className="field sm:col-span-2">
                                    <span>Calendrier de reversement</span>
                                    <select
                                        value={frequency}
                                        onChange={(e) =>
                                            setFrequency(e.target.value)
                                        }
                                    >
                                        <option value="">
                                            Hérité (hebdomadaire)
                                        </option>
                                        <option value="daily">Quotidien</option>
                                        <option value="weekly">
                                            Hebdomadaire
                                        </option>
                                        <option value="monthly">Mensuel</option>
                                    </select>
                                    {frequency !== '' && (
                                        <>
                                            <input
                                                type="hidden"
                                                name="settlement_calendar[frequency]"
                                                value={frequency}
                                            />
                                            <input
                                                type="hidden"
                                                name="settlement_calendar[time]"
                                                value="18:00"
                                            />
                                            <input
                                                type="hidden"
                                                name="settlement_calendar[timezone]"
                                                value="Africa/Porto-Novo"
                                            />
                                            {frequency === 'weekly' && (
                                                <input
                                                    type="hidden"
                                                    name="settlement_calendar[weekday]"
                                                    value="1"
                                                />
                                            )}
                                            {frequency === 'monthly' && (
                                                <input
                                                    type="hidden"
                                                    name="settlement_calendar[day]"
                                                    value="1"
                                                />
                                            )}
                                        </>
                                    )}
                                    {errors.settlement_calendar && (
                                        <small className="text-destructive">
                                            {errors.settlement_calendar}
                                        </small>
                                    )}
                                </label>
                            </div>
                        </div>

                        <div className="cash-modal-actions mt-6">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => onOpenChange(false)}
                                disabled={processing}
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={processing}
                            >
                                Enregistrer les conditions
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
