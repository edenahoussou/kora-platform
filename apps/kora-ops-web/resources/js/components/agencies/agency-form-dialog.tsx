import { Form, router } from '@inertiajs/react';
import {
    Building2,
    Check,
    CreditCard,
    Plus,
    Power,
    UserCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { CorridorCountrySelect } from '@/components/agencies/corridor-country-select';
import { cn } from '@/lib/utils';
import agencies from '@/routes/agencies';

export type AgencyRecord = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    country: string;
    headUserId: number | null;
    headName: string | null;
    status: string;
    createdAt: string | null;
    updatedAt: string | null;
    paymentAccounts: AgencyPaymentAccountRecord[];
};

export type AgencyPaymentAccountRecord = {
    uuid: string;
    network: 'mtn_momo' | 'moov_flooz' | 'celtiis_cash';
    label: string;
    accountIdentifier: string;
    status: 'active' | 'inactive';
};

export type HeadCandidate = {
    id: number;
    name: string;
};

type AgencyFormDialogProps = {
    open: boolean;
    agency?: AgencyRecord | null;
    statuses: Record<string, string>;
    headCandidates: HeadCandidate[];
    onOpenChange: (open: boolean) => void;
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    draft: 'Brouillon',
    suspended: 'Suspendue',
    archived: 'Archivée',
};

export function AgencyFormDialog({
    open,
    agency,
    statuses,
    headCandidates,
    onOpenChange,
}: AgencyFormDialogProps) {
    const isEditing = Boolean(agency);
    const title = isEditing ? 'Modifier l’agence' : 'Nouvelle agence';
    const description = isEditing
        ? 'Mettez à jour les informations du site physique KORA.'
        : 'Créez un nouveau site physique KORA dans le référentiel.';
    const form = isEditing
        ? agencies.update.form({ agency: agency!.uuid })
        : agencies.store.form();

    const [createStatus, setCreateStatus] = useState<'active' | 'draft'>(
        'active',
    );
    const [editStatus, setEditStatus] = useState<string>(
        agency?.status ?? 'active',
    );

    const [accountNetwork, setAccountNetwork] =
        useState<AgencyPaymentAccountRecord['network']>('mtn_momo');
    const [accountLabel, setAccountLabel] = useState('');
    const [accountIdentifier, setAccountIdentifier] = useState('');
    const [accountProcessing, setAccountProcessing] = useState(false);

    const handleClose = (nextOpen: boolean) => {
        onOpenChange(nextOpen);
    };

    const submitAccount = () => {
        if (
            !agency ||
            accountLabel.trim() === '' ||
            accountIdentifier.trim() === ''
        ) {
            return;
        }

        const activeAccount = agency.paymentAccounts.find(
            (account) =>
                account.network === accountNetwork &&
                account.status === 'active',
        );
        const replaceAccountUuid = activeAccount?.uuid;

        if (
            activeAccount &&
            !window.confirm(
                `${activeAccount.label} est déjà actif pour ce réseau. Le nouveau compte le remplacera. Continuer ?`,
            )
        ) {
            return;
        }

        setAccountProcessing(true);
        router.post(
            agencies.paymentAccounts.store.url({ agency: agency.uuid }),
            {
                network: accountNetwork,
                label: accountLabel,
                account_identifier: accountIdentifier,
                replace_account_uuid: replaceAccountUuid,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setAccountProcessing(false);
                    setAccountLabel('');
                    setAccountIdentifier('');
                },
            },
        );
    };

    const toggleAccountStatus = (account: AgencyPaymentAccountRecord) => {
        const nextStatus = account.status === 'active' ? 'inactive' : 'active';
        const activeAccount = agency?.paymentAccounts.find(
            (candidate) =>
                candidate.network === account.network &&
                candidate.status === 'active',
        );
        let replaceAccountUuid: string | undefined;

        if (
            nextStatus === 'inactive' &&
            !window.confirm(`Désactiver ${account.label} ?`)
        ) {
            return;
        }

        if (
            nextStatus === 'active' &&
            activeAccount &&
            activeAccount.uuid !== account.uuid
        ) {
            if (
                !window.confirm(
                    `${account.label} remplacera ${activeAccount.label} comme compte actif. Continuer ?`,
                )
            ) {
                return;
            }

            replaceAccountUuid = activeAccount.uuid;
        }

        router.patch(
            agencies.paymentAccounts.status.url({
                agency: agency!.uuid,
                account: account.uuid,
            }),
            {
                status: nextStatus,
                replace_account_uuid: replaceAccountUuid,
            },
            { preserveScroll: true },
        );
    };

    if (!open) {
        return null;
    }

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) =>
                e.target === e.currentTarget && handleClose(false)
            }
        >
            <Form
                {...form}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() => onOpenChange(false)}
                className="cash-modal cash-opening-modal w-[48rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="agency-modal-title"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    <Building2 className="size-5" />
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="section-kicker">
                                            Référentiel · Agences
                                        </span>
                                        {isEditing && agency?.code && (
                                            <span className="rounded bg-gold/15 px-2 py-0.5 font-mono text-[11px] font-bold text-[var(--kora)]">
                                                {agency.code}
                                            </span>
                                        )}
                                    </div>
                                    <h3 id="agency-modal-title">{title}</h3>
                                    <p>{description}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => handleClose(false)}
                                className="cash-modal-close"
                                aria-label="Fermer"
                                disabled={processing}
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="cash-opening-list">
                            {/* Section 1 : Implantation & Identification */}
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Building2 className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Implantation & Identité</strong>
                                        <small>
                                            Dénomination officielle et
                                            localisation de l’agence
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4">
                                    <label className="field">
                                        <span>
                                            Nom de l’agence{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            className="control"
                                            name="name"
                                            defaultValue={agency?.name ?? ''}
                                            required
                                            placeholder="ex: Agence Cotonou Étoile Rouge"
                                            aria-invalid={!!errors.name}
                                        />
                                        {errors.name ? (
                                            <small className="field-error-text">
                                                {errors.name}
                                            </small>
                                        ) : (
                                            <small>
                                                Dénomination opérationnelle ou
                                                commerciale.
                                            </small>
                                        )}
                                    </label>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field">
                                        <span>
                                            Ville{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            className="control"
                                            name="city"
                                            defaultValue={agency?.city ?? ''}
                                            required
                                            placeholder="ex: Cotonou"
                                            aria-invalid={!!errors.city}
                                        />
                                        {errors.city ? (
                                            <small className="field-error-text">
                                                {errors.city}
                                            </small>
                                        ) : (
                                            <small>
                                                Ville principale d'implantation.
                                            </small>
                                        )}
                                    </label>

                                    <div className="field">
                                        <label
                                            htmlFor="agency-country"
                                            className="font-medium"
                                        >
                                            <span>
                                                Pays du corridor{' '}
                                                <b className="font-black text-destructive">
                                                    *
                                                </b>
                                            </span>
                                        </label>
                                        <CorridorCountrySelect
                                            id="agency-country"
                                            name="country"
                                            defaultValue={
                                                agency?.country ?? 'BJ'
                                            }
                                        />
                                        {errors.country ? (
                                            <small className="field-error-text">
                                                {errors.country}
                                            </small>
                                        ) : (
                                            <small className="text-[11px] text-muted-foreground">
                                                Pays du corridor KORA de
                                                rattachement.
                                            </small>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Section 2 : Supervision & Statut */}
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <UserCheck className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Supervision & Statut</strong>
                                        <small>
                                            Chef d'agence et activation dans le
                                            réseau
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4">
                                    <label className="field">
                                        <span>
                                            Chef d’agence <em>(Optionnel)</em>
                                        </span>
                                        <select
                                            className="control"
                                            name="head_user_id"
                                            defaultValue={
                                                agency?.headUserId ?? ''
                                            }
                                        >
                                            <option value="">
                                                — Aucun chef assigné —
                                            </option>
                                            {headCandidates.map((candidate) => (
                                                <option
                                                    key={candidate.id}
                                                    value={candidate.id}
                                                >
                                                    {candidate.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.head_user_id ? (
                                            <small className="field-error-text">
                                                {errors.head_user_id}
                                            </small>
                                        ) : (
                                            <small>
                                                Collaborateur responsable des
                                                opérations du site.
                                            </small>
                                        )}
                                    </label>

                                    {isEditing ? (
                                        <label className="field">
                                            <span>
                                                Statut de mise en service{' '}
                                                <b className="font-black text-destructive">
                                                    *
                                                </b>
                                            </span>
                                            <select
                                                className="control"
                                                name="status"
                                                value={editStatus}
                                                onChange={(e) =>
                                                    setEditStatus(
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {Object.entries(statuses).map(
                                                    ([value, label]) => (
                                                        <option
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {statusLabels[
                                                                value
                                                            ] ?? label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            {errors.status && (
                                                <small className="field-error-text">
                                                    {errors.status}
                                                </small>
                                            )}
                                        </label>
                                    ) : (
                                        <div>
                                            <input
                                                type="hidden"
                                                name="status"
                                                value={createStatus}
                                            />
                                            <div className="flex items-center justify-between rounded-xl border border-border bg-card p-3">
                                                <div className="space-y-0.5">
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold transition-colors',
                                                                createStatus ===
                                                                    'active'
                                                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                                                    : 'bg-gold/15 text-[var(--kora)] dark:text-gold',
                                                            )}
                                                        >
                                                            {createStatus ===
                                                            'active'
                                                                ? 'Active'
                                                                : 'Brouillon'}
                                                        </span>
                                                        <span className="text-xs font-semibold text-foreground">
                                                            {createStatus ===
                                                            'active'
                                                                ? 'Opérationnelle immédiatement'
                                                                : 'En préparation (non visible aux guichets)'}
                                                        </span>
                                                    </div>
                                                    <small className="block text-[11px] text-muted-foreground">
                                                        {createStatus ===
                                                        'active'
                                                            ? 'L’agence sera directement active pour la billetterie et les caisses.'
                                                            : 'L’agence est enregistrée sans être ouverte aux opérations de vente.'}
                                                    </small>
                                                </div>

                                                <button
                                                    type="button"
                                                    role="switch"
                                                    aria-checked={
                                                        createStatus ===
                                                        'active'
                                                    }
                                                    onClick={() =>
                                                        setCreateStatus(
                                                            (prev) =>
                                                                prev ===
                                                                'active'
                                                                    ? 'draft'
                                                                    : 'active',
                                                        )
                                                    }
                                                    className={cn(
                                                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:ring-2 focus-visible:ring-gold/50 focus-visible:outline-none',
                                                        createStatus ===
                                                            'active'
                                                            ? 'bg-emerald-500'
                                                            : 'bg-muted-foreground/30',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'pointer-events-none inline-block size-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out',
                                                            createStatus ===
                                                                'active'
                                                                ? 'translate-x-5'
                                                                : 'translate-x-0',
                                                        )}
                                                    />
                                                </button>
                                            </div>
                                            {errors.status && (
                                                <small className="field-error-text">
                                                    {errors.status}
                                                </small>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Section 3 (Édition) : Comptes Mobile Money */}
                            {isEditing && (
                                <div className="cash-opening-row">
                                    <div className="cash-opening-row-head">
                                        <span className="cash-opening-icon">
                                            <CreditCard className="size-4" />
                                        </span>
                                        <div>
                                            <strong>
                                                Comptes Mobile Money
                                            </strong>
                                            <small>
                                                Ces comptes seront proposés aux
                                                agents de cette agence.
                                            </small>
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        {agency!.paymentAccounts.length ===
                                        0 ? (
                                            <p className="rounded-lg border border-dashed border-border p-3 text-xs text-muted-foreground">
                                                Aucun compte configuré.
                                            </p>
                                        ) : (
                                            agency!.paymentAccounts.map(
                                                (account) => (
                                                    <div
                                                        key={account.uuid}
                                                        className="flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3 py-2"
                                                    >
                                                        <div>
                                                            <strong className="block text-xs text-foreground">
                                                                {account.label}
                                                            </strong>
                                                            <span className="text-[11px] text-muted-foreground">
                                                                {
                                                                    account.network
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    account.accountIdentifier
                                                                }
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <span
                                                                className={cn(
                                                                    'text-xs font-bold',
                                                                    account.status ===
                                                                        'active'
                                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                                        : 'text-muted-foreground',
                                                                )}
                                                            >
                                                                {account.status ===
                                                                'active'
                                                                    ? 'Actif'
                                                                    : 'Inactif'}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                className="btn btn-ghost btn-sm size-7 p-0"
                                                                aria-label={`${account.status === 'active' ? 'Désactiver' : 'Activer'} ${account.label}`}
                                                                onClick={() =>
                                                                    toggleAccountStatus(
                                                                        account,
                                                                    )
                                                                }
                                                            >
                                                                <Power className="size-3.5" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                ),
                                            )
                                        )}
                                    </div>

                                    <div className="mt-2 grid gap-2 rounded-lg border border-border bg-card p-3">
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <select
                                                value={accountNetwork}
                                                onChange={(e) =>
                                                    setAccountNetwork(
                                                        e.target
                                                            .value as AgencyPaymentAccountRecord['network'],
                                                    )
                                                }
                                                className="control"
                                                aria-label="Réseau"
                                            >
                                                <option value="mtn_momo">
                                                    MTN MoMo
                                                </option>
                                                <option value="moov_flooz">
                                                    Moov Money / Flooz
                                                </option>
                                                <option value="celtiis_cash">
                                                    Celtiis Cash
                                                </option>
                                            </select>
                                            <input
                                                value={accountLabel}
                                                onChange={(e) =>
                                                    setAccountLabel(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Libellé du compte"
                                                required
                                                className="control"
                                            />
                                        </div>
                                        <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
                                            <input
                                                value={accountIdentifier}
                                                onChange={(e) =>
                                                    setAccountIdentifier(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Numéro ou identifiant"
                                                required
                                                className="control"
                                            />
                                            <button
                                                type="button"
                                                disabled={
                                                    accountProcessing ||
                                                    accountLabel.trim() ===
                                                        '' ||
                                                    accountIdentifier.trim() ===
                                                        ''
                                                }
                                                onClick={submitAccount}
                                                className="btn btn-primary"
                                            >
                                                <Plus className="size-3.5" />
                                                Ajouter
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Footer Buttons using Caisse style classes */}
                        <div className="cash-modal-actions mt-6">
                            <button
                                type="button"
                                onClick={() => handleClose(false)}
                                className="btn btn-ghost"
                                disabled={processing}
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="btn btn-primary"
                            >
                                <Check className="size-4" />
                                {processing
                                    ? 'Enregistrement…'
                                    : isEditing
                                      ? 'Enregistrer les changements'
                                      : 'Créer l’agence'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
