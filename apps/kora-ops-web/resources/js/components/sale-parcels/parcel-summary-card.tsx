import {
    AlertTriangle,
    ArrowRight,
    Bus,
    CheckCircle2,
    Package,
    Repeat2,
    Scale,
    Users,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { PARCEL_NATURE_OPTIONS } from './mock-data';
import type { ParcelIntakeDraft, ParcelQuoteBreakdown } from './types';

interface Props {
    draft: ParcelIntakeDraft;
    quote: ParcelQuoteBreakdown;
    currentStep: number;
    onGoToStep: (step: number) => void;
}

export function ParcelSummaryCard({
    draft,
    quote,
    currentStep,
    onGoToStep,
}: Props) {
    const natureOption = PARCEL_NATURE_OPTIONS.find(
        (n) => n.id === draft.nature,
    );

    const itinerary = draft.selectedItinerary;
    const isTransfer = (itinerary?.legs.length ?? 0) > 1;
    const firstLeg = itinerary?.legs[0];
    const lastLeg = itinerary?.legs[itinerary.legs.length - 1];
    const minCargo = itinerary?.minAvailableCargoKg ?? null;
    const cargoExceeded =
        minCargo !== null &&
        !draft.deferToNextAvailableCargo &&
        draft.weightKg > minCargo;

    if (!draft.destinationHub) {
        return (
            <aside className="summary empty-summary kora-sale-parcel-chrome">
                <div className="mx-auto mb-3 flex size-10 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                    <Package className="size-5" />
                </div>
                <p className="text-xs font-bold text-foreground">
                    Bordereau en attente
                </p>
                <p className="mt-1 text-[11px] leading-relaxed text-muted-foreground">
                    Sélectionnez une destination dans le bandeau ci-dessus pour
                    afficher les cars disponibles et préparer le devis.
                </p>
            </aside>
        );
    }

    return (
        <aside className="summary kora-sale-parcel-chrome space-y-4">
            {/* Route Header */}
            <div className="border-b border-border pb-3">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-[10px] font-extrabold tracking-wider text-gold uppercase">
                        Bordereau Colis en direct
                    </span>
                    {currentStep > 1 && (
                        <button
                            type="button"
                            onClick={() => onGoToStep(1)}
                            className="text-[10px] font-bold text-digital hover:underline"
                        >
                            Modifier trajet
                        </button>
                    )}
                </div>

                <div className="mt-1.5 flex items-center gap-1.5 text-sm font-black tracking-tight text-foreground">
                    <span>{draft.originHub.city}</span>
                    <ArrowRight className="size-3.5 text-gold" />
                    <span>{draft.destinationHub.city}</span>
                </div>
                <p className="mt-0.5 text-[11px] text-muted-foreground">
                    Retrait :{' '}
                    <strong className="text-foreground">
                        {draft.destinationHub.name}
                    </strong>
                </p>
            </div>

            {/* Itinerary & Soute Status */}
            <div className="space-y-2 border-b border-border pb-3">
                <div className="flex items-center justify-between text-[10px] font-extrabold tracking-wider text-muted-foreground uppercase">
                    <span className="flex items-center gap-1">
                        <Bus className="size-3 text-gold" />
                        Acheminement &amp; Soute
                    </span>
                    {itinerary && (
                        <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[9px] text-foreground">
                            {itinerary.dayLabel}
                        </span>
                    )}
                </div>

                {draft.deferToNextAvailableCargo ? (
                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-2.5 text-[11px]">
                        <p className="font-bold text-amber-800 dark:text-amber-300">
                            Mise en attente au dépôt (Soute prochaine)
                        </p>
                        <p className="mt-0.5 text-[10px] text-muted-foreground">
                            Le colis sera affecté au prochain car disposant de{' '}
                            {draft.weightKg} kg en soute.
                        </p>
                    </div>
                ) : itinerary && firstLeg && lastLeg ? (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-xs font-bold text-foreground">
                            <span>
                                {firstLeg.departureTime} → {lastLeg.arrivalTime}
                            </span>
                            <span className="text-[11px] font-medium text-muted-foreground">
                                {itinerary.legs
                                    .map((l) => l.departureRef)
                                    .join(' + ')}
                            </span>
                        </div>

                        {isTransfer && (
                            <div className="inline-flex items-center gap-1 rounded-md bg-digital/10 px-2 py-0.5 text-[10px] font-bold text-digital">
                                <Repeat2 className="size-3" />
                                <span>
                                    1 correspondance ·{' '}
                                    {itinerary.transferHubCity} (
                                    {itinerary.transferWaitMinutes} min)
                                </span>
                            </div>
                        )}

                        {/* Cargo Hold Indicator */}
                        {minCargo !== null && (
                            <div
                                className={cn(
                                    'flex items-center justify-between rounded-xl px-2.5 py-2 text-[11px] font-semibold',
                                    cargoExceeded
                                        ? 'border border-destructive/40 bg-destructive/10 text-destructive'
                                        : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                                )}
                            >
                                <span className="flex items-center gap-1.5">
                                    {cargoExceeded ? (
                                        <AlertTriangle className="size-3.5" />
                                    ) : (
                                        <CheckCircle2 className="size-3.5" />
                                    )}
                                    Soute disponible
                                </span>
                                <span className="font-mono font-bold">
                                    {draft.weightKg} kg
                                    {minCargo >= 9999
                                        ? ' · non plafonnée'
                                        : ` / ${minCargo} kg`}
                                </span>
                            </div>
                        )}
                    </div>
                ) : (
                    <p className="text-[11px] text-muted-foreground">
                        Choisissez un départ dans la liste à gauche.
                    </p>
                )}
            </div>

            {/* Actors Snapshot */}
            {(draft.senderName || draft.recipientName) && (
                <div className="space-y-1.5 border-b border-border pb-3 text-[11px]">
                    <div className="flex items-center gap-1 text-[10px] font-extrabold tracking-wider text-muted-foreground uppercase">
                        <Users className="size-3 text-gold" />
                        Acteurs
                    </div>
                    <div className="flex justify-between gap-2">
                        <span className="text-muted-foreground">
                            Expéditeur :
                        </span>
                        <span className="truncate font-bold text-foreground">
                            {draft.senderName || '—'}
                        </span>
                    </div>
                    <div className="flex justify-between gap-2">
                        <span className="text-muted-foreground">
                            Destinataire :
                        </span>
                        <span className="truncate font-bold text-foreground">
                            {draft.recipientName || '—'}
                        </span>
                    </div>
                </div>
            )}

            {/* Package & Live Pricing */}
            <div className="space-y-2 text-xs">
                <div className="flex items-center justify-between text-[10px] font-extrabold tracking-wider text-muted-foreground uppercase">
                    <span className="flex items-center gap-1">
                        <Scale className="size-3 text-gold" />
                        Devis temps réel
                    </span>
                    <span className="font-bold text-foreground">
                        {natureOption?.label} · {draft.weightKg} kg
                    </span>
                </div>

                <div className="space-y-1.5 pt-1 text-[11px]">
                    <div className="flex justify-between text-muted-foreground">
                        <span>Forfait corridor</span>
                        <span className="font-semibold text-foreground">
                            {quote.basePrice.toLocaleString('fr-FR')} FCFA
                        </span>
                    </div>
                    <div className="flex justify-between text-muted-foreground">
                        <span>
                            Poids ({quote.chargeableWeight.toFixed(1)} kg sup.)
                        </span>
                        <span className="font-semibold text-foreground">
                            {quote.weightSurcharge.toLocaleString('fr-FR')} FCFA
                        </span>
                    </div>
                    {quote.transferFee > 0 && (
                        <div className="flex justify-between text-muted-foreground">
                            <span>Transbordement escale</span>
                            <span className="font-semibold text-foreground">
                                {quote.transferFee.toLocaleString('fr-FR')} FCFA
                            </span>
                        </div>
                    )}
                    <div className="flex justify-between text-muted-foreground">
                        <span>TVA (18 % MECeF)</span>
                        <span className="font-semibold text-foreground">
                            {quote.vat18.toLocaleString('fr-FR')} FCFA
                        </span>
                    </div>
                </div>

                {/* Total Box */}
                <div className="mt-2 rounded-xl bg-kora p-3 text-white shadow-sm dark:border dark:border-gold/30">
                    <div className="flex items-baseline justify-between">
                        <span className="text-[10px] font-extrabold tracking-wider text-gold uppercase">
                            Total TTC
                        </span>
                        <span className="text-xl font-black tracking-tight text-white">
                            {quote.totalTtc.toLocaleString('fr-FR')}{' '}
                            <span className="text-xs font-bold text-gold">
                                FCFA
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </aside>
    );
}
