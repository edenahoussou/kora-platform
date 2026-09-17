import { router } from '@inertiajs/react';
import {
    Check,
    CreditCard,
    Edit3,
    Plus,
    Power,
    RotateCcw,
    Smartphone,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { formatMoney } from '@/components/sale/sale-utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import agencies from '@/routes/agencies';

export type MobileMoneyNetwork = 'mtn_momo' | 'moov_flooz' | 'celtiis_cash';

export type AgencyPaymentAccount = {
    uuid: string;
    network: MobileMoneyNetwork;
    label: string;
    accountIdentifier: string;
    status: 'active' | 'inactive';
    balance: number;
};

type Props = {
    agencyUuid: string;
    accounts: AgencyPaymentAccount[];
    canManage: boolean;
};

const NETWORKS: Array<{ value: MobileMoneyNetwork; label: string }> = [
    { value: 'mtn_momo', label: 'MTN MoMo' },
    { value: 'moov_flooz', label: 'Moov Money / Flooz' },
    { value: 'celtiis_cash', label: 'Celtis Cash' },
];

function networkLabel(network: MobileMoneyNetwork): string {
    return (
        NETWORKS.find((option) => option.value === network)?.label ?? network
    );
}

function money(value: number): string {
    return formatMoney(value, 'XOF');
}

function firstError(errors: Record<string, string | string[]>): string | null {
    const error = Object.values(errors)[0];

    return Array.isArray(error) ? (error[0] ?? null) : (error ?? null);
}

export function AgencyPaymentAccountManagement({
    agencyUuid,
    accounts,
    canManage,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<AgencyPaymentAccount | null>(null);
    const [network, setNetwork] = useState<MobileMoneyNetwork>('mtn_momo');
    const [label, setLabel] = useState('');
    const [identifier, setIdentifier] = useState('');
    const [replacementUuid, setReplacementUuid] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const activeAccounts = useMemo(
        () =>
            new Map(
                accounts
                    .filter((account) => account.status === 'active')
                    .map((account) => [account.network, account]),
            ),
        [accounts],
    );

    const resetDialog = () => {
        setDialogOpen(false);
        setEditing(null);
        setLabel('');
        setIdentifier('');
        setReplacementUuid(null);
        setError(null);
    };

    const openCreate = (accountToReplace?: AgencyPaymentAccount) => {
        const suggestedNetwork =
            accountToReplace?.network ??
            NETWORKS.find((option) => !activeAccounts.has(option.value))
                ?.value ??
            'mtn_momo';

        setEditing(null);
        setNetwork(suggestedNetwork);
        setLabel(accountToReplace?.label ?? '');
        setIdentifier('');
        setReplacementUuid(accountToReplace?.uuid ?? null);
        setError(null);
        setDialogOpen(true);
    };

    const openEdit = (account: AgencyPaymentAccount) => {
        setEditing(account);
        setNetwork(account.network);
        setLabel(account.label);
        setIdentifier('');
        setReplacementUuid(null);
        setError(null);
        setDialogOpen(true);
    };

    const submit = () => {
        if (label.trim() === '') {
            return;
        }

        setProcessing(true);
        setError(null);

        if (editing) {
            router.put(
                agencies.paymentAccounts.update.url({
                    agency: agencyUuid,
                    account: editing.uuid,
                }),
                { label: label.trim() },
                {
                    preserveScroll: true,
                    onSuccess: resetDialog,
                    onError: (errors) => setError(firstError(errors)),
                    onFinish: () => setProcessing(false),
                },
            );

            return;
        }

        if (identifier.trim() === '') {
            setProcessing(false);

            return;
        }

        const activeAccount = activeAccounts.get(network);
        const accountToReplace = replacementUuid
            ? accounts.find((account) => account.uuid === replacementUuid)
            : activeAccount;
        const effectiveReplacementUuid = replacementUuid ?? activeAccount?.uuid;

        if (
            accountToReplace &&
            accountToReplace.uuid !== replacementUuid &&
            !window.confirm(
                `${accountToReplace.label} est le compte actif ${networkLabel(network)}. Le nouveau compte le remplacera. Continuer ?`,
            )
        ) {
            setProcessing(false);

            return;
        }

        router.post(
            agencies.paymentAccounts.store.url({ agency: agencyUuid }),
            {
                network,
                label: label.trim(),
                account_identifier: identifier.trim(),
                replace_account_uuid: effectiveReplacementUuid,
            },
            {
                preserveScroll: true,
                onSuccess: resetDialog,
                onError: (errors) => setError(firstError(errors)),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const toggleStatus = (account: AgencyPaymentAccount) => {
        const nextStatus = account.status === 'active' ? 'inactive' : 'active';
        const activeAccount = activeAccounts.get(account.network);
        let replaceAccountUuid: string | null = null;

        if (nextStatus === 'inactive') {
            if (!window.confirm(`Désactiver ${account.label} ?`)) {
                return;
            }
        } else if (activeAccount && activeAccount.uuid !== account.uuid) {
            if (
                !window.confirm(
                    `${account.label} remplacera ${activeAccount.label} comme compte actif ${networkLabel(account.network)}. Continuer ?`,
                )
            ) {
                return;
            }

            replaceAccountUuid = activeAccount.uuid;
        }

        router.patch(
            agencies.paymentAccounts.status.url({
                agency: agencyUuid,
                account: account.uuid,
            }),
            {
                status: nextStatus,
                replace_account_uuid: replaceAccountUuid,
            },
            {
                preserveScroll: true,
                onError: (errors) => setError(firstError(errors)),
            },
        );
    };

    return (
        <>
            <section
                data-onboarding="agency-payment-accounts"
                className="rounded-[18px] border border-border bg-card shadow-sm"
            >
                <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                    <div>
                        <h2 className="font-extrabold">
                            Comptes Mobile Money partagés
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Un seul compte actif par réseau. Les comptes
                            historiques restent visibles et comptabilisés.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {canManage && (
                            <Button
                                data-onboarding="agency-add-payment-account"
                                type="button"
                                size="kora"
                                variant="kora"
                                onClick={() => openCreate()}
                            >
                                <Plus className="size-4" /> Ajouter
                            </Button>
                        )}
                        <CreditCard className="size-5 text-digital" />
                    </div>
                </div>
                <div className="grid gap-3 p-5 md:grid-cols-3">
                    {accounts.length === 0 ? (
                        <div className="col-span-full rounded-xl border border-dashed border-border p-8 text-center">
                            <Smartphone className="mx-auto size-8 text-digital" />
                            <p className="mt-3 text-sm font-bold">
                                Aucun compte Mobile Money configuré
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Ajoutez le compte partagé de chaque réseau
                                utilisé par cette agence.
                            </p>
                            {canManage && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="kora"
                                    className="mt-4"
                                    onClick={() => openCreate()}
                                >
                                    <Plus className="size-4" /> Ajouter un
                                    compte
                                </Button>
                            )}
                        </div>
                    ) : (
                        accounts.map((account) => (
                            <div
                                key={account.uuid}
                                className="flex min-h-44 flex-col rounded-xl border border-border bg-muted/25 p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <span className="text-[0.625rem] font-black tracking-[0.1em] text-muted-foreground uppercase">
                                            {networkLabel(account.network)}
                                        </span>
                                        <span className="mt-1 block text-sm font-bold">
                                            {account.label}
                                        </span>
                                    </div>
                                    <KoraStatusBadge
                                        label={
                                            account.status === 'active'
                                                ? 'Actif'
                                                : 'Historique'
                                        }
                                        tone={
                                            account.status === 'active'
                                                ? 'navy'
                                                : 'muted'
                                        }
                                    />
                                </div>
                                <p className="mt-3 text-xs text-muted-foreground">
                                    {account.accountIdentifier}
                                </p>
                                <strong className="mt-4 block text-lg font-extrabold">
                                    {money(account.balance)}
                                </strong>
                                <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                    {account.status === 'active'
                                        ? 'Disponible pour les nouvelles sessions'
                                        : 'Solde historique inclus au total agence'}
                                </p>
                                {canManage && (
                                    <div className="mt-auto flex justify-end gap-1 pt-4">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Modifier le libellé de ${account.label}`}
                                            onClick={() => openEdit(account)}
                                        >
                                            <Edit3 className="size-4" />
                                        </Button>
                                        {account.status === 'active' && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Remplacer ${account.label}`}
                                                onClick={() =>
                                                    openCreate(account)
                                                }
                                            >
                                                <RotateCcw className="size-4" />
                                            </Button>
                                        )}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`${account.status === 'active' ? 'Désactiver' : 'Activer'} ${account.label}`}
                                            onClick={() =>
                                                toggleStatus(account)
                                            }
                                        >
                                            {account.status === 'active' ? (
                                                <Power className="size-4" />
                                            ) : (
                                                <RotateCcw className="size-4" />
                                            )}
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))
                    )}
                </div>
            </section>

            {dialogOpen && (
                <div className="fixed inset-0 z-50 grid place-items-center bg-[rgba(0,29,78,0.28)] p-4 backdrop-blur-sm">
                    <div className="w-full max-w-lg rounded-[22px] border border-border bg-card p-6 shadow-2xl">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-[0.625rem] font-black tracking-[0.15em] text-gold uppercase">
                                    Administration agence
                                </p>
                                <h2 className="mt-1 text-xl font-extrabold">
                                    {editing
                                        ? 'Modifier le libellé'
                                        : replacementUuid
                                          ? 'Remplacer un compte Mobile Money'
                                          : 'Ajouter un compte Mobile Money'}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {editing
                                        ? 'L’identifiant financier reste inchangé pour préserver l’historique.'
                                        : 'Le compte sera disponible à la prochaine ouverture de session.'}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={resetDialog}
                                aria-label="Fermer"
                            >
                                <X className="size-4" />
                            </Button>
                        </div>

                        {error && (
                            <p className="mt-4 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                                {error}
                            </p>
                        )}

                        <div className="mt-6 grid gap-4">
                            {!editing && (
                                <div className="grid gap-2">
                                    <Label htmlFor="payment-account-network">
                                        Réseau *
                                    </Label>
                                    <select
                                        id="payment-account-network"
                                        value={network}
                                        onChange={(event) => {
                                            const nextNetwork = event.target
                                                .value as MobileMoneyNetwork;

                                            setNetwork(nextNetwork);
                                            setReplacementUuid(
                                                replacementUuid &&
                                                    accounts.find(
                                                        (account) =>
                                                            account.uuid ===
                                                                replacementUuid &&
                                                            account.network ===
                                                                nextNetwork,
                                                    )
                                                    ? replacementUuid
                                                    : null,
                                            );
                                        }}
                                        className="h-11 rounded-xl border border-border bg-muted px-3 text-sm text-foreground"
                                    >
                                        {NETWORKS.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="payment-account-label">
                                    Libellé *
                                </Label>
                                <Input
                                    id="payment-account-label"
                                    value={label}
                                    onChange={(event) =>
                                        setLabel(event.target.value)
                                    }
                                    placeholder="Compte MTN agence"
                                    className="h-11"
                                />
                            </div>
                            {!editing && (
                                <div className="grid gap-2">
                                    <Label htmlFor="payment-account-identifier">
                                        Numéro ou identifiant *
                                    </Label>
                                    <Input
                                        id="payment-account-identifier"
                                        value={identifier}
                                        onChange={(event) =>
                                            setIdentifier(event.target.value)
                                        }
                                        placeholder="+229 97 00 00 01"
                                        className="h-11"
                                    />
                                </div>
                            )}
                        </div>

                        <div className="mt-7 flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={resetDialog}
                            >
                                Annuler
                            </Button>
                            <Button
                                type="button"
                                variant="kora"
                                disabled={
                                    processing ||
                                    label.trim() === '' ||
                                    (!editing && identifier.trim() === '')
                                }
                                onClick={submit}
                            >
                                <Check className="size-4" />
                                {processing ? 'Enregistrement…' : 'Enregistrer'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
