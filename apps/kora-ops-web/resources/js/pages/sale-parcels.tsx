import { Head, Link } from '@inertiajs/react';
import { Keyboard, RotateCcw, Ticket, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { isValidPhoneNumber } from 'react-phone-number-input';
import { toast } from 'sonner';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { SalePageHeading } from '@/components/sale/sale-page-heading';
import {
    CURRENT_COUNTER_HUB,
    INITIAL_INTAKE_DRAFT,
    PRE_REGISTERED_PARCELS,
    calculateParcelPricing,
    getItinerariesForHub,
} from '@/components/sale-parcels/mock-data';
import { ParcelCounterHero } from '@/components/sale-parcels/parcel-counter-hero';
import { ParcelModeLookup } from '@/components/sale-parcels/parcel-mode-lookup';
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
} from '@/components/sale-parcels/types';
import type { BreadcrumbItem } from '@/types';

export default function SaleParcelsPage() {
    const [mode, setMode] = useState<'new' | 'lookup'>('new');
    const [step, setStep] = useState<number>(1);
    const [maxReachedStep, setMaxReachedStep] = useState<number>(1);
    const [draft, setDraft] = useState<ParcelIntakeDraft>(INITIAL_INTAKE_DRAFT);
    const [lookupQuery, setLookupQuery] = useState('');
    const [finalized, setFinalized] = useState<FinalizedParcel | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showShortcutsHelp, setShowShortcutsHelp] = useState(false);

    const destinationInputRef = useRef<HTMLInputElement | null>(null);
    const lookupInputRef = useRef<HTMLInputElement | null>(null);
    const weightInputRef = useRef<HTMLInputElement | null>(null);

    const handleUpdateDraft = useCallback(
        (updates: Partial<ParcelIntakeDraft>) => {
            setDraft((prev) => ({ ...prev, ...updates }));
        },
        [],
    );

    const handleSelectDestination = useCallback((hub: ParcelHub | null) => {
        if (!hub) {
            setDraft((prev) => ({
                ...prev,
                destinationHub: null,
                selectedItinerary: null,
                deferToNextAvailableCargo: false,
            }));

            return;
        }

        const itineraries = getItinerariesForHub(hub);
        const todayItineraries = itineraries.filter((i) => i.dayOffset === 0);
        const nextWindow = todayItineraries.length > 0 ? 'today' : 'tomorrow';
        const defaultItinerary =
            (todayItineraries.length > 0 ? todayItineraries : itineraries)[0] ??
            null;

        setDraft((prev) => ({
            ...prev,
            destinationHub: hub,
            dispatchWindow: nextWindow,
            selectedItinerary: defaultItinerary,
            deferToNextAvailableCargo: false,
        }));
    }, []);

    const goToStep = useCallback((nextStep: number) => {
        setStep(nextStep);
        setMaxReachedStep((prev) => Math.max(prev, nextStep));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, []);

    const handleResetAll = useCallback(() => {
        setDraft(INITIAL_INTAKE_DRAFT);
        setFinalized(null);
        setStep(1);
        setMaxReachedStep(1);
        setMode('new');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, []);

    const handleFinalizeIntake = useCallback(() => {
        setIsSubmitting(true);

        const quote = calculateParcelPricing(
            draft.nature,
            draft.weightKg,
            draft.destinationHub,
            draft.selectedItinerary,
        );

        const trackingNumber = `KC-20261125-${Math.floor(1000 + Math.random() * 9000)}`;
        const securityCode = `KP-${Math.floor(1000 + Math.random() * 9000)}`;

        const finalizedParcel: FinalizedParcel = {
            trackingNumber,
            securityCode,
            issuedAt: new Date().toLocaleString('fr-FR', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }),
            draft,
            quote,
            receiptNumber: `CA-${Date.now().toString().slice(-6)}`,
            agentName: 'Eden A. (Guichet)',
            agentSite: draft.originHub.name,
        };

        setTimeout(() => {
            setFinalized(finalizedParcel);
            setStep(3);
            setMaxReachedStep(3);
            setIsSubmitting(false);
            toast.success(
                `Colis ${trackingNumber} enregistré. Étiquette prête.`,
            );
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 300);
    }, [draft]);

    // Global counter keyboard shortcuts
    useEffect(() => {
        const onKeyDown = (e: KeyboardEvent) => {
            const target = e.target as HTMLElement | null;
            const isTyping =
                target?.tagName === 'INPUT' ||
                target?.tagName === 'TEXTAREA' ||
                target?.tagName === 'SELECT';

            // '?' outside inputs toggles shortcuts overlay
            if (e.key === '?' && !isTyping) {
                e.preventDefault();
                setShowShortcutsHelp((v) => !v);

                return;
            }

            if (e.altKey && e.key.toLowerCase() === 'n') {
                e.preventDefault();
                handleResetAll();
                setTimeout(() => destinationInputRef.current?.focus(), 60);
                toast.info('Nouvel envoi initialisé (Alt+N)');

                return;
            }

            if (e.altKey && e.key.toLowerCase() === 'r') {
                e.preventDefault();
                setFinalized(null);
                setMode('lookup');
                setTimeout(() => lookupInputRef.current?.focus(), 60);

                return;
            }

            if (e.altKey && e.key.toLowerCase() === 'd') {
                e.preventDefault();
                setFinalized(null);
                setMode('new');
                setStep(1);
                setTimeout(() => destinationInputRef.current?.focus(), 60);

                return;
            }

            if (e.altKey && e.key.toLowerCase() === 'w') {
                e.preventDefault();

                if (mode === 'new' && step === 2) {
                    weightInputRef.current?.focus();
                    weightInputRef.current?.select();
                } else if (
                    mode === 'new' &&
                    step === 1 &&
                    draft.destinationHub &&
                    (draft.selectedItinerary || draft.deferToNextAvailableCargo)
                ) {
                    goToStep(2);
                    setTimeout(() => {
                        weightInputRef.current?.focus();
                        weightInputRef.current?.select();
                    }, 80);
                }

                return;
            }

            if (e.altKey && e.key.toLowerCase() === 'p' && finalized) {
                e.preventDefault();
                window.print();

                return;
            }

            // Ctrl+Enter advances step when valid
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && !finalized) {
                e.preventDefault();

                if (mode === 'new') {
                    if (
                        step === 1 &&
                        draft.destinationHub &&
                        (draft.selectedItinerary ||
                            draft.deferToNextAvailableCargo)
                    ) {
                        goToStep(2);
                    } else if (step === 2) {
                        const cargoOk =
                            draft.deferToNextAvailableCargo ||
                            !draft.selectedItinerary ||
                            draft.weightKg <=
                                draft.selectedItinerary.minAvailableCargoKg;

                        const senderPhoneOk =
                            draft.senderPhone.trim() !== '' &&
                            isValidPhoneNumber(draft.senderPhone);
                        const recipientPhoneOk =
                            draft.recipientPhone.trim() !== '' &&
                            isValidPhoneNumber(draft.recipientPhone);
                        const insuredValueOk =
                            draft.nature !== 'express_valeur' ||
                            draft.declaredValueFcfa > 0;

                        if (
                            senderPhoneOk &&
                            draft.senderName.trim().length >= 2 &&
                            recipientPhoneOk &&
                            draft.recipientName.trim().length >= 2 &&
                            draft.weightKg > 0 &&
                            insuredValueOk &&
                            draft.prohibitedAccepted &&
                            cargoOk
                        ) {
                            goToStep(3);
                        } else {
                            toast.error(
                                'Complétez les champs obligatoires et vérifiez la soute avant de continuer.',
                            );
                        }
                    } else if (step === 3 && !isSubmitting) {
                        const quote = calculateParcelPricing(
                            draft.nature,
                            draft.weightKg,
                            draft.destinationHub,
                            draft.selectedItinerary,
                        );

                        const payerNameOk =
                            (draft.payerName || draft.senderName).trim()
                                .length >= 2;
                        const effectiveMomoPhone =
                            draft.momoPhone ||
                            draft.payerPhone ||
                            draft.senderPhone;
                        const momoPhoneOk =
                            effectiveMomoPhone.trim() !== '' &&
                            isValidPhoneNumber(effectiveMomoPhone);

                        const paymentReady =
                            draft.paymentMethod === 'cash'
                                ? draft.cashTendered >= quote.totalTtc &&
                                  payerNameOk
                                : draft.paymentMethod === 'mobile_money'
                                  ? draft.momoProvider !== '' &&
                                    draft.agencyPaymentAccountUuid !== '' &&
                                    momoPhoneOk &&
                                    draft.momoReference.trim() !== '' &&
                                    draft.momoVerifiedManually
                                  : Boolean(draft.companyAccountId);

                        if (paymentReady) {
                            handleFinalizeIntake();
                        } else {
                            toast.error(
                                'Complétez les informations de paiement requises avant de valider.',
                            );
                        }
                    }
                }
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [
        draft,
        finalized,
        goToStep,
        handleFinalizeIntake,
        handleResetAll,
        isSubmitting,
        mode,
        step,
    ]);

    return (
        <>
            <Head title="Guichet Colis · Vente au guichet" />

            <main className="w-full max-w-full overflow-x-hidden pb-12">
                {/* Kora Native Page Heading */}
                <div className="no-print">
                    <SalePageHeading
                        eyebrow="KORA OPS / Vente · Guichet Colis"
                        title="Envoi de colis au guichet"
                        description="Recherchez une destination, affectez le car selon sa soute disponible ou finalisez un pré-enregistrement client."
                        action={
                            <div className="flex flex-wrap items-center gap-2">
                                <ParcelButton
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setShowShortcutsHelp((v) => !v)
                                    }
                                    title="Raccourcis clavier (?)"
                                >
                                    <Keyboard className="size-3.5 text-gold" />
                                    <span>Raccourcis (?)</span>
                                </ParcelButton>

                                <Link href="/sale" className="btn btn-ghost btn-sm">
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

                {/* Keyboard Shortcuts Memo Strip */}
                {showShortcutsHelp && (
                    <div className="no-print mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gold/40 bg-gold/10 px-4 py-3 text-xs text-foreground">
                        <div className="flex flex-wrap items-center gap-4">
                            <span className="font-extrabold">
                                Raccourcis guichet :
                            </span>
                            <span>
                                <kbd className="rounded bg-card px-1.5 py-0.5 font-mono font-bold border border-border">
                                    Alt+N
                                </kbd>{' '}
                                Nouvel envoi
                            </span>
                            <span>
                                <kbd className="rounded bg-card px-1.5 py-0.5 font-mono font-bold border border-border">
                                    Alt+R
                                </kbd>{' '}
                                Pré-enregistrement
                            </span>
                            <span>
                                <kbd className="rounded bg-card px-1.5 py-0.5 font-mono font-bold border border-border">
                                    Alt+D
                                </kbd>{' '}
                                Destination
                            </span>
                            <span>
                                <kbd className="rounded bg-card px-1.5 py-0.5 font-mono font-bold border border-border">
                                    Alt+W
                                </kbd>{' '}
                                Pesée
                            </span>
                            <span>
                                <kbd className="rounded bg-card px-1.5 py-0.5 font-mono font-bold border border-border">
                                    Ctrl+↵
                                </kbd>{' '}
                                Étape suivante
                            </span>
                            <span>
                                <kbd className="rounded bg-card px-1.5 py-0.5 font-mono font-bold border border-border">
                                    Alt+P
                                </kbd>{' '}
                                Imprimer
                            </span>
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

                {/* Progress Bar (New Shipment Mode) */}
                {mode === 'new' && (
                    <ParcelStepStepper
                        currentStep={step}
                        onStepClick={goToStep}
                        maxReachedStep={maxReachedStep}
                        isFinalized={finalized !== null}
                    />
                )}

                {/* Deep Navy Kora Hero (hidden once finalized) */}
                {finalized === null && (
                    <div className="mb-6">
                        <ParcelCounterHero
                            originHub={CURRENT_COUNTER_HUB}
                            mode={mode}
                            onModeChange={(nextMode) => {
                                setMode(nextMode);
                                setFinalized(null);
                            }}
                            selectedDestination={draft.destinationHub}
                            onSelectDestination={handleSelectDestination}
                            dispatchWindow={draft.dispatchWindow}
                            onDispatchWindowChange={(w) =>
                                handleUpdateDraft({ dispatchWindow: w })
                            }
                            lookupQuery={lookupQuery}
                            onLookupQueryChange={setLookupQuery}
                            pendingCount={PRE_REGISTERED_PARCELS.length}
                            destinationInputRef={destinationInputRef}
                            lookupInputRef={lookupInputRef}
                        />
                    </div>
                )}

                {/* Main Content */}
                {finalized !== null ? (
                    <ParcelStepDispatch
                        finalized={finalized}
                        onReset={handleResetAll}
                    />
                ) : mode === 'lookup' ? (
                    <ParcelModeLookup
                        searchQuery={lookupQuery}
                        onFinalize={(result) => {
                            setFinalized(result);
                            setStep(3);
                            setMaxReachedStep(3);
                        }}
                        onSwitchToNew={() => setMode('new')}
                    />
                ) : (
                    /* 2-Column Kora Checkout Layout: Active Step (Left) + Persistent Live Summary (Right) */
                    <div className="checkout-layout">
                        <div className="min-w-0">
                            {step === 1 && (
                                <ParcelStepItineraries
                                    draft={draft}
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
                                    onChangeWindow={(w) =>
                                        handleUpdateDraft({ dispatchWindow: w })
                                    }
                                    onNext={() => goToStep(2)}
                                />
                            )}

                            {step === 2 && (
                                <ParcelStepDetailsWeighing
                                    draft={draft}
                                    onChange={handleUpdateDraft}
                                    onNext={() => goToStep(3)}
                                    onBack={() => goToStep(1)}
                                    weightInputRef={weightInputRef}
                                />
                            )}

                            {step === 3 && (
                                <ParcelStepPayment
                                    draft={draft}
                                    onChange={handleUpdateDraft}
                                    onSubmit={handleFinalizeIntake}
                                    onBack={() => goToStep(2)}
                                    isSubmitting={isSubmitting}
                                />
                            )}
                        </div>

                        <ParcelSummaryCard
                            draft={draft}
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
        { title: 'Guichet colis', href: '/sale/parcels' },
    ] as BreadcrumbItem[],
};
