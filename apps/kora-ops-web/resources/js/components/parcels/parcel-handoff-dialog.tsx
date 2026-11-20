import {
    BadgeCheck,
    KeyRound,
    Loader2,
    RotateCw,
    ShieldAlert,
    ShieldCheck,
    TriangleAlert,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { ParcelButton } from '@/components/parcels/parcel-button';
import type {
    LaravelValidationErrors,
    ParcelListItem,
} from '@/components/parcels/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ParcelHandoffMode = 'receive' | 'release' | 'reissue';

type Props = {
    open: boolean;
    parcel: ParcelListItem | null;
    mode: ParcelHandoffMode;
    onOpenChange: (open: boolean) => void;
    onDone: (message: string) => void;
    onRefresh?: () => void;
};

type VerificationMethod = 'code' | 'identity';

function xsrfToken(): string {
    const value = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';

    return decodeURIComponent(value);
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

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function ParcelHandoffDialog({
    open,
    parcel,
    mode,
    onOpenChange,
    onDone,
    onRefresh,
}: Props) {
    const [method, setMethod] = useState<VerificationMethod>('code');
    const [code, setCode] = useState('');
    const [identityType, setIdentityType] = useState('national_id');
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [attemptsRemaining, setAttemptsRemaining] = useState<number | null>(
        parcel?.handoff?.attempts_remaining ?? null,
    );

    if (parcel === null) {
        return null;
    }

    const isReturn = parcel.status === 'returned';
    const isReceive = mode === 'receive';
    const isReissue = mode === 'reissue';
    const identityAllowed = parcel.can_override_handoff === true;

    const title = isReceive
        ? isReturn
            ? 'Réceptionner le retour'
            : 'Réceptionner le colis'
        : isReissue
          ? 'Renvoyer le code de retrait'
          : isReturn
            ? 'Remettre le retour à l’expéditeur'
            : 'Remettre le colis au destinataire';

    const submit = async () => {
        if (busy) {
            return;
        }

        const endpoint = isReceive
            ? 'receive'
            : isReissue
              ? 'reissue-code'
              : 'release';

        if (
            !isReceive &&
            !isReissue &&
            method === 'code' &&
            code.length !== 6
        ) {
            setError('Saisissez les 6 chiffres du code de retrait.');

            return;
        }

        if (
            !isReceive &&
            !isReissue &&
            method === 'identity' &&
            reason.trim() === ''
        ) {
            setError(
                'Indiquez le motif du contrôle d’identité dans le journal.',
            );

            return;
        }

        setBusy(true);
        setError(null);

        try {
            const payload =
                isReceive || isReissue
                    ? {}
                    : method === 'code'
                      ? { code }
                      : { identity_type: identityType, reason: reason.trim() };
            const response = await fetch(
                `/parcels/data/${parcel.uuid}/${endpoint}`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify(payload),
                },
            );
            const body = (await response.json().catch(() => null)) as {
                message?: string;
            } | null;

            if (!response.ok) {
                const errors = validationErrors(body);
                setError(
                    Object.values(errors)[0]?.[0] ??
                        responseMessage(
                            body,
                            isReceive
                                ? 'La réception n’a pas pu être enregistrée.'
                                : isReissue
                                  ? 'Le code n’a pas pu être renvoyé.'
                                  : 'La remise n’a pas pu être enregistrée.',
                        ),
                );

                if (!isReceive && !isReissue) {
                    if (method === 'code') {
                        setAttemptsRemaining((current) =>
                            response.status === 429
                                ? 0
                                : current === null
                                  ? null
                                  : Math.max(0, current - 1),
                        );

                        try {
                            const detail = await fetch(
                                `/parcels/data/${parcel.uuid}`,
                                {
                                    headers: { Accept: 'application/json' },
                                    credentials: 'same-origin',
                                },
                            );

                            if (detail.ok) {
                                const detailBody = (await detail.json()) as {
                                    data?: {
                                        handoff?: {
                                            attempts_remaining?: number;
                                        };
                                    };
                                };
                                setAttemptsRemaining(
                                    detailBody.data?.handoff
                                        ?.attempts_remaining ?? null,
                                );
                            }
                        } catch {
                            // Le compteur local reste décrémenté.
                        }
                    }

                    onRefresh?.();
                }

                return;
            }

            onDone(
                isReceive
                    ? isReturn
                        ? `Retour de ${parcel.reference} réceptionné à destination.`
                        : `Colis ${parcel.reference} réceptionné au lieu de destination.`
                    : isReissue
                      ? `Nouveau code de retrait généré pour ${parcel.reference}. L’ancien code est invalidé.`
                      : `Colis ${parcel.reference} remis et preuve de remise disponible.`,
            );
        } catch {
            setError(
                'Le réseau est instable. Aucune opération n’a été confirmée.',
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
                        {isReceive ? (
                            <Truck className="size-5 text-gold" />
                        ) : isReissue ? (
                            <RotateCw className="size-5 text-gold" />
                        ) : (
                            <KeyRound className="size-5 text-gold" />
                        )}
                        {title}
                    </DialogTitle>
                    <DialogDescription>
                        {parcel.reference} · {parcel.origin ?? '—'} →{' '}
                        {parcel.destination ?? '—'} · {parcel.recipient_name}
                    </DialogDescription>
                </DialogHeader>

                {isReceive && (
                    <>
                        <div className="flex gap-3 rounded-2xl border border-emerald-600/25 bg-emerald-50 p-4 text-sm">
                            <ShieldCheck className="mt-0.5 size-5 shrink-0 text-emerald-700" />
                            <div>
                                <strong className="block text-foreground">
                                    Confirmer la réception physique
                                </strong>
                                <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                    Le colis sera déclaré disponible au retrait
                                    à {parcel.handoff?.hub_name ?? 'ce lieu'}.
                                    L’agent, le lieu et l’heure seront
                                    journalisés.
                                </span>
                            </div>
                        </div>
                        {isReturn && (
                            <div className="flex gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                Ce retour doit être remis à l’expéditeur
                                uniquement après règlement des frais de retour.
                            </div>
                        )}
                    </>
                )}

                {isReissue && (
                    <>
                        <div className="flex gap-3 rounded-2xl border border-gold/35 bg-gold/10 p-4 text-sm">
                            <ShieldAlert className="mt-0.5 size-5 shrink-0 text-gold" />
                            <div>
                                <strong className="block text-foreground">
                                    Un seul code reste valide
                                </strong>
                                <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                    Le nouveau code à 6 chiffres est envoyé au
                                    destinataire. L’ancien code est invalidé et
                                    le compteur de tentatives est remis à zéro.
                                    Le code n’apparaît jamais dans cette
                                    interface.
                                </span>
                            </div>
                        </div>
                        {attemptsRemaining !== null && (
                            <p className="text-xs text-muted-foreground">
                                Tentatives restantes avant réémission :{' '}
                                <strong className="text-foreground">
                                    {attemptsRemaining}
                                </strong>
                            </p>
                        )}
                    </>
                )}

                {!isReceive && !isReissue && (
                    <>
                        <div className="grid gap-3 rounded-2xl border border-border bg-muted/30 p-4 text-xs sm:grid-cols-2">
                            <div>
                                <span className="block text-muted-foreground">
                                    Destinataire
                                </span>
                                <strong className="text-foreground">
                                    {isReturn
                                        ? parcel.sender_name
                                        : parcel.recipient_name}
                                </strong>
                            </div>
                            <div>
                                <span className="block text-muted-foreground">
                                    Retrait avant
                                </span>
                                <strong className="text-foreground">
                                    {formatDate(parcel.withdraw_by)}
                                </strong>
                            </div>
                            <div>
                                <span className="block text-muted-foreground">
                                    Tentatives restantes
                                </span>
                                <strong className="text-foreground">
                                    {attemptsRemaining ?? '—'}
                                </strong>
                            </div>
                            <div>
                                <span className="block text-muted-foreground">
                                    Lieu
                                </span>
                                <strong className="text-foreground">
                                    {parcel.handoff?.hub_name ?? '—'}
                                </strong>
                            </div>
                        </div>

                        {identityAllowed && (
                            <div className="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    onClick={() => setMethod('code')}
                                    className={`rounded-2xl border p-3 text-left transition ${
                                        method === 'code'
                                            ? 'border-gold bg-gold/10'
                                            : 'border-black/10 bg-muted/30'
                                    }`}
                                >
                                    <KeyRound className="text-navy size-4" />
                                    <strong className="mt-2 block text-sm">
                                        Code de retrait
                                    </strong>
                                    <span className="text-xs text-muted-foreground">
                                        Vérification standard
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setMethod('identity')}
                                    className={`rounded-2xl border p-3 text-left transition ${
                                        method === 'identity'
                                            ? 'border-gold bg-gold/10'
                                            : 'border-black/10 bg-muted/30'
                                    }`}
                                >
                                    <BadgeCheck className="text-navy size-4" />
                                    <strong className="mt-2 block text-sm">
                                        Pièce d’identité
                                    </strong>
                                    <span className="text-xs text-muted-foreground">
                                        Contrôle exceptionnel
                                    </span>
                                </button>
                            </div>
                        )}

                        {method === 'code' ? (
                            <label className="field">
                                <span className="text-xs font-bold text-foreground">
                                    Code secret à 6 chiffres
                                </span>
                                <input
                                    autoFocus
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={6}
                                    value={code}
                                    onChange={(event) =>
                                        setCode(
                                            event.target.value
                                                .replace(/\D/g, '')
                                                .slice(0, 6),
                                        )
                                    }
                                    className="control mt-1 h-12 w-full text-center font-mono text-xl font-black tracking-[0.35em]"
                                    placeholder="••••••"
                                    aria-invalid={error !== null}
                                />
                                <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                                    Le code est vérifié par empreinte et
                                    immédiatement consommé après remise.
                                </span>
                            </label>
                        ) : (
                            <div className="grid gap-3">
                                <div className="flex gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                                    <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                                    Contrôle réservé aux agents autorisés. Ne
                                    saisissez ni numéro ni copie de la pièce :
                                    seul le type et le motif sont journalisés.
                                </div>
                                <label className="field">
                                    <span className="text-xs font-bold text-foreground">
                                        Type de pièce
                                    </span>
                                    <select
                                        value={identityType}
                                        onChange={(event) =>
                                            setIdentityType(event.target.value)
                                        }
                                        className="control mt-1 h-11 w-full"
                                    >
                                        <option value="national_id">
                                            Carte d’identité nationale
                                        </option>
                                        <option value="passport">
                                            Passeport
                                        </option>
                                        <option value="driving_licence">
                                            Permis de conduire
                                        </option>
                                    </select>
                                </label>
                                <label className="field">
                                    <span className="text-xs font-bold text-foreground">
                                        Motif du contrôle
                                    </span>
                                    <textarea
                                        value={reason}
                                        onChange={(event) =>
                                            setReason(event.target.value)
                                        }
                                        maxLength={500}
                                        rows={3}
                                        className="control mt-1 w-full resize-none"
                                        placeholder="Ex. code non reçu, destinataire identifié par l’agence…"
                                    />
                                </label>
                            </div>
                        )}
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
                        Annuler
                    </ParcelButton>
                    <ParcelButton
                        variant={isReceive ? 'success' : 'primary'}
                        onClick={() => void submit()}
                        disabled={
                            busy ||
                            (!isReceive &&
                                !isReissue &&
                                method === 'code' &&
                                code.length !== 6)
                        }
                    >
                        {busy ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : isReceive ? (
                            <Truck className="size-4" />
                        ) : isReissue ? (
                            <RotateCw className="size-4" />
                        ) : (
                            <KeyRound className="size-4" />
                        )}
                        {busy
                            ? 'Enregistrement…'
                            : isReceive
                              ? isReturn
                                  ? 'Confirmer la réception du retour'
                                  : 'Confirmer la réception'
                              : isReissue
                                ? 'Renvoyer le code'
                                : 'Confirmer la remise'}
                    </ParcelButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
