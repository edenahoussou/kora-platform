import { Form } from '@inertiajs/react';
import { Landmark, X } from 'lucide-react';
import remittanceRoutes from '@/routes/kora-points/remittances';
import type { FinanceSettlement } from './kora-admin-ui';
import { formatAmount } from './kora-admin-ui';

type Props = {
    open: boolean;
    settlement: FinanceSettlement | null;
    mode: 'reconcile' | 'reject';
    onOpenChange: (open: boolean) => void;
};

export function KoraRemittanceReceiveDialog({
    open,
    settlement,
    mode,
    onOpenChange,
}: Props) {
    if (!open || !settlement) {
        return null;
    }

    const isReject = mode === 'reject';
    const title = isReject ? 'Rejeter le bordereau' : 'Rapprocher le versement';
    const description = isReject
        ? `Motiver le rejet du bordereau ${settlement.reference}. Le point émetteur sera notifié.`
        : `Confirmez la réception bancaire du bordereau ${settlement.reference}.`;

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) =>
                e.target === e.currentTarget && onOpenChange(false)
            }
        >
            <Form
                {...remittanceRoutes.receive.form({
                    remittance: settlement.uuid,
                })}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() => onOpenChange(false)}
                className="cash-modal cash-opening-modal w-[32rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="kora-receive-modal-title"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    <Landmark className="size-5" />
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="section-kicker">
                                            Finance KORA · Rapprochement
                                        </span>
                                        <span className="rounded bg-gold/15 px-2 py-0.5 font-mono text-[11px] font-bold text-[var(--kora)]">
                                            {settlement.reference}
                                        </span>
                                    </div>
                                    <h3 id="kora-receive-modal-title">
                                        {title}
                                    </h3>
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
                            <div className="grid grid-cols-2 gap-4 rounded-xl border border-border bg-muted/30 px-4 py-3 text-sm">
                                <div>
                                    <span className="block text-xs text-muted-foreground">
                                        Émetteur
                                    </span>
                                    <strong className="text-foreground">
                                        {settlement.point_name}
                                    </strong>
                                </div>
                                <div>
                                    <span className="block text-xs text-muted-foreground">
                                        Montant déclaré
                                    </span>
                                    <strong className="text-foreground">
                                        {formatAmount(
                                            settlement.amount_declared,
                                        )}
                                    </strong>
                                </div>
                                <div>
                                    <span className="block text-xs text-muted-foreground">
                                        Référence de dépôt
                                    </span>
                                    <strong className="text-foreground">
                                        {settlement.payment_reference}
                                    </strong>
                                </div>
                                <div>
                                    <span className="block text-xs text-muted-foreground">
                                        Ventes couvertes
                                    </span>
                                    <strong className="text-foreground">
                                        {settlement.sales_count}
                                    </strong>
                                </div>
                            </div>

                            {isReject && (
                                <input type="hidden" name="reject" value="1" />
                            )}

                            {!isReject && (
                                <label className="field">
                                    <span>
                                        Montant reçu (FCFA){' '}
                                        <b className="font-black text-destructive">
                                            *
                                        </b>
                                    </span>
                                    <input
                                        type="number"
                                        name="received_amount"
                                        min={0}
                                        defaultValue={
                                            settlement.amount_declared
                                        }
                                        required
                                    />
                                    {errors.received_amount && (
                                        <small className="text-destructive">
                                            {errors.received_amount}
                                        </small>
                                    )}
                                </label>
                            )}

                            <label className="field">
                                <span>
                                    {isReject
                                        ? 'Motif du rejet'
                                        : 'Note de rapprochement'}
                                </span>
                                <textarea
                                    name="note"
                                    rows={3}
                                    placeholder={
                                        isReject
                                            ? 'Écart constaté, preuve illisible…'
                                            : 'Référence de valeur, date comptable…'
                                    }
                                    required={isReject}
                                />
                                {errors.note && (
                                    <small className="text-destructive">
                                        {errors.note}
                                    </small>
                                )}
                            </label>
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
                                className={
                                    isReject
                                        ? 'btn btn-destructive'
                                        : 'btn btn-primary'
                                }
                                disabled={processing}
                            >
                                {isReject
                                    ? 'Rejeter le bordereau'
                                    : 'Valider le rapprochement'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
