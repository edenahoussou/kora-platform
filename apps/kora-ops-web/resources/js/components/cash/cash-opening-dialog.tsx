import { useForm } from '@inertiajs/react';
import { Banknote, Camera, Check, Smartphone, X } from 'lucide-react';
import { useMemo } from 'react';
import type { SaleMobileMoneyAccount } from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import { open as openCash } from '@/routes/cash';

type OpeningLedger = {
    kind: 'cash' | 'mobile_money';
    account_uuid: string | null;
    opening_balance: string;
    opening_comment: string;
    evidence: File | null;
};

type Props = {
    accounts: SaleMobileMoneyAccount[];
    onClose: () => void;
    onSuccess?: () => void;
};

function initialLedgers(accounts: SaleMobileMoneyAccount[]): OpeningLedger[] {
    return [
        {
            kind: 'cash',
            account_uuid: null,
            opening_balance: '',
            opening_comment: '',
            evidence: null,
        },
        ...accounts.map((account) => ({
            kind: 'mobile_money' as const,
            account_uuid: account.uuid,
            opening_balance: '',
            opening_comment: '',
            evidence: null,
        })),
    ];
}

export function CashOpeningDialog({ accounts, onClose, onSuccess }: Props) {
    const initial = useMemo(() => initialLedgers(accounts), [accounts]);
    const form = useForm<{ ledgers: OpeningLedger[] }>({ ledgers: initial });
    const totalDeclared = form.data.ledgers.reduce(
        (total, ledger) => total + (Number(ledger.opening_balance) || 0),
        0,
    );
    const canSubmit = form.data.ledgers.every(
        (ledger) =>
            Number.isInteger(Number(ledger.opening_balance)) &&
            Number(ledger.opening_balance) >= 0 &&
            ledger.evidence !== null,
    );

    const setLedger = (
        index: number,
        changes: Partial<OpeningLedger>,
    ): void => {
        form.setData(
            'ledgers',
            form.data.ledgers.map((ledger, ledgerIndex) =>
                ledgerIndex === index ? { ...ledger, ...changes } : ledger,
            ),
        );
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!canSubmit || form.processing) {
            return;
        }

        form.post(openCash.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                onSuccess?.();
                onClose();
            },
        });
    };

    return (
        <div className="cash-modal-backdrop" role="presentation">
            <form
                className="cash-modal cash-opening-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cash-modal-title"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">Session du jour</span>
                        <h3 id="cash-modal-title">Ouvrir la caisse</h3>
                        <p>
                            Déclarez chaque solde visible par l’agent et ajoutez
                            une preuve de comptage ou de consultation.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Fermer"
                        className="cash-modal-close"
                        disabled={form.processing}
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <div className="cash-opening-list">
                    {form.data.ledgers.map((ledger, index) => {
                        const account = ledger.account_uuid
                            ? accounts.find(
                                  (item) => item.uuid === ledger.account_uuid,
                              )
                            : null;
                        const error =
                            form.errors[`ledgers.${index}.evidence`] ??
                            form.errors[`ledgers.${index}.opening_balance`];

                        return (
                            <div
                                className="cash-opening-row"
                                key={ledger.account_uuid ?? 'cash'}
                            >
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        {account ? (
                                            <Smartphone className="size-4" />
                                        ) : (
                                            <Banknote className="size-4" />
                                        )}
                                    </span>
                                    <div>
                                        <strong>
                                            {account?.label ?? 'Espèces'}
                                        </strong>
                                        <small>
                                            {account
                                                ? `${account.accountIdentifier} · ${account.network.replace('_', ' ')}`
                                                : 'Solde physique en XOF'}
                                        </small>
                                    </div>
                                </div>
                                <div className="cash-opening-fields">
                                    <label className="field">
                                        <span>
                                            Solde d’ouverture <b>*</b>
                                        </span>
                                        <input
                                            className="control"
                                            type="number"
                                            min="0"
                                            inputMode="numeric"
                                            value={ledger.opening_balance}
                                            onChange={(event) =>
                                                setLedger(index, {
                                                    opening_balance:
                                                        event.target.value,
                                                })
                                            }
                                            placeholder="0"
                                        />
                                    </label>
                                    <label className="cash-evidence-input">
                                        <span>
                                            <Camera className="size-3.5" />
                                            {ledger.evidence
                                                ? 'Preuve ajoutée'
                                                : 'Ajouter une preuve'}{' '}
                                            <b>*</b>
                                        </span>
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={(event) =>
                                                setLedger(index, {
                                                    evidence:
                                                        event.target
                                                            .files?.[0] ?? null,
                                                })
                                            }
                                        />
                                        <small>
                                            JPEG, PNG ou WebP · 5 Mo maximum
                                        </small>
                                    </label>
                                </div>
                                <label className="field cash-opening-comment">
                                    <span>
                                        Note <em>facultatif</em>
                                    </span>
                                    <input
                                        className="control"
                                        value={ledger.opening_comment}
                                        onChange={(event) =>
                                            setLedger(index, {
                                                opening_comment:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="Ex. comptage effectué avec le responsable"
                                    />
                                </label>
                                {error && (
                                    <small className="field-error-text">
                                        {error}
                                    </small>
                                )}
                                {ledger.evidence && (
                                    <span className="cash-evidence-ready">
                                        <Check className="size-3.5" />{' '}
                                        {ledger.evidence.name}
                                    </span>
                                )}
                            </div>
                        );
                    })}
                </div>

                {form.errors.ledgers && (
                    <div className="cash-form-error" role="alert">
                        {form.errors.ledgers}
                    </div>
                )}

                <div className="cash-opening-total">
                    <span>Total déclaré</span>
                    <strong>{formatMoney(totalDeclared, 'XOF')}</strong>
                </div>

                <div className="cash-modal-actions">
                    <button
                        type="button"
                        onClick={onClose}
                        className="btn btn-ghost"
                        disabled={form.processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className={cn(
                            'btn btn-primary',
                            !canSubmit && 'is-disabled',
                        )}
                        disabled={form.processing || !canSubmit}
                    >
                        {form.processing ? 'Ouverture…' : 'Ouvrir la caisse'}
                    </button>
                </div>
            </form>
        </div>
    );
}
