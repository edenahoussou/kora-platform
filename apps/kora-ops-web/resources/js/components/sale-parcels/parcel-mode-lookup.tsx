import {
    AlertTriangle,
    Banknote,
    CheckCircle2,
    Package,
    Repeat2,
    Scale,
    Smartphone,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { cn } from '@/lib/utils';
import {
    calculateParcelPricing,
    getItinerariesForHub,
    PARCEL_NATURE_OPTIONS,
    PRE_REGISTERED_PARCELS,
} from './mock-data';
import type {
    FinalizedParcel,
    ParcelIntakeDraft,
    ParcelMobileMoneyNetwork,
    PaymentMethod,
    PreRegisteredParcel,
} from './types';

interface Props {
    searchQuery: string;
    onFinalize: (finalized: FinalizedParcel) => void;
    onSwitchToNew: () => void;
}

export function ParcelModeLookup({
    searchQuery,
    onFinalize,
    onSwitchToNew,
}: Props) {
    const [selectedParcel, setSelectedParcel] =
        useState<PreRegisteredParcel | null>(PRE_REGISTERED_PARCELS[0] ?? null);
    const [actualWeightKg, setActualWeightKg] = useState<number>(
        PRE_REGISTERED_PARCELS[0]?.declaredWeightKg ?? 4.5,
    );
    const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('cash');
    const [momoProvider, setMomoProvider] =
        useState<ParcelMobileMoneyNetwork>('mtn_momo');
    const [cashTendered, setCashTendered] = useState<number>(5000);

    const filteredParcels = useMemo(() => {
        const q = searchQuery.trim().toLowerCase();

        if (!q) {
            return PRE_REGISTERED_PARCELS;
        }

        return PRE_REGISTERED_PARCELS.filter(
            (p) =>
                p.trackingNumber.toLowerCase().includes(q) ||
                p.senderPhone.toLowerCase().includes(q) ||
                p.senderName.toLowerCase().includes(q) ||
                p.recipientName.toLowerCase().includes(q),
        );
    }, [searchQuery]);

    const suggestedItinerary = useMemo(() => {
        if (!selectedParcel) {
            return null;
        }

        const list = getItinerariesForHub(selectedParcel.destinationHub);

        return (
            list.find((i) => i.minAvailableCargoKg >= actualWeightKg) ??
            list[0] ??
            null
        );
    }, [selectedParcel, actualWeightKg]);

    const declaredQuote = useMemo(() => {
        if (!selectedParcel) {
            return null;
        }

        return calculateParcelPricing(
            selectedParcel.nature,
            selectedParcel.declaredWeightKg,
            selectedParcel.destinationHub,
            suggestedItinerary,
        );
    }, [selectedParcel, suggestedItinerary]);

    const actualQuote = useMemo(() => {
        if (!selectedParcel) {
            return null;
        }

        return calculateParcelPricing(
            selectedParcel.nature,
            actualWeightKg,
            selectedParcel.destinationHub,
            suggestedItinerary,
        );
    }, [selectedParcel, actualWeightKg, suggestedItinerary]);

    const handleSelectParcel = (parcel: PreRegisteredParcel) => {
        setSelectedParcel(parcel);
        setActualWeightKg(parcel.declaredWeightKg);
        const estQuote = calculateParcelPricing(
            parcel.nature,
            parcel.declaredWeightKg,
            parcel.destinationHub,
            null,
        );
        setCashTendered(Math.ceil(estQuote.totalTtc / 1000) * 1000);
    };

    const isPrepaid = selectedParcel?.paymentStatus === 'prepaid_online';
    const weightDelta = selectedParcel
        ? Number((actualWeightKg - selectedParcel.declaredWeightKg).toFixed(2))
        : 0;
    const priceDelta =
        actualQuote && declaredQuote
            ? actualQuote.totalTtc - declaredQuote.totalTtc
            : 0;

    // Si prépayé en ligne mais poids réel > déclaré => supplément à percevoir
    const amountDueAtCounter = isPrepaid
        ? Math.max(0, priceDelta)
        : (actualQuote?.totalTtc ?? 0);

    const isCashSufficient =
        amountDueAtCounter === 0 ||
        paymentMethod !== 'cash' ||
        cashTendered >= amountDueAtCounter;

    const cargoExceeded =
        suggestedItinerary !== null &&
        actualWeightKg > suggestedItinerary.minAvailableCargoKg;

    const natureOption = PARCEL_NATURE_OPTIONS.find(
        (n) => n.id === selectedParcel?.nature,
    );

    const handleConfirmIntake = () => {
        if (!selectedParcel || !actualQuote) {
            return;
        }

        const draft: ParcelIntakeDraft = {
            originHub: selectedParcel.originHub,
            destinationHub: selectedParcel.destinationHub,
            dispatchWindow: 'today',
            selectedItinerary: suggestedItinerary,
            deferToNextAvailableCargo: cargoExceeded,
            senderName: selectedParcel.senderName,
            senderPhone: selectedParcel.senderPhone,
            recipientName: selectedParcel.recipientName,
            recipientPhone: selectedParcel.recipientPhone,
            notifyWhatsApp: true,
            notifySms: true,
            nature: selectedParcel.nature,
            weightKg: actualWeightKg,
            dimensions: '30 × 20 × 15 cm',
            declaredValueFcfa: selectedParcel.declaredValueFcfa,
            prohibitedAccepted: true,
            notes: selectedParcel.notes,
            paymentMethod:
                amountDueAtCounter === 0 ? 'mobile_money' : paymentMethod,
            cashTendered: amountDueAtCounter === 0 ? 0 : cashTendered,
            payerName: selectedParcel.senderName,
            payerPhone: selectedParcel.senderPhone,
            momoPhone: selectedParcel.senderPhone,
            momoProvider,
            agencyPaymentAccountUuid: 'acc-mtn-01',
            momoReference: 'PREREG-COUNTER',
            momoVerifiedManually: true,
            companyAccountId: 'c-01',
            preRegistrationUuid: selectedParcel.uuid,
        };

        const finalized = {
            trackingNumber: selectedParcel.trackingNumber,
            parcelUuid: selectedParcel.uuid,
            labelUrl: '#',
            receiptUrl: '#',
            receiptParcel: null,
            issuedAt: new Date().toLocaleString('fr-FR', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }),
            draft,
            quote: actualQuote,
            receiptNumber: `CA-${Date.now().toString().slice(-6)}`,
            agentName: 'Eden A. (Guichet)',
            agentSite: selectedParcel.originHub.name,
        } as unknown as FinalizedParcel;

        toast.success(
            `Pré-enregistrement ${selectedParcel.trackingNumber} confirmé !`,
        );
        onFinalize(finalized);
    };

    return (
        <div className="grid animate-in grid-cols-1 gap-6 duration-200 fade-in slide-in-from-bottom-2 lg:grid-cols-12">
            {/* Pending Queue List (5 cols) */}
            <div className="space-y-3 lg:col-span-5">
                <div className="flex items-center justify-between">
                    <span className="section-kicker">
                        File d&apos;attente dépôt ({filteredParcels.length})
                    </span>
                    <button
                        type="button"
                        onClick={onSwitchToNew}
                        className="text-[11px] font-bold text-digital hover:underline"
                    >
                        + Nouvel envoi direct
                    </button>
                </div>

                {filteredParcels.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-border bg-card p-8 text-center text-xs text-muted-foreground">
                        Aucun pré-enregistrement ne correspond à « {searchQuery}{' '}
                        ».
                    </div>
                ) : (
                    <div className="space-y-2.5">
                        {filteredParcels.map((parcel) => {
                            const active = selectedParcel?.uuid === parcel.uuid;
                            const paid =
                                parcel.paymentStatus === 'prepaid_online';

                            return (
                                <button
                                    key={parcel.uuid}
                                    type="button"
                                    onClick={() => handleSelectParcel(parcel)}
                                    className={cn(
                                        'w-full cursor-pointer rounded-[1.125rem] border p-4 text-left transition-all',
                                        active
                                            ? 'border-gold bg-gold/8 shadow-sm ring-2 ring-gold/75'
                                            : 'border-border bg-card hover:border-gold/50',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-xs font-black text-foreground">
                                                    {parcel.trackingNumber}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'rounded-full px-2 py-0.5 text-[9px] font-extrabold',
                                                        paid
                                                            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                                            : 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
                                                    )}
                                                >
                                                    {paid
                                                        ? 'Prépayé en ligne'
                                                        : 'À encaisser'}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-xs font-bold text-foreground">
                                                {parcel.senderName} →{' '}
                                                {parcel.recipientName}
                                            </p>
                                            <p className="text-[11px] text-muted-foreground">
                                                {parcel.originHub.city} →{' '}
                                                {parcel.destinationHub.city} (
                                                {parcel.destinationHub.name})
                                            </p>
                                        </div>

                                        <div className="text-right">
                                            <span className="font-mono text-xs font-black text-foreground">
                                                {parcel.declaredWeightKg} kg
                                            </span>
                                            <p className="text-[9px] text-muted-foreground">
                                                déclaré
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Detail, Scale Verification & Counter Payment (7 cols) */}
            <div className="lg:col-span-7">
                {selectedParcel && actualQuote ? (
                    <div className="space-y-4 rounded-[1.125rem] border border-border bg-card p-5 shadow-xs">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/70 pb-3">
                            <div className="flex items-center gap-2.5">
                                <span className="flex size-9 items-center justify-center rounded-xl bg-kora text-gold">
                                    <Package className="size-4" />
                                </span>
                                <div>
                                    <h3 className="text-sm font-black text-foreground">
                                        {selectedParcel.trackingNumber} ·{' '}
                                        {selectedParcel.destinationHub.city}
                                    </h3>
                                    <p className="text-[11px] text-muted-foreground">
                                        {selectedParcel.createdAt} ·{' '}
                                        {natureOption?.label}
                                    </p>
                                </div>
                            </div>

                            {suggestedItinerary && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-[10px] font-bold text-foreground">
                                    {suggestedItinerary.legs.length > 1 && (
                                        <Repeat2 className="size-3 text-digital" />
                                    )}
                                    Car{' '}
                                    {suggestedItinerary.legs[0].departureTime} (
                                    {suggestedItinerary.minAvailableCargoKg} kg
                                    soute)
                                </span>
                            )}
                        </div>

                        {/* Actors */}
                        <div className="grid grid-cols-2 gap-3 rounded-xl bg-muted/40 p-3 text-xs">
                            <div>
                                <span className="text-[9px] font-extrabold text-muted-foreground uppercase">
                                    Expéditeur
                                </span>
                                <p className="font-bold text-foreground">
                                    {selectedParcel.senderName}
                                </p>
                                <p className="text-muted-foreground">
                                    {selectedParcel.senderPhone}
                                </p>
                            </div>
                            <div>
                                <span className="text-[9px] font-extrabold text-muted-foreground uppercase">
                                    Destinataire
                                </span>
                                <p className="font-bold text-foreground">
                                    {selectedParcel.recipientName}
                                </p>
                                <p className="text-muted-foreground">
                                    {selectedParcel.recipientPhone}
                                </p>
                            </div>
                        </div>

                        {/* Scale Verification */}
                        <div className="space-y-3 rounded-2xl border border-border bg-muted/25 p-4">
                            <div className="flex items-center justify-between">
                                <span className="flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                                    <Scale className="size-4 text-gold" />
                                    Pesée réelle de contrôle au guichet
                                </span>
                                <span className="text-[11px] text-muted-foreground">
                                    Déclaré client :{' '}
                                    <strong>
                                        {selectedParcel.declaredWeightKg} kg
                                    </strong>
                                </span>
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:items-center">
                                <label className="field">
                                    <span className="field-label">
                                        Poids réel mesuré (kg){' '}
                                        <span className="required-asterisk">
                                            *
                                        </span>
                                    </span>
                                    <input
                                        type="number"
                                        min="0.1"
                                        step="0.1"
                                        value={actualWeightKg || ''}
                                        onChange={(e) =>
                                            setActualWeightKg(
                                                Math.max(
                                                    0.1,
                                                    Number(e.target.value),
                                                ),
                                            )
                                        }
                                        className="control font-mono! text-base! font-black!"
                                    />
                                </label>

                                <div className="rounded-xl border border-border bg-card px-3 py-2 text-xs">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Écart poids :
                                        </span>
                                        <strong
                                            className={cn(
                                                weightDelta > 0
                                                    ? 'text-amber-600 dark:text-amber-400'
                                                    : 'text-emerald-600 dark:text-emerald-400',
                                            )}
                                        >
                                            {weightDelta > 0
                                                ? `+${weightDelta} kg`
                                                : weightDelta < 0
                                                  ? `${weightDelta} kg`
                                                  : 'Exact'}
                                        </strong>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Ajustement TTC :
                                        </span>
                                        <strong className="text-foreground">
                                            {priceDelta > 0
                                                ? `+${priceDelta.toLocaleString('fr-FR')} FCFA`
                                                : priceDelta < 0
                                                  ? `${priceDelta.toLocaleString('fr-FR')} FCFA`
                                                  : '0 FCFA'}
                                        </strong>
                                    </div>
                                </div>
                            </div>

                            {cargoExceeded && (
                                <div className="flex items-center gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-2 text-[11px] text-amber-800 dark:text-amber-300">
                                    <AlertTriangle className="size-4 shrink-0" />
                                    <span>
                                        Poids réel supérieur à la soute du car
                                        suggéré : le colis basculera en dépôt
                                        prioritaire prochain car.
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Payment at counter if needed */}
                        {amountDueAtCounter > 0 && (
                            <div className="space-y-3 rounded-2xl border border-border bg-muted/25 p-4">
                                <span className="text-[11px] font-extrabold text-foreground uppercase">
                                    {isPrepaid
                                        ? `Supplément surpoids à encaisser : ${amountDueAtCounter.toLocaleString('fr-FR')} FCFA`
                                        : `Encaissement au dépôt : ${amountDueAtCounter.toLocaleString('fr-FR')} FCFA`}
                                </span>

                                <div className="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setPaymentMethod('cash')}
                                        className={cn(
                                            'payment-choice min-h-14! cursor-pointer',
                                            paymentMethod === 'cash' &&
                                                'is-active',
                                        )}
                                    >
                                        <span className="payment-choice-icon">
                                            <Banknote className="size-4" />
                                        </span>
                                        <span className="text-xs font-extrabold">
                                            Espèces
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setPaymentMethod('mobile_money')
                                        }
                                        className={cn(
                                            'payment-choice min-h-14! cursor-pointer',
                                            paymentMethod === 'mobile_money' &&
                                                'is-active',
                                        )}
                                    >
                                        <span className="payment-choice-icon">
                                            <Smartphone className="size-4" />
                                        </span>
                                        <span className="text-xs font-extrabold">
                                            Mobile Money
                                        </span>
                                    </button>
                                </div>

                                {paymentMethod === 'cash' ? (
                                    <div className="grid grid-cols-2 items-center gap-3">
                                        <label className="field">
                                            <span className="field-label">
                                                Montant remis (FCFA){' '}
                                                <span className="required-asterisk">
                                                    *
                                                </span>
                                            </span>
                                            <input
                                                type="number"
                                                step="500"
                                                value={cashTendered || ''}
                                                onChange={(e) =>
                                                    setCashTendered(
                                                        Number(e.target.value),
                                                    )
                                                }
                                                className="control font-mono! font-black!"
                                            />
                                        </label>
                                        <div className="text-right text-xs">
                                            <span className="text-muted-foreground">
                                                Monnaie à rendre :
                                            </span>
                                            <p className="text-base font-black text-foreground">
                                                {Math.max(
                                                    0,
                                                    cashTendered -
                                                        amountDueAtCounter,
                                                ).toLocaleString('fr-FR')}{' '}
                                                FCFA
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setMomoProvider('mtn_momo')
                                            }
                                            className={cn(
                                                'flex-1 cursor-pointer rounded-lg border py-1.5 text-xs font-bold',
                                                momoProvider === 'mtn_momo'
                                                    ? 'border-gold bg-gold/15'
                                                    : 'border-border bg-card',
                                            )}
                                        >
                                            MTN MoMo
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setMomoProvider('moov_flooz')
                                            }
                                            className={cn(
                                                'flex-1 cursor-pointer rounded-lg border py-1.5 text-xs font-bold',
                                                momoProvider === 'moov_flooz'
                                                    ? 'border-digital bg-digital/15'
                                                    : 'border-border bg-card',
                                            )}
                                        >
                                            Moov Money
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Final CTA */}
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border/70 pt-3">
                            <div>
                                <span className="text-[10px] font-bold text-muted-foreground uppercase">
                                    Total à percevoir au guichet
                                </span>
                                <div className="text-xl font-black text-foreground">
                                    {amountDueAtCounter === 0 ? (
                                        <span className="text-emerald-600 dark:text-emerald-400">
                                            0 FCFA (Déjà payé)
                                        </span>
                                    ) : (
                                        <span>
                                            {amountDueAtCounter.toLocaleString(
                                                'fr-FR',
                                            )}{' '}
                                            FCFA
                                        </span>
                                    )}
                                </div>
                            </div>

                            <ParcelButton
                                variant="success"
                                disabled={!isCashSufficient}
                                onClick={handleConfirmIntake}
                                className="min-h-11! px-5! text-xs!"
                            >
                                <CheckCircle2 className="size-4" />
                                <span>
                                    Valider prise en charge &amp; Étiquette
                                </span>
                            </ParcelButton>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-2xl border border-dashed border-border bg-card p-10 text-center text-xs text-muted-foreground">
                        Sélectionnez un pré-enregistrement dans la liste de
                        gauche.
                    </div>
                )}
            </div>
        </div>
    );
}
