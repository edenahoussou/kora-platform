import { Head, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ClipboardList,
    FileText,
    Image,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { handleAgentNavigate } from '@/components/kora-points/common/agent-nav';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import koraPointRoutes from '@/routes/kora-point';
import type { KoraPointProfile, FinancialHealth } from '@/types/kora-points';

type AssignedAction = {
    uuid: string;
    visit_uuid: string;
    visit_type: string;
    visit_status: string;
    point_name: string;
    label: string;
    due_date: string | null;
    status: string;
    realization_note: string | null;
    review_reason: string | null;
    photos: {
        uuid: string;
        name: string;
        mime_type: string;
        download_url: string;
    }[];
};

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    actions: {
        data: AssignedAction[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

const statusLabels: Record<string, string> = {
    pending: 'À réaliser',
    returned: 'À reprendre',
    realized: 'En attente de validation KORA',
    validated: 'Validée',
};

function ActionCard({ action }: { action: AssignedAction }) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const form = useForm({ realization_note: '' });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setIsSubmitting(true);
        form.post(
            koraPointRoutes.visits.actions.realize.url({
                visit: action.visit_uuid,
                action: action.uuid,
            }),
            {
                preserveScroll: true,
                onFinish: () => setIsSubmitting(false),
                onSuccess: () => form.reset(),
            },
        );
    };

    return (
        <article className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border bg-muted/20 px-4 py-3 sm:px-5">
                <div className="flex items-start gap-3">
                    <span className="mt-0.5 grid size-9 place-items-center rounded-lg bg-gold/15 text-[var(--kora)]">
                        <ClipboardList className="size-4" />
                    </span>
                    <div>
                        <h2 className="text-sm font-bold text-foreground">
                            {action.label}
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {action.point_name} ·{' '}
                            {action.visit_type === 'quality'
                                ? 'Visite qualité'
                                : action.visit_type === 'commercial_follow_up'
                                  ? 'Suivi commercial'
                                  : 'Contrôle PLV'}
                        </p>
                    </div>
                </div>
                <KoraStatusBadge
                    label={statusLabels[action.status] ?? action.status}
                    tone={
                        action.status === 'validated'
                            ? 'emerald'
                            : action.status === 'returned'
                              ? 'amber'
                              : action.status === 'realized'
                                ? 'blue'
                                : 'muted'
                    }
                />
            </header>
            <div className="space-y-4 p-4 sm:p-5">
                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <CalendarDays className="size-3.5" /> Échéance :{' '}
                    {action.due_date
                        ? new Intl.DateTimeFormat('fr-FR', {
                              dateStyle: 'medium',
                          }).format(new Date(action.due_date))
                        : '—'}
                </p>
                {action.review_reason ? (
                    <p className="rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">
                        À reprendre : {action.review_reason}
                    </p>
                ) : null}
                {action.realization_note ? (
                    <p className="rounded-lg bg-muted/50 px-3 py-2 text-sm leading-6">
                        {action.realization_note}
                    </p>
                ) : null}
                {action.photos.length ? (
                    <div className="flex flex-wrap gap-2">
                        {action.photos.map((photo) => (
                            <a
                                key={photo.uuid}
                                href={photo.download_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1.5 text-xs font-medium text-primary hover:bg-muted"
                            >
                                <Image className="size-3.5" />
                                {photo.name}
                            </a>
                        ))}
                    </div>
                ) : null}
                {action.status === 'pending' || action.status === 'returned' ? (
                    <form
                        onSubmit={submit}
                        className="space-y-3 border-t border-border pt-4"
                    >
                        <label
                            htmlFor={`realization-${action.uuid}`}
                            className="flex items-center gap-1.5 text-xs font-semibold text-foreground"
                        >
                            <FileText className="size-3.5 text-muted-foreground" />
                            Compte rendu de réalisation
                            <span
                                className="ml-0.5 text-red-600"
                                aria-hidden="true"
                            >
                                *
                            </span>
                        </label>
                        <textarea
                            id={`realization-${action.uuid}`}
                            required
                            minLength={3}
                            maxLength={2000}
                            value={form.data.realization_note}
                            onChange={(event) =>
                                form.setData(
                                    'realization_note',
                                    event.target.value,
                                )
                            }
                            aria-describedby={
                                form.errors.realization_note
                                    ? `realization-error-${action.uuid}`
                                    : undefined
                            }
                            rows={3}
                            className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                            placeholder="Décrivez ce qui a été réalisé…"
                        />
                        {form.errors.realization_note ? (
                            <p
                                id={`realization-error-${action.uuid}`}
                                role="alert"
                                className="text-xs text-destructive"
                            >
                                {form.errors.realization_note}
                            </p>
                        ) : null}
                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={isSubmitting || form.processing}
                                className="btn btn-primary btn-sm"
                            >
                                <Check className="size-3.5" /> Signaler comme
                                réalisée
                            </button>
                        </div>
                    </form>
                ) : action.status === 'realized' ? (
                    <p className="text-xs font-medium text-muted-foreground">
                        Votre signalement a été transmis à KORA pour validation.
                    </p>
                ) : (
                    <p className="text-xs font-medium text-emerald-700">
                        Cette action a été validée par KORA.
                    </p>
                )}
            </div>
        </article>
    );
}

export default function AgentVisitActionsPage({
    point,
    health,
    actions,
}: Props) {
    return (
        <KoraPointShell
            point={point}
            health={health}
            activePage="visit-actions"
            onNavigate={handleAgentNavigate}
        >
            <Head title={`Actions de visite — ${point.name}`} />
            <div className="space-y-5">
                <header>
                    <span className="section-kicker">
                        KORA POINT · Suivi terrain
                    </span>
                    <h1 className="mt-1 text-2xl font-extrabold tracking-tight">
                        Actions de visite
                    </h1>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Signalez la réalisation des actions qui vous sont
                        confiées. La Direction KORA vérifie ensuite le résultat.
                    </p>
                </header>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-xl border border-gold/30 bg-gold/10 p-4">
                        <p className="text-xs font-semibold text-muted-foreground">
                            Actions confiées
                        </p>
                        <p className="mt-2 text-2xl font-extrabold tabular-nums">
                            {actions.total}
                        </p>
                    </div>
                    <div className="rounded-xl border border-sky-200 bg-sky-50 p-4">
                        <p className="text-xs font-semibold text-sky-800">
                            Point concerné
                        </p>
                        <p className="mt-2 text-base font-bold text-sky-950">
                            {point.name}
                        </p>
                    </div>
                </div>
                {actions.data.length ? (
                    <div className="space-y-3">
                        {actions.data.map((action) => (
                            <ActionCard key={action.uuid} action={action} />
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                        <span className="mx-auto grid size-12 place-items-center rounded-full bg-gold/15 text-[var(--kora)]">
                            <UserRound className="size-5" />
                        </span>
                        <h2 className="mt-3 text-sm font-bold">
                            Aucune action à suivre
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Les actions qui vous seront confiées lors d’une
                            visite apparaîtront ici.
                        </p>
                    </div>
                )}
            </div>
        </KoraPointShell>
    );
}
