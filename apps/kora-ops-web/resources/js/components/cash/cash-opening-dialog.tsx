import { useForm } from '@inertiajs/react';
import { Banknote, Smartphone, X } from 'lucide-react';
import { useMemo } from 'react';
import type { SaleMobileMoneyAccount } from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import { open as openCash } from '@/routes/cash';
import { CameraCapture } from './camera-capture';

type OpeningLedger = {
    kind: 'cash' | 'mobile_money';
    account_uuid: string | null;
    opening_balance: string;
    opening_comment: string;
    evidence: File | null;
    evidence_captured_at: string | null;
    evidence_preview_url: string | null;
};

type Props = {
    accounts: SaleMobileMoneyAccount[];
    cashRegisters?: CashRegisterOption[];
    onClose: () => void;
    onSuccess?: () => void;
};

type CashRegisterOption = {
    uuid: string;
    code: string;
    label: string;
};

function initialLedgers(accounts: SaleMobileMoneyAccount[]): OpeningLedger[] {
    return [
        {
            kind: 'cash',
            account_uuid: null,
            opening_balance: '',
            opening_comment: '',
            evidence: null,
            evidence_captured_at: null,
            evidence_preview_url: null,
        },
        ...accounts.map((account) => ({
            kind: 'mobile_money' as const,
            account_uuid: account.uuid,
            opening_balance: '',
            opening_comment: '',
            evidence: null,
            evidence_captured_at: null,
            evidence_preview_url: null,
        })),
    ];
}

export function CashOpeningDialog({
    accounts,
    cashRegisters = [],
    onClose,
    onSuccess,
}: Props) {
    const initial = useMemo(() => initialLedgers(accounts), [accounts]);
    const form = useForm<{
        cash_register_uuid: string;
        ledgers: OpeningLedger[];
    }>({
        cash_register_uuid: cashRegisters[0]?.uuid ?? '',
        ledgers: initial,
    });
    // Business-rule errors like `cash_session` (e.g. "no agency assigned")
    // aren't tied to any form field, so they fall outside the strict
    // FormDataErrors<T> typing; read them via a loosely-typed view.
    const errors = form.errors as Record<string, string | undefined>;
    const totalDeclared = form.data.ledgers.reduce(
        (total, ledger) => total + (Number(ledger.opening_balance) || 0),
        0,
    );
    const canSubmit =
        form.data.ledgers.every(
            (ledger) =>
                Number.isInteger(Number(ledger.opening_balance)) &&
                Number(ledger.opening_balance) >= 0 &&
                ledger.evidence !== null &&
                ledger.evidence_captured_at !== null,
        ) &&
        (cashRegisters.length === 0 || form.data.cash_register_uuid !== '');

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

        form.transform((data) => ({
            cash_register_uuid: data.cash_register_uuid,
            ledgers: data.ledgers.map((ledger) => ({
                kind: ledger.kind,
                account_uuid: ledger.account_uuid,
                opening_balance: ledger.opening_balance,
                opening_comment: ledger.opening_comment,
                evidence: ledger.evidence,
                evidence_captured_at: ledger.evidence_captured_at,
            })),
        }));

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
                    <label className="field cash-opening-register">
                        <span>
                            Caisse du guichet <b>*</b>
                        </span>
                        <select
                            className="control"
                            value={form.data.cash_register_uuid}
                            onChange={(event) =>
                                form.setData(
                                    'cash_register_uuid',
                                    event.target.value,
                                )
                            }
                            required
                        >
                            <option value="">Sélectionner une caisse</option>
                            {cashRegisters.map((register) => (
                                <option
                                    key={register.uuid}
                                    value={register.uuid}
                                >
                                    {register.code} · {register.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    {form.data.ledgers.map((ledger, index) => {
                        const account = ledger.account_uuid
                            ? accounts.find(
                                  (item) => item.uuid === ledger.account_uuid,
                              )
                            : null;
                        const balanceError =
                            form.errors[`ledgers.${index}.opening_balance`];
                        const evidenceError =
                            form.errors[`ledgers.${index}.evidence`] ??
                            form.errors[
                                `ledgers.${index}.evidence_captured_at`
                            ];

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
                                        {balanceError && (
                                            <small className="field-error-text">
                                                {balanceError}
                                            </small>
                                        )}
                                    </label>
                                    <CameraCapture
                                        label="Preuve d’ouverture"
                                        error={evidenceError}
                                        value={
                                            ledger.evidence &&
                                            ledger.evidence_preview_url
                                                ? {
                                                      file: ledger.evidence,
                                                      capturedAt:
                                                          ledger.evidence_captured_at ??
                                                          '',
                                                      previewUrl:
                                                          ledger.evidence_preview_url,
                                                  }
                                                : null
                                        }
                                        onChange={(captured) =>
                                            setLedger(index, {
                                                evidence:
                                                    captured?.file ?? null,
                                                evidence_captured_at:
                                                    captured?.capturedAt ??
                                                    null,
                                                evidence_preview_url:
                                                    captured?.previewUrl ??
                                                    null,
                                            })
                                        }
                                    />
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
                            </div>
                        );
                    })}
                </div>

                {(errors.cash_session || form.errors.ledgers) && (
                    <div className="cash-form-error" role="alert">
                        {errors.cash_session ?? form.errors.ledgers}
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
