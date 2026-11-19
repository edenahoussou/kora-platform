import {
    ArrowRight,
    Ban,
    Building2,
    CalendarClock,
    ExternalLink,
    Package,
    Phone,
    Route as RouteIcon,
    Scale,
    TriangleAlert,
    User,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { parcelStatusLabel } from '@/components/parcels/status';
import type { ParcelListItem } from '@/components/parcels/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    parcel: ParcelListItem | null;
    onOpenChange: (open: boolean) => void;
};

function formatGrams(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return `${new Intl.NumberFormat('fr-FR').format(value)} g`;
}

function formatAmount(value: number, currency: string): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} ${currency}`;
}

function formatDateTime(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function ParcelDetailDialog({ parcel, onOpenChange }: Props) {
    return (
        <Dialog open={parcel !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-[46rem]">
                {parcel && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="flex flex-wrap items-center gap-2">
                                Colis {parcel.reference}
                                <KoraStatusBadge
                                    tone={
                                        parcel.status === 'cancelled' ||
                                        parcel.status === 'refused'
                                            ? 'destructive'
                                            : 'blue'
                                    }
                                    label={parcelStatusLabel(parcel.status)}
                                />
                            </DialogTitle>
                            <DialogDescription>
                                Dossier Colis OPS : expéditeur, destinataire,
                                trajet, poids et état de suivi.
                            </DialogDescription>
                        </DialogHeader>

                        {parcel.incident_open && (
                            <div className="flex items-center gap-2 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                                <TriangleAlert className="size-4" />
                                Un incident est ouvert sur ce colis.
                            </div>
                        )}

                        <div className="grid gap-4 sm:grid-cols-[1fr_11rem]">
                            <div className="grid gap-3">
                                <div className="rounded-2xl border border-border bg-muted/25 p-4">
                                    <div className="flex items-center gap-2 text-xs font-bold text-muted-foreground uppercase">
                                        <RouteIcon />
                                        Trajet
                                    </div>
                                    <p className="mt-2 flex items-center gap-2 text-base font-black text-foreground">
                                        {parcel.origin ?? '—'}
                                        <ArrowRight className="size-4 text-gold" />
                                        {parcel.destination ?? '—'}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Départ{' '}
                                        {parcel.departure_uuid
                                            ? 'affecté'
                                            : 'non affecté'}{' '}
                                        · ETA {formatDateTime(parcel.eta)}
                                    </p>
                                </div>

                                <div className="grid gap-3 rounded-2xl border border-border bg-card p-4">
                                    <InfoLine
                                        icon={User}
                                        label="Expéditeur"
                                        value={parcel.sender_name}
                                    />
                                    <InfoLine
                                        icon={User}
                                        label="Destinataire"
                                        value={parcel.recipient_name}
                                    />
                                    <InfoLine
                                        icon={Building2}
                                        label="Lieu de dépôt"
                                        value={
                                            parcel.deposit_hub?.city ??
                                            parcel.selling_agency?.name ??
                                            '—'
                                        }
                                    />
                                    <InfoLine
                                        icon={Scale}
                                        label="Poids"
                                        value={`${formatGrams(parcel.weight_grams)} pesé · ${formatGrams(parcel.declared_grams)} déclaré`}
                                    />
                                    <InfoLine
                                        icon={Package}
                                        label="Montant"
                                        value={
                                            parcel.quote === null
                                                ? 'Calculé à la pesée'
                                                : formatAmount(
                                                      parcel.quote.total,
                                                      parcel.quote.currency,
                                                  )
                                        }
                                    />
                                    {parcel.inspection?.state === 'refused' && (
                                        <InfoLine
                                            icon={Ban}
                                            label="Motif du refus"
                                            value={
                                                parcel.inspection
                                                    .refusal_reason ?? '—'
                                            }
                                        />
                                    )}
                                    <InfoLine
                                        icon={CalendarClock}
                                        label="Dernière mise à jour"
                                        value={formatDateTime(
                                            parcel.tracking_updated_at ??
                                                parcel.updated_at,
                                        )}
                                    />
                                </div>

                                <div className="rounded-2xl border border-border bg-card p-4">
                                    <p className="text-xs font-bold text-muted-foreground uppercase">
                                        Contenu déclaré
                                    </p>
                                    <p className="mt-1 text-sm text-foreground">
                                        {parcel.contents || '—'}
                                    </p>
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        Retrait avant{' '}
                                        {formatDateTime(parcel.withdraw_by)}
                                    </p>
                                </div>
                            </div>

                            <div className="grid content-start gap-3">
                                <div className="grid place-items-center rounded-2xl border border-border bg-white p-4">
                                    {parcel.qr_payload ? (
                                        <QRCodeSVG
                                            value={parcel.qr_payload}
                                            size={128}
                                            bgColor="#ffffff"
                                            fgColor="#001D4E"
                                            level="M"
                                            includeMargin
                                        />
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            QR indisponible
                                        </span>
                                    )}
                                    <p className="mt-2 text-center text-[0.625rem] leading-4 text-muted-foreground">
                                        QR de suivi
                                    </p>
                                </div>
                                <div className="rounded-xl border border-border bg-muted/25 px-3 py-2 text-xs text-muted-foreground">
                                    <Phone className="mr-1 inline size-3" />
                                    Les coordonnées complètes restent masquées
                                    dans cette vue OPS.
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <ParcelButton
                                variant="ghost"
                                onClick={() => onOpenChange(false)}
                            >
                                Fermer
                            </ParcelButton>
                            {parcel.tracking_url && (
                                <a
                                    href={parcel.tracking_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="btn btn-primary"
                                >
                                    <ExternalLink className="size-3.5" />
                                    Ouvrir le suivi client
                                </a>
                            )}
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function InfoLine({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-start justify-between gap-3 text-sm">
            <span className="flex items-center gap-2 text-muted-foreground">
                <Icon className="size-3.5 text-gold" />
                {label}
            </span>
            <span className="text-right font-semibold text-foreground">
                {value}
            </span>
        </div>
    );
}
