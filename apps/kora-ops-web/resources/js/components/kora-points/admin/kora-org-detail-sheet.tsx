import { router } from '@inertiajs/react';
import {
    AlertCircle,
    Coins,
    Download,
    FileText,
    Link2,
    Phone,
    Store,
    Unlink,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import koraPoints from '@/routes/kora-points';
import type {
    AdminOrganization,
    AdminPermissions,
    OrgSubPoint,
    UnattachedPoint,
} from './kora-admin-ui';
import { formatAmount, PointStatusBadge } from './kora-admin-ui';

type Props = {
    open: boolean;
    organization: AdminOrganization | null;
    subpoints: OrgSubPoint[];
    unattachedPoints: UnattachedPoint[];
    permissions: AdminPermissions;
    onOpenChange: (open: boolean) => void;
};

const frequencyLabels: Record<number, string> = {
    1: 'Quotidien',
    7: 'Hebdomadaire',
    30: 'Mensuel',
};

export function KoraOrgDetailSheet({
    open,
    organization,
    subpoints,
    unattachedPoints,
    permissions,
    onOpenChange,
}: Props) {
    const [attachUuid, setAttachUuid] = useState('');
    const [attachError, setAttachError] = useState<string | null>(null);
    const [attaching, setAttaching] = useState(false);

    if (!organization) {
        return null;
    }

    const orgPoints = subpoints.filter(
        (sp) => sp.organization_uuid === organization.uuid,
    );

    const updatePointOrganization = (
        point: OrgSubPoint | UnattachedPoint,
        organizationUuid: string | null,
    ) => {
        setAttachError(null);
        setAttaching(true);
        router.put(
            koraPoints.update.url({ point: point.uuid }),
            {
                code: point.code,
                name: point.name,
                city: point.city,
                country:
                    'country' in point && point.country ? point.country : 'BJ',
                responsible_name:
                    'manager_name' in point && point.manager_name
                        ? point.manager_name
                        : point.responsible_name,
                phone: point.phone,
                address: point.address,
                latitude: 'latitude' in point ? point.latitude : null,
                longitude: 'longitude' in point ? point.longitude : null,
                organization_uuid: organizationUuid,
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setAttachError(
                        Object.values(errors)[0] ??
                            'Le rattachement a été refusé.',
                    ),
                onFinish: () => setAttaching(false),
            },
        );
    };

    const attach = () => {
        const point = unattachedPoints.find((p) => p.uuid === attachUuid);

        if (!point) {
            return;
        }

        updatePointOrganization(point, organization.uuid);
        setAttachUuid('');
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex h-full w-full flex-col gap-0 overflow-y-auto border-l border-border bg-card p-0 shadow-[-20px_0_60px_rgba(0,29,78,0.18)] sm:max-w-[38rem]">
                <SheetTitle className="sr-only">
                    Organisation {organization.name}
                </SheetTitle>
                <SheetDescription className="sr-only">
                    Fiche détaillée de l’organisation partenaire
                </SheetDescription>

                <div className="border-b border-border bg-card px-6 py-5">
                    <div className="flex items-start gap-3">
                        {organization.logo_url ? (
                            <img
                                src={organization.logo_url}
                                alt={`Logo ${organization.name}`}
                                className="size-11 shrink-0 rounded-xl bg-white object-contain ring-1 ring-border"
                            />
                        ) : (
                            <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-kora text-xs font-black text-white">
                                {organization.code.slice(0, 3)}
                            </span>
                        )}
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <span className="section-kicker">
                                    Réseau commercial · Organisation
                                </span>
                                <span className="text-muted-foreground/40">
                                    •
                                </span>
                                <span className="font-mono text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                    {organization.code}
                                </span>
                            </div>
                            <h3 className="mt-1 truncate text-lg font-black tracking-tight text-foreground">
                                {organization.name}
                            </h3>
                            <div className="mt-1 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                {organization.phone && (
                                    <span className="flex items-center gap-1">
                                        <Phone className="size-3 text-gold" />
                                        {organization.phone}
                                    </span>
                                )}
                                <span className="flex items-center gap-1">
                                    <Store className="size-3 text-gold" />
                                    {organization.points_count} point(s)
                                    rattaché(s)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex-1 space-y-6 overflow-y-auto px-6 py-6">
                    <div className="rounded-2xl border border-border bg-gradient-to-b from-muted/30 to-muted/10 p-5 shadow-xs">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <Coins className="size-4 text-gold" />
                                <strong className="text-sm font-bold text-foreground">
                                    Conditions cadres
                                </strong>
                            </div>
                        </div>
                        <dl className="mt-4 grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Commission
                                </dt>
                                <dd className="font-bold text-foreground">
                                    {(
                                        organization.default_terms
                                            .commission_rate_bps / 100
                                    ).toLocaleString('fr-FR', {
                                        maximumFractionDigits: 2,
                                    })}
                                    {' %'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Plafond global
                                </dt>
                                <dd className="font-bold text-foreground">
                                    {formatAmount(
                                        organization.default_terms.credit_limit,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Reversements
                                </dt>
                                <dd className="font-bold text-foreground">
                                    {frequencyLabels[
                                        organization.default_terms
                                            .settlement_frequency_days
                                    ] ?? 'Hebdomadaire'}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <div className="mb-3 flex items-center justify-between">
                            <h4 className="flex items-center gap-2 text-sm font-bold text-foreground">
                                <Store className="size-4 text-gold" />
                                Points rattachés ({orgPoints.length})
                            </h4>
                        </div>

                        {permissions.canManage &&
                            unattachedPoints.length > 0 && (
                                <div className="mb-4 flex items-center gap-2">
                                    <select
                                        className="field flex-1"
                                        value={attachUuid}
                                        onChange={(e) =>
                                            setAttachUuid(e.target.value)
                                        }
                                    >
                                        <option value="">
                                            Rattacher un point autonome…
                                        </option>
                                        {unattachedPoints.map((p) => (
                                            <option key={p.uuid} value={p.uuid}>
                                                {p.name} ({p.code}) — {p.city}
                                            </option>
                                        ))}
                                    </select>
                                    <button
                                        type="button"
                                        className="btn btn-primary btn-sm"
                                        onClick={attach}
                                        disabled={!attachUuid || attaching}
                                    >
                                        <Link2 className="size-3.5" />
                                        {attaching ? 'Rattachement…' : 'Rattacher'}
                                    </button>
                                </div>
                            )}

                        {attachError && (
                            <div className="mb-4 flex items-start gap-2.5 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3">
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

                        {orgPoints.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border px-4 py-8 text-center">
                                <Store className="mx-auto size-8 text-muted-foreground/40" />
                                <p className="mt-2 text-sm font-medium text-muted-foreground">
                                    Aucun point rattaché
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Rattachez un point autonome ou créez-en un
                                    depuis l’écran Points de vente.
                                </p>
                            </div>
                        ) : (
                            <ul className="space-y-2">
                                {orgPoints.map((sp) => (
                                    <li
                                        key={sp.uuid}
                                        className="flex items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <strong className="truncate text-sm font-bold text-foreground">
                                                    {sp.name}
                                                </strong>
                                                <PointStatusBadge
                                                    status={sp.status}
                                                />
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                {sp.code} · {sp.city} ·{' '}
                                                {(
                                                    sp.commission_rate_bps / 100
                                                ).toLocaleString('fr-FR', {
                                                    maximumFractionDigits: 2,
                                                })}
                                                {' % · '}
                                                {formatAmount(
                                                    sp.current_exposure,
                                                )}{' '}
                                                /{' '}
                                                {formatAmount(sp.credit_limit)}
                                            </span>
                                        </div>
                                        {permissions.canManage && (
                                            <div className="flex shrink-0 items-center gap-1">
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs"
                                                    title="Détacher le point"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Détacher « ${sp.name} » de ${organization.name} ?`,
                                                            )
                                                        ) {
                                                            updatePointOrganization(
                                                                sp,
                                                                null,
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <Unlink className="size-3.5" />
                                                </button>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {organization.users.length > 0 && (
                        <div>
                            <h4 className="mb-3 flex items-center gap-2 text-sm font-bold text-foreground">
                                <Users className="size-4 text-gold" />
                                Utilisateurs assignés
                            </h4>
                            <ul className="space-y-2">
                                {organization.users.map((user) => (
                                    <li
                                        key={user.uuid}
                                        className="flex items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <strong className="block truncate text-sm font-bold text-foreground">
                                                {user.name}
                                            </strong>
                                            <span className="text-xs text-muted-foreground">
                                                {user.role_label} · {user.email}
                                            </span>
                                        </div>
                                        <span
                                            className={
                                                user.status === 'ACTIVE'
                                                    ? 'text-xs font-bold text-emerald-600'
                                                    : 'text-xs font-bold text-destructive'
                                            }
                                        >
                                            {user.status === 'ACTIVE'
                                                ? 'Actif'
                                                : 'Suspendu'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {organization.documents.length > 0 && (
                        <div>
                            <h4 className="mb-3 flex items-center gap-2 text-sm font-bold text-foreground">
                                <FileText className="size-4 text-gold" />
                                Documents contractuels
                            </h4>
                            <ul className="space-y-2">
                                {organization.documents.map((doc) => (
                                    <li
                                        key={doc.uuid}
                                        className="flex items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <strong className="block truncate text-sm font-medium text-foreground">
                                                {doc.name}
                                            </strong>
                                            <span className="text-xs text-muted-foreground">
                                                {doc.type} · {doc.uploaded_at}
                                            </span>
                                        </div>
                                        <a
                                            href={doc.url}
                                            className="btn btn-ghost btn-xs shrink-0"
                                            title="Télécharger"
                                        >
                                            <Download className="size-3.5" />
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
