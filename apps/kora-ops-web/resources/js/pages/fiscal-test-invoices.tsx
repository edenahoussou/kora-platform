import { Head, router, usePoll, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    Download,
    Eye,
    FileText,
    LoaderCircle,
    RefreshCw,
} from 'lucide-react';
import { useState } from 'react';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import { store } from '@/routes/fiscal-test-invoices';

type RunSummary = {
    uuid: string;
    caseNumber: number;
    status: string;
    statusLabel: string;
    reference: string | null;
    mecefUid: string | null;
    amount: number;
    lastError: string | null;
    createdAt: string | null;
    canDownloadPdf: boolean;
    pdfUrl: string;
    viewUrl: string;
};

type Case = {
    number: number;
    title: string;
    description: string;
    needsClientIfu: boolean;
    canRun: boolean;
    latestRun: RunSummary | null;
    runs: { summary: RunSummary }[];
    historyCount: number;
};

type Props = {
    cases: Case[];
    testModeEnabled: boolean;
    configured: boolean;
};

function formatDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'En attente';
}

export default function FiscalTestInvoices({
    cases,
    testModeEnabled,
    configured,
}: Props) {
    usePoll(5000, { only: ['cases'] });
    const { hasRole } = usePermissions();
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const [clientIfuByCase, setClientIfuByCase] = useState<
        Record<number, string>
    >({});
    const [processingCase, setProcessingCase] = useState<number | null>(null);
    const canRunCampaign =
        hasRole('system_admin') && testModeEnabled && configured;

    function issue(caseNumber: number) {
        setProcessingCase(caseNumber);
        router.post(
            store().url,
            {
                caseNumber,
                clientIfu: clientIfuByCase[caseNumber] ?? '',
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessingCase(null),
            },
        );
    }

    return (
        <>
            <Head title="Campagne d’autodéclaration DGI" />
            <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <header className="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-4">
                        <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-gold/15 text-gold">
                            <FileText className="size-6" />
                        </span>
                        <div>
                            <p className="text-[0.65rem] font-black tracking-[0.16em] text-muted-foreground uppercase">
                                Fiscalisation · campagne temporaire
                            </p>
                            <h1 className="mt-1 text-2xl font-extrabold tracking-[-0.04em] text-foreground">
                                Autodéclaration e‑MECeF
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                                Émets séparément les tests 1 à 10 du formulaire
                                DGI. Les factures de cette campagne sont isolées
                                des ventes et rapports financiers KORA BUS.
                            </p>
                        </div>
                    </div>
                    <span
                        className={cn(
                            'inline-flex items-center gap-2 self-start rounded-full px-3 py-1.5 text-xs font-bold sm:self-center',
                            testModeEnabled && configured
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                : 'bg-destructive/10 text-destructive',
                        )}
                    >
                        {testModeEnabled && configured ? (
                            <Check className="size-3.5" />
                        ) : (
                            <AlertTriangle className="size-3.5" />
                        )}
                        {testModeEnabled && configured
                            ? 'API TEST prête'
                            : 'API TEST non configurée'}
                    </span>
                </header>

                {(!testModeEnabled || !configured) && (
                    <section className="rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-foreground">
                        <strong>Émission désactivée.</strong> Active le mode
                        e‑MECeF TEST et configure le token TEST ainsi que l’IFU
                        de l’émetteur dans Paramètres → Intégrations.
                    </section>
                )}

                {errors?.clientIfu && (
                    <p className="rounded-lg bg-destructive/10 px-4 py-3 text-sm font-semibold text-destructive">
                        {errors.clientIfu}
                    </p>
                )}

                <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {cases.map((testCase) => {
                        const latest = testCase.latestRun;
                        const isProcessing = processingCase === testCase.number;

                        return (
                            <article
                                key={testCase.number}
                                className="flex min-h-64 flex-col rounded-xl border border-border/70 bg-card p-5 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-10 items-center justify-center rounded-lg bg-kora text-sm font-black text-white">
                                            {testCase.number}
                                        </span>
                                        <div>
                                            <p className="text-[0.625rem] font-black tracking-[0.12em] text-muted-foreground uppercase">
                                                Test DGI
                                            </p>
                                            <h2 className="text-base font-extrabold text-foreground">
                                                {testCase.title}
                                            </h2>
                                        </div>
                                    </div>
                                    {latest && (
                                        <span
                                            className={cn(
                                                'rounded-full px-2.5 py-1 text-[0.65rem] font-bold',
                                                latest.status === 'certified'
                                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                    : latest.status === 'failed'
                                                      ? 'bg-destructive/10 text-destructive'
                                                      : 'bg-gold/15 text-amber-800 dark:text-amber-200',
                                            )}
                                        >
                                            {latest.statusLabel}
                                        </span>
                                    )}
                                </div>

                                <p className="mt-4 min-h-10 text-sm leading-6 text-muted-foreground">
                                    {testCase.description}
                                </p>

                                {testCase.needsClientIfu && (
                                    <label className="mt-3 flex flex-col gap-1.5 text-xs font-semibold text-foreground">
                                        IFU du client de démonstration
                                        <input
                                            value={
                                                clientIfuByCase[
                                                    testCase.number
                                                ] ?? ''
                                            }
                                            onChange={(event) =>
                                                setClientIfuByCase(
                                                    (previous) => ({
                                                        ...previous,
                                                        [testCase.number]:
                                                            event.target.value
                                                                .replace(
                                                                    /\D/g,
                                                                    '',
                                                                )
                                                                .slice(0, 13),
                                                    }),
                                                )
                                            }
                                            inputMode="numeric"
                                            maxLength={13}
                                            placeholder="13 chiffres"
                                            className="h-10 rounded-lg border border-border bg-background px-3 text-sm outline-none focus:border-gold focus:ring-2 focus:ring-gold/20"
                                        />
                                    </label>
                                )}

                                {latest && (
                                    <div className="mt-4 rounded-lg bg-muted/35 px-3 py-2.5 text-xs">
                                        <div className="flex items-center justify-between gap-3 text-muted-foreground">
                                            <span>
                                                {formatDate(latest.createdAt)}
                                            </span>
                                            <span>
                                                {testCase.historyCount}{' '}
                                                émission(s)
                                            </span>
                                        </div>
                                        {latest.reference && (
                                            <p className="mt-1 truncate font-bold text-foreground">
                                                {latest.reference}
                                            </p>
                                        )}
                                        {latest.lastError && (
                                            <p className="mt-2 text-destructive">
                                                {latest.lastError}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <div className="mt-auto flex flex-wrap items-center gap-2 pt-5">
                                    <button
                                        type="button"
                                        onClick={() => issue(testCase.number)}
                                        disabled={
                                            !canRunCampaign ||
                                            !testCase.canRun ||
                                            isProcessing ||
                                            (testCase.needsClientIfu &&
                                                (
                                                    clientIfuByCase[
                                                        testCase.number
                                                    ] ?? ''
                                                ).length !== 13)
                                        }
                                        className="btn btn-primary inline-flex items-center gap-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {isProcessing ? (
                                            <LoaderCircle className="size-3.5 animate-spin" />
                                        ) : (
                                            <RefreshCw className="size-3.5" />
                                        )}
                                        {isProcessing
                                            ? 'Mise en file…'
                                            : latest
                                              ? 'Émettre à nouveau'
                                              : 'Émettre le test'}
                                    </button>
                                    {!testCase.canRun && (
                                        <span className="text-[0.7rem] text-muted-foreground">
                                            D’abord certifier le test 5
                                        </span>
                                    )}
                                    {latest?.canDownloadPdf && (
                                        <a
                                            href={latest.pdfUrl}
                                            className="btn btn-secondary inline-flex items-center gap-2"
                                        >
                                            <Download className="size-3.5" />{' '}
                                            PDF
                                        </a>
                                    )}
                                    {testCase.runs.map(({ summary }) => (
                                        <a
                                            key={summary.uuid}
                                            href={summary.viewUrl}
                                            className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-bold text-kora hover:bg-kora/5 dark:text-digital"
                                        >
                                            <Eye className="size-3.5" /> Détail
                                            #{summary.uuid.slice(0, 8)}
                                        </a>
                                    ))}
                                </div>
                            </article>
                        );
                    })}
                </section>

                <footer className="rounded-xl border border-border/70 bg-muted/25 px-4 py-3 text-xs leading-5 text-muted-foreground">
                    Cette page n’accepte que les tests 1 à 10 et n’émet que vers
                    le domaine officiel e‑MECeF TEST. Chaque clic crée une
                    nouvelle émission conservée dans l’historique.
                </footer>
            </main>
        </>
    );
}
