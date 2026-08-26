import { Form, router } from '@inertiajs/react';
import { Building2, Check, CreditCard, Plus, Power, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

        setAccountProcessing(true);
        router.post(
            agencies.paymentAccounts.store.url({ agency: agency.uuid }),
            {
                network: accountNetwork,
                label: accountLabel,
                account_identifier: accountIdentifier,
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

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogPortal>
                <DialogOverlay className="bg-[rgba(0,29,78,0.24)] backdrop-blur-md" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                className="max-w-[38rem] rounded-[1.75rem] border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] [&>button]:hidden"
            >
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex items-start justify-between gap-5 border-b border-border/70 p-7 pb-6">
                                <div className="flex items-start gap-4">
                                    <span className="grid size-12 shrink-0 place-items-center rounded-[0.9rem] bg-gold text-[var(--kora)] shadow-[0_11px_25px_rgba(246,176,0,0.22)]">
                                        <Building2 className="size-5" />
                                    </span>
                                    <div>
                                        <span className="mb-1 block text-[0.6875rem] font-black tracking-[0.15em] text-gold uppercase">
                                            Référentiel agences
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {title}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                            {description}
                                        </DialogDescription>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => handleClose(false)}
                                    className="grid size-9 shrink-0 place-items-center rounded-[0.7rem] bg-muted text-foreground transition-[background,transform] duration-200 hover:scale-[1.03] hover:bg-muted/80"
                                    aria-label="Fermer"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>

                            <div className="grid gap-5 p-7">
                                <div className="grid gap-2">
                                    <Label htmlFor="agency-code">
                                        Code agence *
                                    </Label>
                                    <Input
                                        id="agency-code"
                                        name="code"
                                        defaultValue={agency?.code ?? ''}
                                        required
                                        maxLength={32}
                                        placeholder="COT"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted uppercase shadow-[inset_0_0_0_1px_var(--border)] focus-visible:bg-background"
                                    />
                                    <InputError message={errors.code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="agency-name">
                                        Nom de l’agence *
                                    </Label>
                                    <Input
                                        id="agency-name"
                                        name="name"
                                        defaultValue={agency?.name ?? ''}
                                        required
                                        placeholder="Agence Cotonou Centre"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)] focus-visible:bg-background"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="agency-city">
                                            Ville *
                                        </Label>
                                        <Input
                                            id="agency-city"
                                            name="city"
                                            defaultValue={agency?.city ?? ''}
                                            required
                                            placeholder="Cotonou"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)] focus-visible:bg-background"
                                        />
                                        <InputError message={errors.city} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="agency-country">
                                            Pays *
                                        </Label>
                                        <Input
                                            id="agency-country"
                                            name="country"
                                            defaultValue={
                                                agency?.country ?? 'BJ'
                                            }
                                            onChange={(event) => {
                                                event.currentTarget.value =
                                                    event.currentTarget.value.toUpperCase();
                                            }}
                                            required
                                            maxLength={2}
                                            placeholder="BJ"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted uppercase shadow-[inset_0_0_0_1px_var(--border)] focus-visible:bg-background"
                                        />
                                        <InputError message={errors.country} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="agency-head">
                                        Chef d’agence
                                    </Label>
                                    <select
                                        id="agency-head"
                                        name="head_user_id"
                                        defaultValue={agency?.headUserId ?? ''}
                                        className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] transition-[box-shadow,transform,background] outline-none focus:translate-y-[-1px] focus:bg-background focus:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                                    >
                                        <option value="">— Aucun —</option>
                                        {headCandidates.map((candidate) => (
                                            <option
                                                key={candidate.id}
                                                value={candidate.id}
                                            >
                                                {candidate.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.head_user_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="agency-status">
                                        Statut *
                                    </Label>
                                    <select
                                        id="agency-status"
                                        name="status"
                                        defaultValue={agency?.status ?? 'draft'}
                                        className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] transition-[box-shadow,transform,background] outline-none focus:translate-y-[-1px] focus:bg-background focus:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                                    >
                                        {Object.entries(statuses).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {statusLabels[value] ??
                                                        label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <InputError message={errors.status} />
                                </div>

                                {isEditing && (
                                    <div className="border-t border-border/70 pt-5">
                                        <div className="mb-4 flex items-center justify-between gap-3">
                                            <div>
                                                <span className="flex items-center gap-2 text-sm font-bold text-foreground">
                                                    <CreditCard className="size-4 text-gold" />{' '}
                                                    Comptes Mobile Money
                                                </span>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Ces comptes seront proposés
                                                    uniquement aux agents de
                                                    cette agence.
                                                </p>
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            {agency!.paymentAccounts.length ===
                                            0 ? (
                                                <p className="rounded-[0.75rem] border border-dashed border-border p-3 text-xs text-muted-foreground">
                                                    Aucun compte configuré.
                                                </p>
                                            ) : (
                                                agency!.paymentAccounts.map(
                                                    (account) => (
                                                        <div
                                                            key={account.uuid}
                                                            className="flex items-center justify-between gap-3 rounded-[0.75rem] border border-border bg-muted/30 px-3 py-2.5"
                                                        >
                                                            <div>
                                                                <strong className="block text-xs text-foreground">
                                                                    {
                                                                        account.label
                                                                    }
                                                                </strong>
                                                                <span className="text-[0.6875rem] text-muted-foreground">
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
                                                                    className={`text-[0.6875rem] font-bold ${account.status === 'active' ? 'text-digital' : 'text-muted-foreground'}`}
                                                                >
                                                                    {account.status ===
                                                                    'active'
                                                                        ? 'Actif'
                                                                        : 'Inactif'}
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                                    aria-label={`${account.status === 'active' ? 'Désactiver' : 'Activer'} ${account.label}`}
                                                                    onClick={() =>
                                                                        router.patch(
                                                                            agencies.paymentAccounts.status.url(
                                                                                {
                                                                                    agency: agency!
                                                                                        .uuid,
                                                                                    account:
                                                                                        account.uuid,
                                                                                },
                                                                            ),
                                                                            {
                                                                                status:
                                                                                    account.status ===
                                                                                    'active'
                                                                                        ? 'inactive'
                                                                                        : 'active',
                                                                            },
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
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
                                        <div className="mt-4 grid gap-3 rounded-[0.75rem] bg-muted/30 p-3">
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <select
                                                    value={accountNetwork}
                                                    onChange={(event) =>
                                                        setAccountNetwork(
                                                            event.target
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
                                                        Celtis Cash
                                                    </option>
                                                </select>
                                                <Input
                                                    value={accountLabel}
                                                    onChange={(event) =>
                                                        setAccountLabel(
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Libellé du compte"
                                                    required
                                                    className="h-10 rounded-[0.7rem] border-0 bg-background"
                                                />
                                            </div>
                                            <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
                                                <Input
                                                    value={accountIdentifier}
                                                    onChange={(event) =>
                                                        setAccountIdentifier(
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Numéro / identifiant du compte"
                                                    required
                                                    className="h-10 rounded-[0.7rem] border-0 bg-background"
                                                />
                                                <Button
                                                    type="button"
                                                    disabled={
                                                        accountProcessing ||
                                                        accountLabel.trim() ===
                                                            '' ||
                                                        accountIdentifier.trim() ===
                                                            ''
                                                    }
                                                    onClick={submitAccount}
                                                    size="sm"
                                                    className="h-10 rounded-[0.7rem] bg-gold text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                                                >
                                                    <Plus className="size-4" />{' '}
                                                    Ajouter
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-col-reverse gap-3 border-t border-border/70 bg-muted/30 p-7 sm:flex-row sm:justify-end">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => handleClose(false)}
                                    disabled={processing}
                                    className="h-12 rounded-[0.8rem] px-5"
                                >
                                    Annuler
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-12 rounded-[0.8rem] bg-gold px-6 font-medium text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] hover:bg-[var(--kora)] hover:text-white"
                                >
                                    <Check className="size-4" />
                                    {processing
                                        ? 'Enregistrement…'
                                        : isEditing
                                          ? 'Enregistrer les changements'
                                          : 'Créer l’agence'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
