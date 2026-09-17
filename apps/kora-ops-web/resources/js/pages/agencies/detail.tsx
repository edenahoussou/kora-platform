import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    Building2,
    Check,
    Edit3,
    HelpCircle,
    LockKeyhole,
    Plus,
    RotateCcw,
    ShieldCheck,
    Smartphone,
    Vault,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { AgencyPaymentAccountManagement } from '@/components/agencies/agency-payment-account-management';
import type { AgencyPaymentAccount } from '@/components/agencies/agency-payment-account-management';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { OnboardingTour } from '@/components/onboarding/onboarding-tour';
import type { OnboardingStep } from '@/components/onboarding/onboarding-tour';
import { formatMoney } from '@/components/sale/sale-utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOnboarding } from '@/hooks/use-onboarding';
import agencies from '@/routes/agencies';
import cashRegisterRoutes from '@/routes/agencies/cash-registers';
import type { BreadcrumbItem } from '@/types';

type UserOption = { uuid: string; name: string };
type Register = {
    uuid: string;
    code: string;
    label: string;
    kind: 'counter' | 'vault';
    status: 'active' | 'inactive';
    publishedBalance: number;
    reservedAmount: number;
    availableBalance: number;
    lastSession: {
        uuid: string;
        status: string;
        agentName: string | null;
        businessDate: string;
    } | null;
    lastMovementAt: string | null;
    authorizedUsers: UserOption[];
};
type Props = {
    agency: {
        uuid: string;
        code: string;
        name: string;
        city: string;
        country: string;
        head: UserOption | null;
        status: string;
    };
    cashSummary: { cash: number; mobile_money: number; global: number };
    cashRegisters: Register[];
    paymentAccounts: AgencyPaymentAccount[];
    agentCandidates: UserOption[];
    permissions: {
        canManage: boolean;
        canViewCash: boolean;
        canCreateTransfer: boolean;
        canApproveTransfer: boolean;
    };
};

const ONBOARDING_STEPS: OnboardingStep[] = [
    {
        target: '[data-onboarding="agency-summary"]',
        title: 'Synthèse agence',
        body: 'Les espèces regroupent les guichets et le coffre local. Le Mobile Money reste calculé une seule fois au niveau des comptes partagés.',
        side: 'bottom',
    },
    {
        target: '[data-onboarding="agency-vault"]',
        title: 'Coffre local',
        body: 'Le coffre est une caisse physique stable avec son propre journal. Il ne possède pas de session permanente de guichet.',
        side: 'right',
    },
    {
        target: '[data-onboarding="agency-counters"]',
        title: 'Caisses des guichets',
        body: 'Chaque tiroir physique dispose d’un code, d’agents autorisés, d’un solde publié et d’un montant réservé par les sorties en attente.',
        side: 'top',
    },
    {
        target: '[data-onboarding="agency-add-counter"]',
        title: 'Ajouter un guichet',
        body: 'Créez une caisse stable puis affectez les agents autorisés. Une session ne pourra être ouverte que sur une caisse active.',
        side: 'left',
    },
    {
        target: '[data-onboarding="agency-status"]',
        title: 'Statuts et contrôle',
        body: 'Une caisse avec une session ouverte, un solde ou une sortie en attente ne peut pas être désactivée.',
        side: 'left',
    },
    {
        target: '[data-onboarding="agency-payment-accounts"]',
        title: 'Comptes Mobile Money',
        body: 'Les comptes partagés se gèrent ici. Un compte historique conserve son solde mais ne peut plus recevoir de nouveaux paiements.',
        side: 'top',
    },
];

function money(value: number): string {
    return formatMoney(value, 'XOF');
}

function tone(status: string): 'navy' | 'gold' | 'muted' | 'destructive' {
    if (status === 'active' || status === 'open') {
        return 'navy';
    }

    if (status === 'pending' || status === 'pending_approval') {
        return 'gold';
    }

    if (status === 'inactive' || status === 'closed') {
        return 'muted';
    }

    return 'destructive';
}

export default function AgencyDetailPage({
    agency,
    cashSummary,
    cashRegisters,
    paymentAccounts,
    agentCandidates,
    permissions,
}: Props) {
    const [tab, setTab] = useState<'information' | 'cash'>('cash');
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Register | null>(null);
    const [label, setLabel] = useState('');
    const [selectedUsers, setSelectedUsers] = useState<string[]>([]);
    const onboarding = useOnboarding('agency-cash');

    useEffect(() => {
        if (onboarding.shouldAutoStart) {
            const timer = setTimeout(() => onboarding.start(), 400);

            return () => clearTimeout(timer);
        }
    }, [onboarding.shouldAutoStart]); // eslint-disable-line react-hooks/exhaustive-deps

    const vault = cashRegisters.find((register) => register.kind === 'vault');
    const counters = cashRegisters.filter(
        (register) => register.kind === 'counter',
    );
    const formTitle = editing ? 'Modifier la caisse' : 'Nouveau guichet';

    const openCreate = () => {
        setEditing(null);
        setLabel('');
        setSelectedUsers([]);
        setFormOpen(true);
    };

    const openEdit = (register: Register) => {
        setEditing(register);
        setLabel(register.label);
        setSelectedUsers(register.authorizedUsers.map((user) => user.uuid));
        setFormOpen(true);
    };

    const submitRegister = () => {
        if (label.trim() === '') {
            return;
        }

        const payload = {
            label: label.trim(),
            authorized_user_uuids: selectedUsers,
        };
        const options = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };

        if (editing) {
            router.put(
                cashRegisterRoutes.update.url({ cashRegister: editing.uuid }),
                payload,
                options,
            );
        } else {
            router.post(
                cashRegisterRoutes.store.url({ agency: agency.uuid }),
                payload,
                options,
            );
        }
    };

    const toggleStatus = (register: Register) => {
        const next = register.status === 'active' ? 'inactive' : 'active';

        if (
            next === 'inactive' &&
            !window.confirm(`Désactiver ${register.label} ?`)
        ) {
            return;
        }

        router.patch(
            cashRegisterRoutes.status.url({ cashRegister: register.uuid }),
            { status: next },
            { preserveScroll: true },
        );
    };

    const totalCounters = useMemo(
        () =>
            counters.reduce(
                (sum, register) => sum + register.publishedBalance,
                0,
            ),
        [counters],
    );

    return (
        <>
            <Head title={`${agency.name} · Agence`} />
            <div className="kora-page-head mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <Link
                        href={agencies.index.url()}
                        className="mb-3 inline-flex items-center gap-2 text-xs font-bold text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" /> Retour aux agences
                    </Link>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Ressources / Agences / {agency.code}
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        {agency.name}
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Supervisez les caisses physiques, le coffre agence et
                        les comptes Mobile Money partagés.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <KoraStatusBadge
                        label={
                            agency.status === 'active'
                                ? 'Agence active'
                                : agency.status
                        }
                        tone={tone(agency.status)}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Rejouer le guide"
                        onClick={onboarding.reset}
                    >
                        <HelpCircle className="size-4" />
                    </Button>
                </div>
            </div>

            <div
                className="mb-6 flex gap-2 border-b border-border"
                role="tablist"
            >
                {(['information', 'cash'] as const).map((item) => (
                    <button
                        key={item}
                        type="button"
                        role="tab"
                        aria-selected={tab === item}
                        onClick={() => setTab(item)}
                        className={`border-b-2 px-4 py-3 text-sm font-bold transition-colors ${tab === item ? 'border-gold text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                    >
                        {item === 'information' ? 'Informations' : 'Caisses'}
                    </button>
                ))}
            </div>

            {tab === 'information' ? (
                <div className="grid max-w-3xl gap-5 rounded-[18px] border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-3">
                        <Building2 className="size-5 text-gold" />
                        <div>
                            <p className="text-xs font-black tracking-[0.12em] text-muted-foreground uppercase">
                                Référentiel
                            </p>
                            <h2 className="text-lg font-extrabold">
                                Informations de l’agence
                            </h2>
                        </div>
                    </div>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Code
                            </dt>
                            <dd className="mt-1 font-bold">{agency.code}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Localisation
                            </dt>
                            <dd className="mt-1 font-bold">
                                {agency.city} · {agency.country}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Chef d’agence
                            </dt>
                            <dd className="mt-1 font-bold">
                                {agency.head?.name ?? 'Non assigné'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Caisse espèces
                            </dt>
                            <dd className="mt-1 font-bold">
                                {counters.length} guichet
                                {counters.length > 1 ? 's' : ''} + 1 coffre
                            </dd>
                        </div>
                    </dl>
                </div>
            ) : (
                <div className="space-y-6">
                    <div
                        data-onboarding="agency-summary"
                        className="grid gap-4 sm:grid-cols-3"
                    >
                        {(
                            [
                                [
                                    'Espèces agence',
                                    cashSummary.cash,
                                    Banknote,
                                    'text-gold',
                                ],
                                [
                                    'Mobile Money partagé',
                                    cashSummary.mobile_money,
                                    Smartphone,
                                    'text-digital',
                                ],
                                [
                                    'Total global',
                                    cashSummary.global,
                                    ShieldCheck,
                                    'text-emerald-600',
                                ],
                            ] as [string, number, LucideIcon, string][]
                        ).map(([title, amount, Icon, color]) => (
                            <div
                                key={String(title)}
                                className="rounded-[18px] border border-border bg-card p-5 shadow-sm"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-bold text-muted-foreground">
                                        {title}
                                    </span>
                                    <Icon className={`size-5 ${color}`} />
                                </div>
                                <strong className="mt-4 block text-2xl font-extrabold tracking-tight">
                                    {money(Number(amount))}
                                </strong>
                                <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                                    Solde comptable publié
                                </span>
                            </div>
                        ))}
                    </div>

                    <div className="grid gap-5 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.6fr)]">
                        <section
                            data-onboarding="agency-vault"
                            className="rounded-[18px] border border-border bg-card p-5 shadow-sm"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-10 place-items-center rounded-xl bg-gold/15 text-gold">
                                        <Vault className="size-5" />
                                    </span>
                                    <div>
                                        <p className="text-[0.625rem] font-black tracking-[0.13em] text-muted-foreground uppercase">
                                            Caisse physique stable
                                        </p>
                                        <h2 className="font-extrabold">
                                            Coffre agence
                                        </h2>
                                    </div>
                                </div>
                                {vault && (
                                    <KoraStatusBadge
                                        label="Actif"
                                        tone="navy"
                                    />
                                )}
                            </div>
                            {vault ? (
                                <>
                                    <strong className="mt-8 block text-3xl font-extrabold tracking-tight">
                                        {money(vault.publishedBalance)}
                                    </strong>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Disponible :{' '}
                                        {money(vault.availableBalance)} ·
                                        Réservé : {money(vault.reservedAmount)}
                                    </p>
                                    <div className="mt-6 rounded-xl bg-muted/45 p-3 text-xs leading-relaxed text-muted-foreground">
                                        <LockKeyhole className="mr-1 inline size-3.5 text-gold" />{' '}
                                        Journal propre, sans session permanente
                                        de guichet.
                                    </div>
                                </>
                            ) : (
                                <p className="mt-8 text-sm text-muted-foreground">
                                    Le coffre sera créé automatiquement.
                                </p>
                            )}
                        </section>

                        <section
                            data-onboarding="agency-counters"
                            className="rounded-[18px] border border-border bg-card shadow-sm"
                        >
                            <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                                <div>
                                    <h2 className="font-extrabold">
                                        Caisses des guichets
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {counters.length} caisse
                                        {counters.length > 1 ? 's' : ''} ·{' '}
                                        {money(totalCounters)} publié
                                    </p>
                                </div>
                                {permissions.canManage && (
                                    <Button
                                        data-onboarding="agency-add-counter"
                                        type="button"
                                        size="kora"
                                        variant="kora"
                                        onClick={openCreate}
                                    >
                                        <Plus className="size-4" /> Ajouter
                                    </Button>
                                )}
                            </div>
                            <div className="divide-y divide-border/70">
                                {counters.length === 0 ? (
                                    <div className="px-5 py-12 text-center">
                                        <Banknote className="mx-auto size-8 text-gold" />
                                        <p className="mt-3 text-sm font-bold">
                                            Aucun guichet configuré
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Créez le premier tiroir physique de
                                            cette agence.
                                        </p>
                                    </div>
                                ) : (
                                    counters.map((register) => (
                                        <div
                                            key={register.uuid}
                                            className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="flex items-start gap-3">
                                                <span className="grid size-9 place-items-center rounded-lg bg-muted text-xs font-black text-foreground">
                                                    {register.code.replace(
                                                        'G-',
                                                        '',
                                                    )}
                                                </span>
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <strong className="text-sm">
                                                            {register.label}
                                                        </strong>
                                                        <KoraStatusBadge
                                                            label={
                                                                register.status ===
                                                                'active'
                                                                    ? 'Actif'
                                                                    : 'Inactif'
                                                            }
                                                            tone={tone(
                                                                register.status,
                                                            )}
                                                        />
                                                    </div>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {register
                                                            .authorizedUsers
                                                            .length > 0
                                                            ? register.authorizedUsers
                                                                  .map(
                                                                      (user) =>
                                                                          user.name,
                                                                  )
                                                                  .join(', ')
                                                            : 'Aucun agent affecté'}
                                                    </p>
                                                    <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                                        Session :{' '}
                                                        {register.lastSession
                                                            ? `${register.lastSession.status} · ${register.lastSession.agentName ?? '—'}`
                                                            : 'Aucune'}{' '}
                                                        · Réservé :{' '}
                                                        {money(
                                                            register.reservedAmount,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between gap-4 sm:justify-end">
                                                <div className="text-left sm:text-right">
                                                    <strong className="block text-sm font-extrabold">
                                                        {money(
                                                            register.publishedBalance,
                                                        )}
                                                    </strong>
                                                    <span className="text-[0.6875rem] text-muted-foreground">
                                                        Disponible{' '}
                                                        {money(
                                                            register.availableBalance,
                                                        )}
                                                    </span>
                                                </div>
                                                {permissions.canManage && (
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Modifier ${register.label}`}
                                                            onClick={() =>
                                                                openEdit(
                                                                    register,
                                                                )
                                                            }
                                                        >
                                                            <Edit3 className="size-4" />
                                                        </Button>
                                                        <Button
                                                            data-onboarding="agency-status"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`${register.status === 'active' ? 'Désactiver' : 'Activer'} ${register.label}`}
                                                            onClick={() =>
                                                                toggleStatus(
                                                                    register,
                                                                )
                                                            }
                                                        >
                                                            {register.status ===
                                                            'active' ? (
                                                                <X className="size-4" />
                                                            ) : (
                                                                <RotateCcw className="size-4" />
                                                            )}
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>
                    </div>

                    <AgencyPaymentAccountManagement
                        agencyUuid={agency.uuid}
                        accounts={paymentAccounts}
                        canManage={permissions.canManage}
                    />
                </div>
            )}

            {formOpen && (
                <div className="fixed inset-0 z-50 grid place-items-center bg-[rgba(0,29,78,0.28)] p-4 backdrop-blur-sm">
                    <div className="w-full max-w-lg rounded-[22px] border border-border bg-card p-6 shadow-2xl">
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="text-[0.625rem] font-black tracking-[0.15em] text-gold uppercase">
                                    Administration des caisses
                                </p>
                                <h2 className="mt-1 text-xl font-extrabold">
                                    {formTitle}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Le solde et les mouvements restent gérés
                                    dans l’écran Caisse.
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => setFormOpen(false)}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                        <div className="mt-6 grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="register-label">
                                    Libellé *
                                </Label>
                                <Input
                                    id="register-label"
                                    value={label}
                                    onChange={(event) =>
                                        setLabel(event.target.value)
                                    }
                                    placeholder="Guichet principal"
                                    className="h-11"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="register-users">
                                    Agents autorisés
                                </Label>
                                <select
                                    id="register-users"
                                    multiple
                                    value={selectedUsers}
                                    onChange={(event) =>
                                        setSelectedUsers(
                                            Array.from(
                                                event.target.selectedOptions,
                                                (option) => option.value,
                                            ),
                                        )
                                    }
                                    className="min-h-28 rounded-xl border border-border bg-muted px-3 py-2 text-sm"
                                >
                                    <option disabled value="">
                                        Choisir les agents
                                    </option>
                                    {agentCandidates.map((user) => (
                                        <option
                                            key={user.uuid}
                                            value={user.uuid}
                                        >
                                            {user.name}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    Maintenez Ctrl/Cmd pour sélectionner
                                    plusieurs agents.
                                </p>
                            </div>
                        </div>
                        <div className="mt-7 flex justify-end gap-2">
                            <Button
                                variant="ghost"
                                onClick={() => setFormOpen(false)}
                            >
                                Annuler
                            </Button>
                            <Button
                                variant="kora"
                                onClick={submitRegister}
                                disabled={label.trim() === ''}
                            >
                                <Check className="size-4" /> Enregistrer
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            <OnboardingTour
                steps={ONBOARDING_STEPS}
                isActive={onboarding.isActive}
                onComplete={onboarding.complete}
                onDismiss={onboarding.dismiss}
            />
        </>
    );
}

AgencyDetailPage.layout = {
    breadcrumbs: [
        { title: 'Agences', href: '/agencies' },
        { title: 'Fiche agence', href: '/agencies' },
    ] satisfies BreadcrumbItem[],
};
