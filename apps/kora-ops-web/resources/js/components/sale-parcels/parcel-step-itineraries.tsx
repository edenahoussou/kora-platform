import {
    ArrowRight,
    BusFront,
    CalendarClock,
    CheckCircle2,
    Clock,
    MapPin,
    Repeat2,
    Truck,
} from 'lucide-react';
import { useMemo } from 'react';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { cn } from '@/lib/utils';
import { getItinerariesForHub } from './mock-data';
import type { ParcelIntakeDraft, ParcelItinerary } from './types';

interface Props {
    draft: ParcelIntakeDraft;
    onSelectItinerary: (itinerary: ParcelItinerary | null) => void;
    onToggleDefer: (defer: boolean) => void;
    onChangeWindow: (window: 'today' | 'tomorrow' | 'all') => void;
    onNext: () => void;
}

export function ParcelStepItineraries({
    draft,
    onSelectItinerary,
    onToggleDefer,
    onChangeWindow,
    onNext,
}: Props) {
    const allItineraries = useMemo(() => {
        if (!draft.destinationHub) {
return [];
}

        return getItinerariesForHub(draft.destinationHub);
    }, [draft.destinationHub]);

    const visibleItineraries = useMemo(() => {
        if (draft.dispatchWindow === 'all') {
return allItineraries;
}

        const targetOffset = draft.dispatchWindow === 'today' ? 0 : 1;

        return allItineraries.filter((i) => i.dayOffset === targetOffset);
    }, [allItineraries, draft.dispatchWindow]);

    const tomorrowCount = useMemo(
        () => allItineraries.filter((i) => i.dayOffset === 1).length,
        [allItineraries],
    );

    if (!draft.destinationHub) {
        return (
            <div className="rounded-2xl border border-dashed border-border bg-card p-10 text-center">
                <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-kora/10 text-kora dark:bg-gold/15 dark:text-gold">
                    <MapPin className="size-6" />
                </div>
                <h2 className="text-base font-bold text-foreground">
                    Sélectionnez une destination ci-dessus
                </h2>
                <p className="mx-auto mt-1 max-w-md text-xs leading-relaxed text-muted-foreground">
                    Choisissez l&apos;agence ou le point partenaire de retrait dans
                    le bandeau bleu KORA (raccourci{' '}
                    <kbd className="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px]">
                        Alt+D
                    </kbd>
                    ) pour charger automatiquement les cars directs, correspondances
                    et places en soute.
                </p>
            </div>
        );
    }

    const canContinue =
        draft.selectedItinerary !== null || draft.deferToNextAvailableCargo;

    return (
        <section className="departure-results animate-in fade-in slide-in-from-bottom-2 duration-200">
            <div className="results-head">
                <div>
                    <span className="section-kicker">
                        {draft.originHub.city} → {draft.destinationHub.city} ·{' '}
                        {visibleItineraries.length} itinéraire(s)
                    </span>
                    <h2 className="results-title">
                        <BusFront className="size-5 text-gold" />
                        <span>
                            {draft.dispatchWindow === 'today'
                                ? "Départs disponibles aujourd'hui"
                                : draft.dispatchWindow === 'tomorrow'
                                  ? 'Départs prévus demain'
                                  : "Départs aujourd'hui & demain"}{' '}
                            <span className="required-asterisk">*</span>
                        </span>
                        <ArrowRight className="size-4 text-gold" />
                    </h2>
                </div>

                <span className="hidden rounded-full border border-border bg-card px-3 py-1 text-[11px] font-bold text-foreground sm:inline-flex sm:items-center sm:gap-1.5">
                    <Truck className="size-3.5 text-gold" />
                    Soute vérifiée en direct
                </span>
            </div>

            {/* Case: No departures left today -> immediate fallback suggestion to Tomorrow */}
            {visibleItineraries.length === 0 &&
            draft.dispatchWindow === 'today' ? (
                <div className="rounded-2xl border border-gold/40 bg-gold/10 p-5 text-foreground">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-kora text-gold">
                                <CalendarClock className="size-5" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-foreground">
                                    Aucun départ disponible aujourd&apos;hui vers{' '}
                                    {draft.destinationHub.city}
                                </h3>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Les derniers cars du jour ont déjà quitté
                                    l&apos;agence.{' '}
                                    {tomorrowCount > 0
                                        ? `${tomorrowCount} départ(s) programmé(s) dès demain matin.`
                                        : 'Vous pouvez enregistrer le colis en file prioritaire.'}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            {tomorrowCount > 0 && (
                                <ParcelButton
                                    variant="primary"
                                    onClick={() => onChangeWindow('tomorrow')}
                                >
                                    <span>
                                        Voir les départs de demain (
                                        {tomorrowCount})
                                    </span>
                                    <ArrowRight className="size-3.5" />
                                </ParcelButton>
                            )}
                            <ParcelButton
                                variant="ghost"
                                onClick={() => {
                                    onToggleDefer(true);
                                    onSelectItinerary(null);
                                }}
                            >
                                Mettre en attente de prochain car
                            </ParcelButton>
                        </div>
                    </div>
                </div>
            ) : (
                <div className="space-y-3">
                    {visibleItineraries.map((itinerary) => {
                        const isSelected =
                            !draft.deferToNextAvailableCargo &&
                            draft.selectedItinerary?.uuid === itinerary.uuid;
                        const isTransfer = itinerary.legs.length > 1;
                        const firstLeg = itinerary.legs[0];
                        const lastLeg =
                            itinerary.legs[itinerary.legs.length - 1];
                        const cargoLow = itinerary.minAvailableCargoKg <= 15;

                        return (
                            <button
                                key={itinerary.uuid}
                                type="button"
                                onClick={() => {
                                    onToggleDefer(false);
                                    onSelectItinerary(itinerary);
                                }}
                                className={cn(
                                    'w-full cursor-pointer rounded-[1.125rem] border bg-card p-4 text-left transition-all',
                                    isSelected
                                        ? 'border-gold bg-gold/5 shadow-md ring-2 ring-gold/75'
                                        : 'border-border hover:border-gold/60 hover:shadow-xs',
                                )}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    {/* Left: Times + Route + Transfer badge */}
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="rounded-md bg-kora px-2 py-0.5 text-[10px] font-extrabold text-gold">
                                                {itinerary.dayLabel}
                                            </span>
                                            <span className="text-lg font-black tracking-tight text-foreground">
                                                {firstLeg.departureTime} →{' '}
                                                {lastLeg.arrivalTime}
                                            </span>
                                            <span className="text-xs font-medium text-muted-foreground">
                                                ({itinerary.totalDurationLabel})
                                            </span>

                                            {itinerary.isNextDeparture && (
                                                <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-300">
                                                    Prochain départ
                                                </span>
                                            )}

                                            {isTransfer ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-digital/10 px-2 py-0.5 text-[10px] font-bold text-digital">
                                                    <Repeat2 className="size-3" />
                                                    1 correspondance à{' '}
                                                    {itinerary.transferHubCity}
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold text-muted-foreground">
                                                    Trajet direct
                                                </span>
                                            )}
                                        </div>

                                        {/* Legs detail strip */}
                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                            {itinerary.legs.map((leg, idx) => (
                                                <div
                                                    key={leg.uuid}
                                                    className="flex items-center gap-2"
                                                >
                                                    <div className="rounded-lg border border-border/80 bg-muted/40 px-2.5 py-1.5">
                                                        <div className="flex items-center gap-1.5 font-bold text-foreground">
                                                            <span>
                                                                {leg.originCity}{' '}
                                                                →{' '}
                                                                {
                                                                    leg.destinationCity
                                                                }
                                                            </span>
                                                            <span className="font-mono text-[10px] text-muted-foreground">
                                                                ({leg.departureRef}{' '}
                                                                ·{' '}
                                                                {
                                                                    leg.busRegistration
                                                                }
                                                                )
                                                            </span>
                                                        </div>
                                                        <div className="mt-0.5 flex items-center gap-2 text-[10px] text-muted-foreground">
                                                            <span>
                                                                {leg.departureTime}{' '}
                                                                –{' '}
                                                                {leg.arrivalTime}
                                                            </span>
                                                            <span>•</span>
                                                            <span
                                                                className={cn(
                                                                    'font-bold',
                                                                    leg.availableCargoKg <=
                                                                        15
                                                                        ? 'text-amber-600 dark:text-amber-400'
                                                                        : 'text-emerald-600 dark:text-emerald-400',
                                                                )}
                                                            >
                                                                Soute :{' '}
                                                                {
                                                                    leg.availableCargoKg
                                                                }{' '}
                                                                / {leg.totalCargoKg}{' '}
                                                                kg libres
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {idx <
                                                        itinerary.legs.length -
                                                            1 && (
                                                        <div className="flex flex-col items-center text-[9px] font-bold text-digital">
                                                            <Clock className="size-3" />
                                                            <span>
                                                                {
                                                                    itinerary.transferWaitMinutes
                                                                }
                                                                m escale
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* Right: Cargo Bottleneck summary + Selection check */}
                                    <div className="flex flex-col items-end justify-between gap-2 text-right">
                                        <div
                                            className={cn(
                                                'rounded-xl px-2.5 py-1 text-[11px] font-bold',
                                                cargoLow
                                                    ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                                    : 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
                                            )}
                                        >
                                            {cargoLow
                                                ? `Soute limitée : ${itinerary.minAvailableCargoKg} kg max`
                                                : `Soute ouverte : ${itinerary.minAvailableCargoKg} kg dispo`}
                                        </div>

                                        {isSelected && (
                                            <span className="inline-flex items-center gap-1 text-xs font-extrabold text-kora dark:text-gold">
                                                <CheckCircle2 className="size-4 text-gold" />
                                                Car sélectionné
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </button>
                        );
                    })}
                </div>
            )}

            {/* Defer / Hold option */}
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/80 bg-muted/40 px-4 py-3">
                <label className="flex cursor-pointer items-center gap-2.5 text-xs">
                    <input
                        type="checkbox"
                        checked={draft.deferToNextAvailableCargo}
                        onChange={(e) => {
                            const checked = e.target.checked;
                            onToggleDefer(checked);

                            if (checked) {
onSelectItinerary(null);
}
                        }}
                        className="size-4 rounded border-border text-kora focus:ring-gold"
                    />
                    <span>
                        <strong className="text-foreground">
                            Enregistrer en dépôt libre (sans bloquer un car)
                        </strong>
                        <span className="block text-[11px] text-muted-foreground">
                            Utile pour les colis volumineux qui partiront avec le
                            prochain car ayant la soute requise.
                        </span>
                    </span>
                </label>

                <ParcelButton
                    variant="primary"
                    disabled={!canContinue}
                    onClick={onNext}
                >
                    <span>Continuer · Coordonnées &amp; Pesée</span>
                    <kbd className="hidden rounded bg-kora/15 px-1.5 py-0.5 font-mono text-[9px] sm:inline-block">
                        Ctrl+↵
                    </kbd>
                    <ArrowRight className="size-3.5" />
                </ParcelButton>
            </div>
        </section>
    );
}
