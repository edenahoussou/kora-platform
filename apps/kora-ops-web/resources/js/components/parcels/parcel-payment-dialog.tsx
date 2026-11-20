import {
    ArrowRight,
    Banknote,
    CheckCircle2,
    Coins,
    Loader2,
    LockKeyhole,
    Smartphone,
    TriangleAlert,
    WalletCards,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type {
    LaravelValidationErrors,
    ParcelListItem,
    ParcelPaymentOptions,
    ParcelPaymentResult,
} from '@/components/parcels/types';
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
    onPaid: (payment: ParcelPaymentResult) => void;
};

type Method = 'cash' | 'mobile_money';

function xsrfToken(): string {
    const value = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';

    return decodeURIComponent(value);
}

function newIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16);
        const value = char === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}

function formatAmount(value: number, currency: string): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} ${currency}`;
}

function responseMessage(payload: unknown, fallback: string): string {
    if (
        payload !== null &&
        typeof payload === 'object' &&
        'message' in payload &&
        typeof payload.message === 'string'
    ) {
        return payload.message;
    }

    return fallback;
}

function validationErrors(payload: unknown): LaravelValidationErrors {
    if (
        payload !== null &&
        typeof payload === 'object' &&
        'errors' in payload &&
        payload.errors !== null &&
        typeof payload.errors === 'object'
    ) {
        return payload.errors as LaravelValidationErrors;
    }

    return {};
}

export function ParcelPaymentDialog({
    open,
    parcel,
    onOpenChange,
    onPaid,
}: Props) {
    const [options, setOptions] = useState<ParcelPaymentOptions | null>(null);
    const [loading, setLoading] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [method, setMethod] = useState<Method>('cash');
    const [accountUuid, setAccountUuid] = useState('');
    const [providerReference, setProviderReference] = useState('');
    const [cashReceived, setCashReceived] = useState('');
    const [idempotencyKey, setIdempotencyKey] = useState(newIdempotencyKey);

    useEffect(() => {
        if (!open || parcel === null) {
            return;
        }

        let cancelled = false;

        const load = async () => {
            setLoading(true);
            setError(null);
            setOptions(null);
            setMethod('cash');
            setAccountUuid('');
            setProviderReference('');
            setCashReceived('');
            setIdempotencyKey(newIdempotencyKey());

            try {
                const response = await fetch(
                    `/parcels/data/${parcel.uuid}/payment-options`,
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    },
                );
                const body = (await response.json().catch(() => null)) as {
                    data?: ParcelPaymentOptions;
                } | null;

                if (!response.ok || body?.data === undefined) {
                    throw new Error(
                        responseMessage(
                            body,
                            'Les moyens d’encaissement du guichet sont indisponibles.',
                        ),
                    );
                }

                if (cancelled) {
                    return;
                }

                setOptions(body.data);
                const electronic = body.data.methods.find(
                    (candidate) => candidate.code === 'mobile_money',
                );
                setAccountUuid(electronic?.accounts?.[0]?.uuid ?? '');

                if (
                    !body.data.methods.some(
                        (candidate) =>
                            candidate.code === 'cash' && candidate.available,
                    )
                ) {
                    setMethod('mobile_money');
                }
            } catch (reason) {
                if (!cancelled) {
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : 'Les moyens d’encaissement du guichet sont indisponibles.',
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

    const total = parcel.quote?.total ?? 0;
    const currency = options?.currency ?? parcel.quote?.currency ?? '';
    const isReturn = parcel.status === 'returned';
    const received = Number.parseInt(cashReceived, 10) || 0;
    const change = Math.max(received - total, 0);
    const accounts =
        options?.methods.find((candidate) => candidate.code === 'mobile_money')
            ?.accounts ?? [];
    const hasSession = options?.cash_session !== null && options !== null;

    const submit = async () => {
        if (busy || parcel.quote === null) {
            return;
        }

        if (!hasSession) {
            setError(
                'Ouvrez une session de caisse pour l’agence de ce hub avant d’encaisser.',
            );

            return;
        }

        if (method === 'cash' && received < total) {
            setError('Le montant reçu est insuffisant.');

            return;
        }

        if (
            method === 'mobile_money' &&
            (accountUuid === '' || providerReference.trim() === '')
        ) {
            setError(
                'Sélectionnez le compte Mobile Money et saisissez la référence du fournisseur.',
            );

            return;
        }

        setBusy(true);
        setError(null);

        try {
            const response = await fetch(`/parcels/data/${parcel.uuid}/pay`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(
                    method === 'cash'
                        ? {
                              idempotency_key: idempotencyKey,
                              method: 'cash',
                              amount: total,
                              cash_received_amount: received,
                          }
                        : {
                              idempotency_key: idempotencyKey,
                              method: 'mobile_money',
                              amount: total,
                              agency_payment_account_uuid: accountUuid,
                              provider_reference: providerReference.trim(),
                              mobile_money_network:
                                  accounts.find(
                                      (account) => account.uuid === accountUuid,
                                  )?.network ?? null,
                          },
                ),
            });
            const body = (await response.json().catch(() => null)) as {
                data?: ParcelPaymentResult;
            } | null;

            if (!response.ok || body?.data === undefined) {
                const errors = validationErrors(body);
                setError(
                    Object.values(errors)[0]?.[0] ??
                        responseMessage(
                            body,
                            'Le paiement n’a pas été confirmé.',
                        ),
                );

                return;
            }

            toast.success(
                `Paiement ${formatAmount(total, currency)} encaissé sur ${parcel.reference}.`,
            );
            onPaid(body.data);
        } catch {
            setError(
                'Le réseau est instable. Aucun encaissement n’a été enregistré.',
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-[560px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <WalletCards className="size-5 text-gold" />
                        {isReturn
                            ? 'Encaisser les frais de retour'
                            : 'Encaisser le colis'}
                    </DialogTitle>
                    <DialogDescription>
                        {parcel.reference} · {parcel.origin ?? '—'} →{' '}
                        {parcel.destination ?? '—'} · {parcel.sender_name} →{' '}
                        {parcel.recipient_name}
                    </DialogDescription>
                </DialogHeader>

                <div className="bg-navy rounded-2xl p-4 text-white">
                    <div className="text-[0.625rem] font-black tracking-[0.16em] text-white/55 uppercase">
                        {isReturn
                            ? 'Frais de retour à encaisser'
                            : 'Montant à encaisser'}
                    </div>
                    <div className="mt-1 text-2xl font-black">
                        {formatAmount(total, currency)}
                    </div>
                    <div className="mt-1 text-xs text-white/65">
                        {isReturn
                            ? 'Frais de retour calculés · TVA'
                            : 'Devis pesé définitif · TVA'}{' '}
                        {parcel.quote
                            ? formatAmount(parcel.quote.tax, currency)
                            : '—'}
                    </div>
                </div>

                {loading && (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        Vérification de la caisse et des moyens autorisés…
                    </div>
                )}

                {!loading && options !== null && !hasSession && (
                    <div className="flex gap-2 rounded-xl border border-gold/30 bg-gold/10 p-3 text-sm text-foreground">
                        <LockKeyhole className="mt-0.5 size-4 shrink-0 text-gold" />
                        <div>
                            <strong className="block">
                                Session de caisse requise
                            </strong>
                            Ouvrez la caisse de l’agence du hub de dépôt avant
                            d’encaisser ce colis.
                        </div>
                    </div>
                )}

                {!loading && options !== null && hasSession && (
                    <>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                onClick={() => setMethod('cash')}
                                className={`rounded-2xl border p-3 text-left transition ${
                                    method === 'cash'
                                        ? 'border-gold bg-gold/10'
                                        : 'border-black/10 bg-muted/30'
                                }`}
                            >
                                <Banknote className="text-navy size-4" />
                                <strong className="mt-2 block text-sm">
                                    Espèces
                                </strong>
                                <span className="text-xs text-muted-foreground">
                                    Caisse guichet ouverte
                                </span>
                            </button>
                            <button
                                type="button"
                                disabled={accounts.length === 0}
                                onClick={() => setMethod('mobile_money')}
                                className={`rounded-2xl border p-3 text-left transition disabled:opacity-45 ${
                                    method === 'mobile_money'
                                        ? 'border-gold bg-gold/10'
                                        : 'border-black/10 bg-muted/30'
                                }`}
                            >
                                <Smartphone className="text-navy size-4" />
                                <strong className="mt-2 block text-sm">
                                    Mobile Money
                                </strong>
                                <span className="text-xs text-muted-foreground">
                                    {accounts.length === 0
                                        ? 'Aucun compte actif'
                                        : `${accounts.length} compte(s) actif(s)`}
                                </span>
                            </button>
                        </div>

                        {method === 'cash' ? (
                            <div className="grid gap-2">
                                <label className="text-xs font-bold text-foreground">
                                    Montant reçu
                                    <input
                                        inputMode="numeric"
                                        value={cashReceived}
                                        onChange={(event) =>
                                            setCashReceived(
                                                event.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        className="mt-1 h-11 w-full rounded-xl border border-black/10 bg-card px-3 text-sm font-bold outline-none focus:ring-2 focus:ring-gold/35"
                                        placeholder={String(total)}
                                    />
                                </label>
                                <div className="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2 text-xs">
                                    <span className="text-muted-foreground">
                                        Rendu monnaie
                                    </span>
                                    <strong>
                                        {formatAmount(change, currency)}
                                    </strong>
                                </div>
                            </div>
                        ) : (
                            <div className="grid gap-2">
                                <label className="text-xs font-bold text-foreground">
                                    Compte d’encaissement
                                    <select
                                        value={accountUuid}
                                        onChange={(event) =>
                                            setAccountUuid(event.target.value)
                                        }
                                        className="mt-1 h-11 w-full rounded-xl border border-black/10 bg-card px-3 text-sm font-bold outline-none focus:ring-2 focus:ring-gold/35"
                                    >
                                        {accounts.map((account) => (
                                            <option
                                                key={account.uuid}
                                                value={account.uuid}
                                            >
                                                {account.label} ·{' '}
                                                {account.network}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="text-xs font-bold text-foreground">
                                    Référence fournisseur
                                    <input
                                        value={providerReference}
                                        onChange={(event) =>
                                            setProviderReference(
                                                event.target.value,
                                            )
                                        }
                                        className="mt-1 h-11 w-full rounded-xl border border-black/10 bg-card px-3 font-mono text-sm outline-none focus:ring-2 focus:ring-gold/35"
                                        placeholder="Référence de la transaction"
                                    />
                                </label>
                            </div>
                        )}

                        <div className="flex items-center gap-2 rounded-xl bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                            <CheckCircle2 className="size-4 text-emerald-600" />
                            Caisse :{' '}
                            {options.cash_session?.cash_register ?? 'guichet'} ·
                            Agence : {options.agency?.name ?? '—'}
                        </div>
                    </>
                )}

                {error !== null && (
                    <div className="flex gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        {error}
                    </div>
                )}

                <DialogFooter>
                    <ParcelButton
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={busy}
                    >
                        Fermer
                    </ParcelButton>
                    <ParcelButton
                        variant="primary"
                        onClick={() => void submit()}
                        disabled={
                            busy ||
                            loading ||
                            !hasSession ||
                            parcel.quote === null
                        }
                    >
                        {busy ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Coins className="size-4" />
                        )}
                        {busy ? 'Encaissement…' : 'Confirmer le paiement'}
                        {!busy && <ArrowRight className="size-4" />}
                    </ParcelButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
