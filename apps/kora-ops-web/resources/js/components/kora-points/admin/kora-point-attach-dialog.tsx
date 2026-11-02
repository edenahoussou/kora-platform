import { router } from '@inertiajs/react';
import { AlertCircle, Coins, Link2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import koraPoints from '@/routes/kora-points';
import type { AdminOrganization, AdminPoint } from './kora-admin-ui';
import { formatAmount } from './kora-admin-ui';

type Props = {
    open: boolean;
    point: AdminPoint | null;
    organizations: AdminOrganization[];
    onOpenChange: (open: boolean) => void;
};

const frequencyLabels: Record<number, string> = {
    1: 'Quotidien',
    7: 'Hebdomadaire',
    10: 'Décadaire',
    15: 'Bimensuel',
    30: 'Mensuel',
};

export function KoraPointAttachDialog({
    open,
    point,
    organizations,
    onOpenChange,
}: Props) {
    const [organizationUuid, setOrganizationUuid] = useState('');
    const [attachError, setAttachError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) {
            setOrganizationUuid('');
            setAttachError(null);
        }
    }, [open]);

    if (!open || !point) {
        return null;
    }

    const selectedOrg = organizations.find((o) => o.uuid === organizationUuid);

    const attach = () => {
        if (!organizationUuid) {
            return;
        }

        setProcessing(true);
        setAttachError(null);

        router.put(
            koraPoints.update.url({ point: point.uuid }),
            {
                code: point.code,
                name: point.name,
                city: point.city,
                country: point.country || 'BJ',
                responsible_name: point.responsible_name,
                phone: point.phone,
                address: point.address,
                latitude: point.latitude,
                longitude: point.longitude,
                organization_uuid: organizationUuid,
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setAttachError(
                        (Object.values(errors)[0] as string) ??
                            'Le rattachement a été refusé.',
                    );
                },
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setAttachError(null);
                    onOpenChange(false);
                },
            },
        );
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) =>
                e.target === e.currentTarget && onOpenChange(false)
            }
        >
            <div
                className="cash-modal cash-opening-modal w-[32rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="kora-attach-modal-title"
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <Link2 className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Réseau commercial · Rattachement
                            </span>
                            <h3 id="kora-attach-modal-title">
                                Rattacher le point
                            </h3>
                            <p>
                                Attribuez « {point.name} » à une organisation
                                partenaire. Il héritera de ses conditions
                                cadres.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="cash-modal-close"
                        aria-label="Fermer"
                        disabled={processing}
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <div className="space-y-4">
                    {attachError && (
                        <div className="flex items-start gap-2.5 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3">
                            <AlertCircle className="mt-0.5 size-4 shrink-0 text-destructive" />
                            <div className="min-w-0">
                                <strong className="block text-xs font-bold text-destructive">
                                    Rattachement impossible
                                </strong>
                                <span className="text-xs text-destructive/90">
                                    {attachError}
                                </span>
                            </div>
                        </div>
                    )}

                    <div className="space-y-2">
                        <span className="text-xs font-bold text-foreground">
                            Organisation partenaire{' '}
                            <b className="font-black text-destructive">*</b>
                        </span>

                        <div
                            role="radiogroup"
                            aria-label="Organisation partenaire"
                            className="max-h-60 space-y-2 overflow-y-auto pr-1"
                        >
                            {organizations.map((org) => {
                                const isSelected = organizationUuid === org.uuid;

                                return (
                                    <label
                                        key={org.uuid}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors',
                                            isSelected
                                                ? 'border-gold bg-gold/5 ring-2 ring-gold'
                                                : 'border-border bg-card hover:border-gold/50 hover:bg-muted/30',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="organization_selection"
                                            value={org.uuid}
                                            checked={isSelected}
                                            onChange={() => {
                                                setOrganizationUuid(org.uuid);
                                                setAttachError(null);
                                            }}
                                            className="sr-only"
                                        />
                                        {org.logo_url ? (
                                            <img
                                                src={org.logo_url}
                                                alt={`Logo ${org.name}`}
                                                className="size-10 shrink-0 rounded-lg bg-white object-contain ring-1 ring-border"
                                            />
                                        ) : (
                                            <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-kora text-xs font-black text-white">
                                                {org.code.slice(0, 3).toUpperCase()}
                                            </span>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center justify-between gap-2">
                                                <strong className="truncate text-sm font-bold text-foreground">
                                                    {org.name}
                                                </strong>
                                                <span className="font-mono text-xs font-semibold text-muted-foreground uppercase">
                                                    {org.code}
                                                </span>
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                {org.points_count} point{org.points_count > 1 ? 's' : ''} rattaché{org.points_count > 1 ? 's' : ''}
                                            </span>
                                        </div>
                                    </label>
                                );
                            })}
                        </div>
                    </div>

                    {selectedOrg && (
                        <div className="rounded-xl border border-border bg-gradient-to-b from-muted/30 to-muted/10 p-4">
                            <div className="flex items-center gap-2">
                                <Coins className="size-4 text-gold" />
                                <strong className="text-xs font-bold text-foreground">
                                    Conditions cadres héritées
                                </strong>
                            </div>
                            <dl className="mt-3 grid grid-cols-3 gap-3 text-xs">
                                <div>
                                    <dt className="text-muted-foreground">Commission</dt>
                                    <dd className="font-bold text-foreground">
                                        {(
                                            selectedOrg.default_terms.commission_rate_bps / 100
                                        ).toLocaleString('fr-FR', { maximumFractionDigits: 2 })}
                                        {' %'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Plafond</dt>
                                    <dd className="font-bold text-foreground">
                                        {formatAmount(selectedOrg.default_terms.credit_limit)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Reversement</dt>
                                    <dd className="font-bold text-foreground">
                                        {frequencyLabels[
                                            selectedOrg.default_terms.settlement_frequency_days
                                        ] ?? 'Hebdomadaire'}
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-3 text-xs text-blue-600 dark:text-blue-400">
                                ✓ Le logo de l'organisation s'affichera sur ce point.
                            </p>
                        </div>
                    )}
                </div>

                <div className="cash-modal-actions mt-6">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={attach}
                        disabled={processing || !organizationUuid}
                    >
                        <Link2 className="size-4" />
                        {processing ? 'Rattachement en cours…' : 'Rattacher le point'}
                    </button>
                </div>
            </div>
        </div>
    );
}
