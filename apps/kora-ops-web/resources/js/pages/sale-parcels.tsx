import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Keyboard,
    Loader2,
    RotateCcw,
    Ticket,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { SalePageHeading } from '@/components/sale/sale-page-heading';
import {
    buildFinalizedParcel,
    createParcel,
    departureToItinerary,
    describeApiError,
    fetchConfiguration,
    fetchPaymentOptions,
    fetchParcel,
    fetchQuote,
    fetchRouting,
    inspectParcel,
    payParcel,
    paymentAccounts,
    quoteToBreakdown,
} from '@/components/sale-parcels/api';
import type {
    ApiDeparture,
    ParcelConfiguration,
    ParcelPaymentOptions,
} from '@/components/sale-parcels/api';
import { ParcelCounterHero } from '@/components/sale-parcels/parcel-counter-hero';
import { ParcelStepDetailsWeighing } from '@/components/sale-parcels/parcel-step-details-weighing';
import { ParcelStepDispatch } from '@/components/sale-parcels/parcel-step-dispatch';
import { ParcelStepItineraries } from '@/components/sale-parcels/parcel-step-itineraries';
import { ParcelStepPayment } from '@/components/sale-parcels/parcel-step-payment';
import { ParcelStepStepper } from '@/components/sale-parcels/parcel-step-stepper';
import { ParcelSummaryCard } from '@/components/sale-parcels/parcel-summary-card';
import type {
    FinalizedParcel,
    ParcelHub,
    ParcelIntakeDraft,
    ParcelItinerary,
    ParcelQuoteBreakdown,
} from '@/components/sale-parcels/types';
import type { BreadcrumbItem } from '@/types';

const EMPTY_HUB: ParcelHub = {
    uuid: '',
    code: '—',
    name: 'Guichet Colis',
    city: '',
    region: '',
    type: 'agency',
    address: '',
    phone: '',
};

const EMPTY_QUOTE: ParcelQuoteBreakdown = {
    basePrice: 0,
    chargeableWeight: 0,
    weightSurcharge: 0,
    transferFee: 0,
    subtotalHt: 0,
    vat18: 0,
    totalTtc: 0,
};

function emptyDraft(originHub: ParcelHub): ParcelIntakeDraft {
    return {
        originHub,
        destinationHub: null,
        dispatchWindow: 'today',
        selectedItinerary: null,
        deferToNextAvailableCargo: false,
        senderName: '',
        senderPhone: '',
        recipientName: '',
        recipientPhone: '',
        notifyWhatsApp: true,
        notifySms: true,
        nature: 'standard',
        weightKg: 2.5,
        dimensions: '35 × 25 × 15 cm',
        declaredValueFcfa: 0,
        prohibitedAccepted: false,
        notes: '',
        paymentMethod: 'cash',
        cashTendered: 0,
        payerName: '',
        payerPhone: '',
        momoPhone: '',
        momoProvider: '',
        agencyPaymentAccountUuid: '',
        momoReference: '',
        momoVerifiedManually: false,
        companyAccountId: '',
    };
}

function initialCounterParams(): { mode: 'new' | 'lookup'; ref: string } {
    if (typeof window === 'undefined') {
        return { mode: 'new', ref: '' };
    }

    const params = new URLSearchParams(window.location.search);

    return {
        mode: params.get('mode') === 'lookup' ? 'lookup' : 'new',
        ref: params.get('ref') ?? '',
    };
}

const INTAKE_KEYS: Array<keyof ParcelIntakeDraft> = [
    'destinationHub',
    'selectedItinerary',
    'deferToNextAvailableCargo',
    'senderName',
    'senderPhone',
    'recipientName',
    'recipientPhone',
    'nature',
    'weightKg',
    'notes',
];

export default function SaleParcelsPage() {
    const { auth } = usePage().props;
    const initialParams = initialCounterParams();
    const [mode, setMode] = useState<'new' | 'lookup'>(initialParams.mode);
    const [step, setStep] = useState<number>(1);
    const [maxReachedStep, setMaxReachedStep] = useState<number>(1);
    const [configuration, setConfiguration] =
        useState<ParcelConfiguration | null>(null);
    const [originHub, setOriginHub] = useState<ParcelHub>(EMPTY_HUB);
    const [draft, setDraft] = useState<ParcelIntakeDraft>(
        emptyDraft(EMPTY_HUB),
    );
    const [itineraries, setItineraries] = useState<ParcelItinerary[]>([]);
    const [quote, setQuote] = useState<ParcelQuoteBreakdown>(EMPTY_QUOTE);
    const [paymentOptions, setPaymentOptions] =
        useState<ParcelPaymentOptions | null>(null);
    const [lookupQuery, setLookupQuery] = useState(initialParams.ref);
    const [finalized, setFinalized] = useState<FinalizedParcel | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [flowError, setFlowError] = useState<string | null>(null);
    const [paymentError, setPaymentError] = useState<string | null>(null);
    const [showShortcutsHelp, setShowShortcutsHelp] = useState(false);

    const destinationInputRef = useRef<HTMLInputElement | null>(null);
    const lookupInputRef = useRef<HTMLInputElement | null>(null);
    const weightInputRef = useRef<HTMLInputElement | null>(null);
    const intakeRef = useRef<{
        fingerprint: string;
        parcelUuid: string;
    } | null>(null);
    const paymentKeyRef = useRef<string | null>(null);

    useEffect(() => {
        let active = true;

        fetchConfiguration()
            .then((config) => {
                if (!active) {
                    return;
                }

                const hubFromCounter =
                    config.hubs.find(
                        (hub) =>
                            hub.uuid === config.counter.hub_uuid &&
                            hub.deposit_allowed,
                    ) ??
                    config.hubs.find(
                        (hub) =>
                            hub.deposit_allowed &&
                            hub.agency_uuid === config.counter.agency_uuid,
                    ) ??
                    config.hubs.find((hub) => hub.deposit_allowed);

                if (!hubFromCounter) {
                    setFlowError(
                        'Aucun guichet Colis actif n’est rattaché à votre agence.',
                    );

                    return;
                }

                const mapped = {
                    uuid: hubFromCounter.uuid,
                    code: `HUB-${hubFromCounter.uuid.slice(0, 4).toUpperCase()}`,
                    name:
                        hubFromCounter.name ??
                        hubFromCounter.address ??
                        hubFromCounter.city ??
                        'Guichet Colis',
                    city: hubFromCounter.city ?? '',
                    region: hubFromCounter.country ?? '',
                    type:
                        hubFromCounter.kind === 'partner_point'
                            ? 'partner_point'
                            : 'agency',
                    address: hubFromCounter.address ?? '',
                    phone: '',
                    isFrequent: true,
                } as ParcelHub;

                setConfiguration(config);
                setOriginHub(mapped);
                setDraft((previous) => ({
                    ...previous,
                    originHub: mapped,
                }));
            })
            .catch((error) => {
                if (active) {
                    setFlowError(describeApiError(error));
                }
            })
            .finally(() => {
                if (active) {
                    setIsLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    const hubMap = useMemo(() => {
        const list = configuration?.hubs ?? [];

        return new Map(
            list.map((hub) => [
                hub.uuid,
                {
                    uuid: hub.uuid,
                    code: `HUB-${hub.uuid.slice(0, 4).toUpperCase()}`,
                    name: hub.name ?? hub.address ?? hub.city ?? 'Hub colis',
                    city: hub.city ?? '',
                    region: hub.country ?? '',
                    type:
                        hub.kind === 'partner_point'
                            ? 'partner_point'
                            : 'agency',
                    address: hub.address ?? '',
                    phone: '',
                    isFrequent: hub.kind === 'agency',
                } as ParcelHub,
            ]),
        );
    }, [configuration]);

    const destinationHubs = useMemo(() => {
        if (!configuration || originHub.uuid === '') {
            return [];
        }

        const destinationUuids = configuration.corridors
            .filter(
                (corridor) =>
                    corridor.enabled &&
                    corridor.origin_hub_uuid === originHub.uuid,
            )
            .map((corridor) => corridor.destination_hub_uuid);

        return [...new Set(destinationUuids)]
            .map((uuid) => hubMap.get(uuid))
            .filter((hub): hub is ParcelHub => Boolean(hub));
    }, [configuration, hubMap, originHub.uuid]);

    const isPartner = configuration?.counter.is_partner === true;

    const handleUpdateDraft = useCallback(
        (updates: Partial<ParcelIntakeDraft>) => {
            const intakeChanged = Object.keys(updates).some((key) =>
                INTAKE_KEYS.includes(key as keyof ParcelIntakeDraft),
            );

            if (intakeChanged) {
                intakeRef.current = null;
                paymentKeyRef.current = null;
                setPaymentOptions(null);
                setPaymentError(null);
                setFinalized(null);
            }

            if (
                'destinationHub' in updates ||
                'selectedItinerary' in updates ||
                'weightKg' in updates ||
                'nature' in updates
            ) {
                setQuote(EMPTY_QUOTE);
            }

            setDraft((previous) => ({ ...previous, ...updates }));
        },
        [],
    );

    const handleSelectDestination = useCallback(
        async (hub: ParcelHub | null) => {
            intakeRef.current = null;
            paymentKeyRef.current = null;
            setPaymentOptions(null);
            setPaymentError(null);
            setFinalized(null);
            setQuote(EMPTY_QUOTE);

            if (!hub) {
                setDraft((previous) => ({
                    ...previous,
                    destinationHub: null,
                    selectedItinerary: null,
                    deferToNextAvailableCargo: false,
                }));
                setItineraries([]);

                return;
            }

            setDraft((previous) => ({
                ...previous,
                destinationHub: hub,
                selectedItinerary: null,
                deferToNextAvailableCargo: false,
            }));
            setFlowError(null);
            setItineraries([]);

            try {
                const departures = await fetchRouting(originHub.uuid, hub.uuid);
                const mapped = departures.map(
                    (departure: ApiDeparture, index: number) => ({
                        ...departureToItinerary(departure, hub.uuid),
                        isNextDeparture: index === 0,
                    }),
                );
                const today = mapped.filter(
                    (itinerary) => itinerary.dayOffset === 0,
                );
                const defaultItinerary = today[0] ?? mapped[0] ?? null;

                setItineraries(mapped);
                setDraft((previous) => ({
                    ...previous,
                    selectedItinerary: defaultItinerary,
                    dispatchWindow:
                        defaultItinerary?.dayOffset === 1
                            ? 'tomorrow'
                            : 'today',
                }));
            } catch (error) {
                setDraft((previous) => ({
                    ...previous,
                    selectedItinerary: null,
                }));
                setFlowError(
                    `Aucun départ n’a pu être chargé pour ${hub.city}. ${describeApiError(error)}`,
                );
            }
        },
        [originHub.uuid],
    );

    const loadPaymentStep = useCallback(async () => {
        if (!draft.destinationHub) {
            setFlowError('Choisissez d’abord une destination.');

            return;
        }

        const weightGrams = Math.round(draft.weightKg * 1000);

        if (weightGrams <= 0) {
            setFlowError('Le poids doit être supérieur à 0 kg.');

            return;
        }

        if (
            !draft.deferToNextAvailableCargo &&
            draft.selectedItinerary &&
            draft.weightKg > draft.selectedItinerary.minAvailableCargoKg
        ) {
            setFlowError(
                `La soute de ce départ ne peut accueillir que ${draft.selectedItinerary.minAvailableCargoKg} kg. Choisissez un autre départ.`,
            );

            return;
        }

        setIsSubmitting(true);
        setFlowError(null);
        setPaymentError(null);

        try {
            const fingerprint = JSON.stringify({
                destination: draft.destinationHub.uuid,
                weightGrams,
                senderName: draft.senderName,
                senderPhone: draft.senderPhone,
                recipientName: draft.recipientName,
                recipientPhone: draft.recipientPhone,
                notes: draft.notes,
                nature: draft.nature,
            });

            const freshQuote = await fetchQuote({
                deposit_hub_uuid: originHub.uuid,
                destination_hub_uuid: draft.destinationHub.uuid,
                weight_grams: weightGrams,
            });
            setQuote(quoteToBreakdown(freshQuote));

            if (intakeRef.current?.fingerprint !== fingerprint) {
                const created = await createParcel({
                    deposit_hub_uuid: originHub.uuid,
                    destination_hub_uuid: draft.destinationHub.uuid,
                    weight_grams: weightGrams,
                    sender_name: draft.senderName.trim(),
                    sender_phone: draft.senderPhone,
                    recipient_name: draft.recipientName.trim(),
                    recipient_phone: draft.recipientPhone,
                    contents:
                        draft.notes.trim() ||
                        `${draft.nature} — ${draft.weightKg} kg`,
                    idempotency_key: crypto.randomUUID(),
                });
                intakeRef.current = {
                    fingerprint,
                    parcelUuid: created.uuid,
                };
            }

            if (!intakeRef.current) {
                throw new Error('Le colis n’a pas pu être préparé.');
            }

            await inspectParcel(intakeRef.current.parcelUuid, weightGrams);

            const options = await fetchPaymentOptions(
                intakeRef.current.parcelUuid,
            );
            setPaymentOptions(options);
            setStep(3);
            setMaxReachedStep(3);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error) {
            setFlowError(describeApiError(error));
        } finally {
            setIsSubmitting(false);
        }
    }, [draft, originHub.uuid]);

    const handleFinalizeIntake = useCallback(async () => {
        if (!intakeRef.current || !paymentOptions || !draft.destinationHub) {
            setPaymentError(
                'Le dossier de paiement n’est pas prêt. Revenez à l’étape précédente.',
            );

            return;
        }

        setIsSubmitting(true);
        setPaymentError(null);

        try {
            const parcelUuid = intakeRef.current.parcelUuid;
            const amount = quote.totalTtc;
            paymentKeyRef.current ??= crypto.randomUUID();
            const isCash = draft.paymentMethod === 'cash';

            await payParcel(parcelUuid, {
                idempotency_key: paymentKeyRef.current,
                method: isCash ? 'cash' : 'mobile_money',
                amount,
                cash_received_amount: isCash ? draft.cashTendered : undefined,
                mobile_money_network: isCash
                    ? undefined
                    : draft.momoProvider || undefined,
                agency_payment_account_uuid:
                    !isCash && !isPartner && draft.agencyPaymentAccountUuid
                        ? draft.agencyPaymentAccountUuid
                        : undefined,
                provider_reference: isCash
                    ? undefined
                    : draft.momoReference.trim(),
                payer_name: draft.payerName || draft.senderName,
                payer_phone:
                    draft.payerPhone || draft.momoPhone || draft.senderPhone,
            });

            const parcel = await fetchParcel(parcelUuid);
            const finalizedDraft = {
                ...draft,
                originHub,
                paymentMethod: isCash ? 'cash' : 'mobile_money',
            } as ParcelIntakeDraft;
            const result = buildFinalizedParcel(
                parcel,
                finalizedDraft,
                quote,
                auth.user?.name ?? 'Agent guichet',
                originHub.name,
            );

            setFinalized(result);
            setMaxReachedStep(3);
            toast.success(`Colis ${result.trackingNumber} enregistré.`);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error) {
            paymentKeyRef.current = null;
            setPaymentError(describeApiError(error));
        } finally {
            setIsSubmitting(false);
        }
    }, [auth.user, draft, originHub, isPartner, paymentOptions, quote]);

    const goToStep = useCallback((nextStep: number) => {
        setFlowError(null);
        setStep(nextStep);
        setMaxReachedStep((previous) => Math.max(previous, nextStep));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, []);

    const handleResetAll = useCallback(() => {
        intakeRef.current = null;
        paymentKeyRef.current = null;
        setDraft(emptyDraft(originHub));
        setItineraries([]);
        setQuote(EMPTY_QUOTE);
        setPaymentOptions(null);
        setFinalized(null);
        setFlowError(null);
        setPaymentError(null);
        setStep(1);
        setMaxReachedStep(1);
        setMode('new');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, [originHub]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            const isTyping =
                target?.tagName === 'INPUT' ||
                target?.tagName === 'TEXTAREA' ||
                target?.tagName === 'SELECT';

            if (event.key === '?' && !isTyping) {
                event.preventDefault();
                setShowShortcutsHelp((visible) => !visible);

                return;
            }

            if (event.altKey && event.key.toLowerCase() === 'n') {
                event.preventDefault();
                handleResetAll();
                setTimeout(() => destinationInputRef.current?.focus(), 60);
                toast.info('Nouvel envoi initialisé (Alt+N)');

                return;
            }

            if (event.altKey && event.key.toLowerCase() === 'd') {
                event.preventDefault();
                setFinalized(null);
                setMode('new');
                setStep(1);
                setTimeout(() => destinationInputRef.current?.focus(), 60);

                return;
            }

            if (event.altKey && event.key.toLowerCase() === 'w') {
                event.preventDefault();

                if (mode === 'new' && step === 2) {
                    weightInputRef.current?.focus();
                    weightInputRef.current?.select();
                }

                return;
            }

            if (event.altKey && event.key.toLowerCase() === 'p' && finalized) {
                event.preventDefault();
                window.print();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [finalized, handleResetAll, mode, step]);

    if (isLoading) {
        return (
            <main className="flex min-h-[50vh] items-center justify-center gap-3 text-sm text-muted-foreground">
                <Loader2 className="size-5 animate-spin text-gold" />
                Chargement du guichet Colis…
            </main>
        );
    }

    if (flowError && !configuration) {
        return (
            <main className="mx-auto max-w-xl px-4 py-16">
                <div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-6 text-sm">
                    <div className="flex items-center gap-2 font-bold text-destructive">
                        <TriangleAlert className="size-5" />
                        Guichet indisponible
                    </div>
                    <p className="mt-2 leading-relaxed text-foreground">
                        {flowError}
                    </p>
                    <Link href="/sale/parcels" className="btn btn-ghost mt-4">
                        <ArrowLeft className="size-4" /> Retour à la liste
                    </Link>
                </div>
            </main>
        );
    }

    return (
        <>
            <Head title="Guichet Colis · Vente au guichet" />

            <main className="w-full max-w-full overflow-x-hidden pb-12">
                <div className="no-print">
                    <SalePageHeading
                        eyebrow="KORA OPS / Vente · Guichet Colis"
                        title={
                            isPartner
                                ? 'Envoi de colis au point partenaire'
                                : 'Envoi de colis au guichet'
                        }
                        description={
                            isPartner
                                ? 'Enregistrez l’envoi, affectez le car puis encaissez le colis au point partenaire.'
                                : 'Recherchez une destination, affectez le car selon sa soute disponible puis encaissez le colis au comptoir.'
                        }
                        action={
                            <div className="flex flex-wrap items-center gap-2">
                                <Link
                                    href="/sale/parcels"
                                    className="btn btn-ghost btn-sm"
                                >
                                    <ArrowLeft className="size-3.5 text-gold" />
                                    <span>Liste des colis</span>
                                </Link>

                                <ParcelButton
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setShowShortcutsHelp(
                                            (visible) => !visible,
                                        )
                                    }
                                    title="Raccourcis clavier (?)"
                                >
                                    <Keyboard className="size-3.5 text-gold" />
                                    <span>Raccourcis (?)</span>
                                </ParcelButton>

                                <Link
                                    href="/sale"
                                    className="btn btn-ghost btn-sm"
                                >
                                    <Ticket className="size-3.5 text-gold" />
                                    <span>Billetterie Passagers</span>
                                </Link>

                                {(draft.destinationHub || finalized) && (
                                    <ParcelButton
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleResetAll}
                                    >
                                        <RotateCcw className="size-3.5" />
                                        <span>Réinitialiser (Alt+N)</span>
                                    </ParcelButton>
                                )}
                            </div>
                        }
                    />
                </div>

                {showShortcutsHelp && (
                    <div className="no-print mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gold/40 bg-gold/10 px-4 py-3 text-xs text-foreground">
                        <div className="flex flex-wrap items-center gap-4">
                            <span className="font-extrabold">
                                Raccourcis guichet :
                            </span>
                            <span>Alt+N Nouvel envoi</span>
                            <span>Alt+D Destination</span>
                            <span>Alt+W Pesée</span>
                            <span>Alt+P Imprimer</span>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowShortcutsHelp(false)}
                            aria-label="Fermer l'aide raccourcis"
                            className="rounded-md p-1 hover:bg-black/10"
                        >
                            <X className="size-3.5" />
                        </button>
                    </div>
                )}

                {mode === 'new' && (
                    <ParcelStepStepper
                        currentStep={step}
                        onStepClick={goToStep}
                        maxReachedStep={maxReachedStep}
                        isFinalized={finalized !== null}
                    />
                )}

                {finalized === null && mode === 'new' && (
                    <div className="mb-6">
                        <ParcelCounterHero
                            originHub={originHub}
                            destinationHubs={destinationHubs}
                            mode={mode}
                            onModeChange={(nextMode) => {
                                setMode(nextMode);

                                if (nextMode === 'lookup') {
                                    setFlowError(
                                        'Le pré-enregistrement client se consulte depuis le registre Colis.',
                                    );
                                }
                            }}
                            selectedDestination={draft.destinationHub}
                            onSelectDestination={handleSelectDestination}
                            dispatchWindow={draft.dispatchWindow}
                            onDispatchWindowChange={(window) =>
                                handleUpdateDraft({ dispatchWindow: window })
                            }
                            lookupQuery={lookupQuery}
                            onLookupQueryChange={setLookupQuery}
                            pendingCount={0}
                            destinationInputRef={destinationInputRef}
                            lookupInputRef={lookupInputRef}
                        />
                    </div>
                )}

                {mode === 'lookup' ? (
                    <div className="rounded-2xl border border-border bg-card p-10 text-center">
                        <TriangleAlert className="mx-auto size-8 text-gold" />
                        <h2 className="mt-3 text-base font-bold text-foreground">
                            Reprise d’un pré-enregistrement
                        </h2>
                        <p className="mx-auto mt-1 max-w-md text-xs leading-relaxed text-muted-foreground">
                            Ouvrez le registre Colis pour retrouver un colis
                            déjà déclaré par un client, puis poursuivez sa pesée
                            et son encaissement.
                        </p>
                        <div className="mt-4 flex justify-center gap-2">
                            <Link
                                href="/sale/parcels"
                                className="btn btn-primary btn-sm"
                            >
                                Ouvrir le registre
                            </Link>
                            <ParcelButton
                                variant="ghost"
                                size="sm"
                                onClick={() => setMode('new')}
                            >
                                Nouvel envoi
                            </ParcelButton>
                        </div>
                    </div>
                ) : finalized !== null ? (
                    <ParcelStepDispatch
                        finalized={finalized}
                        onReset={handleResetAll}
                    />
                ) : (
                    <div className="checkout-layout">
                        <div className="min-w-0">
                            {step === 1 && (
                                <ParcelStepItineraries
                                    draft={draft}
                                    itineraries={itineraries}
                                    onSelectItinerary={(itinerary) =>
                                        handleUpdateDraft({
                                            selectedItinerary: itinerary,
                                        })
                                    }
                                    onToggleDefer={(defer) =>
                                        handleUpdateDraft({
                                            deferToNextAvailableCargo: defer,
                                        })
                                    }
                                    onChangeWindow={(window) =>
                                        handleUpdateDraft({
                                            dispatchWindow: window,
                                        })
                                    }
                                    onNext={() => goToStep(2)}
                                />
                            )}

                            {step === 2 && (
                                <ParcelStepDetailsWeighing
                                    draft={draft}
                                    itineraries={itineraries}
                                    knownClients={[]}
                                    onChange={handleUpdateDraft}
                                    onNext={loadPaymentStep}
                                    onBack={() => goToStep(1)}
                                    weightInputRef={weightInputRef}
                                />
                            )}

                            {step === 3 && (
                                <ParcelStepPayment
                                    draft={draft}
                                    quote={quote}
                                    accounts={
                                        paymentOptions
                                            ? paymentAccounts(paymentOptions)
                                            : []
                                    }
                                    cashSessionOpen={
                                        paymentOptions?.cash_session !== null
                                    }
                                    isPartner={
                                        paymentOptions?.is_partner ?? isPartner
                                    }
                                    submitError={paymentError ?? flowError}
                                    onChange={handleUpdateDraft}
                                    onSubmit={handleFinalizeIntake}
                                    onBack={() => goToStep(2)}
                                    isSubmitting={isSubmitting}
                                />
                            )}

                            {flowError && step !== 3 && (
                                <div
                                    role="alert"
                                    className="mt-3 flex items-start gap-2 rounded-xl border border-destructive/40 bg-destructive/10 px-3 py-2.5 text-xs text-destructive"
                                >
                                    <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                    <span className="leading-relaxed">
                                        {flowError}
                                    </span>
                                </div>
                            )}
                        </div>

                        <ParcelSummaryCard
                            draft={draft}
                            quote={quote}
                            currentStep={step}
                            onGoToStep={goToStep}
                        />
                    </div>
                )}
            </main>
        </>
    );
}

SaleParcelsPage.layout = {
    breadcrumbs: [
        { title: 'Vente', href: '/sale' },
        { title: 'Colis', href: '/sale/parcels' },
        { title: 'Enregistrer un colis', href: '/sale/parcels/new' },
    ] as BreadcrumbItem[],
};
