import { Building2, UserRound } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SaleCompanyAccount, SalePayerType } from './sale-types';
import { formatMoney } from './sale-utils';

type Props = {
    payerType: SalePayerType;
    selectedCompanyAccountUuid: string | null;
    companyAccounts: SaleCompanyAccount[];
    amount: number;
    currency: string;
    onPayerTypeChange: (type: SalePayerType) => void;
    onCompanyAccountChange: (uuid: string | null) => void;
    disabled?: boolean;
};

export function PayerSelector({
    payerType,
    selectedCompanyAccountUuid,
    companyAccounts,
    amount,
    currency,
    onPayerTypeChange,
    onCompanyAccountChange,
    disabled = false,
}: Props) {
    if (companyAccounts.length === 0) {
        return null;
    }

    return (
        <div className="payer-selector">
            <span className="payer-selector-label">
                <UserRound className="size-3.5" />
                Payeur
            </span>
            <div className="payer-toggle">
                <button
                    type="button"
                    className={cn(
                        'payer-choice',
                        payerType === 'personal' && 'is-active',
                    )}
                    disabled={disabled}
                    onClick={() => onPayerTypeChange('personal')}
                >
                    <UserRound className="size-3.5" />
                    Personne physique
                </button>
                <button
                    type="button"
                    className={cn(
                        'payer-choice',
                        payerType === 'company' && 'is-active',
                    )}
                    disabled={disabled}
                    onClick={() => onPayerTypeChange('company')}
                >
                    <Building2 className="size-3.5" />
                    Personne morale
                </button>
            </div>
            {payerType === 'company' && (
                <label className="field">
                    <span className="field-label">
                        <Building2 className="size-3.5" />
                        Compte personne morale
                    </span>
                    <select
                        className="control"
                        value={selectedCompanyAccountUuid ?? ''}
                        disabled={disabled}
                        onChange={(e) =>
                            onCompanyAccountChange(e.target.value || null)
                        }
                    >
                        <option value="">Sélectionner le compte</option>
                        {companyAccounts.map((account) => (
                            <option key={account.uuid} value={account.uuid}>
                                {account.name} ·{' '}
                                {formatMoney(account.availableCredit, 'XOF')}{' '}
                                dispo
                            </option>
                        ))}
                    </select>
                    {selectedCompanyAccountUuid &&
                        (() => {
                            const account = companyAccounts.find(
                                (a) => a.uuid === selectedCompanyAccountUuid,
                            );

                            if (!account) {
                                return null;
                            }

                            const insufficient =
                                account.availableCredit < amount;

                            return (
                                <small
                                    className={cn(
                                        'payment-field-help',
                                        insufficient && 'field-error-text',
                                    )}
                                >
                                    {insufficient
                                        ? `Crédit insuffisant (${formatMoney(account.availableCredit, currency)} / ${formatMoney(amount, currency)})`
                                        : `Crédit disponible : ${formatMoney(account.availableCredit, currency)}`}
                                </small>
                            );
                        })()}
                </label>
            )}
        </div>
    );
}
