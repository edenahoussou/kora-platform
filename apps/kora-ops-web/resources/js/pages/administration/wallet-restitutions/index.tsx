import { Head, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Check,
    CheckCircle2,
    CircleDollarSign,
    ClipboardList,
    FileText,
    Phone,
    Search,
    ShieldCheck,
    UserRound,
    WalletCards,
    X,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import PhoneInput from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { toast } from 'sonner';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import { Button } from '@/components/ui/button';

type RestitutionStatus = 'requested' | 'approved' | 'rejected' | 'paid';

type Restitution = {
    uuid: string;
    wallet_uuid: string | null;
    holder: { name: string | null; phone: string | null } | null;
    amount: number;
    status: RestitutionStatus;
    note: string | null;
    rejection_reason: string | null;
    payout_reference: string | null;
    payout_channel: string | null;
    requested_by: string | null;
    approved_by: string | null;
    approved_at: string | null;
    paid_at: string | null;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    restitutions: Paginated<Restitution>;
    filters: { status: RestitutionStatus | 'all' };
    permissions: { canRequest: boolean; canManage: boolean };
    statuses: Record<RestitutionStatus, string>;
    basePath: string;
    lookupPath: string;
};

type Action = 'approve' | 'reject' | 'paid';

const statusTone: Record<RestitutionStatus, string> = {
    requested:
        'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
    approved: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
    rejected:
        'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
};

const formatAmount = (amount: number) =>
    `${amount.toLocaleString('fr-FR')} FCFA`;

const formatDate = (date: string | null) =>
    date
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(date))
        : '—';

export default function WalletRestitutionsPage({
    restitutions,
    filters,
    permissions,
    statuses,
    basePath,
    lookupPath,
}: Props) {
    const [requestOpen, setRequestOpen] = useState(false);
    const [action, setAction] = useState<{
        type: Action;
        restitution: Restitution;
    } | null>(null);

    const pagination: PaginationMeta = {
        currentPage: restitutions.current_page,
        lastPage: restitutions.last_page,
        perPage: restitutions.per_page,
        total: restitutions.total,
        from: restitutions.from,
        to: restitutions.to,
        baseUrl: basePath,
    };

    const columns: TableColumn<Restitution>[] = [
        {
            key: 'holder',
            header: 'Titulaire',
            render: (row) => (
                <div className="min-w-48">
                    <p className="font-bold text-foreground">
                        {row.holder?.name ?? 'Nom non renseigné'}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {row.holder?.phone ?? 'Téléphone indisponible'}
                    </p>
                </div>
            ),
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (row) => (
                <span className="font-bold text-foreground tabular-nums">
                    {formatAmount(row.amount)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (row) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-1 text-[0.6875rem] font-extrabold ${statusTone[row.status]}`}
                >
                    {statuses[row.status]}
                </span>
            ),
        },
        {
            key: 'request',
            header: 'Demande',
            render: (row) => (
                <div className="min-w-36">
                    <p className="font-semibold text-foreground">
                        {row.requested_by ?? '—'}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {formatDate(row.created_at)}
                    </p>
                </div>
            ),
        },
        {
            key: 'trace',
            header: 'Trace de versement',
            render: (row) => (
                <div className="min-w-40 text-xs text-muted-foreground">
                    {row.payout_reference ? (
                        <>
                            <p className="font-bold text-foreground">
                                {row.payout_reference}
                            </p>
                            <p>
                                {[row.payout_channel, formatDate(row.paid_at)]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </>
                    ) : row.rejection_reason ? (
                        <span className="text-rose-700 dark:text-rose-300">
                            {row.rejection_reason}
                        </span>
                    ) : (
                        '—'
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => {
                if (!permissions.canManage) {
                    return <span className="text-muted-foreground">—</span>;
                }

                return (
                    <div className="flex justify-end gap-2">
                        {row.status === 'requested' && (
                            <>
                                <Button
                                    size="sm"
                                    className="bg-emerald-700 text-white hover:bg-emerald-800"
                                    onClick={() =>
                                        setAction({
                                            type: 'approve',
                                            restitution: row,
                                        })
                                    }
                                >
                                    <Check className="size-3.5" /> Approuver
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        setAction({
                                            type: 'reject',
                                            restitution: row,
                                        })
                                    }
                                >
                                    <X className="size-3.5" /> Rejeter
                                </Button>
                            </>
                        )}
                        {row.status === 'approved' && (
                            <Button
                                size="sm"
                                className="bg-[var(--kora)] text-white hover:bg-[var(--kora)]/90"
                                onClick={() =>
                                    setAction({
                                        type: 'paid',
                                        restitution: row,
                                    })
                                }
                            >
                                <CircleDollarSign className="size-3.5" /> Tracer
                                le versement
                            </Button>
                        )}
                    </div>
                );
            },
        },
    ];

    const visit = (nextStatus: RestitutionStatus | 'all') => {
        router.get(
            basePath,
            nextStatus === 'all' ? {} : { status: nextStatus },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Restitutions Wallet" />
            <main className="cash-page flex flex-col gap-5 pb-8">
                <header className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <span className="section-kicker">Wallet KORA BUS</span>
                        <h1 className="mt-1 text-2xl font-black tracking-tight text-foreground">
                            Restitutions
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Suivez les demandes exceptionnelles, leur
                            approbation et les versements tracés.
                        </p>
                    </div>
                    {permissions.canRequest && (
                        <Button
                            className="bg-[var(--kora)] text-white hover:bg-[var(--kora)]/90"
                            onClick={() => setRequestOpen(true)}
                        >
                            <WalletCards className="size-4" /> Nouvelle demande
                        </Button>
                    )}
                </header>

                <section
                    className="cash-filterbar cash-reveal mb-4 flex flex-col gap-3 sm:flex-row sm:items-center"
                    aria-label="Filtres des restitutions"
                >
                    <label className="cash-filter-field relative block w-full sm:max-w-xs">
                        <span className="sr-only">Statut de la demande</span>
                        <select
                            value={filters.status}
                            onChange={(event) => {
                                const next = event.target.value as
                                    RestitutionStatus | 'all';
                                visit(next);
                            }}
                            className="cash-filter-select w-full"
                        >
                            <option value="all">Tous les statuts</option>
                            {Object.entries(statuses).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            visit('all');
                        }}
                    >
                        <Search className="size-4" /> Réinitialiser
                    </Button>
                </section>

                <div className="cash-reveal cash-reveal-delay-1">
                    <KoraTable
                        variant="cash"
                        columns={columns}
                        data={restitutions.data}
                        rowKey={(row) => row.uuid}
                        pagination={pagination}
                        showHeader={false}
                        emptyTitle="Aucune demande de restitution"
                        emptyMessage="Les demandes Wallet apparaîtront ici avec leur statut et leur trace de versement."
                        emptyIcon={ClipboardList}
                    />
                </div>
            </main>

            {requestOpen && (
                <WalletRestitutionRequestDialog
                    basePath={basePath}
                    lookupPath={lookupPath}
                    onClose={() => setRequestOpen(false)}
                />
            )}
            {action && (
                <RestitutionActionDialog
                    action={action}
                    basePath={basePath}
                    onClose={() => setAction(null)}
                />
            )}
        </>
    );
}

function WalletRestitutionRequestDialog({
    basePath,
    lookupPath,
    onClose,
}: {
    basePath: string;
    lookupPath: string;
    onClose: () => void;
}) {
    const form = useForm({
        wallet_uuid: '',
        holder_phone: '',
        holder_confirmed: false,
        amount: '',
        note: '',
    });
    const [wallet, setWallet] = useState<{
        uuid: string;
        holder: { name: string | null; phone: string | null };
        available: { cash: number };
    } | null>(null);
    const [lookupBusy, setLookupBusy] = useState(false);
    const [lookupError, setLookupError] = useState('');

    useDialogEscape(onClose, form.processing);

    const lookup = async () => {
        setLookupBusy(true);
        setLookupError('');
        setWallet(null);
        form.setData('wallet_uuid', '');

        try {
            const url = new URL(lookupPath, window.location.origin);
            url.searchParams.set('phone', form.data.holder_phone);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            const payload = (await response.json()) as {
                data: typeof wallet;
            };

            if (!response.ok || payload.data === null) {
                setLookupError('Aucun Wallet disponible pour ce numéro.');

                return;
            }

            setWallet(payload.data);
            form.setData('wallet_uuid', payload.data.uuid);
            form.setData(
                'holder_phone',
                payload.data.holder.phone ?? form.data.holder_phone,
            );
        } catch {
            setLookupError('La recherche du Wallet a échoué. Réessayez.');
        } finally {
            setLookupBusy(false);
        }
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(basePath, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('La demande de restitution a été enregistrée.');
                onClose();
            },
        });
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(event) =>
                event.target === event.currentTarget && onClose()
            }
        >
            <form
                className="cash-modal cash-opening-modal w-[42rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="wallet-restitution-title"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <WalletCards className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Wallet KORA BUS
                            </span>
                            <h2 id="wallet-restitution-title">
                                Nouvelle demande de restitution
                            </h2>
                            <p>
                                Le versement reste en attente d’approbation
                                avant tout débit du Wallet.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                        aria-label="Fermer"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <div className="cash-opening-list">
                    <label className="field">
                        <span className="flex items-center gap-2">
                            <Phone className="size-4 text-muted-foreground" />{' '}
                            Téléphone du titulaire <RequiredMark />
                        </span>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <PhoneInput
                                international
                                defaultCountry="BJ"
                                labels={phoneLabels}
                                value={form.data.holder_phone || undefined}
                                required
                                onChange={(value) => {
                                    form.setData((current) => ({
                                        ...current,
                                        holder_phone: value ?? '',
                                        wallet_uuid: '',
                                    }));
                                    setWallet(null);
                                }}
                                aria-required="true"
                                aria-invalid={Boolean(form.errors.holder_phone)}
                                className="min-w-0 flex-1"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={lookup}
                                disabled={lookupBusy || !form.data.holder_phone}
                            >
                                <Search className="size-4" />{' '}
                                {lookupBusy ? 'Recherche…' : 'Rechercher'}
                            </Button>
                        </div>
                        <FieldError>{form.errors.holder_phone}</FieldError>
                        {lookupError && (
                            <small className="field-error-text" role="alert">
                                {lookupError}
                            </small>
                        )}
                    </label>

                    {wallet && (
                        <section
                            className="rounded-xl border border-border bg-muted/30 p-4"
                            aria-live="polite"
                        >
                            <p className="flex items-center gap-2 text-sm font-bold text-foreground">
                                <UserRound className="size-4 text-gold" />{' '}
                                {wallet.holder.name ?? 'Nom non renseigné'}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Disponible en cash :{' '}
                                <strong className="text-foreground">
                                    {formatAmount(wallet.available.cash)}
                                </strong>
                            </p>
                        </section>
                    )}

                    <div className="cash-opening-fields">
                        <label className="field">
                            <span className="flex items-center gap-2">
                                <CircleDollarSign className="size-4 text-muted-foreground" />{' '}
                                Montant demandé <RequiredMark />
                            </span>
                            <input
                                type="number"
                                min="1"
                                max={wallet?.available.cash}
                                step="1"
                                className="control"
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                                required
                                aria-required="true"
                                aria-invalid={Boolean(form.errors.amount)}
                            />
                            <FieldError>{form.errors.amount}</FieldError>
                        </label>
                        <label className="field">
                            <span className="flex items-center gap-2">
                                <FileText className="size-4 text-muted-foreground" />{' '}
                                Note{' '}
                                <span className="text-xs font-normal text-muted-foreground">
                                    (facultative)
                                </span>
                            </span>
                            <input
                                type="text"
                                maxLength={1000}
                                className="control"
                                value={form.data.note}
                                onChange={(event) =>
                                    form.setData('note', event.target.value)
                                }
                            />
                            <FieldError>{form.errors.note}</FieldError>
                        </label>
                    </div>

                    <label className="flex cursor-pointer items-start gap-3 rounded-xl bg-gold/5 p-3.5 ring-1 ring-gold/20">
                        <input
                            type="checkbox"
                            className="mt-0.5 size-4 accent-gold"
                            checked={form.data.holder_confirmed}
                            onChange={(event) =>
                                form.setData(
                                    'holder_confirmed',
                                    event.target.checked,
                                )
                            }
                            required
                            aria-required="true"
                        />
                        <span className="flex items-start gap-2 text-sm text-foreground">
                            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-gold" />
                            <span>
                                Le titulaire est présent et confirme la demande
                                de restitution. <RequiredMark />
                            </span>
                        </span>
                    </label>
                    <FieldError>{form.errors.holder_confirmed}</FieldError>
                </div>

                <div className="cash-modal-actions">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={onClose}
                        disabled={form.processing}
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        className="bg-[var(--kora)] text-white hover:bg-[var(--kora)]/90"
                        disabled={
                            form.processing ||
                            !wallet ||
                            !form.data.holder_confirmed
                        }
                    >
                        <CheckCircle2 className="size-4" />{' '}
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Envoyer la demande'}
                    </Button>
                </div>
            </form>
        </div>
    );
}

function RestitutionActionDialog({
    action,
    basePath,
    onClose,
}: {
    action: { type: Action; restitution: Restitution };
    basePath: string;
    onClose: () => void;
}) {
    const form = useForm({
        reason: '',
        payout_reference: '',
        payout_channel: '',
    });
    useDialogEscape(onClose, form.processing);

    const labels = {
        approve: {
            title: 'Approuver la restitution',
            description:
                'La demande sera autorisée. Son montant sera débité uniquement après le versement tracé.',
        },
        reject: {
            title: 'Rejeter la demande',
            description:
                'Indiquez le motif qui sera conservé dans l’historique.',
        },
        paid: {
            title: 'Tracer le versement',
            description:
                'Confirmez le paiement externe. Cette action débite le Wallet du montant approuvé.',
        },
    }[action.type];

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const base = `${basePath}/${action.restitution.uuid}`;
        const route =
            action.type === 'approve'
                ? `${base}/approve`
                : action.type === 'reject'
                  ? `${base}/reject`
                  : `${base}/mark-paid`;
        form.transform(() => {
            if (action.type === 'reject') {
                return {
                    reason: form.data.reason,
                    payout_reference: '',
                    payout_channel: '',
                };
            }

            if (action.type === 'paid') {
                return {
                    reason: '',
                    payout_reference: form.data.payout_reference,
                    payout_channel: form.data.payout_channel,
                };
            }

            return { reason: '', payout_reference: '', payout_channel: '' };
        });
        form.post(route, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    action.type === 'approve'
                        ? 'La restitution a été approuvée.'
                        : action.type === 'reject'
                          ? 'La demande a été rejetée.'
                          : 'Le versement a été tracé.',
                );
                onClose();
            },
        });
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(event) =>
                event.target === event.currentTarget && onClose()
            }
        >
            <form
                className="cash-modal cash-opening-modal w-[36rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="wallet-action-title"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            {action.type === 'approve' ? (
                                <BadgeCheck className="size-5" />
                            ) : action.type === 'reject' ? (
                                <XCircle className="size-5" />
                            ) : (
                                <CircleDollarSign className="size-5" />
                            )}
                        </span>
                        <div>
                            <span className="section-kicker">
                                {formatAmount(action.restitution.amount)}
                            </span>
                            <h2 id="wallet-action-title">{labels.title}</h2>
                            <p>{labels.description}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                        aria-label="Fermer"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <div className="cash-opening-list">
                    {action.type === 'approve' && (
                        <p className="rounded-xl border border-border bg-muted/30 p-4 text-sm text-muted-foreground">
                            Cette demande concerne{' '}
                            <strong className="text-foreground">
                                {action.restitution.holder?.name ??
                                    'le titulaire du Wallet'}
                            </strong>
                            .
                        </p>
                    )}
                    {action.type === 'reject' && (
                        <label className="field">
                            <span className="flex items-center gap-2">
                                <FileText className="size-4 text-muted-foreground" />{' '}
                                Motif du rejet <RequiredMark />
                            </span>
                            <textarea
                                className="control min-h-28 py-2"
                                maxLength={1000}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                required
                                aria-required="true"
                                aria-invalid={Boolean(form.errors.reason)}
                            />
                            <FieldError>{form.errors.reason}</FieldError>
                        </label>
                    )}
                    {action.type === 'paid' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="field">
                                <span className="flex items-center gap-2">
                                    <ClipboardList className="size-4 text-muted-foreground" />{' '}
                                    Référence du versement <RequiredMark />
                                </span>
                                <input
                                    className="control"
                                    maxLength={255}
                                    value={form.data.payout_reference}
                                    onChange={(event) =>
                                        form.setData(
                                            'payout_reference',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    aria-required="true"
                                    aria-invalid={Boolean(
                                        form.errors.payout_reference,
                                    )}
                                />
                                <FieldError>
                                    {form.errors.payout_reference}
                                </FieldError>
                            </label>
                            <label className="field">
                                <span className="flex items-center gap-2">
                                    <WalletCards className="size-4 text-muted-foreground" />{' '}
                                    Canal{' '}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        (facultatif)
                                    </span>
                                </span>
                                <input
                                    className="control"
                                    maxLength={100}
                                    value={form.data.payout_channel}
                                    onChange={(event) =>
                                        form.setData(
                                            'payout_channel',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {form.errors.payout_channel}
                                </FieldError>
                            </label>
                        </div>
                    )}
                </div>

                <div className="cash-modal-actions">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={onClose}
                        disabled={form.processing}
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        className={
                            action.type === 'reject'
                                ? 'bg-rose-700 text-white hover:bg-rose-800'
                                : 'bg-[var(--kora)] text-white hover:bg-[var(--kora)]/90'
                        }
                        disabled={
                            form.processing ||
                            (action.type === 'reject' &&
                                !form.data.reason.trim()) ||
                            (action.type === 'paid' &&
                                !form.data.payout_reference.trim())
                        }
                    >
                        {form.processing
                            ? 'Enregistrement…'
                            : action.type === 'approve'
                              ? 'Approuver'
                              : action.type === 'reject'
                                ? 'Rejeter'
                                : 'Confirmer le versement'}
                    </Button>
                </div>
            </form>
        </div>
    );
}

function RequiredMark() {
    return (
        <span className="text-destructive" aria-hidden="true">
            *
        </span>
    );
}

function FieldError({ children }: { children?: string }) {
    return children ? (
        <small
            className="field-error-text font-semibold text-destructive"
            role="alert"
        >
            {children}
        </small>
    ) : null;
}

function useDialogEscape(onClose: () => void, disabled: boolean) {
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && !disabled) {
                onClose();
            }
        };
        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [disabled, onClose]);
}

WalletRestitutionsPage.layout = {
    breadcrumbs: [
        { title: 'Finance', href: '/fiscal-invoices' },
        {
            title: 'Restitutions Wallet',
            href: '/administration/wallet-restitutions',
        },
    ],
};
