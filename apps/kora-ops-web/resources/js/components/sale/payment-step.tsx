import {
    ArrowLeft,
    Banknote,
    Building2,
    Check,
    CheckCircle2,
    Clock3,
    Coins,
    HandCoins,
    Hash,
    LockKeyhole,
    LoaderCircle,
    Phone,
    Receipt,
    ShieldCheck,
    Smartphone,
    UserRound,
    WalletCards,
    Search,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { toast } from 'sonner';
import { CashOpeningDialog } from '@/components/cash/cash-opening-dialog';
import { cn, uuid } from '@/lib/utils';
import { SaleJourneySummary } from './sale-journey-summary';
import type {
    SaleCashSession,
    SaleDeparture,
    SaleItineraryQuote,
    SaleMobileMoneyAccount,
    SaleMobileMoneyNetwork,
    SalePaymentMethod,
    SaleReservation,
    SaleSearch,
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
    wallet_uuid: string | null;
    wallet_amount: number | null;
    points_amount: number | null;
    wallet_holder_phone: string;
    wallet_holder_confirmed: boolean;
};

type WalletLookup = {
    uuid: string;
    holder: { name: string | null; phone: string | null };
    available: { cash: number; points: number };
    settings: {
        points_min_spend: number;
        wallet_cash_enabled: boolean;
        wallet_points_enabled: boolean;
    };
};

type Props = {
    departure: SaleDeparture | null;
    reservation: SaleReservation;
    cashSession: SaleCashSession;
    mobileMoneyAccounts: SaleMobileMoneyAccount[];
    canDebitWallet: boolean;
    processing: boolean;
    errors: Record<string, string>;
    onSubmit: (payload: CounterSalePaymentPayload) => void;
    onBack: () => void;
    onHoldExpired: () => void;
    search?: SaleSearch | null;
    itineraryQuote?: SaleItineraryQuote | null;
    isPartner?: boolean;
};

type MobileMoneyNetworkMeta = {
    label: string;
    color: string;
    bg: string;
    icon: LucideIcon;
    country: 'bj' | 'tg' | 'ci';
    countryName: string;
    flag: string;
};

const networkMeta: Record<SaleMobileMoneyNetwork, MobileMoneyNetworkMeta> = {
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
    canDebitWallet,
    processing,
    errors,
    onSubmit,
    onBack,
    onHoldExpired,
    search = null,
    itineraryQuote = null,
    isPartner = false,
}: Props) {
    const [method, setMethod] = useState<SalePaymentMethod>(
        reservation.payerType === 'company' && reservation.companyAccount
            ? 'company_credit'
            : 'cash',
    );
    const [cashReceived, setCashReceived] = useState('');
    const [network, setNetwork] = useState<SaleMobileMoneyNetwork | ''>('');
    const [accountUuid, setAccountUuid] = useState('');
    const [payerPhone, setPayerPhone] = useState('');
    const [payerName, setPayerName] = useState('');
    const [reference, setReference] = useState('');
    const [verified, setVerified] = useState(false);
    const [walletPhone, setWalletPhone] = useState('');
    const [wallet, setWallet] = useState<WalletLookup | null>(null);
    const [walletPointsAmount, setWalletPointsAmount] = useState(0);
    const [walletHolderConfirmed, setWalletHolderConfirmed] = useState(false);
    const [walletLookupLoading, setWalletLookupLoading] = useState(false);
    const [key] = useState(uuid);
    const [cashModalOpen, setCashModalOpen] = useState(false);
    const holdCountdown = useHoldCountdown(
        reservation.expiresAt,
        reservation.heldAt,
    );
    const expirationHandled = useRef(false);

    useEffect(() => {
        if (!holdCountdown.isExpired || expirationHandled.current) {
            return;
        }

        expirationHandled.current = true;
        onHoldExpired();
    }, [holdCountdown.isExpired, onHoldExpired]);

    // For partner points (and general convenience), prefill payer information
    // with primary passenger if available.
    const [prevPassengers, setPrevPassengers] = useState(
        reservation.passengers,
    );

    if (reservation.passengers !== prevPassengers) {
        setPrevPassengers(reservation.passengers);
        const primary = reservation.passengers?.[0];

        if (primary) {
            const name = `${primary.firstName} ${primary.lastName}`.trim();

            if (!payerName && name) {
                setPayerName(name);
            }

            if (!payerPhone && primary.phone) {
                setPayerPhone(primary.phone);
            }
        }
    }

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
    const phoneValid =
        payerPhone.trim() !== '' && isValidPhoneNumber(payerPhone);
    const cashReady =
        (cashSession.isOpen || isPartner) &&
        isCashSufficient &&
        payerName.trim() !== '' &&
        (!isPartner || phoneValid);
    const mobileReady =
        (cashSession.isOpen || isPartner) &&
        network !== '' &&
        (isPartner || accountUuid !== '') &&
        phoneValid &&
        reference.trim() !== '' &&
        verified;
    const isCompanyCredit =
        reservation.payerType === 'company' &&
        reservation.companyAccount !== null;
    const companyCreditReady = isCompanyCredit;
    const maximumPoints = wallet?.settings.wallet_points_enabled
        ? Math.min(wallet.available.points, total)
        : 0;
    const minimumPoints = wallet?.settings.points_min_spend ?? 100;
    const walletCashAmount = total - walletPointsAmount;
    const walletCanCoverTotal = Boolean(
        wallet &&
        wallet.settings.wallet_cash_enabled &&
        wallet.available.cash +
            (maximumPoints >= minimumPoints ? maximumPoints : 0) >=
            total &&
        walletPointsAmount <= maximumPoints &&
        (walletPointsAmount === 0 || walletPointsAmount >= minimumPoints) &&
        walletCashAmount <= wallet.available.cash,
    );
    const canSubmit =
        !holdCountdown.isExpired &&
        !processing &&
        (method === 'cash'
            ? cashReady
            : method === 'mobile_money'
              ? mobileReady
              : method === 'company_credit'
                ? companyCreditReady
                : method === 'wallet'
                  ? walletCanCoverTotal &&
                    walletHolderConfirmed &&
                    !walletLookupLoading
                  : false);

    const mobileChecklist = useMemo(
        () => [
            { label: 'Réseau sélectionné', done: network !== '' },
            ...(!isPartner
                ? [{ label: 'Compte agence choisi', done: accountUuid !== '' }]
                : []),
            { label: 'Téléphone du payeur saisi', done: phoneValid },
            {
                label: 'Référence de transaction',
                done: reference.trim() !== '',
            },
            {
                label: isPartner
                    ? 'Crédit vérifié sur votre compte'
                    : 'Crédit vérifié manuellement',
                done: verified,
            },
        ],
        [isPartner, network, accountUuid, phoneValid, reference, verified],
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

    const lookupWallet = async () => {
        if (!isValidPhoneNumber(walletPhone)) {
            toast.error('Saisissez un numéro de téléphone valide.');

            return;
        }

        setWalletLookupLoading(true);
        setWallet(null);
        setWalletHolderConfirmed(false);

        try {
            const url = new URL('/sale/wallet-lookup', window.location.origin);
            url.searchParams.set('phone', walletPhone);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('La recherche du Wallet a échoué.');
            }

            const result = (await response.json()) as {
                data: WalletLookup | null;
            };

            if (!result.data) {
                toast.error('Aucun Wallet n’est associé à ce numéro.');

                return;
            }

            setWallet(result.data);
            const suggestedPoints = Math.max(
                0,
                Math.min(
                    result.data.available.points,
                    total - result.data.available.cash,
                ),
            );
            setWalletPointsAmount(
                suggestedPoints > 0 &&
                    suggestedPoints < result.data.settings.points_min_spend
                    ? Math.min(
                          result.data.available.points,
                          result.data.settings.points_min_spend,
                      )
                    : suggestedPoints,
            );
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'La recherche du Wallet a échoué.',
            );
        } finally {
            setWalletLookupLoading(false);
        }
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
                method === 'mobile_money' && !isPartner ? accountUuid : null,
            payer_phone:
                method === 'mobile_money'
                    ? payerPhone.trim()
                    : method === 'cash'
                      ? payerPhone.trim()
                      : '',
            payer_name:
                method === 'mobile_money'
                    ? payerName.trim()
                    : method === 'cash'
                      ? payerName.trim()
                      : '',
            provider_reference:
                method === 'mobile_money' ? reference.trim() : '',
            verified_manually: method === 'mobile_money' ? verified : true,
            wallet_uuid: method === 'wallet' ? (wallet?.uuid ?? null) : null,
            wallet_amount: method === 'wallet' ? walletCashAmount : null,
            points_amount: method === 'wallet' ? walletPointsAmount : null,
            wallet_holder_phone:
                method === 'wallet'
                    ? (wallet?.holder.phone ?? walletPhone)
                    : '',
            wallet_holder_confirmed:
                method === 'wallet' ? walletHolderConfirmed : false,
        });
    };

    const submitLabel = processing
        ? 'Confirmation…'
        : method === 'cash'
          ? 'Encaisser les espèces'
          : method === 'mobile_money'
            ? 'Confirmer le transfert Mobile Money'
            : method === 'wallet'
              ? 'Confirmer le débit Wallet'
              : 'Imputer le crédit entreprise';

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

                    {!isPartner &&
                        !cashSession.isOpen &&
                        method !== 'company_credit' &&
                        method !== 'wallet' && (
                            <div className="cash-required-card" role="status">
                                <span className="cash-required-icon">
                                    <Banknote className="size-5" />
                                </span>
                                <div className="cash-required-copy">
                                    <strong>
                                        Votre caisse est encore fermée
                                    </strong>
                                    <p>
                                        Ouvrez la session du jour avec votre
                                        fond de caisse pour pouvoir enregistrer
                                        cette vente.
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
                        {canDebitWallet && (
                            <PaymentChoice
                                active={method === 'wallet'}
                                onClick={() => setMethod('wallet')}
                                icon={WalletCards}
                                title="Wallet KORA"
                                description="Solde cash et points"
                            />
                        )}
                        <PaymentChoice
                            active={method === 'mobile_money'}
                            onClick={() => setMethod('mobile_money')}
                            icon={Smartphone}
                            title="Mobile Money"
                            description="Vérification manuelle"
                        />
                        {isCompanyCredit && (
                            <PaymentChoice
                                active={method === 'company_credit'}
                                onClick={() => setMethod('company_credit')}
                                icon={Building2}
                                title="Crédit entreprise"
                                description={
                                    reservation.companyAccount?.name ??
                                    'Compte entreprise'
                                }
                            />
                        )}
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
                            payerName={payerName}
                            payerPhone={payerPhone}
                            onPayerNameChange={setPayerName}
                            onPayerPhoneChange={setPayerPhone}
                            isPartner={isPartner}
                        />
                    ) : method === 'mobile_money' ? (
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
                            isPartner={isPartner}
                        />
                    ) : method === 'wallet' ? (
                        <WalletPaymentFields
                            phone={walletPhone}
                            wallet={wallet}
                            pointsAmount={walletPointsAmount}
                            cashAmount={walletCashAmount}
                            total={total}
                            confirmed={walletHolderConfirmed}
                            loading={walletLookupLoading}
                            canCoverTotal={walletCanCoverTotal}
                            errors={errors}
                            onPhoneChange={(phone) => {
                                setWalletPhone(phone);
                                setWallet(null);
                                setWalletHolderConfirmed(false);
                            }}
                            onLookup={lookupWallet}
                            onPointsChange={setWalletPointsAmount}
                            onConfirmedChange={setWalletHolderConfirmed}
                        />
                    ) : (
                        <CompanyCreditFields
                            reservation={reservation}
                            total={total}
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
                    search={search}
                    itineraryQuote={itineraryQuote}
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
                    isPartner={isPartner}
                />
            </form>
            {cashModalOpen && (
                <CashOpeningDialog
                    accounts={mobileMoneyAccounts}
                    cashRegisters={cashSession.cashRegisters}
                    onClose={() => setCashModalOpen(false)}
                />
            )}
        </>
    );
}

function WalletPaymentFields({
    phone,
    wallet,
    pointsAmount,
    cashAmount,
    total,
    confirmed,
    loading,
    canCoverTotal,
    errors,
    onPhoneChange,
    onLookup,
    onPointsChange,
    onConfirmedChange,
}: {
    phone: string;
    wallet: WalletLookup | null;
    pointsAmount: number;
    cashAmount: number;
    total: number;
    confirmed: boolean;
    loading: boolean;
    canCoverTotal: boolean;
    errors: Record<string, string>;
    onPhoneChange: (phone: string) => void;
    onLookup: () => void;
    onPointsChange: (amount: number) => void;
    onConfirmedChange: (confirmed: boolean) => void;
}) {
    const minimumPoints = wallet?.settings.points_min_spend ?? 100;
    const maximumPoints = wallet?.settings.wallet_points_enabled
        ? Math.min(wallet.available.points, total)
        : 0;

    return (
        <div className="space-y-5 rounded-2xl border border-border bg-muted/30 p-4 sm:p-5">
            <div className="space-y-2">
                <label
                    htmlFor="wallet-holder-phone"
                    className="flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <Phone className="size-4 text-muted-foreground" />
                    Téléphone du titulaire
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                </label>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <PhoneInput
                        id="wallet-holder-phone"
                        international
                        defaultCountry="BJ"
                        labels={phoneLabels}
                        value={phone || undefined}
                        onChange={(value) => onPhoneChange(value ?? '')}
                        required
                        aria-required="true"
                        aria-invalid={Boolean(errors.wallet_holder_phone)}
                        className="flex-1"
                    />
                    <button
                        type="button"
                        className="btn btn-secondary shrink-0"
                        onClick={onLookup}
                        disabled={loading || !phone}
                    >
                        {loading ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <Search className="size-4" />
                        )}
                        Rechercher le Wallet
                    </button>
                </div>
                {errors.wallet_holder_phone && (
                    <p className="text-sm text-destructive" role="alert">
                        {errors.wallet_holder_phone}
                    </p>
                )}
            </div>

            {wallet && (
                <>
                    <section
                        className="rounded-xl border border-border bg-card p-4"
                        aria-live="polite"
                    >
                        <div className="flex items-start gap-3">
                            <span className="grid size-10 place-items-center rounded-xl bg-gold/15 text-gold">
                                <WalletCards className="size-5" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Titulaire trouvé
                                </p>
                                <p className="mt-1 font-bold text-foreground">
                                    {wallet.holder.name ?? 'Nom non renseigné'}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {wallet.holder.phone ?? phone}
                                </p>
                            </div>
                        </div>
                        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <BalanceTile
                                icon={WalletCards}
                                label="Cash disponible"
                                value={`${wallet.available.cash.toLocaleString('fr-FR')} FCFA`}
                            />
                            <BalanceTile
                                icon={Coins}
                                label="Points disponibles"
                                value={`${wallet.available.points.toLocaleString('fr-FR')} pts`}
                            />
                        </div>
                    </section>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl border border-border bg-card p-4">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Montant du billet
                            </p>
                            <strong className="mt-2 block text-lg text-foreground">
                                {total.toLocaleString('fr-FR')} FCFA
                            </strong>
                        </div>
                        <div className="space-y-2 rounded-xl border border-border bg-card p-4">
                            <label
                                htmlFor="wallet-points-amount"
                                className="flex items-center gap-2 text-sm font-semibold text-foreground"
                            >
                                <Coins className="size-4 text-muted-foreground" />
                                Points à utiliser
                            </label>
                            <input
                                id="wallet-points-amount"
                                type="number"
                                min={pointsAmount === 0 ? 0 : minimumPoints}
                                max={maximumPoints}
                                step={1}
                                value={pointsAmount}
                                onChange={(event) =>
                                    onPointsChange(
                                        Math.max(
                                            0,
                                            Number(event.target.value) || 0,
                                        ),
                                    )
                                }
                                aria-describedby="wallet-points-hint"
                                aria-invalid={Boolean(errors.points_amount)}
                                className="form-input w-full"
                            />
                            <p
                                id="wallet-points-hint"
                                className="text-xs text-muted-foreground"
                            >
                                Facultatif · minimum{' '}
                                {minimumPoints.toLocaleString('fr-FR')} points
                                quand utilisés.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center justify-between rounded-xl bg-kora px-4 py-3 text-white">
                        <span className="text-sm font-semibold">
                            Cash Wallet débité
                        </span>
                        <strong>
                            {cashAmount.toLocaleString('fr-FR')} FCFA
                        </strong>
                    </div>

                    {!canCoverTotal && (
                        <p
                            className="rounded-lg border border-amber-300/60 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
                            role="status"
                        >
                            Le solde disponible en cash et en points ne couvre
                            pas le montant total du billet.
                        </p>
                    )}

                    <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-gold/30 bg-gold/5 p-3 text-sm text-foreground">
                        <input
                            type="checkbox"
                            checked={confirmed}
                            onChange={(event) =>
                                onConfirmedChange(event.target.checked)
                            }
                            required
                            aria-required="true"
                            className="mt-0.5 size-4 accent-gold"
                        />
                        <span className="flex items-start gap-2">
                            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-gold" />
                            <span>
                                Le titulaire est présent, a vérifié le montant
                                et autorise le débit de son Wallet.
                                <span
                                    className="ml-1 text-destructive"
                                    aria-hidden="true"
                                >
                                    *
                                </span>
                            </span>
                        </span>
                    </label>
                    {errors.wallet_holder_confirmed && (
                        <p className="text-sm text-destructive" role="alert">
                            {errors.wallet_holder_confirmed}
                        </p>
                    )}
                </>
            )}
        </div>
    );
}

function BalanceTile({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-lg bg-muted/60 p-3">
            <span className="flex items-center gap-2 text-xs text-muted-foreground">
                <Icon className="size-3.5" /> {label}
            </span>
            <strong className="mt-1 block text-sm font-bold text-foreground">
                {value}
            </strong>
        </div>
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
    payerName,
    payerPhone,
    onPayerNameChange,
    onPayerPhoneChange,
    isPartner = false,
}: {
    total: number;
    currency: string;
    cashReceived: string;
    change: number;
    isSufficient: boolean;
    error?: string;
    onChange: (value: string) => void;
    onQuickAmount: (amount: number) => void;
    payerName: string;
    payerPhone: string;
    onPayerNameChange: (value: string) => void;
    onPayerPhoneChange: (value: string) => void;
    isPartner?: boolean;
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
                            type="text"
                            value={payerName}
                            onChange={(event) =>
                                onPayerNameChange(event.target.value)
                            }
                            placeholder="Nom du client"
                        />
                    </span>
                    <small className="payment-field-help">
                        Nom du client qui règle la vente (pour la facturation
                        DGI).
                    </small>
                </label>
                <label className="field">
                    <span className="field-label">
                        <Phone className="size-3.5" />
                        Téléphone du payeur{' '}
                        {isPartner ? (
                            <span className="required-asterisk">*</span>
                        ) : null}
                    </span>
                    <span className="payment-field-control">
                        <Phone
                            className="payment-field-icon"
                            aria-hidden="true"
                        />
                        <input
                            type="tel"
                            value={payerPhone}
                            onChange={(event) =>
                                onPayerPhoneChange(event.target.value)
                            }
                            placeholder="+229 ..."
                        />
                    </span>
                    <small className="payment-field-help">
                        {isPartner
                            ? 'Numéro de contact du client (requis pour le point partenaire).'
                            : 'Numéro de contact du client (optionnel).'}
                    </small>
                </label>
            </div>
        </div>
    );
}

function CompanyCreditFields({
    reservation,
    total,
}: {
    reservation: SaleReservation;
    total: number;
}) {
    const account = reservation.companyAccount;

    return (
        <div className="company-credit-section">
            <div className="company-credit-info">
                <div className="company-credit-header">
                    <Building2 className="size-5" />
                    <div>
                        <strong>
                            {account?.name ?? 'Compte personne morale'}
                        </strong>
                        <small>
                            {account?.type === 'ngo'
                                ? 'ONG'
                                : account?.type === 'embassy'
                                  ? 'Ambassade'
                                  : 'Entreprise'}{' '}
                            · Réf {account?.reference ?? '—'}
                        </small>
                    </div>
                </div>
                <div className="company-credit-amount">
                    <div className="summary-row">
                        <span>Montant à imputer</span>
                        <strong>
                            {formatMoney(total, reservation.currency)}
                        </strong>
                    </div>
                    <p className="company-credit-help">
                        Le crédit sera prélevé sur le plafond mensuel du compte.
                        La réservation a déjà été réservée lors du blocage des
                        places.
                    </p>
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
    isPartner = false,
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
    isPartner?: boolean;
}) {
    const [countryFilter, setCountryFilter] = useState<
        'all' | 'bj' | 'tg' | 'ci'
    >('all');

    return (
        <div className="mobile-money-section">
            <div className="mm-step">
                <div className="mm-step-head">
                    <span className="mm-step-number">1</span>
                    <span className="mm-step-title">
                        <Smartphone className="size-3.5" />
                        {isPartner
                            ? 'Réseau Mobile Money'
                            : 'Réseau & compte agence'}
                    </span>
                </div>
                <div className="mm-step-body">
                    <div className="mb-3 flex flex-wrap items-center gap-1.5 border-b border-border/50 pb-2 text-xs">
                        <button
                            type="button"
                            className={cn(
                                'rounded-md px-2.5 py-1 text-xs font-medium transition',
                                countryFilter === 'all'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-muted/80',
                            )}
                            onClick={() => setCountryFilter('all')}
                        >
                            Tous les pays ({Object.keys(networkMeta).length})
                        </button>
                        <button
                            type="button"
                            className={cn(
                                'flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition',
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
                                'flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition',
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
                                'flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition',
                                countryFilter === 'ci'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-muted/80',
                            )}
                            onClick={() => setCountryFilter('ci')}
                        >
                            <span>🇨🇮</span> Côte d’Ivoire
                        </button>
                    </div>

                    <div className="mm-network-grid">
                        {(Object.keys(networkMeta) as SaleMobileMoneyNetwork[])
                            .filter(
                                (value) =>
                                    countryFilter === 'all' ||
                                    networkMeta[value].country ===
                                        countryFilter,
                            )
                            .map((value) => {
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
                                            <strong className="flex items-center gap-1.5">
                                                <span>{meta.flag}</span>
                                                <span>{meta.label}</span>
                                            </strong>
                                            <small>
                                                {isPartner
                                                    ? meta.countryName
                                                    : `${
                                                          accounts.filter(
                                                              (a) =>
                                                                  a.network ===
                                                                  value,
                                                          ).length
                                                      } compte(s)`}
                                            </small>
                                        </span>
                                        {isActive && (
                                            <Check className="mm-network-check size-3.5" />
                                        )}
                                    </button>
                                );
                            })}
                    </div>
                    {isPartner ? (
                        <div className="mt-3 flex items-start gap-2.5 rounded-lg border border-primary/20 bg-primary/5 p-3 text-xs text-foreground/80">
                            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-primary" />
                            <div className="space-y-0.5">
                                <p className="font-semibold text-foreground">
                                    Encaissement direct partenaire KORA
                                </p>
                                <p className="leading-relaxed text-muted-foreground">
                                    Vous encaissez directement le montant sur
                                    votre propre téléphone ou compte marchand{' '}
                                    <strong>
                                        {network
                                            ? networkMeta[network].label
                                            : 'Mobile Money'}
                                    </strong>
                                    . Le montant net sera déduit de votre
                                    disponible KORA.
                                </p>
                            </div>
                        </div>
                    ) : (
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
                                Compte de l’agence sur lequel le transfert doit
                                arriver.
                            </small>
                            {errors.agency_payment_account_uuid && (
                                <small className="field-error-text">
                                    {errors.agency_payment_account_uuid}
                                </small>
                            )}
                        </label>
                    )}
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
                                    {isPartner
                                        ? 'Je confirme manuellement avoir bien reçu la somme convenue sur mon compte / téléphone partenaire.'
                                        : `Je confirme manuellement que le montant a été crédité sur le compte Mobile Money de l’agence${selectedAccount ? ` (${selectedAccount.accountIdentifier})` : ''}.`}
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
