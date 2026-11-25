import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Bus,
    CheckCircle2,
    Clock,
    Globe2,
    KeyRound,
    PackageCheck,
    Phone,
    Printer,
    Scale,
    ShieldAlert,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import {
    parcelStatusLabel,
    parcelStatusTone,
} from '@/components/parcels/status';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { CounterParcelRecord } from './types';

interface ParcelRegistryDrawerProps {
    parcel: CounterParcelRecord | null;
    onOpenChange: (open: boolean) => void;
    onMarkReceived: (parcel: CounterParcelRecord) => void;
    onConfirmRelease: (parcel: CounterParcelRecord, pin: string) => void;
    onPrintLabel: (parcel: CounterParcelRecord) => void;
}

function formatMoney(amount: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
}

export function ParcelRegistryDrawer({
    parcel,
    onOpenChange,
    onMarkReceived,
    onConfirmRelease,
    onPrintLabel,
}: ParcelRegistryDrawerProps) {
    const [pinInput, setPinInput] = useState('');
    const [pinError, setPinError] = useState<string | null>(null);

    const handleReleaseSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!parcel) {
            return;
        }

        const cleaned = pinInput.trim();

        if (cleaned.length < 4) {
            setPinError('Saisissez le code PIN à 4 chiffres du destinataire.');

            return;
        }

        if (cleaned !== parcel.securityCode) {
            setPinError(
                `Code PIN invalide pour ce colis (démo : ${parcel.securityCode}).`,
            );

            return;
        }

        setPinError(null);
        setPinInput('');
        onConfirmRelease(parcel, cleaned);
    };

    const isReadOnlyNetwork = parcel?.direction === 'network';

    return (
        <Sheet
            open={parcel !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setPinInput('');
                    setPinError(null);
                }

                onOpenChange(open);
            }}
        >
            <SheetContent
                side="right"
                className="w-full overflow-y-auto border-l border-border bg-card p-0 sm:max-w-[32rem]"
            >
                {parcel && (
                    <div className="flex h-full flex-col">
                        <SheetHeader className="border-b border-border bg-muted/35 px-6 py-5 text-left">
                            <div className="flex flex-wrap items-center gap-2 pr-6">
                                <span className="rounded-md bg-kora px-2 py-0.5 font-mono text-xs font-black tracking-wide text-gold">
                                    {parcel.reference}
                                </span>
                                <KoraStatusBadge
                                    tone={parcelStatusTone(parcel.status)}
                                    label={parcelStatusLabel(parcel.status)}
                                />
                                {parcel.direction === 'outbound' && (
                                    <KoraStatusBadge
                                        tone="navy"
                                        label="Expédié ici"
                                    />
                                )}
                                {parcel.direction === 'inbound' && (
                                    <KoraStatusBadge
                                        tone="blue"
                                        label="Arrivée agence"
                                    />
                                )}
                                {isReadOnlyNetwork && (
                                    <KoraStatusBadge
                                        tone="amber"
                                        icon={<Globe2 className="size-3" />}
                                        label="Réseau · Lecture seule"
                                    />
                                )}
                            </div>
                            <SheetTitle className="mt-2 text-lg font-extrabold tracking-tight text-foreground">
                                {parcel.contents}
                            </SheetTitle>
                            <SheetDescription className="text-xs text-muted-foreground">
                                {parcel.originCity} ({parcel.originHubName}) →{' '}
                                {parcel.destinationCity} (
                                {parcel.destinationHubName})
                            </SheetDescription>
                        </SheetHeader>

                        <div className="flex-1 space-y-5 px-6 py-5">
                            {isReadOnlyNetwork && (
                                <div className="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs text-foreground">
                                    <Globe2 className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                    <div>
                                        <strong className="block font-bold">
                                            Consultation inter-agences (lecture
                                            seule)
                                        </strong>
                                        <p className="mt-0.5 text-muted-foreground">
                                            Ce colis n’appartient pas au
                                            périmètre physique de votre agence.
                                            Vous pouvez renseigner le client sur
                                            son acheminement, sans effectuer de
                                            remise ni d’encaissement.
                                        </p>
                                    </div>
                                </div>
                            )}

                            {parcel.incidentOpen && parcel.incidentNote && (
                                <div className="flex items-start gap-3 rounded-xl border border-destructive/30 bg-destructive/10 p-3.5 text-xs text-destructive">
                                    <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                                    <div>
                                        <strong className="block font-bold">
                                            Incident ouvert sur ce colis
                                        </strong>
                                        <p className="mt-0.5 leading-5">
                                            {parcel.incidentNote}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* Corridor & Bus Card */}
                            <div className="rounded-xl border border-border bg-muted/30 p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-[0.65rem] font-black tracking-wider text-muted-foreground uppercase">
                                        Acheminement &amp; car
                                    </span>
                                    <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-[0.65rem] font-bold text-foreground">
                                        {parcel.lineCode}
                                    </span>
                                </div>
                                <div className="mt-2.5 flex items-center gap-2 text-sm font-extrabold text-foreground">
                                    <span>{parcel.originCity}</span>
                                    <ArrowRight className="size-4 text-gold" />
                                    <span>{parcel.destinationCity}</span>
                                </div>
                                <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                    <span className="inline-flex items-center gap-1.5">
                                        <Bus className="size-3.5 text-gold" />
                                        {parcel.departureRef
                                            ? `${parcel.departureRef} · ${parcel.departureLabel}`
                                            : parcel.departureLabel}
                                    </span>
                                </div>
                            </div>

                            {/* Weight, Value & Billing */}
                            <div className="grid grid-cols-3 gap-2.5">
                                <div className="rounded-xl border border-border bg-background p-3">
                                    <span className="block text-[0.625rem] font-bold text-muted-foreground uppercase">
                                        Poids
                                    </span>
                                    <strong className="mt-1 block text-sm font-extrabold text-foreground">
                                        {parcel.weightKg !== null
                                            ? `${parcel.weightKg} kg`
                                            : `~${parcel.declaredWeightKg} kg`}
                                    </strong>
                                    <small className="text-[0.625rem] text-muted-foreground">
                                        {parcel.weightKg !== null
                                            ? 'Pesé guichet'
                                            : 'Déclaré client'}
                                    </small>
                                </div>
                                <div className="rounded-xl border border-border bg-background p-3">
                                    <span className="block text-[0.625rem] font-bold text-muted-foreground uppercase">
                                        Montant TTC
                                    </span>
                                    <strong className="mt-1 block text-sm font-extrabold text-foreground">
                                        {formatMoney(parcel.amountFcfa)}
                                    </strong>
                                    <small className="text-[0.625rem] text-muted-foreground">
                                        {parcel.paymentChannelLabel}
                                    </small>
                                </div>
                                <div className="rounded-xl border border-border bg-background p-3">
                                    <span className="block text-[0.625rem] font-bold text-muted-foreground uppercase">
                                        Dimensions
                                    </span>
                                    <strong className="mt-1 block text-xs font-bold text-foreground">
                                        {parcel.dimensions}
                                    </strong>
                                    <small className="text-[0.625rem] text-muted-foreground">
                                        {parcel.declaredValueFcfa > 0
                                            ? `Val. ${formatMoney(parcel.declaredValueFcfa)}`
                                            : 'Sans valeur décl.'}
                                    </small>
                                </div>
                            </div>

                            {/* Sender & Recipient */}
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-xl border border-border bg-background p-3.5">
                                    <span className="flex items-center gap-1.5 text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                        <User className="size-3 text-gold" />
                                        Expéditeur
                                    </span>
                                    <strong className="mt-1.5 block text-xs font-extrabold text-foreground">
                                        {parcel.senderName}
                                    </strong>
                                    <span className="mt-1 inline-flex items-center gap-1 text-[0.7rem] text-muted-foreground">
                                        <Phone className="size-3" />
                                        {parcel.senderPhone}
                                    </span>
                                </div>
                                <div className="rounded-xl border border-border bg-background p-3.5">
                                    <span className="flex items-center gap-1.5 text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                        <User className="size-3 text-emerald-600 dark:text-emerald-400" />
                                        Destinataire
                                    </span>
                                    <strong className="mt-1.5 block text-xs font-extrabold text-foreground">
                                        {parcel.recipientName}
                                    </strong>
                                    <span className="mt-1 inline-flex items-center gap-1 text-[0.7rem] text-muted-foreground">
                                        <Phone className="size-3" />
                                        {parcel.recipientPhone}
                                    </span>
                                </div>
                            </div>

                            {/* Movements Timeline */}
                            <div>
                                <h4 className="mb-3 flex items-center gap-1.5 text-[0.6875rem] font-black tracking-wider text-muted-foreground uppercase">
                                    <Clock className="size-3.5 text-gold" />
                                    Historique des mouvements
                                </h4>
                                <ol className="relative ml-2 space-y-4 border-l border-border pl-4">
                                    {parcel.movements.map((mv) => (
                                        <li key={mv.id} className="relative">
                                            <span
                                                className={cn(
                                                    'absolute top-1 -left-[1.3125rem] grid size-2.5 rounded-full ring-4 ring-card',
                                                    mv.done
                                                        ? 'bg-emerald-500'
                                                        : 'bg-muted-foreground/40',
                                                )}
                                            />
                                            <div className="flex items-baseline justify-between gap-2">
                                                <strong className="text-xs font-bold text-foreground">
                                                    {mv.label}
                                                </strong>
                                                <span className="shrink-0 text-[0.65rem] text-muted-foreground">
                                                    {mv.timestamp}
                                                </span>
                                            </div>
                                            <p className="mt-0.5 text-[0.6875rem] text-muted-foreground">
                                                {mv.location} · {mv.actor}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </div>

                            {/* Contextual Counter Actions (disabled for cross-agency network lookup) */}
                            {!isReadOnlyNetwork && (
                                <div className="space-y-3 rounded-xl border border-border bg-muted/25 p-4">
                                    <span className="block text-[0.65rem] font-black tracking-wider text-muted-foreground uppercase">
                                        Action guichet rapide
                                    </span>

                                    {parcel.status === 'preregistered' && (
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <p className="text-xs text-muted-foreground">
                                                Colis pré-enregistré par le
                                                client. Passez à la pesée pour
                                                éditer l’étiquette.
                                            </p>
                                            <Link
                                                href={`/sale/parcels/new?mode=lookup&ref=${encodeURIComponent(parcel.reference)}`}
                                                className="btn btn-primary btn-sm"
                                            >
                                                <Scale className="size-3.5" />
                                                Peser &amp; finaliser
                                            </Link>
                                        </div>
                                    )}

                                    {parcel.status === 'arrived' && (
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <p className="text-xs text-muted-foreground">
                                                Ce colis vient d’arriver au
                                                quai. Confirmez sa mise en
                                                magasin pour notifier le
                                                destinataire.
                                            </p>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onMarkReceived(parcel)
                                                }
                                                className="btn btn-primary btn-sm"
                                            >
                                                <PackageCheck className="size-3.5" />
                                                Réceptionner &amp; notifier
                                            </button>
                                        </div>
                                    )}

                                    {parcel.status === 'available' && (
                                        <form
                                            onSubmit={handleReleaseSubmit}
                                            className="space-y-2.5"
                                        >
                                            <p className="text-xs text-muted-foreground">
                                                Vérifiez le code PIN de retrait
                                                présenté par{' '}
                                                <strong>
                                                    {parcel.recipientName}
                                                </strong>{' '}
                                                (démo :{' '}
                                                <code className="font-mono font-bold text-foreground">
                                                    {parcel.securityCode}
                                                </code>
                                                ).
                                            </p>
                                            <div className="flex gap-2">
                                                <div className="relative flex-1">
                                                    <KeyRound className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                                    <input
                                                        type="text"
                                                        maxLength={6}
                                                        value={pinInput}
                                                        onChange={(e) => {
                                                            setPinInput(
                                                                e.target.value,
                                                            );
                                                            setPinError(null);
                                                        }}
                                                        placeholder="Code PIN (4 chiffres)"
                                                        className="h-9 w-full rounded-lg border border-border bg-background pr-3 pl-8 font-mono text-xs font-bold text-foreground focus:border-gold focus:ring-2 focus:ring-gold/30 focus:outline-none"
                                                    />
                                                </div>
                                                <button
                                                    type="submit"
                                                    className="btn btn-primary btn-sm"
                                                >
                                                    <CheckCircle2 className="size-3.5" />
                                                    Valider la remise
                                                </button>
                                            </div>
                                            {pinError && (
                                                <p className="flex items-center gap-1.5 text-[0.7rem] font-semibold text-destructive">
                                                    <AlertTriangle className="size-3.5" />
                                                    {pinError}
                                                </p>
                                            )}
                                        </form>
                                    )}

                                    {![
                                        'preregistered',
                                        'arrived',
                                        'available',
                                    ].includes(parcel.status) && (
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="text-xs text-muted-foreground">
                                                Réimpression bordereau ou suivi
                                                client.
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onPrintLabel(parcel)
                                                }
                                                className="btn btn-ghost btn-sm"
                                            >
                                                <Printer className="size-3.5" />
                                                Bordereau d’envoi
                                            </button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
