import { ArrowRight, Bus, Eye, Globe2, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import {
    parcelStatusLabel,
    parcelStatusTone,
} from '@/components/parcels/status';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { NETWORK_LOOKUP_PARCELS } from './mock-data';
import type { CounterParcelRecord } from './types';

interface NetworkParcelLookupDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    agencyParcels: CounterParcelRecord[];
    onSelectParcel: (parcel: CounterParcelRecord) => void;
}

export function NetworkParcelLookupDialog({
    open,
    onOpenChange,
    agencyParcels,
    onSelectParcel,
}: NetworkParcelLookupDialogProps) {
    const [query, setQuery] = useState('');

    const matches = useMemo(() => {
        const trimmed = query.trim().toLowerCase();

        if (trimmed.length < 3) {
            return [];
        }

        const strippedPhone = trimmed.replace(/\s+/g, '');
        const all = [...NETWORK_LOOKUP_PARCELS, ...agencyParcels];

        return all.filter(
            (p) =>
                p.reference.toLowerCase().includes(trimmed) ||
                p.senderPhone.replace(/\s+/g, '').includes(strippedPhone) ||
                p.recipientPhone.replace(/\s+/g, '').includes(strippedPhone) ||
                p.senderName.toLowerCase().includes(trimmed) ||
                p.recipientName.toLowerCase().includes(trimmed),
        );
    }, [query, agencyParcels]);

    const handleChoose = (parcel: CounterParcelRecord) => {
        onSelectParcel(parcel);
        onOpenChange(false);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    setQuery('');
                }

                onOpenChange(nextOpen);
            }}
        >
            <DialogContent className="rounded-2xl border border-border bg-card p-6 sm:max-w-[34rem]">
                <DialogHeader>
                    <div className="flex items-center gap-2.5">
                        <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400">
                            <Globe2 className="size-4" />
                        </span>
                        <div>
                            <DialogTitle className="text-base font-extrabold tracking-tight text-foreground">
                                Suivi réseau hors agence
                            </DialogTitle>
                            <DialogDescription className="text-xs text-muted-foreground">
                                Renseignez un client sur un colis expédié ou
                                attendu sur une autre agence (lecture seule).
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gold" />
                        <input
                            type="text"
                            autoFocus
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Référence exacte (ex. KC-20261125-7741), téléphone ou nom…"
                            aria-label="Rechercher un colis sur tout le réseau KORA"
                            className="control h-11 w-full rounded-xl pl-10 text-xs font-semibold"
                        />
                    </div>

                    {query.trim().length < 3 ? (
                        <div className="rounded-xl border border-border bg-muted/35 p-3.5">
                            <span className="block text-[0.65rem] font-extrabold tracking-wider text-muted-foreground uppercase">
                                Exemples hors périmètre agence
                            </span>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {NETWORK_LOOKUP_PARCELS.map((sample) => (
                                    <button
                                        key={sample.uuid}
                                        type="button"
                                        onClick={() =>
                                            setQuery(sample.reference)
                                        }
                                        className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1.5 text-[0.7rem] font-bold text-foreground transition-colors hover:border-gold"
                                    >
                                        <span className="font-mono text-gold">
                                            {sample.reference}
                                        </span>
                                        <span className="text-muted-foreground">
                                            ({sample.originCity} →{' '}
                                            {sample.destinationCity})
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : matches.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border px-4 py-8 text-center text-xs text-muted-foreground">
                            Aucun colis trouvé sur le réseau pour « {query} ».
                        </div>
                    ) : (
                        <div className="max-h-72 space-y-2 overflow-y-auto pr-0.5">
                            {matches.map((parcel) => (
                                <button
                                    key={parcel.uuid}
                                    type="button"
                                    onClick={() => handleChoose(parcel)}
                                    className="flex w-full cursor-pointer items-start justify-between gap-3 rounded-xl border border-border bg-background p-3.5 text-left transition-all hover:border-gold hover:bg-muted/40"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-xs font-black text-foreground">
                                                {parcel.reference}
                                            </span>
                                            <KoraStatusBadge
                                                tone={parcelStatusTone(
                                                    parcel.status,
                                                )}
                                                label={parcelStatusLabel(
                                                    parcel.status,
                                                )}
                                            />
                                            <span className="rounded bg-muted px-1.5 py-0.5 text-[0.625rem] font-bold text-muted-foreground">
                                                {parcel.direction === 'network'
                                                    ? 'Hors agence · Lecture seule'
                                                    : 'Périmètre agence'}
                                            </span>
                                        </div>
                                        <p className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                            <span>{parcel.originCity}</span>
                                            <ArrowRight className="size-3.5 text-gold" />
                                            <span>
                                                {parcel.destinationCity}
                                            </span>
                                            <span className="font-normal text-muted-foreground">
                                                · {parcel.contents}
                                            </span>
                                        </p>
                                        <p className="flex items-center gap-1.5 text-[0.7rem] text-muted-foreground">
                                            <Bus className="size-3 text-gold" />
                                            <span>
                                                {parcel.departureLabel ??
                                                    parcel.updatedAt}
                                            </span>
                                        </p>
                                    </div>
                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-lg bg-kora px-2.5 py-1.5 text-[0.7rem] font-bold text-white">
                                        <Eye className="size-3.5" />
                                        <span>Voir</span>
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
