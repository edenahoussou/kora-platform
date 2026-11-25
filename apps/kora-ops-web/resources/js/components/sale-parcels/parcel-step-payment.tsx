import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    Banknote,
    Building2,
    Check,
    CheckCircle2,
    HandCoins,
    Hash,
    LockKeyhole,
    Phone,
    Receipt,
    ShieldCheck,
    Smartphone,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { cn } from '@/lib/utils';
import {
    AGENCY_MOMO_ACCOUNTS,
    COMPANY_ACCOUNTS,
    calculateParcelPricing,
} from './mock-data';
import type {
    ParcelIntakeDraft,
    ParcelMobileMoneyNetwork,
    PaymentMethod,
} from './types';

interface Props {
    draft: ParcelIntakeDraft;
    onChange: (updates: Partial<ParcelIntakeDraft>) => void;
    onSubmit: () => void;
    onBack: () => void;
    isSubmitting: boolean;
}

type MobileMoneyNetworkMeta = {
    label: string;
    color: string;
    bg: string;
    icon: LucideIcon;
    country: 'bj' | 'tg' | 'ci';
    countryName: string;
    flag: string;
};

const NETWORK_META: Record<ParcelMobileMoneyNetwork, MobileMoneyNetworkMeta> = {
    // Bénin 🇧🇯
    mtn_momo: {
        label: 'MTN MoMo',
        color: '#ffcc00',
        bg: 'rgba(255, 204, 0, 0.12)',
        icon: Smartphone,
        country: 'bj',
        countryName: 'Bénin',
        flag: '🇧🇯',
    },
    moov_flooz: {
        label: 'Moov Money / Flooz',
        color: '#0066cc',
        bg: 'rgba(0, 102, 204, 0.12)',
        icon: Smartphone,
        country: 'bj',
        countryName: 'Bénin',
        flag: '🇧🇯',
    },
    celtiis_cash: {
        label: 'Celtiis Cash',
        color: '#00a651',
        bg: 'rgba(0, 166, 81, 0.12)',
        icon: Smartphone,
        country: 'bj',
        countryName: 'Bénin',
        flag: '🇧🇯',
    },
    // Togo 🇹🇬
    tmoney_tg: {
        label: 'TMoney (Togocom)',
        color: '#ffb600',
        bg: 'rgba(255, 182, 0, 0.12)',
        icon: Smartphone,
        country: 'tg',
        countryName: 'Togo',
        flag: '🇹🇬',
    },
    moov_flooz_tg: {
        label: 'Moov Money Togo',
        color: '#0077c8',
        bg: 'rgba(0, 119, 200, 0.12)',
        icon: Smartphone,
        country: 'tg',
        countryName: 'Togo',
        flag: '🇹🇬',
    },
    // Côte d'Ivoire 🇨🇮
    wave_ci: {
        label: 'Wave CI',
        color: '#1dc3e4',
        bg: 'rgba(29, 195, 228, 0.14)',
        icon: Smartphone,
        country: 'ci',
        countryName: "Côte d'Ivoire",
        flag: '🇨🇮',
    },
    orange_money_ci: {
        label: 'Orange Money CI',
        color: '#ff7900',
        bg: 'rgba(255, 121, 0, 0.12)',
        icon: Smartphone,
        country: 'ci',
        countryName: "Côte d'Ivoire",
        flag: '🇨🇮',
    },
    mtn_momo_ci: {
        label: 'MTN MoMo CI',
        color: '#ffcc00',
        bg: 'rgba(255, 204, 0, 0.12)',
        icon: Smartphone,
        country: 'ci',
        countryName: "Côte d'Ivoire",
        flag: '🇨🇮',
    },
    moov_flooz_ci: {
        label: 'Moov Money CI',
        color: '#0066cc',
        bg: 'rgba(0, 102, 204, 0.12)',
        icon: Smartphone,
        country: 'ci',
        countryName: "Côte d'Ivoire",
        flag: '🇨🇮',
    },
};

function formatFcfa(amount: number): string {
    return `${amount.toLocaleString('fr-FR')} FCFA`;
}

function smartQuickAmounts(total: number): number[] {
    if (total <= 0) {
        return [1000, 2000, 5000, 10000];
    }

    const steps =
        total > 5000 ? [1000, 2000, 5000, 10000] : [500, 1000, 2000, 5000];
    const maxAmount = total + 10000;
    const amounts = new Set<number>();

    for (const step of steps) {
        const next = Math.ceil((total + 1) / step) * step;

        if (next <= maxAmount) {
            amounts.add(next);
        }
    }

    const result = [...amounts].sort((a, b) => a - b).slice(0, 4);

    if (result.length < 2) {
        result.push(total + 1000);
        result.push(total + 5000);

        return [...new Set(result)].sort((a, b) => a - b).slice(0, 4);
    }

    return result;
}

export function ParcelStepPayment({
    draft,
    onChange,
    onSubmit,
    onBack,
    isSubmitting,
}: Props) {
    const [attemptedSubmit, setAttemptedSubmit] = useState(false);
    const [countryFilter, setCountryFilter] = useState<
        'all' | 'bj' | 'tg' | 'ci'
    >('all');

    const quote = useMemo(
        () =>
            calculateParcelPricing(
                draft.nature,
                draft.weightKg,
                draft.destinationHub,
                draft.selectedItinerary,
            ),
        [
            draft.nature,
            draft.weightKg,
            draft.destinationHub,
            draft.selectedItinerary,
        ],
    );

    const total = quote.totalTtc;
    const effectivePayerName = draft.payerName || draft.senderName;
    const effectivePayerPhone =
        draft.momoPhone || draft.payerPhone || draft.senderPhone;

    const changeDue = Math.max(0, draft.cashTendered - total);
    const isCashSufficient = draft.cashTendered >= total;
    const quickAmounts = useMemo(() => smartQuickAmounts(total), [total]);

    const networkAccounts = useMemo(
        () =>
            draft.momoProvider
                ? AGENCY_MOMO_ACCOUNTS.filter(
                      (acc) => acc.network === draft.momoProvider,
                  )
                : [],
        [draft.momoProvider],
    );

    const selectedAgencyAccount = useMemo(
        () =>
            networkAccounts.find(
                (acc) => acc.uuid === draft.agencyPaymentAccountUuid,
            ) ?? null,
        [networkAccounts, draft.agencyPaymentAccountUuid],
    );

    const selectedCompanyAccount = useMemo(
        () =>
            COMPANY_ACCOUNTS.find((acc) => acc.id === draft.companyAccountId) ??
            COMPANY_ACCOUNTS[0],
        [draft.companyAccountId],
    );

    const payerPhoneValid =
        effectivePayerPhone.trim() !== '' &&
        isValidPhoneNumber(effectivePayerPhone);

    const cashReady =
        isCashSufficient && effectivePayerName.trim().length >= 2;
    const mobileReady =
        draft.momoProvider !== '' &&
        draft.agencyPaymentAccountUuid !== '' &&
        payerPhoneValid &&
        draft.momoReference.trim() !== '' &&
        draft.momoVerifiedManually;
    const companyCreditReady = Boolean(draft.companyAccountId);

    const canSubmit =
        !isSubmitting &&
        (draft.paymentMethod === 'cash'
            ? cashReady
            : draft.paymentMethod === 'mobile_money'
              ? mobileReady
              : companyCreditReady);

    const handleMethodChange = (method: PaymentMethod) => {
        setAttemptedSubmit(false);
        onChange({
            paymentMethod: method,
            cashTendered:
                method === 'cash'
                    ? draft.cashTendered || Math.ceil(total / 1000) * 1000
                    : draft.cashTendered,
            payerName: draft.payerName || draft.senderName,
            payerPhone: draft.payerPhone || draft.senderPhone,
            momoPhone: draft.momoPhone || draft.senderPhone,
        });
    };

    const handleSelectNetwork = (value: ParcelMobileMoneyNetwork) => {
        const accounts = AGENCY_MOMO_ACCOUNTS.filter(
            (acc) => acc.network === value,
        );
        onChange({
            momoProvider: value,
            agencyPaymentAccountUuid:
                accounts.length === 1 ? accounts[0].uuid : '',
        });
    };

    const handleFormSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!canSubmit) {
            setAttemptedSubmit(true);

            return;
        }

        onSubmit();
    };

    const submitLabel = isSubmitting
        ? 'Confirmation…'
        : draft.paymentMethod === 'cash'
          ? 'Encaisser les espèces & Émettre'
          : draft.paymentMethod === 'mobile_money'
            ? 'Confirmer le transfert Mobile Money'
            : 'Imputer le crédit entreprise';

    return (
        <form
            className="checkout-main kora-panel animate-in fade-in slide-in-from-bottom-2 duration-200"
            onSubmit={handleFormSubmit}
            noValidate
        >
            {/* Header identique à Vente au Guichet */}
            <div className="checkout-title">
                <div className="finalization-heading">
                    <div>
                        <span className="section-kicker">
                            Étape 3 · Encaissement &amp; Fiscalisation MECeF
                        </span>
                        <h2>Confirmer le paiement</h2>
                        <p>
                            Sélectionnez le moyen reçu au guichet. Le Mobile
                            Money est vérifié manuellement sur le compte de
                            l&apos;agence.
                        </p>
                    </div>
                    <div className="finalization-status is-complete">
                        <Banknote className="size-4" />
                        <strong>{formatFcfa(total)}</strong>
                    </div>
                </div>
                <p className="mt-2 text-[10px] text-muted-foreground">
                    Les champs marqués <span className="required-asterisk">*</span>{' '}
                    sont obligatoires.
                </p>
            </div>

            {/* Sélecteur des moyens de paiement (.payment-methods / .payment-choice) */}
            <div
                className="payment-methods grid-flow-dense"
                role="radiogroup"
                aria-label="Moyen de paiement"
            >
                <PaymentChoice
                    active={draft.paymentMethod === 'cash'}
                    onClick={() => handleMethodChange('cash')}
                    icon={HandCoins}
                    title="Espèces"
                    description="Encaissement au guichet"
                />
                <PaymentChoice
                    active={draft.paymentMethod === 'mobile_money'}
                    onClick={() => handleMethodChange('mobile_money')}
                    icon={Smartphone}
                    title="Mobile Money"
                    description="Vérification manuelle"
                />
                <PaymentChoice
                    active={draft.paymentMethod === 'company_account'}
                    onClick={() => handleMethodChange('company_account')}
                    icon={Building2}
                    title="Crédit entreprise"
                    description="Facturation fin mois"
                />
            </div>

            {/* 1 · ESPÈCES */}
            {draft.paymentMethod === 'cash' && (
                <div className="cash-payment-section">
                    <div className="cash-quick-amounts">
                        <button
                            type="button"
                            className="cash-quick-btn"
                            onClick={() => onChange({ cashTendered: total })}
                        >
                            Montant exact
                        </button>
                        {quickAmounts.map((amount) => (
                            <button
                                key={amount}
                                type="button"
                                className="cash-quick-btn"
                                onClick={() =>
                                    onChange({ cashTendered: amount })
                                }
                            >
                                {formatFcfa(amount)}
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
                                    id="cash-tendered-input"
                                    autoFocus
                                    inputMode="numeric"
                                    min="0"
                                    step="500"
                                    type="number"
                                    value={draft.cashTendered || ''}
                                    aria-invalid={
                                        attemptedSubmit && !isCashSufficient
                                    }
                                    onChange={(e) =>
                                        onChange({
                                            cashTendered: Math.max(
                                                0,
                                                Number(e.target.value),
                                            ),
                                        })
                                    }
                                    placeholder="0"
                                />
                            </span>
                            <small className="payment-field-help">
                                Saisissez la somme remise par le client.
                            </small>
                            {attemptedSubmit && !isCashSufficient && (
                                <small className="field-error-text">
                                    Montant insuffisant (manque{' '}
                                    {formatFcfa(total - draft.cashTendered)}).
                                </small>
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
                                    isCashSufficient &&
                                        changeDue > 0 &&
                                        'is-positive',
                                )}
                            >
                                <Banknote
                                    className="payment-field-icon"
                                    aria-hidden="true"
                                />
                                <strong>
                                    {isCashSufficient
                                        ? formatFcfa(changeDue)
                                        : `Manque ${formatFcfa(total - draft.cashTendered)}`}
                                </strong>
                            </span>
                            <small className="payment-field-help">
                                La monnaie s&apos;affiche dès que le montant
                                reçu est saisi.
                            </small>
                        </div>
                    </div>

                    <div className="cash-input-grid">
                        <label className="field">
                            <span className="field-label">
                                <UserRound className="size-3.5" />
                                Nom du payeur{' '}
                                <span className="required-asterisk">*</span>
                            </span>
                            <span className="payment-field-control">
                                <UserRound
                                    className="payment-field-icon"
                                    aria-hidden="true"
                                />
                                <input
                                    id="cash-payer-name"
                                    type="text"
                                    value={effectivePayerName}
                                    aria-invalid={
                                        attemptedSubmit &&
                                        effectivePayerName.trim().length < 2
                                    }
                                    onChange={(e) =>
                                        onChange({ payerName: e.target.value })
                                    }
                                    placeholder="Nom du client"
                                />
                            </span>
                            <small className="payment-field-help">
                                Nom du client qui règle l&apos;envoi (pour la
                                facturation DGI / MECeF).
                            </small>
                            {attemptedSubmit &&
                                effectivePayerName.trim().length < 2 && (
                                    <small className="field-error-text">
                                        Nom du payeur requis.
                                    </small>
                                )}
                        </label>

                        <label className="field">
                            <span className="field-label">
                                <Phone className="size-3.5" />
                                Téléphone du payeur
                            </span>
                            <span className="payment-field-control">
                                <Phone
                                    className="payment-field-icon"
                                    aria-hidden="true"
                                />
                                <input
                                    id="cash-payer-phone"
                                    type="tel"
                                    value={
                                        draft.payerPhone || draft.senderPhone
                                    }
                                    onChange={(e) =>
                                        onChange({ payerPhone: e.target.value })
                                    }
                                    placeholder="+229 ..."
                                />
                            </span>
                            <small className="payment-field-help">
                                Numéro de contact du client (optionnel).
                            </small>
                        </label>
                    </div>
                </div>
            )}

            {/* 2 · MOBILE MONEY (3 étapes alignées sur Vente au Guichet) */}
            {draft.paymentMethod === 'mobile_money' && (
                <div className="mobile-money-section">
                    <div className="mm-step">
                        <div className="mm-step-head">
                            <span className="mm-step-number">1</span>
                            <span className="mm-step-title">
                                <Smartphone className="size-3.5" />
                                Réseau &amp; compte agence{' '}
                                <span className="required-asterisk">*</span>
                            </span>
                        </div>

                        <div className="mm-step-body">
                            <div className="mb-1 flex flex-wrap items-center gap-1.5 border-b border-border/50 pb-2 text-xs">
                                <button
                                    type="button"
                                    className={cn(
                                        'cursor-pointer rounded-md px-2.5 py-1 text-xs font-medium transition',
                                        countryFilter === 'all'
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-muted-foreground hover:bg-muted/80',
                                    )}
                                    onClick={() => setCountryFilter('all')}
                                >
                                    Tous les pays (
                                    {Object.keys(NETWORK_META).length})
                                </button>
                                <button
                                    type="button"
                                    className={cn(
                                        'flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition',
                                        countryFilter === 'bj'
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-muted-foreground hover:bg-muted/80',
                                    )}
                                    onClick={() => setCountryFilter('bj')}
                                >
                                    <span>🇧🇯</span> Bénin
                                </button>
                                <button
                                    type="button"
                                    className={cn(
                                        'flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition',
                                        countryFilter === 'tg'
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-muted-foreground hover:bg-muted/80',
                                    )}
                                    onClick={() => setCountryFilter('tg')}
                                >
                                    <span>🇹🇬</span> Togo
                                </button>
                                <button
                                    type="button"
                                    className={cn(
                                        'flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition',
                                        countryFilter === 'ci'
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-muted-foreground hover:bg-muted/80',
                                    )}
                                    onClick={() => setCountryFilter('ci')}
                                >
                                    <span>🇨🇮</span> Côte d&apos;Ivoire
                                </button>
                            </div>

                            <div className="mm-network-grid">
                                {(
                                    Object.keys(
                                        NETWORK_META,
                                    ) as ParcelMobileMoneyNetwork[]
                                )
                                    .filter(
                                        (value) =>
                                            countryFilter === 'all' ||
                                            NETWORK_META[value].country ===
                                                countryFilter,
                                    )
                                    .map((value) => {
                                        const meta = NETWORK_META[value];
                                        const Icon = meta.icon;
                                        const isActive =
                                            draft.momoProvider === value;
                                        const count =
                                            AGENCY_MOMO_ACCOUNTS.filter(
                                                (a) => a.network === value,
                                            ).length;

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
                                                              borderColor:
                                                                  meta.color,
                                                              background:
                                                                  meta.bg,
                                                          }
                                                        : undefined
                                                }
                                                onClick={() =>
                                                    handleSelectNetwork(value)
                                                }
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
                                                    <strong className="flex items-center gap-1.5">
                                                        <span>{meta.flag}</span>
                                                        <span>
                                                            {meta.label}
                                                        </span>
                                                    </strong>
                                                    <small>
                                                        {count > 0
                                                            ? `${count} compte(s)`
                                                            : meta.countryName}
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
                                    value={draft.agencyPaymentAccountUuid}
                                    aria-invalid={
                                        attemptedSubmit &&
                                        !draft.agencyPaymentAccountUuid
                                    }
                                    onChange={(e) =>
                                        onChange({
                                            agencyPaymentAccountUuid:
                                                e.target.value,
                                        })
                                    }
                                    disabled={
                                        !draft.momoProvider ||
                                        networkAccounts.length === 1
                                    }
                                >
                                    <option value="">
                                        {draft.momoProvider
                                            ? networkAccounts.length > 0
                                                ? 'Sélectionner le compte'
                                                : 'Aucun compte configuré sur ce réseau (simulé)'
                                            : "Choisir le réseau d'abord"}
                                    </option>
                                    {networkAccounts.map((account) => (
                                        <option
                                            key={account.uuid}
                                            value={account.uuid}
                                        >
                                            {account.label} ·{' '}
                                            {account.accountIdentifier}
                                        </option>
                                    ))}
                                </select>
                                <small className="payment-field-help">
                                    Compte de l&apos;agence sur lequel le
                                    transfert doit arriver.
                                </small>
                                {attemptedSubmit &&
                                    !draft.agencyPaymentAccountUuid && (
                                        <small className="field-error-text">
                                            Sélectionnez un réseau et son compte
                                            agence associé.
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
                                    id="momo-phone-input"
                                    className={cn(
                                        'control phone-field-control',
                                        attemptedSubmit &&
                                            !payerPhoneValid &&
                                            'phone-field-control-invalid',
                                    )}
                                    defaultCountry="BJ"
                                    international
                                    labels={phoneLabels}
                                    value={effectivePayerPhone || undefined}
                                    onChange={(value) =>
                                        onChange({ momoPhone: value ?? '' })
                                    }
                                    numberInputProps={{
                                        placeholder: '+229 01 97 00 00 00',
                                        autoComplete: 'tel',
                                    }}
                                />
                                <small className="payment-field-help">
                                    Numéro Mobile Money du client qui envoie le
                                    transfert.
                                </small>
                                {attemptedSubmit && !payerPhoneValid && (
                                    <small className="field-error-text">
                                        Numéro Mobile Money du payeur requis.
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
                                    value={effectivePayerName}
                                    onChange={(e) =>
                                        onChange({ payerName: e.target.value })
                                    }
                                    autoComplete="name"
                                    placeholder="Nom du payeur"
                                />
                                <small className="payment-field-help">
                                    Tel qu&apos;affiché dans la notification SMS
                                    du réseau.
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
                                    id="momo-reference-input"
                                    className="control"
                                    value={draft.momoReference}
                                    aria-invalid={
                                        attemptedSubmit &&
                                        draft.momoReference.trim() === ''
                                    }
                                    onChange={(e) =>
                                        onChange({
                                            momoReference: e.target.value,
                                        })
                                    }
                                    placeholder="ex: MP240825.1234.A56789"
                                />
                                <small className="payment-field-help">
                                    Code de transaction envoyé par SMS au
                                    client.
                                </small>
                                {attemptedSubmit &&
                                    draft.momoReference.trim() === '' && (
                                        <small className="field-error-text">
                                            Référence de transaction requise.
                                        </small>
                                    )}
                            </label>

                            <div
                                className={cn(
                                    'mm-verify-card',
                                    draft.momoVerifiedManually && 'is-verified',
                                )}
                            >
                                <label className="mm-verify-check">
                                    <input
                                        type="checkbox"
                                        checked={draft.momoVerifiedManually}
                                        onChange={(e) =>
                                            onChange({
                                                momoVerifiedManually:
                                                    e.target.checked,
                                            })
                                        }
                                    />
                                    <span>
                                        <strong>
                                            J&apos;ai vérifié le crédit reçu{' '}
                                            <span className="required-asterisk">
                                                *
                                            </span>
                                        </strong>
                                        <small>
                                            Je confirme manuellement que le
                                            montant a été crédité sur le compte
                                            Mobile Money de l&apos;agence
                                            {selectedAgencyAccount
                                                ? ` (${selectedAgencyAccount.accountIdentifier})`
                                                : ''}
                                            .
                                        </small>
                                    </span>
                                </label>
                                {draft.momoVerifiedManually && (
                                    <CheckCircle2 className="mm-verify-icon size-5" />
                                )}
                            </div>
                            {attemptedSubmit && !draft.momoVerifiedManually && (
                                <small className="field-error-text">
                                    Cochez cette case après vérification du SMS
                                    de réception.
                                </small>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* 3 · CRÉDIT ENTREPRISE */}
            {draft.paymentMethod === 'company_account' && (
                <div className="company-credit-section mt-5 space-y-4">
                    <label className="field">
                        <span className="field-label">
                            <Building2 className="size-3.5" />
                            Compte Entreprise à imputer{' '}
                            <span className="required-asterisk">*</span>
                        </span>
                        <select
                            id="company-account-select"
                            value={draft.companyAccountId}
                            onChange={(e) =>
                                onChange({
                                    companyAccountId: e.target.value,
                                })
                            }
                            className="control"
                        >
                            {COMPANY_ACCOUNTS.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.label}
                                </option>
                            ))}
                        </select>
                    </label>

                    <div className="company-credit-info">
                        <div className="company-credit-header">
                            <Building2 className="size-5 text-gold" />
                            <div>
                                <strong>{selectedCompanyAccount.name}</strong>
                                <small>
                                    {selectedCompanyAccount.type === 'ngo'
                                        ? 'ONG'
                                        : selectedCompanyAccount.type ===
                                            'embassy'
                                          ? 'Ambassade'
                                          : 'Entreprise'}{' '}
                                    · Réf {selectedCompanyAccount.reference}
                                </small>
                            </div>
                        </div>
                        <div className="company-credit-amount">
                            <div className="summary-row">
                                <span>Montant à imputer</span>
                                <strong>{formatFcfa(total)}</strong>
                            </div>
                            <p className="company-credit-help">
                                Le crédit sera prélevé sur le plafond mensuel du
                                compte et consolidé sur le relevé de fin de
                                mois.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Actions identiques à Vente au Guichet */}
            <div className="checkout-actions payment-actions mt-5">
                <button
                    type="button"
                    onClick={onBack}
                    disabled={isSubmitting}
                    className="btn btn-ghost cursor-pointer"
                >
                    <ArrowLeft className="size-4" /> Retour aux détails
                </button>
                <button
                    type="submit"
                    disabled={isSubmitting}
                    className={cn(
                        'btn btn-primary cursor-pointer',
                        !canSubmit && 'opacity-85',
                    )}
                >
                    <LockKeyhole className="size-4" />
                    <span>{submitLabel}</span>
                    <kbd className="hidden rounded bg-black/15 px-1.5 py-0.5 font-mono text-[9px] sm:inline-block">
                        Ctrl+↵
                    </kbd>
                </button>
            </div>

            <div className="payment-security-badge">
                <ShieldCheck className="size-3.5" />
                Paiement sécurisé · Étiquette thermique &amp; reçu MECeF émis
                après confirmation
            </div>
        </form>
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
            className={cn('payment-choice cursor-pointer', active && 'is-active')}
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
