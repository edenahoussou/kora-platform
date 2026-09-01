import {
    ArrowLeft,
    Banknote,
    Building2,
    Check,
    CheckCircle2,
    Clock3,
    HandCoins,
    Hash,
    LockKeyhole,
    Phone,
    Receipt,
    ShieldCheck,
    Smartphone,
    UserRound,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { CashOpeningDialog } from '@/components/cash/cash-opening-dialog';
import { cn, uuid } from '@/lib/utils';
import { SaleJourneySummary } from './sale-journey-summary';
import type {
    SaleCashSession,
    SaleDeparture,
    SaleMobileMoneyAccount,
    SaleMobileMoneyNetwork,
    SalePaymentMethod,
    SaleReservation,
} from './sale-types';
import { formatMoney, formatRemainingTime } from './sale-utils';
import { useHoldCountdown } from './use-hold-countdown';

export type CounterSalePaymentPayload = {
    method: SalePaymentMethod;
    amount: number;
    idempotency_key: string;
    cash_received_amount: number | null;
    mobile_money_network: SaleMobileMoneyNetwork | null;
    agency_payment_account_uuid: string | null;
    payer_phone: string;
    payer_name: string;
    provider_reference: string;
    verified_manually: boolean;
};

type Props = {
    departure: SaleDeparture | null;
    reservation: SaleReservation;
    cashSession: SaleCashSession;
    mobileMoneyAccounts: SaleMobileMoneyAccount[];
    processing: boolean;
    errors: Record<string, string>;
    onSubmit: (payload: CounterSalePaymentPayload) => void;
    onBack: () => void;
    onHoldExpired: () => void;
};

const networkMeta: Record<
    SaleMobileMoneyNetwork,
    { label: string; color: string; bg: string; icon: LucideIcon }
> = {
    mtn_momo: {
        label: 'MTN MoMo',
        color: '#ffcc00',
        bg: 'rgba(255, 204, 0, 0.12)',
        icon: Smartphone,
    },
    moov_flooz: {
        label: 'Moov Money',
        color: '#0066cc',
        bg: 'rgba(0, 102, 204, 0.12)',
        icon: Smartphone,
    },
    celtiis_cash: {
        label: 'Celtiis Cash',
        color: '#00a651',
        bg: 'rgba(0, 166, 81, 0.12)',
        icon: Smartphone,
    },
};

function smartQuickAmounts(total: number): number[] {
    if (total <= 0) {
        return [1000, 2000, 5000, 10000];
    }

    // Use real FCFA bill denominations as steps
    const steps =
        total > 5000 ? [1000, 2000, 5000, 10000] : [500, 1000, 2000, 5000];
    const maxAmount = total + 10000;
    const amounts = new Set<number>();

    for (const step of steps) {
        // Next multiple of step strictly above total
        const next = Math.ceil((total + 1) / step) * step;

        if (next <= maxAmount) {
            amounts.add(next);
        }
    }

    const result = [...amounts].sort((a, b) => a - b).slice(0, 4);

    // Ensure at least 2 options
    if (result.length < 2) {
        result.push(total + 1000);
        result.push(total + 5000);

        return [...new Set(result)].sort((a, b) => a - b).slice(0, 4);
    }

    return result;
}

export function PaymentStep({
    departure,
    reservation,
    cashSession,
    mobileMoneyAccounts,
    processing,
    errors,
    onSubmit,
    onBack,
    onHoldExpired,
}: Props) {
    const [method, setMethod] = useState<SalePaymentMethod>('cash');
    const [cashReceived, setCashReceived] = useState('');
    const [network, setNetwork] = useState<SaleMobileMoneyNetwork | ''>('');
    const [accountUuid, setAccountUuid] = useState('');
    const [payerPhone, setPayerPhone] = useState('');
    const [payerName, setPayerName] = useState('');
    const [reference, setReference] = useState('');
    const [verified, setVerified] = useState(false);
    const [key] = useState(uuid);
    const [cashModalOpen, setCashModalOpen] = useState(false);
    const holdCountdown = useHoldCountdown(reservation.expiresAt);
    const expirationHandled = useRef(false);

    useEffect(() => {
        if (!holdCountdown.isExpired || expirationHandled.current) {
            return;
        }

        expirationHandled.current = true;
        onHoldExpired();
    }, [holdCountdown.isExpired, onHoldExpired]);

    const total = reservation.totalAmount;
    const received = Number(cashReceived) || 0;
    const change = Math.max(0, received - total);
    const isCashSufficient = received >= total;
    const networkAccounts = useMemo(
        () =>
            network
                ? mobileMoneyAccounts.filter(
                      (account) => account.network === network,
                  )
                : [],
        [mobileMoneyAccounts, network],
    );
    const selectedAccount = useMemo(
        () => networkAccounts.find((account) => account.uuid === accountUuid),
        [networkAccounts, accountUuid],
    );
    const cashReady = cashSession.isOpen && isCashSufficient;
    const phoneValid =
        payerPhone.trim() !== '' && isValidPhoneNumber(payerPhone);
    const mobileReady =
        cashSession.isOpen &&
        network !== '' &&
        accountUuid !== '' &&
        phoneValid &&
        reference.trim() !== '' &&
        verified;
    const canSubmit =
        !holdCountdown.isExpired &&
        !processing &&
        (method === 'cash' ? cashReady : mobileReady);

    const mobileChecklist = useMemo(
        () => [
            { label: 'Réseau sélectionné', done: network !== '' },
            { label: 'Compte agence choisi', done: accountUuid !== '' },
            { label: 'Téléphone du payeur saisi', done: phoneValid },
            {
                label: 'Référence de transaction',
                done: reference.trim() !== '',
            },
            { label: 'Crédit vérifié manuellement', done: verified },
        ],
        [network, accountUuid, phoneValid, reference, verified],
    );
    const mobileChecklistDone = mobileChecklist.filter(
        (item) => item.done,
    ).length;

    const selectNetwork = (value: SaleMobileMoneyNetwork) => {
        setNetwork(value);
        const accounts = mobileMoneyAccounts.filter(
            (account) => account.network === value,
        );
        setAccountUuid(accounts.length === 1 ? accounts[0].uuid : '');
    };

    const applyQuickAmount = (amount: number) => {
        setCashReceived(String(amount));
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        onSubmit({
            method,
            amount: total,
            idempotency_key: key,
            cash_received_amount: method === 'cash' ? received : null,
            mobile_money_network:
                method === 'mobile_money' && network ? network : null,
            agency_payment_account_uuid:
                method === 'mobile_money' ? accountUuid : null,
            payer_phone: method === 'mobile_money' ? payerPhone.trim() : '',
            payer_name: method === 'mobile_money' ? payerName.trim() : '',
            provider_reference:
                method === 'mobile_money' ? reference.trim() : '',
            verified_manually: method === 'mobile_money' ? verified : true,
        });
    };

    const submitLabel = processing
        ? 'Confirmation…'
        : method === 'cash'
          ? 'Encaisser les espèces'
          : 'Confirmer le transfert Mobile Money';

    return (
        <>
            <form className="checkout-layout" onSubmit={submit} noValidate>
                <section className="checkout-main kora-panel">
                    <div className="checkout-title">
                        <div className="finalization-heading">
                            <div>
                                <span className="section-kicker">
                                    Étape 4 · Encaissement
                                </span>
                                <h2>Confirmer le paiement</h2>
                                <p>
                                    Sélectionnez le moyen reçu au guichet. Le
                                    Mobile Money est vérifié manuellement sur le
                                    compte de l’agence.
                                </p>
                            </div>
                            <div className="finalization-status is-complete">
                                <Clock3 className="size-4" />
                                <strong>
                                    {formatRemainingTime(reservation.expiresAt)}
                                </strong>
                            </div>
                        </div>
                    </div>

                    {!cashSession.isOpen && (
                        <div className="cash-required-card" role="status">
                            <span className="cash-required-icon">
                                <Banknote className="size-5" />
                            </span>
                            <div className="cash-required-copy">
                                <strong>Votre caisse est encore fermée</strong>
                                <p>
                                    Ouvrez la session du jour avec votre fond de
                                    caisse pour pouvoir enregistrer cette vente.
                                </p>
                            </div>
                            {cashSession.canOpen ? (
                                <button
                                    type="button"
                                    onClick={() => setCashModalOpen(true)}
                                    className="cash-required-action"
                                >
                                    Ouvrir la caisse
                                </button>
                            ) : (
                                <span className="cash-required-help">
                                    Contactez votre responsable
                                </span>
                            )}
                        </div>
                    )}

                    {errors.reservation && (
                        <div
                            className="mb-5 rounded-[0.9rem] border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
                            role="alert"
                        >
                            {errors.reservation}
                        </div>
                    )}

                    <div
                        className="payment-methods grid-flow-dense"
                        role="radiogroup"
                        aria-label="Moyen de paiement"
                    >
                        <PaymentChoice
                            active={method === 'cash'}
                            onClick={() => setMethod('cash')}
                            icon={HandCoins}
                            title="Espèces"
                            description="Encaissement au guichet"
                        />
                        <PaymentChoice
                            active={method === 'mobile_money'}
                            onClick={() => setMethod('mobile_money')}
                            icon={Smartphone}
                            title="Mobile Money"
                            description="Vérification manuelle"
                        />
                        <button
                            type="button"
                            disabled
                            className="payment-choice is-disabled"
                            aria-label="Mini Wallet, bientôt disponible"
                        >
                            <span className="payment-choice-icon">
                                <WalletCards className="size-4" />
                            </span>
                            <span className="payment-choice-copy">
                                <strong>Mini Wallet</strong>
                                <small>Bientôt disponible</small>
                            </span>
                        </button>
                    </div>

                    {method === 'cash' ? (
                        <CashPaymentFields
                            total={total}
                            currency={reservation.currency}
                            cashReceived={cashReceived}
                            change={change}
                            isSufficient={isCashSufficient}
                            error={errors.cash_received_amount}
                            onChange={setCashReceived}
                            onQuickAmount={applyQuickAmount}
                        />
                    ) : (
                        <MobileMoneyFields
                            network={network}
                            accountUuid={accountUuid}
                            accounts={networkAccounts}
                            selectedAccount={selectedAccount}
                            payerPhone={payerPhone}
                            payerName={payerName}
                            reference={reference}
                            verified={verified}
                            errors={errors}
                            onNetworkChange={selectNetwork}
                            onAccountChange={setAccountUuid}
                            onPayerPhoneChange={setPayerPhone}
                            onPayerNameChange={setPayerName}
                            onReferenceChange={setReference}
                            onVerifiedChange={setVerified}
                        />
                    )}

                    <div className="checkout-actions payment-actions">
                        <button
                            type="button"
                            onClick={onBack}
                            disabled={processing}
                            className="btn btn-ghost"
                        >
                            <ArrowLeft className="size-4" /> Modifier les
                            passagers
                        </button>
                        <button
                            type="submit"
                            disabled={!canSubmit}
                            className="btn btn-primary"
                        >
                            {processing ? (
                                submitLabel
                            ) : (
                                <>
                                    <LockKeyhole className="size-4" />
                                    {submitLabel}
                                </>
                            )}
                        </button>
                    </div>

                    <div className="payment-security-badge">
                        <ShieldCheck className="size-3.5" />
                        Paiement sécurisé · Billets émis après confirmation
                    </div>
                </section>

                <SaleJourneySummary
                    departure={departure}
                    reservation={reservation}
                    variant="payment"
                    cashSession={cashSession}
                    onOpenCash={() => setCashModalOpen(true)}
                    paymentMethod={method}
                    cashBreakdown={{
                        total,
                        received,
                        change,
                        isSufficient: isCashSufficient,
                        currency: reservation.currency,
                    }}
                    mobileChecklist={mobileChecklist}
                    mobileChecklistDone={mobileChecklistDone}
                    selectedNetwork={
                        network
                            ? {
                                  label: networkMeta[network].label,
                                  color: networkMeta[network].color,
                              }
                            : null
                    }
                    selectedAccount={selectedAccount ?? null}
                />
            </form>
            {cashModalOpen && (
                <CashOpeningDialog
                    accounts={mobileMoneyAccounts}
                    onClose={() => setCashModalOpen(false)}
                />
            )}
        </>
    );
}

function PaymentChoice({
    active,
    onClick,
    icon: Icon,
    title,
    description,
}: {
    active: boolean;
    onClick: () => void;
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={active}
            onClick={onClick}
            className={cn('payment-choice', active && 'is-active')}
        >
            <span className="payment-choice-icon">
                <Icon className="size-4" />
            </span>
            <span className="payment-choice-copy">
                <strong>{title}</strong>
                <small>{description}</small>
            </span>
            {active && <Check className="payment-choice-check size-3.5" />}
        </button>
    );
}

function CashPaymentFields({
    total,
    currency,
    cashReceived,
    change,
    isSufficient,
    error,
    onChange,
    onQuickAmount,
}: {
    total: number;
    currency: string;
    cashReceived: string;
    change: number;
    isSufficient: boolean;
    error?: string;
    onChange: (value: string) => void;
    onQuickAmount: (amount: number) => void;
}) {
    const quickAmounts = useMemo(() => smartQuickAmounts(total), [total]);

    return (
        <div className="cash-payment-section">
            <div className="cash-quick-amounts">
                <button
                    type="button"
                    className="cash-quick-btn"
                    onClick={() => onQuickAmount(total)}
                >
                    Montant exact
                </button>
                {quickAmounts.map((amount) => (
                    <button
                        key={amount}
                        type="button"
                        className="cash-quick-btn"
                        onClick={() => onQuickAmount(amount)}
                    >
                        {formatMoney(amount, currency)}
                    </button>
                ))}
            </div>

            <div className="cash-input-grid">
                <label className="field">
                    <span className="field-label">
                        <HandCoins className="size-3.5" />
                        Montant reçu{' '}
                        <span className="required-asterisk">*</span>
                    </span>
                    <span className="payment-field-control">
                        <HandCoins
                            className="payment-field-icon"
                            aria-hidden="true"
                        />
                        <input
                            autoFocus
                            inputMode="numeric"
                            min="0"
                            type="number"
                            value={cashReceived}
                            onChange={(event) => onChange(event.target.value)}
                            aria-invalid={Boolean(error)}
                            placeholder="0"
                        />
                    </span>
                    <small className="payment-field-help">
                        Saisissez la somme remise par le client.
                    </small>
                    {error && (
                        <small className="field-error-text">{error}</small>
                    )}
                </label>
                <div className="field">
                    <span className="field-label">
                        <Banknote className="size-3.5" />
                        Monnaie à rendre
                    </span>
                    <span
                        className={cn(
                            'payment-field-control is-readonly',
                            isSufficient && change > 0 && 'is-positive',
                        )}
                    >
                        <Banknote
                            className="payment-field-icon"
                            aria-hidden="true"
                        />
                        <strong>{formatMoney(change, currency)}</strong>
                    </span>
                    <small className="payment-field-help">
                        La monnaie s’affiche dès que le montant reçu est saisi.
                    </small>
                </div>
            </div>
        </div>
    );
}

function MobileMoneyFields({
    network,
    accountUuid,
    accounts,
    selectedAccount,
    payerPhone,
    payerName,
    reference,
    verified,
    errors,
    onNetworkChange,
    onAccountChange,
    onPayerPhoneChange,
    onPayerNameChange,
    onReferenceChange,
    onVerifiedChange,
}: {
    network: SaleMobileMoneyNetwork | '';
    accountUuid: string;
    accounts: SaleMobileMoneyAccount[];
    selectedAccount: SaleMobileMoneyAccount | undefined;
    payerPhone: string;
    payerName: string;
    reference: string;
    verified: boolean;
    errors: Record<string, string>;
    onNetworkChange: (value: SaleMobileMoneyNetwork) => void;
    onAccountChange: (value: string) => void;
    onPayerPhoneChange: (value: string) => void;
    onPayerNameChange: (value: string) => void;
    onReferenceChange: (value: string) => void;
    onVerifiedChange: (value: boolean) => void;
}) {
    return (
        <div className="mobile-money-section">
            <div className="mm-step">
                <div className="mm-step-head">
                    <span className="mm-step-number">1</span>
                    <span className="mm-step-title">
                        <Smartphone className="size-3.5" />
                        Réseau & compte agence
                    </span>
                </div>
                <div className="mm-step-body">
                    <div className="mm-network-grid">
                        {(
                            Object.keys(networkMeta) as SaleMobileMoneyNetwork[]
                        ).map((value) => {
                            const meta = networkMeta[value];
                            const Icon = meta.icon;
                            const isActive = network === value;

                            return (
                                <button
                                    key={value}
                                    type="button"
                                    className={cn(
                                        'mm-network-card',
                                        isActive && 'is-active',
                                    )}
                                    style={
                                        isActive
                                            ? {
                                                  borderColor: meta.color,
                                                  background: meta.bg,
                                              }
                                            : undefined
                                    }
                                    onClick={() => onNetworkChange(value)}
                                >
                                    <span
                                        className="mm-network-icon"
                                        style={{
                                            color: meta.color,
                                            background: meta.bg,
                                        }}
                                    >
                                        <Icon className="size-4" />
                                    </span>
                                    <span className="mm-network-copy">
                                        <strong>{meta.label}</strong>
                                        <small>
                                            {
                                                accounts.filter(
                                                    (a) => a.network === value,
                                                ).length
                                            }{' '}
                                            compte(s)
                                        </small>
                                    </span>
                                    {isActive && (
                                        <Check className="mm-network-check size-3.5" />
                                    )}
                                </button>
                            );
                        })}
                    </div>
                    <label className="field">
                        <span className="field-label">
                            <Building2 className="size-3.5" />
                            Compte agence crédité{' '}
                            <span className="required-asterisk">*</span>
                        </span>
                        <select
                            className="control"
                            value={accountUuid}
                            onChange={(event) =>
                                onAccountChange(event.target.value)
                            }
                            disabled={!network || accounts.length === 1}
                        >
                            <option value="">
                                {network
                                    ? 'Sélectionner le compte'
                                    : 'Choisir le réseau d’abord'}
                            </option>
                            {accounts.map((account) => (
                                <option key={account.uuid} value={account.uuid}>
                                    {account.label} ·{' '}
                                    {account.accountIdentifier}
                                </option>
                            ))}
                        </select>
                        <small className="payment-field-help">
                            Compte de l’agence sur lequel le transfert doit
                            arriver.
                        </small>
                        {errors.agency_payment_account_uuid && (
                            <small className="field-error-text">
                                {errors.agency_payment_account_uuid}
                            </small>
                        )}
                    </label>
                </div>
            </div>

            <div className="mm-step">
                <div className="mm-step-head">
                    <span className="mm-step-number">2</span>
                    <span className="mm-step-title">
                        <UserRound className="size-3.5" />
                        Informations du payeur
                    </span>
                </div>
                <div className="mm-step-body mm-step-body-grid">
                    <label className="field">
                        <span className="field-label">
                            <Phone className="size-3.5" />
                            Numéro du payeur{' '}
                            <span className="required-asterisk">*</span>
                        </span>
                        <PhoneInput
                            className="control phone-field-control"
                            defaultCountry="BJ"
                            international
                            labels={phoneLabels}
                            value={payerPhone || undefined}
                            onChange={(value) =>
                                onPayerPhoneChange(value ?? '')
                            }
                            placeholder="Numéro Mobile Money"
                        />
                        <small className="payment-field-help">
                            Numéro Mobile Money du client qui envoie le
                            transfert.
                        </small>
                        {errors.payer_phone && (
                            <small className="field-error-text">
                                {errors.payer_phone}
                            </small>
                        )}
                    </label>
                    <label className="field">
                        <span className="field-label">
                            <UserRound className="size-3.5" />
                            Nom affiché par le réseau · optionnel
                        </span>
                        <input
                            className="control"
                            value={payerName}
                            onChange={(event) =>
                                onPayerNameChange(event.target.value)
                            }
                            autoComplete="name"
                            placeholder="Nom du payeur"
                        />
                        <small className="payment-field-help">
                            Tel qu’affiché dans la notification SMS du réseau.
                        </small>
                    </label>
                </div>
            </div>

            <div className="mm-step">
                <div className="mm-step-head">
                    <span className="mm-step-number">3</span>
                    <span className="mm-step-title">
                        <Receipt className="size-3.5" />
                        Confirmation manuelle
                    </span>
                </div>
                <div className="mm-step-body">
                    <label className="field">
                        <span className="field-label">
                            <Hash className="size-3.5" />
                            Référence de transaction{' '}
                            <span className="required-asterisk">*</span>
                        </span>
                        <input
                            className="control"
                            value={reference}
                            onChange={(event) =>
                                onReferenceChange(event.target.value)
                            }
                            placeholder="ex: MP240825.1234.A56789"
                        />
                        <small className="payment-field-help">
                            Code de transaction envoyé par SMS au client.
                        </small>
                        {errors.provider_reference && (
                            <small className="field-error-text">
                                {errors.provider_reference}
                            </small>
                        )}
                    </label>

                    <div
                        className={cn(
                            'mm-verify-card',
                            verified && 'is-verified',
                        )}
                    >
                        <label className="mm-verify-check">
                            <input
                                type="checkbox"
                                checked={verified}
                                onChange={(event) =>
                                    onVerifiedChange(event.target.checked)
                                }
                            />
                            <span>
                                <strong>J’ai vérifié le crédit reçu</strong>
                                <small>
                                    Je confirme manuellement que le montant a
                                    été crédité sur le compte Mobile Money de
                                    l’agence
                                    {selectedAccount
                                        ? ` (${selectedAccount.accountIdentifier})`
                                        : ''}
                                    .
                                </small>
                            </span>
                        </label>
                        {verified && (
                            <CheckCircle2 className="mm-verify-icon size-5" />
                        )}
                    </div>
                    {errors.verified_manually && (
                        <small className="field-error-text">
                            {errors.verified_manually}
                        </small>
                    )}
                </div>
            </div>
        </div>
    );
}
