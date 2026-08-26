import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Banknote,
    Check,
    CheckCircle2,
    ChevronRight,
    CircleAlert,
    FileCheck2,
    HandCoins,
    History,
    Plus,
    Smartphone,
    Upload,
    WalletCards,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { CashOpeningDialog } from '@/components/cash/cash-opening-dialog';
import type {
    SaleCashLedger,
    SaleCashMedia,
    SaleCashSession,
    SaleMobileMoneyAccount,
} from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import { approveVariance, close as closeCash } from '@/routes/cash';
import { approve, reject, store } from '@/routes/cash/movements';

type CashMovement = {
    uuid: string;
    ledgerUuid: string | null;
    type: string;
    direction: 'in' | 'out';
    status: 'posted' | 'pending' | 'approved' | 'rejected' | 'reversed';
    amount: number;
    reference: string | null;
    destinationLabel: string | null;
    occurredAt: string | null;
    media: SaleCashMedia[];
};
type CashSessionPage = SaleCashSession & {
    ledgers: SaleCashLedger[];
    movements: CashMovement[];
    openedAt?: string | null;
    agentName?: string | null;
    agencyName?: string | null;
};
type Props = {
    cashSession: CashSessionPage | null;
    mobileMoneyAccounts: SaleMobileMoneyAccount[];
    permissions: {
        canOpen: boolean;
        canClose: boolean;
        canApproveVariance: boolean;
        canCreateMovement: boolean;
        canApproveMovement: boolean;
    };
};

const networkLabels: Record<string, string> = {
    mtn_momo: 'MTN MoMo',
    moov_flooz: 'Moov Money / Flooz',
    celtiis_cash: 'Celtiis Cash',
};
const statusLabels: Record<string, string> = {
    open: 'Ouverte',
    pending_approval: 'En attente d’approbation',
    closed: 'Clôturée',
    posted: 'Publié',
    pending: 'En attente',
    approved: 'Approuvé',
    rejected: 'Rejeté',
    reversed: 'Contrepassé',
};

export default function CashPage({
    cashSession,
    mobileMoneyAccounts,
    permissions,
}: Props) {
    const [openingOpen, setOpeningOpen] = useState(false);
    const [closingOpen, setClosingOpen] = useState(false);
    const [movementOpen, setMovementOpen] = useState(false);

    return (
        <>
            <Head title="Caisse journalière" />
            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Finance / Caisse
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Caisse journalière
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Suivez séparément les espèces et chaque compte Mobile
                        Money de votre agence.
                    </p>
                </div>
                {cashSession?.status === 'open' && permissions.canClose && (
                    <button
                        className="btn btn-primary"
                        type="button"
                        onClick={() => setClosingOpen(true)}
                    >
                        <FileCheck2 className="size-4" /> Préparer la clôture
                    </button>
                )}
            </div>
            {!cashSession ? (
                <EmptyCashState
                    canOpen={permissions.canOpen}
                    accounts={mobileMoneyAccounts}
                    onOpen={() => setOpeningOpen(true)}
                />
            ) : (
                <>
                    <CashHeader
                        session={cashSession}
                        onMovement={() => setMovementOpen(true)}
                        canCreateMovement={permissions.canCreateMovement}
                    />
                    <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {cashSession.ledgers.map((ledger) => (
                            <LedgerCard key={ledger.uuid} ledger={ledger} />
                        ))}
                    </div>
                    <div className="mt-5 grid gap-5 xl:grid-cols-[1.4fr_0.6fr]">
                        <MovementTable
                            movements={cashSession.movements}
                            canApprove={permissions.canApproveMovement}
                        />
                        <PendingActions
                            session={cashSession}
                            canApproveVariance={permissions.canApproveVariance}
                        />
                    </div>
                </>
            )}
            {openingOpen && (
                <CashOpeningDialog
                    accounts={mobileMoneyAccounts}
                    onClose={() => setOpeningOpen(false)}
                />
            )}
            {closingOpen && cashSession && (
                <CloseDialog
                    session={cashSession}
                    onClose={() => setClosingOpen(false)}
                />
            )}
            {movementOpen && cashSession && (
                <MovementDialog
                    session={cashSession}
                    onClose={() => setMovementOpen(false)}
                />
            )}
        </>
    );
}

function EmptyCashState({
    canOpen,
    accounts,
    onOpen,
}: {
    canOpen: boolean;
    accounts: SaleMobileMoneyAccount[];
    onOpen: () => void;
}) {
    return (
        <section className="kora-panel rounded-[var(--kora-radius)] border border-border bg-card p-8 shadow-[0_8px_30px_rgba(0,29,78,0.045)]">
            <div className="mx-auto flex max-w-xl flex-col items-center text-center">
                <span className="mb-5 grid size-16 place-items-center rounded-2xl bg-gold/10 text-gold">
                    <WalletCards className="size-8" />
                </span>
                <span className="mb-2 text-[0.6875rem] font-extrabold tracking-[0.14em] text-muted-foreground uppercase">
                    Session du jour
                </span>
                <h2 className="text-xl font-extrabold tracking-tight text-foreground">
                    Votre caisse n’est pas ouverte
                </h2>
                <p className="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                    Déclarez votre solde espèces et chaque compte Mobile Money
                    actif avant d’enregistrer une vente.
                </p>
                {canOpen ? (
                    <button
                        type="button"
                        className="btn btn-primary mt-6"
                        onClick={onOpen}
                    >
                        <Plus className="size-4" /> Ouvrir la caisse
                    </button>
                ) : (
                    <div className="mt-6 inline-flex items-center gap-2 rounded-xl bg-muted px-4 py-3 text-xs font-bold text-muted-foreground">
                        <CircleAlert className="size-4" /> Demandez l’ouverture
                        à votre responsable.
                    </div>
                )}
                {accounts.length > 0 && (
                    <p className="mt-5 text-[0.6875rem] text-muted-foreground">
                        {accounts.length} compte{accounts.length > 1 ? 's' : ''}{' '}
                        Mobile Money seront à déclarer.
                    </p>
                )}
            </div>
        </section>
    );
}

function CashHeader({
    session,
    onMovement,
    canCreateMovement,
}: {
    session: CashSessionPage;
    onMovement: () => void;
    canCreateMovement: boolean;
}) {
    const totalExpected = session.ledgers.reduce(
        (sum, ledger) => sum + ledger.expectedBalance,
        0,
    );
    const pending = session.movements.filter(
        (movement) => movement.status === 'pending',
    ).length;

    return (
        <section className="kora-panel flex flex-col gap-5 rounded-[var(--kora-radius)] border border-border bg-card p-5 shadow-[0_8px_30px_rgba(0,29,78,0.045)] sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-3">
                <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-kora text-gold">
                    <CheckCircle2 className="size-5" />
                </span>
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-sm font-extrabold text-foreground">
                            Session du {session.businessDate}
                        </h2>
                        <StatusPill status={session.status ?? 'open'} />
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {session.agentName ?? 'Agent connecté'}
                        {session.agencyName ? ` · ${session.agencyName}` : ''}
                    </p>
                </div>
            </div>
            <div className="flex items-center gap-4 sm:gap-7">
                <div>
                    <span className="block text-[0.65rem] font-bold tracking-wide text-muted-foreground uppercase">
                        Solde attendu
                    </span>
                    <strong className="mt-1 block text-lg font-black tracking-tight text-foreground">
                        {formatMoney(totalExpected, 'XOF')}
                    </strong>
                </div>
                {pending > 0 && (
                    <div className="rounded-lg bg-gold/10 px-3 py-2 text-[0.6875rem] font-extrabold text-gold">
                        {pending} en attente
                    </div>
                )}
                {canCreateMovement && session.status === 'open' && (
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={onMovement}
                    >
                        <ArrowUpRight className="size-4" /> Reversement
                    </button>
                )}
            </div>
        </section>
    );
}

function LedgerCard({ ledger }: { ledger: SaleCashLedger }) {
    const isCash = ledger.kind === 'cash';
    const label = isCash
        ? 'Espèces'
        : `${networkLabels[ledger.account?.network ?? ''] ?? 'Mobile Money'} · ${ledger.account?.label ?? 'Compte'}`;
    const balance = ledger.actualBalance ?? ledger.expectedBalance;

    return (
        <article className="kora-panel rounded-[var(--kora-radius)] border border-border bg-card p-4 shadow-[0_8px_30px_rgba(0,29,78,0.045)]">
            <div className="flex items-start justify-between gap-3">
                <span
                    className={cn(
                        'grid size-9 place-items-center rounded-lg',
                        isCash
                            ? 'bg-gold/15 text-gold'
                            : 'bg-digital/10 text-digital',
                    )}
                >
                    {isCash ? (
                        <HandCoins className="size-4" />
                    ) : (
                        <Smartphone className="size-4" />
                    )}
                </span>
                <StatusPill status={ledger.status} />
            </div>
            <p
                className="mt-4 truncate text-xs font-extrabold text-foreground"
                title={label}
            >
                {label}
            </p>
            {ledger.account && (
                <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                    {ledger.account.accountIdentifier}
                </p>
            )}
            <strong className="mt-4 block text-xl font-black tracking-tight text-foreground">
                {formatMoney(balance, 'XOF')}
            </strong>
            <div className="mt-3 flex items-center justify-between border-t border-border pt-3 text-[0.65rem] font-bold text-muted-foreground">
                <span>
                    Ouverture {formatMoney(ledger.openingBalance, 'XOF')}
                </span>
                {ledger.variance !== null && ledger.variance !== 0 ? (
                    <span className="text-destructive">
                        Écart {formatMoney(ledger.variance, 'XOF')}
                    </span>
                ) : (
                    <span className="text-emerald-600">À jour</span>
                )}
            </div>
        </article>
    );
}

function MovementTable({
    movements,
    canApprove,
}: {
    movements: CashMovement[];
    canApprove: boolean;
}) {
    return (
        <section className="kora-panel overflow-hidden rounded-[var(--kora-radius)] border border-border bg-card shadow-[0_8px_30px_rgba(0,29,78,0.045)]">
            <div className="flex items-center justify-between border-b border-border px-5 py-4">
                <div>
                    <h2 className="text-sm font-extrabold text-foreground">
                        Mouvements récents
                    </h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Les écritures publiées restent traçables.
                    </p>
                </div>
                <History className="size-4 text-muted-foreground" />
            </div>
            {movements.length === 0 ? (
                <p className="px-5 py-10 text-center text-sm text-muted-foreground">
                    Aucun mouvement enregistré aujourd’hui.
                </p>
            ) : (
                <div className="divide-y divide-border">
                    {movements.map((movement) => (
                        <MovementRow
                            key={movement.uuid}
                            movement={movement}
                            canApprove={canApprove}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

function MovementRow({
    movement,
    canApprove,
}: {
    movement: CashMovement;
    canApprove: boolean;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
            <div className="flex min-w-0 items-center gap-3">
                <span
                    className={cn(
                        'grid size-8 shrink-0 place-items-center rounded-lg',
                        movement.direction === 'in'
                            ? 'bg-emerald-500/10 text-emerald-600'
                            : 'bg-orange-500/10 text-orange-600',
                    )}
                >
                    {movement.direction === 'in' ? (
                        <ArrowDownLeft className="size-4" />
                    ) : (
                        <ArrowUpRight className="size-4" />
                    )}
                </span>
                <div className="min-w-0">
                    <p className="truncate text-xs font-extrabold text-foreground">
                        {movement.type.replaceAll('_', ' ')}
                    </p>
                    <p className="mt-0.5 truncate text-[0.65rem] text-muted-foreground">
                        {movement.reference ?? 'Sans référence'}
                        {movement.destinationLabel
                            ? ` · ${movement.destinationLabel}`
                            : ''}
                    </p>
                </div>
            </div>
            <div className="flex items-center gap-3">
                <strong
                    className={cn(
                        'text-xs font-black',
                        movement.direction === 'in'
                            ? 'text-emerald-600'
                            : 'text-orange-600',
                    )}
                >
                    {movement.direction === 'in' ? '+' : '-'}
                    {formatMoney(movement.amount, 'XOF')}
                </strong>
                <StatusPill status={movement.status} />
                {canApprove && movement.status === 'pending' && (
                    <>
                        <button
                            className="grid size-7 place-items-center rounded-md bg-emerald-500/10 text-emerald-600"
                            onClick={() =>
                                router.post(approve.url(movement.uuid))
                            }
                            aria-label="Approuver"
                        >
                            <Check className="size-3.5" />
                        </button>
                        <button
                            className="grid size-7 place-items-center rounded-md bg-destructive/10 text-destructive"
                            onClick={() =>
                                router.post(reject.url(movement.uuid), {
                                    reason: 'Rejeté depuis la caisse',
                                })
                            }
                            aria-label="Rejeter"
                        >
                            <X className="size-3.5" />
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}

function PendingActions({
    session,
    canApproveVariance,
}: {
    session: CashSessionPage;
    canApproveVariance: boolean;
}) {
    const needsApproval = session.status === 'pending_approval';

    return (
        <section className="kora-panel rounded-[var(--kora-radius)] border border-border bg-card p-5 shadow-[0_8px_30px_rgba(0,29,78,0.045)]">
            <div className="flex items-center gap-2">
                <CircleAlert
                    className={cn(
                        'size-4',
                        needsApproval ? 'text-gold' : 'text-emerald-600',
                    )}
                />
                <h2 className="text-sm font-extrabold text-foreground">
                    À surveiller
                </h2>
            </div>
            <div className="mt-4 space-y-3">
                {needsApproval ? (
                    <div className="rounded-xl bg-gold/10 p-3 text-xs leading-5 text-foreground">
                        <strong>Écart à approuver.</strong>
                        <br />
                        La clôture restera en attente jusqu’à la validation d’un
                        responsable.
                    </div>
                ) : (
                    <div className="rounded-xl bg-emerald-500/10 p-3 text-xs leading-5 text-foreground">
                        <strong>Caisse équilibrée.</strong>
                        <br />
                        Aucune action prioritaire pour cette session.
                    </div>
                )}
                {needsApproval && canApproveVariance && (
                    <button
                        type="button"
                        className="btn btn-primary w-full"
                        onClick={() =>
                            router.post(approveVariance.url(session.uuid!))
                        }
                    >
                        Approuver l’écart <ChevronRight className="size-4" />
                    </button>
                )}
            </div>
        </section>
    );
}

function StatusPill({ status }: { status: string }) {
    return (
        <span
            className={cn(
                'rounded-full px-2 py-1 text-[0.6rem] font-extrabold',
                ['open', 'posted', 'approved', 'closed'].includes(status)
                    ? 'bg-emerald-500/10 text-emerald-600'
                    : status === 'rejected'
                      ? 'bg-destructive/10 text-destructive'
                      : 'bg-gold/15 text-gold',
            )}
        >
            {statusLabels[status] ?? status}
        </span>
    );
}

function CloseDialog({
    session,
    onClose,
}: {
    session: CashSessionPage;
    onClose: () => void;
}) {
    const form = useForm<{
        ledgers: Array<{
            uuid: string;
            actual_balance: string;
            closing_comment: string;
            evidence: File | null;
        }>;
    }>({
        ledgers: session.ledgers.map((ledger) => ({
            uuid: ledger.uuid,
            actual_balance: String(ledger.expectedBalance),
            closing_comment: '',
            evidence: null,
        })),
    });
    const valid = form.data.ledgers.every(
        (ledger) =>
            Number.isInteger(Number(ledger.actual_balance)) &&
            Number(ledger.actual_balance) >= 0 &&
            ledger.evidence !== null,
    );
    const update = (
        index: number,
        changes: Partial<(typeof form.data.ledgers)[number]>,
    ) =>
        form.setData(
            'ledgers',
            form.data.ledgers.map((ledger, ledgerIndex) =>
                ledgerIndex === index ? { ...ledger, ...changes } : ledger,
            ),
        );
    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!valid || form.processing) {
            return;
        }

        form.post(closeCash.url(session.uuid!), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="cash-modal-backdrop" role="presentation">
            <form
                className="cash-modal cash-opening-modal"
                role="dialog"
                aria-modal="true"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">Fin de journée</span>
                        <h3>Clôturer la caisse</h3>
                        <p>
                            Déclarez le solde constaté pour chaque sous-journal.
                            Une différence sera soumise à approbation.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <div className="cash-opening-list">
                    {form.data.ledgers.map((ledger, index) => {
                        const source = session.ledgers.find(
                            (item) => item.uuid === ledger.uuid,
                        );

                        return (
                            <div className="cash-opening-row" key={ledger.uuid}>
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        {source?.kind === 'cash' ? (
                                            <Banknote className="size-4" />
                                        ) : (
                                            <Smartphone className="size-4" />
                                        )}
                                    </span>
                                    <div>
                                        <strong>
                                            {source?.kind === 'cash'
                                                ? 'Espèces'
                                                : source?.account?.label}
                                        </strong>
                                        <small>
                                            Attendu ·{' '}
                                            {formatMoney(
                                                source?.expectedBalance ?? 0,
                                                'XOF',
                                            )}
                                        </small>
                                    </div>
                                </div>
                                <div className="cash-opening-fields">
                                    <label className="field">
                                        <span>
                                            Solde réel <b>*</b>
                                        </span>
                                        <input
                                            className="control"
                                            type="number"
                                            min="0"
                                            value={ledger.actual_balance}
                                            onChange={(event) =>
                                                update(index, {
                                                    actual_balance:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </label>
                                    <label className="cash-evidence-input">
                                        <span>
                                            <Upload className="size-3.5" />
                                            {ledger.evidence
                                                ? 'Preuve ajoutée'
                                                : 'Ajouter une preuve'}{' '}
                                            <b>*</b>
                                        </span>
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={(event) =>
                                                update(index, {
                                                    evidence:
                                                        event.target
                                                            .files?.[0] ?? null,
                                                })
                                            }
                                        />
                                        <small>
                                            JPEG, PNG ou WebP · 5 Mo maximum
                                        </small>
                                    </label>
                                </div>
                                <label className="field cash-opening-comment">
                                    <span>
                                        Commentaire{' '}
                                        <em>obligatoire si écart</em>
                                    </span>
                                    <input
                                        className="control"
                                        value={ledger.closing_comment}
                                        onChange={(event) =>
                                            update(index, {
                                                closing_comment:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="Expliquez une différence éventuelle"
                                    />
                                </label>
                            </div>
                        );
                    })}
                </div>
                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={onClose}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={!valid || form.processing}
                    >
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Soumettre la clôture'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function MovementDialog({
    session,
    onClose,
}: {
    session: CashSessionPage;
    onClose: () => void;
}) {
    const form = useForm({
        ledger_uuid: session.ledgers[0]?.uuid ?? '',
        amount: '',
        type: 'reversement',
        destination_label: '',
        destination_reference: '',
        description: '',
        evidence: null as File | null,
    });
    const valid =
        form.data.ledger_uuid !== '' &&
        Number(form.data.amount) > 0 &&
        form.data.destination_label.trim() !== '' &&
        form.data.destination_reference.trim() !== '' &&
        form.data.evidence !== null;
    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!valid || form.processing) {
            return;
        }

        form.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="cash-modal-backdrop" role="presentation">
            <form
                className="cash-modal"
                role="dialog"
                aria-modal="true"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">Sortie de fonds</span>
                        <h3>Enregistrer un reversement</h3>
                        <p>
                            Le mouvement sera soumis à validation et ne
                            modifiera le solde qu’après approbation.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="field">
                        <span>
                            Sous-journal <b>*</b>
                        </span>
                        <select
                            className="control"
                            value={form.data.ledger_uuid}
                            onChange={(event) =>
                                form.setData('ledger_uuid', event.target.value)
                            }
                        >
                            {session.ledgers.map((ledger) => (
                                <option key={ledger.uuid} value={ledger.uuid}>
                                    {ledger.kind === 'cash'
                                        ? 'Espèces'
                                        : ledger.account?.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="field">
                        <span>
                            Montant <b>*</b>
                        </span>
                        <input
                            className="control"
                            type="number"
                            min="1"
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                            placeholder="0"
                        />
                    </label>
                    <label className="field">
                        <span>
                            Destinataire <b>*</b>
                        </span>
                        <input
                            className="control"
                            value={form.data.destination_label}
                            onChange={(event) =>
                                form.setData(
                                    'destination_label',
                                    event.target.value,
                                )
                            }
                            placeholder="Ex. Trésorerie KORA"
                        />
                    </label>
                    <label className="field">
                        <span>
                            Référence <b>*</b>
                        </span>
                        <input
                            className="control"
                            value={form.data.destination_reference}
                            onChange={(event) =>
                                form.setData(
                                    'destination_reference',
                                    event.target.value,
                                )
                            }
                            placeholder="Référence du reversement"
                        />
                    </label>
                </div>
                <label className="field mt-4">
                    <span>
                        Justificatif <b>*</b>
                    </span>
                    <input
                        className="control"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(event) =>
                            form.setData(
                                'evidence',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <small>JPEG, PNG ou WebP · 5 Mo maximum</small>
                </label>
                <label className="field mt-4">
                    <span>
                        Commentaire <em>facultatif</em>
                    </span>
                    <textarea
                        className="control min-h-20"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                </label>
                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={onClose}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={!valid || form.processing}
                    >
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Soumettre le reversement'}
                    </button>
                </div>
            </form>
        </div>
    );
}
