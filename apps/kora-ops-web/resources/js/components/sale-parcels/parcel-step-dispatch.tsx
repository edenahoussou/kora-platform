import {
    CheckCircle2,
    Copy,
    ExternalLink,
    FileCheck,
    MessageCircle,
    PackagePlus,
    Printer,
    Repeat2,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { ParcelReceiptDialog } from '@/components/parcels/parcel-receipt-dialog';
import type { FinalizedParcel } from './types';

interface Props {
    finalized: FinalizedParcel;
    onReset: () => void;
}

export function ParcelStepDispatch({ finalized, onReset }: Props) {
    const [copied, setCopied] = useState(false);
    const [receiptOpen, setReceiptOpen] = useState(false);
    const { trackingNumber, draft, quote, issuedAt, receiptNumber, agentName } =
        finalized;

    const itinerary = draft.selectedItinerary;
    const isTransfer = (itinerary?.legs.length ?? 0) > 1;
    const firstLeg = itinerary?.legs[0];

    const handleCopyTracking = () => {
        void navigator.clipboard.writeText(trackingNumber);
        setCopied(true);
        toast.success(`Référence ${trackingNumber} copiée`);
        setTimeout(() => setCopied(false), 2000);
    };

    const handlePrintLabel = () => {
        toast.success('Impression de l’étiquette thermique lancée.');
        window.print();
    };

    const handlePrintReceipt = () => {
        setReceiptOpen(true);
    };

    const trackingUrl =
        finalized.receiptParcel.tracking_url ??
        `https://kora.express/track/${trackingNumber}`;
    const handleSendWhatsApp = () => {
        const text = `Bonjour ${draft.recipientName}, un colis KORA EXPRESS vous est adressé depuis ${draft.originHub.city} par ${draft.senderName}.\n\n📦 Réf. de suivi : ${trackingNumber}\n📍 Lieu de retrait : ${draft.destinationHub?.name}\n\nSuivez votre colis sur ${trackingUrl}`;
        window.open(
            `https://wa.me/${draft.recipientPhone.replace(/\D/g, '')}?text=${encodeURIComponent(text)}`,
            '_blank',
            'noopener',
        );
    };

    return (
        <div className="animate-in space-y-6 duration-300 fade-in slide-in-from-bottom-2">
            {/* Success Banner (hidden on print) */}
            <div className="no-print flex flex-wrap items-center justify-between gap-4 rounded-[1.125rem] border border-emerald-500/30 bg-emerald-500/8 p-5">
                <div className="flex items-center gap-3.5">
                    <div className="flex size-11 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm">
                        <CheckCircle2 className="size-6" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-700 uppercase dark:text-emerald-300">
                                Enregistré &amp; prêt à charger
                            </span>
                            <span className="text-[11px] text-muted-foreground">
                                • {issuedAt}
                            </span>
                        </div>
                        <h2 className="mt-0.5 text-lg font-black tracking-tight text-foreground sm:text-xl">
                            Colis {trackingNumber} confirmé
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            {draft.deferToNextAvailableCargo
                                ? 'Colis en attente au dépôt (prochain car disponible).'
                                : isTransfer
                                  ? `Acheminement via ${itinerary?.transferHubCity} (${itinerary?.legs.map((l) => l.departureRef).join(' + ')}).`
                                  : `Affecté au départ ${firstLeg?.departureRef ?? ''} (${firstLeg?.departureTime ?? ''}).`}
                        </p>
                    </div>
                </div>

                <ParcelButton
                    variant="primary"
                    onClick={onReset}
                    className="min-h-11! px-5! text-xs!"
                >
                    <PackagePlus className="size-4" />
                    <span>Nouvel envoi</span>
                    <kbd className="hidden rounded bg-kora/15 px-1.5 py-0.5 font-mono text-[9px] sm:inline-block">
                        Alt+N
                    </kbd>
                </ParcelButton>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* Thermal Label (Isolated printable block) */}
                <div className="space-y-3 lg:col-span-7">
                    <div className="no-print flex items-center justify-between">
                        <span className="section-kicker">
                            Étiquette Thermique Autocollante · Format 100 × 150
                            mm
                        </span>
                        <ParcelButton
                            variant="navy"
                            size="sm"
                            onClick={handlePrintLabel}
                        >
                            <Printer className="size-3.5 text-gold" />
                            <span>Imprimer</span>
                            <kbd className="py-0.2 rounded bg-white/15 px-1 font-mono text-[9px]">
                                Alt+P
                            </kbd>
                        </ParcelButton>
                    </div>

                    <div className="parcel-label-printable rounded-2xl border-2 border-slate-900 bg-white p-6 font-mono text-slate-950 shadow-xl">
                        {/* Label Brand Header */}
                        <div className="flex items-center justify-between border-b-2 border-slate-900 pb-3">
                            <div>
                                <h3 className="text-2xl font-black tracking-tight text-slate-950">
                                    KORA EXPRESS
                                </h3>
                                <p className="text-[10px] font-bold tracking-widest text-slate-600 uppercase">
                                    Service Colis Interurbain
                                </p>
                            </div>
                            <div className="text-right">
                                <span className="rounded bg-slate-950 px-2 py-0.5 text-xs font-bold text-white">
                                    {isTransfer ? 'TRANSBORDEMENT' : 'DIRECT'}
                                </span>
                                <p className="mt-0.5 text-[10px] font-bold text-slate-600">
                                    {draft.originHub.code} →{' '}
                                    {draft.destinationHub?.code}
                                </p>
                            </div>
                        </div>

                        {/* Route & QR */}
                        <div className="my-4 flex items-center justify-between gap-4 border-b-2 border-slate-900 pb-4">
                            <div>
                                <span className="text-[10px] font-bold text-slate-500 uppercase">
                                    Axe d&apos;acheminement
                                </span>
                                <div className="text-3xl font-black tracking-tight sm:text-4xl">
                                    {draft.originHub.city
                                        .slice(0, 3)
                                        .toUpperCase()}
                                    {isTransfer && itinerary?.transferHubCity
                                        ? ` › ${itinerary.transferHubCity.slice(0, 3).toUpperCase()} › `
                                        : ' → '}
                                    {draft.destinationHub?.city
                                        .slice(0, 3)
                                        .toUpperCase()}
                                </div>
                                <span className="text-xs font-bold text-slate-700">
                                    {draft.destinationHub?.name}
                                </span>
                                {isTransfer && (
                                    <p className="mt-1 flex items-center gap-1 text-[10px] font-bold text-slate-700">
                                        <Repeat2 className="size-3" />
                                        Escale transbordement :{' '}
                                        {itinerary?.transferHubCity}
                                    </p>
                                )}
                            </div>

                            <div className="flex shrink-0 flex-col items-center justify-center rounded-xl border-2 border-slate-900 bg-white p-2">
                                <QRCodeSVG
                                    value={trackingUrl}
                                    size={96}
                                    level="H"
                                />
                                <span className="mt-1 text-[8px] font-bold tracking-tighter">
                                    SCANNER POUR ÉMARGER
                                </span>
                            </div>
                        </div>

                        {/* Tracking reference only — the pickup PIN is never printed
                            on the sender-facing label or bordereau. */}
                        <div className="border-b-2 border-slate-900 pb-3 text-xs">
                            <span className="text-[10px] font-bold text-slate-500 uppercase">
                                N° de Suivi
                            </span>
                            <div className="text-lg font-black tracking-wider text-slate-950">
                                {trackingNumber}
                            </div>
                        </div>

                        {/* Parties */}
                        <div className="my-3 grid grid-cols-2 gap-3 border-b-2 border-slate-900 pb-3 text-xs">
                            <div>
                                <span className="text-[10px] font-bold text-slate-500 uppercase">
                                    Expéditeur
                                </span>
                                <p className="font-bold text-slate-950">
                                    {draft.senderName}
                                </p>
                                <p className="text-[11px] text-slate-700">
                                    {draft.senderPhone}
                                </p>
                                <p className="text-[10px] text-slate-500">
                                    {draft.originHub.name}
                                </p>
                            </div>
                            <div className="border-l-2 border-slate-300 pl-3">
                                <span className="text-[10px] font-bold text-slate-500 uppercase">
                                    Destinataire
                                </span>
                                <p className="font-bold text-slate-950">
                                    {draft.recipientName}
                                </p>
                                <p className="text-[11px] text-slate-700">
                                    {draft.recipientPhone}
                                </p>
                                <p className="text-[10px] font-bold text-slate-700">
                                    {draft.destinationHub?.name}
                                </p>
                            </div>
                        </div>

                        {/* Cargo & Bus Meta */}
                        <div className="grid grid-cols-3 gap-2 text-[11px]">
                            <div>
                                <span className="text-[9px] font-bold text-slate-500 uppercase">
                                    Poids Réel
                                </span>
                                <p className="text-sm font-extrabold">
                                    {draft.weightKg} kg
                                </p>
                            </div>
                            <div>
                                <span className="text-[9px] font-bold text-slate-500 uppercase">
                                    Car(s) Assigné(s)
                                </span>
                                <p className="font-bold">
                                    {draft.deferToNextAvailableCargo
                                        ? 'Prochain car dispo'
                                        : itinerary?.legs
                                              .map(
                                                  (l) =>
                                                      `${l.departureRef} (${l.departureTime})`,
                                              )
                                              .join(' → ')}
                                </p>
                            </div>
                            <div className="text-right">
                                <span className="text-[9px] font-bold text-slate-500 uppercase">
                                    Guichet
                                </span>
                                <p className="text-[10px]">
                                    {issuedAt.slice(0, 10)} ·{' '}
                                    {agentName.split(' ')[0]}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Right: Actions & Digital Receipt (hidden on print) */}
                <div className="no-print space-y-4 lg:col-span-5">
                    <div className="space-y-3 rounded-[1.125rem] border border-border bg-card p-5 shadow-xs">
                        <span className="section-kicker">Actions Guichet</span>

                        <div className="grid gap-2.5">
                            <ParcelButton
                                variant="navy"
                                onClick={handlePrintLabel}
                                className="min-h-11! w-full justify-between! px-4! text-xs!"
                            >
                                <span className="flex items-center gap-2">
                                    <Printer className="size-4 text-gold" />
                                    Imprimer l&apos;étiquette thermique
                                </span>
                                <kbd className="rounded bg-white/15 px-1.5 py-0.5 font-mono text-[9px]">
                                    Alt+P
                                </kbd>
                            </ParcelButton>

                            <ParcelButton
                                variant="ghost"
                                onClick={handlePrintReceipt}
                                className="min-h-11! w-full justify-between! px-4! text-xs!"
                            >
                                <span className="flex items-center gap-2">
                                    <FileCheck className="size-4 text-gold" />
                                    Imprimer le reçu client (MECeF)
                                </span>
                                <span>→</span>
                            </ParcelButton>

                            <ParcelButton
                                variant="success"
                                onClick={handleSendWhatsApp}
                                className="min-h-11! w-full justify-between! px-4! text-xs!"
                            >
                                <span className="flex items-center gap-2">
                                    <MessageCircle className="size-4" />
                                    Notifier sur WhatsApp
                                </span>
                                <ExternalLink className="size-3.5" />
                            </ParcelButton>
                        </div>
                    </div>

                    <div className="space-y-2 rounded-[1.125rem] border border-border bg-muted/35 p-4 text-xs">
                        <div className="flex justify-between text-muted-foreground">
                            <span>Reçu N° :</span>
                            <span className="font-mono font-bold text-foreground">
                                {receiptNumber}
                            </span>
                        </div>
                        <div className="flex justify-between text-muted-foreground">
                            <span>Total Encaissé :</span>
                            <strong className="text-foreground">
                                {quote.totalTtc.toLocaleString('fr-FR')} FCFA
                            </strong>
                        </div>
                        <div className="flex justify-between text-muted-foreground">
                            <span>Règlement :</span>
                            <span className="font-bold text-foreground uppercase">
                                {draft.paymentMethod}
                            </span>
                        </div>
                        <div className="flex justify-between border-t border-border pt-2 text-muted-foreground">
                            <span>Réf. Suivi :</span>
                            <button
                                type="button"
                                onClick={handleCopyTracking}
                                className="inline-flex items-center gap-1 font-mono font-bold text-digital hover:underline"
                            >
                                <span>{trackingNumber}</span>
                                <Copy className="size-3" />
                                {copied && (
                                    <span className="ml-1 text-[10px] text-emerald-600">
                                        Copié
                                    </span>
                                )}
                            </button>
                        </div>
                    </div>

                    <ParcelButton
                        variant="primary"
                        onClick={onReset}
                        className="min-h-11! w-full text-xs!"
                    >
                        <PackagePlus className="size-4" />
                        <span>Client suivant (Nouvel envoi)</span>
                    </ParcelButton>
                </div>
            </div>

            <ParcelReceiptDialog
                open={receiptOpen}
                parcel={finalized.receiptParcel}
                onOpenChange={setReceiptOpen}
            />
        </div>
    );
}
