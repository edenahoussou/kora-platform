import {
    CheckCircle2,
    FileWarning,
    Loader2,
    Printer,
    ReceiptText,
    ShieldCheck,
    TriangleAlert,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useState } from 'react';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type { ParcelListItem, ParcelReceipt } from '@/components/parcels/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    parcel: ParcelListItem | null;
    onOpenChange: (open: boolean) => void;
};

function formatAmount(value: number, currency: string): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} ${currency}`;
}

function formatGrams(value: number | null): string {
    return value === null
        ? '—'
        : `${new Intl.NumberFormat('fr-FR').format(value)} g`;
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function methodLabel(method: string | null): string {
    return (
        {
            cash: 'Espèces',
            mobile_money: 'Mobile Money',
            card: 'Carte bancaire',
            wallet: 'Wallet KORA',
        }[method ?? ''] ??
        method ??
        '—'
    );
}

export function ParcelReceiptDialog({ open, parcel, onOpenChange }: Props) {
    const [receipt, setReceipt] = useState<ParcelReceipt | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open || parcel === null) {
            return;
        }

        let cancelled = false;

        const load = async () => {
            setLoading(true);
            setError(null);
            setReceipt(null);

            try {
                const response = await fetch(
                    `/parcels/data/${parcel.uuid}/receipt${
                        parcel.status === 'returned'
                            ? '?kind=return_receipt'
                            : ''
                    }`,
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    },
                );
                const body = (await response.json().catch(() => null)) as {
                    data?: ParcelReceipt;
                    message?: string;
                } | null;

                if (!response.ok || body?.data === undefined) {
                    throw new Error(
                        body?.message ??
                            'Le reçu de ce colis est indisponible.',
                    );
                }

                if (!cancelled) {
                    setReceipt(body.data);
                }
            } catch (reason) {
                if (!cancelled) {
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : 'Le reçu de ce colis est indisponible.',
                    );
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [open, parcel]);

    if (parcel === null) {
        return null;
    }

    const certified = receipt?.receipt_status === 'certified';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-[620px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <ReceiptText className="size-5 text-gold" />
                        {parcel.status === 'returned'
                            ? 'Reçu des frais de retour'
                            : 'Reçu Colis'}
                    </DialogTitle>
                    <DialogDescription>
                        {parcel.reference} ·{' '}
                        {parcel.status === 'returned'
                            ? 'frais de retour réglés avant remise à l’expéditeur.'
                            : 'document remis à l’expéditeur au moment du dépôt.'}
                    </DialogDescription>
                </DialogHeader>

                {loading && (
                    <div className="flex min-h-40 items-center justify-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        Chargement du reçu…
                    </div>
                )}

                {error !== null && (
                    <div className="flex gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        {error}
                    </div>
                )}

                {receipt !== null && (
                    <>
                        <div
                            className={`flex items-start gap-3 rounded-2xl border p-3 ${
                                certified
                                    ? 'border-emerald-600/30 bg-emerald-50'
                                    : 'border-gold/35 bg-gold/10'
                            }`}
                        >
                            {certified ? (
                                <ShieldCheck className="mt-0.5 size-5 shrink-0 text-emerald-700" />
                            ) : (
                                <FileWarning className="mt-0.5 size-5 shrink-0 text-gold" />
                            )}
                            <div className="min-w-0">
                                <strong className="block text-sm">
                                    {receipt.receipt_label}
                                </strong>
                                {certified ? (
                                    <span className="mt-1 block text-xs text-emerald-800">
                                        Reçu fiscal certifié · MECeF{' '}
                                        {receipt.fiscal.code}
                                    </span>
                                ) : (
                                    <span className="mt-1 block text-xs text-foreground/75">
                                        Ce document atteste l’encaissement KORA
                                        mais n’est pas un reçu fiscal certifié.
                                        La certification MECeF est en attente
                                        d’un état assaini.
                                    </span>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-[1fr_150px]">
                            <div className="grid gap-2 text-sm">
                                <div className="flex justify-between gap-3 border-b border-black/5 pb-1">
                                    <span className="text-muted-foreground">
                                        Référence
                                    </span>
                                    <strong className="font-mono text-xs">
                                        {receipt.reference}
                                    </strong>
                                </div>
                                <div className="flex justify-between gap-3 border-b border-black/5 pb-1">
                                    <span className="text-muted-foreground">
                                        Trajet
                                    </span>
                                    <strong className="text-right">
                                        {receipt.route.origin ?? '—'} →{' '}
                                        {receipt.route.destination ?? '—'}
                                    </strong>
                                </div>
                                <div className="flex justify-between gap-3 border-b border-black/5 pb-1">
                                    <span className="text-muted-foreground">
                                        Poids
                                    </span>
                                    <strong>
                                        {formatGrams(
                                            receipt.parcel.weight_grams,
                                        )}
                                    </strong>
                                </div>
                                <div className="flex justify-between gap-3 border-b border-black/5 pb-1">
                                    <span className="text-muted-foreground">
                                        Expéditeur
                                    </span>
                                    <strong className="text-right">
                                        {receipt.parties.sender_name}
                                    </strong>
                                </div>
                                <div className="flex justify-between gap-3 border-b border-black/5 pb-1">
                                    <span className="text-muted-foreground">
                                        Destinataire
                                    </span>
                                    <strong className="text-right">
                                        {receipt.parties.recipient_name}
                                    </strong>
                                </div>
                                <div className="flex justify-between gap-3 border-b border-black/5 pb-1">
                                    <span className="text-muted-foreground">
                                        Paiement
                                    </span>
                                    <strong>
                                        {methodLabel(receipt.payment.method)}
                                    </strong>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Émis le
                                    </span>
                                    <strong>
                                        {formatDate(receipt.issued_at)}
                                    </strong>
                                </div>
                            </div>

                            <div className="flex flex-col items-center justify-center rounded-2xl bg-muted/35 p-3">
                                <QRCodeSVG
                                    value={
                                        certified &&
                                        receipt.fiscal.qr_payload !== null
                                            ? receipt.fiscal.qr_payload
                                            : receipt.qr_payload
                                    }
                                    size={112}
                                    level="M"
                                    includeMargin
                                />
                                <span className="mt-2 text-center text-[0.625rem] font-bold text-muted-foreground">
                                    {certified
                                        ? 'QR fiscal MECeF'
                                        : 'QR de vérification KORA'}
                                </span>
                            </div>
                        </div>

                        <div className="bg-navy rounded-2xl p-4 text-white">
                            <div className="grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <span className="block text-white/55">
                                        Net HT
                                    </span>
                                    <strong className="mt-0.5 block text-sm">
                                        {formatAmount(
                                            receipt.charge.net,
                                            receipt.currency,
                                        )}
                                    </strong>
                                </div>
                                <div>
                                    <span className="block text-white/55">
                                        TVA {receipt.charge.tax_rate}%
                                    </span>
                                    <strong className="mt-0.5 block text-sm">
                                        {formatAmount(
                                            receipt.charge.tax,
                                            receipt.currency,
                                        )}
                                    </strong>
                                </div>
                                <div>
                                    <span className="block text-white/55">
                                        Total TTC
                                    </span>
                                    <strong className="mt-0.5 block text-base text-gold">
                                        {formatAmount(
                                            receipt.charge.total,
                                            receipt.currency,
                                        )}
                                    </strong>
                                </div>
                            </div>
                            <div className="mt-2 flex items-center gap-1 text-[0.625rem] text-white/55">
                                <CheckCircle2 className="size-3" />
                                Code fiscal {receipt.charge.tax_code} · grille v
                                {receipt.charge.tariff_version ?? '—'}
                            </div>
                        </div>
                    </>
                )}

                <DialogFooter>
                    <ParcelButton
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                    >
                        Fermer
                    </ParcelButton>
                    <ParcelButton
                        variant="navy"
                        onClick={() => window.print()}
                        disabled={receipt === null}
                    >
                        <Printer className="size-4" />
                        Imprimer
                    </ParcelButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
