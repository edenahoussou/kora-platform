import { router } from '@inertiajs/react';
import { ArrowUpRight, Building2, Check, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import companies from '@/routes/companies';
import { formatFcfa } from './company-account-types';
import type {
    CompanyAccountRecord,
    CompanyUserCandidate,
} from './company-account-types';

type Props = {
    account: CompanyAccountRecord | null;
    candidates: CompanyUserCandidate[];
    canManageMembers: boolean;
    canViewCredit: boolean;
    onClose: () => void;
};

const typeLabels: Record<string, string> = {
    company: 'Entreprise',
    ngo: 'ONG',
    embassy: 'Ambassade',
};

const typeTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    company: 'navy',
    ngo: 'blue',
    embassy: 'gold',
};

export function CompanyAccountDetailPanel({
    account,
    candidates,
    canManageMembers,
    canViewCredit,
    onClose,
}: Props) {
    const [userUuid, setUserUuid] = useState('');
    const [role, setRole] = useState<'referent' | 'member'>('member');

    if (!account) {
        return null;
    }

    const addMember = () => {
        if (!userUuid) {
            toast.error('Sélectionnez un utilisateur.');

            return;
        }

        if (
            role === 'referent' &&
            account.referent &&
            account.referent.uuid !== userUuid &&
            !window.confirm(
                `${account.referent.name} sera remplacé comme responsable du compte. Continuer ?`,
            )
        ) {
            return;
        }

        router.post(
            companies.members.store.url({ companyAccount: account.uuid }),
            { user_uuid: userUuid, role },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setUserUuid('');
                    toast.success('Membre mis à jour.');
                },
            },
        );
    };

    const creditUsed = Math.min(
        100,
        account.monthlyCreditLimit
            ? ((account.consumedCredit + account.reservedCredit) /
                  account.monthlyCreditLimit) *
                  100
            : 0,
    );

    return (
        <div
            className="fixed inset-0 z-50"
            role="presentation"
            onMouseDown={(event) =>
                event.target === event.currentTarget && onClose()
            }
        >
            <div className="companies-panel-backdrop absolute inset-0 bg-[rgba(0,29,78,0.3)] backdrop-blur-[9px]" />
            <aside
                className="companies-panel absolute top-0 right-0 flex h-full w-full max-w-[38rem] flex-col border-l border-border bg-card shadow-[-20px_0_60px_rgba(0,29,78,0.14)]"
                role="dialog"
                aria-modal="true"
                aria-labelledby="company-detail-title"
            >
                <header className="flex items-start justify-between gap-4 border-b border-border px-6 py-5">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <Building2 className="size-4" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Fiche organisation
                            </span>
                            <h2
                                id="company-detail-title"
                                className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground"
                            >
                                {account.name}
                            </h2>
                            <p className="mt-0.5 text-[0.625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                {account.reference}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="cash-modal-close"
                        aria-label="Fermer"
                    >
                        <X className="size-4" />
                    </button>
                </header>

                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-6">
                    <div className="flex items-center gap-2">
                        <KoraStatusBadge
                            label={
                                account.status === 'active'
                                    ? 'Active'
                                    : 'Inactive'
                            }
                            tone={
                                account.status === 'active' ? 'navy' : 'muted'
                            }
                        />
                        <KoraStatusBadge
                            label={typeLabels[account.type] ?? account.type}
                            tone={typeTones[account.type] ?? 'muted'}
                        />
                    </div>

                    <section className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Building2 className="size-4" />
                            </span>
                            <div>
                                <strong>Coordonnées</strong>
                                <small>
                                    Informations déclarées au référentiel.
                                </small>
                            </div>
                        </div>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            {[
                                ['Email', account.email],
                                ['Téléphone', account.phone],
                                ['Adresse', account.address],
                                [
                                    'Ville / pays',
                                    [account.city, account.country]
                                        .filter(Boolean)
                                        .join(' / '),
                                ],
                                ['IFU', account.ifu],
                                ['RCCM', account.rccm],
                            ].map(([label, value]) => (
                                <div key={label as string}>
                                    <dt className="text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                                        {label}
                                    </dt>
                                    <dd className="mt-1 text-xs font-bold text-foreground">
                                        {value || 'Non renseigné'}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    {canViewCredit && (
                        <section className="cash-opening-row">
                            <div className="cash-opening-row-head">
                                <div>
                                    <strong>Crédit mensuel</strong>
                                    <small>Mois en cours</small>
                                </div>
                            </div>
                            <div className="companies-fields-grid companies-metrics-grid">
                                <Metric
                                    label="Plafond"
                                    value={account.monthlyCreditLimit}
                                />
                                <Metric
                                    label="Consommé"
                                    value={account.consumedCredit}
                                />
                                <Metric
                                    label="Disponible"
                                    value={account.availableCredit}
                                />
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-gold transition-[width] duration-500"
                                    style={{ width: `${creditUsed}%` }}
                                />
                            </div>
                            {account.creditActivity.length > 0 && (
                                <div className="divide-y divide-black/[0.045] rounded-xl border border-border">
                                    {account.creditActivity.map((movement) => (
                                        <div
                                            key={movement.uuid}
                                            className="flex items-center justify-between px-3.5 py-2.5"
                                        >
                                            <span>
                                                <strong className="block text-xs font-black text-foreground">
                                                    {movement.type === 'debit'
                                                        ? 'Débit crédit'
                                                        : 'Remboursement'}
                                                </strong>
                                                <span className="text-[0.625rem] text-muted-foreground">
                                                    {movement.reference}
                                                </span>
                                            </span>
                                            <strong
                                                className={
                                                    movement.type === 'debit'
                                                        ? 'text-xs font-black text-foreground'
                                                        : 'text-xs font-black text-emerald-600'
                                                }
                                            >
                                                {movement.type === 'debit'
                                                    ? '-'
                                                    : '+'}
                                                {formatFcfa(movement.amount)}
                                            </strong>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    )}

                    <section className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <UserPlus className="size-4" />
                            </span>
                            <div>
                                <strong>Responsable et acheteurs</strong>
                                <small>
                                    Utilisateurs voyageurs autorisés à utiliser
                                    ce compte.
                                </small>
                            </div>
                        </div>
                        <div className="space-y-2">
                            {account.members.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-border px-4 py-4 text-xs text-muted-foreground">
                                    Aucun membre actif pour le moment.
                                </p>
                            ) : (
                                account.members.map((member) => (
                                    <div
                                        key={member.uuid}
                                        className="flex items-center justify-between rounded-xl border border-border px-3.5 py-2.5"
                                    >
                                        <div>
                                            <strong className="block text-xs font-black text-foreground">
                                                {member.name}
                                            </strong>
                                            <span className="text-[0.625rem] text-muted-foreground">
                                                {member.email ||
                                                    'Email non renseigné'}
                                            </span>
                                        </div>
                                        <KoraStatusBadge
                                            label={
                                                member.role === 'referent'
                                                    ? 'Responsable'
                                                    : 'Acheteur'
                                            }
                                            tone={
                                                member.role === 'referent'
                                                    ? 'gold'
                                                    : 'muted'
                                            }
                                        />
                                    </div>
                                ))
                            )}
                        </div>
                        {canManageMembers && (
                            <div className="grid gap-2.5 rounded-xl border border-gold/25 bg-gold/5 p-4">
                                <label className="field">
                                    <span>Utilisateur</span>
                                    <select
                                        className="control"
                                        value={userUuid}
                                        onChange={(event) =>
                                            setUserUuid(event.target.value)
                                        }
                                    >
                                        <option value="">
                                            Sélectionner un utilisateur
                                        </option>
                                        {candidates.map((candidate) => (
                                            <option
                                                key={candidate.uuid}
                                                value={candidate.uuid}
                                            >
                                                {candidate.name} —{' '}
                                                {candidate.email}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <div className="grid items-end gap-2.5 sm:grid-cols-[1fr_auto]">
                                    <label className="field">
                                        <span>Rôle</span>
                                        <select
                                            className="control"
                                            value={role}
                                            onChange={(event) =>
                                                setRole(
                                                    event.target.value as
                                                        'referent' | 'member',
                                                )
                                            }
                                        >
                                            <option value="member">
                                                Acheteur
                                            </option>
                                            <option value="referent">
                                                Responsable du compte
                                            </option>
                                        </select>
                                    </label>
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        onClick={addMember}
                                    >
                                        <Check className="size-4" /> Enregistrer
                                    </button>
                                </div>
                            </div>
                        )}
                    </section>

                    <div className="flex items-start gap-3 rounded-xl bg-gold/10 p-3 text-xs leading-5 text-foreground">
                        <ArrowUpRight className="mt-0.5 size-4 shrink-0 text-gold" />
                        <span>
                            Les factures mensuelles et exports de déplacements
                            seront disponibles dans une prochaine évolution.
                        </span>
                    </div>
                </div>
            </aside>
        </div>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <span className="block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                {label}
            </span>
            <strong className="mt-1 block text-xs font-black text-foreground">
                {formatFcfa(value)}
            </strong>
        </div>
    );
}
