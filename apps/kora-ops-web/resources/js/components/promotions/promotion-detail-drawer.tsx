import {
    CalendarClock,
    Crosshair,
    History,
    ListChecks,
    Tag,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    PromotionDetail,
    PromotionDisplayStatus,
    PromotionRecord,
} from './promotion-types';
import { formatBenefit, formatFcfa, formatPeriod } from './promotion-types';

type Tab = 'conditions' | 'scope' | 'uses' | 'history';

type Props = {
    open: boolean;
    promotion: PromotionRecord | null;
    detail: PromotionDetail | null;
    loading: boolean;
    statusLabels: Record<string, string>;
    statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'>;
    scopeLabels: Record<string, string>;
    channelLabels: Record<string, string>;
    onOpenChange: (open: boolean) => void;
};

const tabs: Array<{ id: Tab; label: string; icon: typeof Tag }> = [
    { id: 'conditions', label: 'Conditions', icon: ListChecks },
    { id: 'scope', label: 'Périmètre', icon: Crosshair },
    { id: 'uses', label: 'Utilisations', icon: Tag },
    { id: 'history', label: 'Historique', icon: History },
];

const eventLabels: Record<string, string> = {
    promotion_created: 'Création',
    promotion_updated: 'Modification',
    promotion_status_changed: 'Changement de statut',
};

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

function scopeDescription(
    promotion: PromotionRecord,
    scopeLabels: Record<string, string>,
    channelLabels: Record<string, string>,
): string {
    switch (promotion.scopeType) {
        case 'network':
            return 'Toutes les lignes et tous les canaux de vente.';
        case 'channel':
            return `Canal : ${channelLabels[promotion.channel ?? ''] ?? promotion.channel ?? '—'}.`;
        case 'line':
            return promotion.line
                ? `Ligne ${promotion.line.code} · ${promotion.line.name}.`
                : 'Ligne supprimée.';
        case 'segment':
            return promotion.line
                ? `Ligne ${promotion.line.code} · ${promotion.originStopName ?? '?'} → ${promotion.destinationStopName ?? '?'}.`
                : 'Segment supprimé.';
        default:
            return scopeLabels[promotion.scopeType] ?? promotion.scopeType;
    }
}

export function PromotionDetailDrawer({
    open,
    promotion,
    detail,
    loading,
    statusLabels,
    statusTones,
    scopeLabels,
    channelLabels,
    onOpenChange,
}: Props) {
    const [tab, setTab] = useState<Tab>('conditions');

    if (!open || !promotion) {
        return null;
    }

    const displayStatus: PromotionDisplayStatus = promotion.displayStatus;

    return (
        <div className="fixed inset-0 z-50" role="presentation">
            <div
                className="companies-panel-backdrop absolute inset-0 bg-[rgba(0,29,78,0.3)] backdrop-blur-[9px]"
                onClick={() => onOpenChange(false)}
            />
            <aside
                className="companies-panel absolute top-0 right-0 flex h-full w-full max-w-[38rem] flex-col border-l border-border bg-card shadow-[-20px_0_60px_rgba(0,29,78,0.14)]"
                role="dialog"
                aria-modal="true"
                aria-label={`Promotion ${promotion.name}`}
            >
                <header className="flex items-start justify-between gap-4 border-b border-border px-6 py-5">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <Tag className="size-4" />
                        </span>
                        <div>
                            <span className="section-kicker">Promotion</span>
                            <h3 className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground">
                                {promotion.name}
                            </h3>
                            <p className="mt-0.5 text-[0.625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                {promotion.code} · {formatBenefit(promotion)}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <KoraStatusBadge
                            label={statusLabels[displayStatus] ?? displayStatus}
                            tone={statusTones[displayStatus] ?? 'muted'}
                        />
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            className="cash-modal-close"
                            aria-label="Fermer"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                </header>

                <nav className="flex gap-1 border-b border-border px-6 pt-3">
                    {tabs.map(({ id, label, icon: Icon }) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => setTab(id)}
                            className={cn(
                                'flex items-center gap-1.5 rounded-t-[10px] px-3.5 py-2.5 text-[0.72rem] font-bold transition-colors duration-300',
                                tab === id
                                    ? 'bg-muted/60 text-foreground shadow-[inset_0_-2px_0_0_var(--color-gold)]'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-3.5" strokeWidth={1.8} />
                            {label}
                        </button>
                    ))}
                </nav>

                <div className="min-h-0 flex-1 overflow-y-auto p-6">
                    {tab === 'conditions' && (
                        <section className="cash-opening-row">
                            <div className="cash-opening-row-head">
                                <span className="cash-opening-icon">
                                    <ListChecks className="size-4" />
                                </span>
                                <div>
                                    <strong>Conditions d’application</strong>
                                    <small>
                                        La remise se déclenche à la saisie du
                                        code, sur les ventes nouvelles
                                        uniquement.
                                    </small>
                                </div>
                            </div>
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt className="text-[0.5625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                        Avantage
                                    </dt>
                                    <dd className="mt-1 text-xs font-bold text-foreground">
                                        {formatBenefit(promotion)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[0.5625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                        Priorité
                                    </dt>
                                    <dd className="mt-1 text-xs font-bold text-foreground">
                                        {promotion.priority}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[0.5625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                        Période
                                    </dt>
                                    <dd className="mt-1 text-xs font-bold text-foreground">
                                        {formatPeriod(promotion)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[0.5625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                        Utilisations
                                    </dt>
                                    <dd className="mt-1 text-xs font-bold text-foreground">
                                        {promotion.usedCount}
                                        {promotion.maxUses !== null
                                            ? ` / ${promotion.maxUses}`
                                            : ' (illimitées)'}
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-4 rounded-[10px] bg-muted/50 px-3.5 py-3 text-[0.68rem] leading-relaxed text-muted-foreground">
                                Une promotion ne modifie pas les ventes déjà
                                confirmées et respecte sa période d’application.
                                La remise s’applique après la tarification
                                dynamique et avant la remise groupe.
                            </p>
                        </section>
                    )}

                    {tab === 'scope' && (
                        <section className="cash-opening-row">
                            <div className="cash-opening-row-head">
                                <span className="cash-opening-icon">
                                    <Crosshair className="size-4" />
                                </span>
                                <div>
                                    <strong>Périmètre d’application</strong>
                                    <small>
                                        {scopeLabels[promotion.scopeType] ??
                                            promotion.scopeType}
                                    </small>
                                </div>
                            </div>
                            <p className="text-xs leading-relaxed text-foreground">
                                {scopeDescription(
                                    promotion,
                                    scopeLabels,
                                    channelLabels,
                                )}
                            </p>
                        </section>
                    )}

                    {tab === 'uses' && (
                        <section className="cash-opening-row">
                            <div className="cash-opening-row-head">
                                <span className="cash-opening-icon">
                                    <Tag className="size-4" />
                                </span>
                                <div>
                                    <strong>Utilisations</strong>
                                    <small>
                                        Ventes payées ayant consommé ce code.
                                    </small>
                                </div>
                            </div>
                            {loading ? (
                                <p className="text-xs text-muted-foreground">
                                    Chargement des utilisations…
                                </p>
                            ) : detail === null ||
                              detail.redemptions.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    Aucune utilisation enregistrée pour le
                                    moment.
                                </p>
                            ) : (
                                <div className="divide-y divide-black/[0.045] rounded-xl border border-border">
                                    {detail.redemptions.map((redemption) => (
                                        <div
                                            key={redemption.uuid}
                                            className="flex items-center justify-between px-3.5 py-2.5"
                                        >
                                            <div>
                                                <strong className="block text-xs font-black text-foreground">
                                                    {formatFcfa(
                                                        redemption.discountAmount,
                                                    )}
                                                </strong>
                                                <span className="text-[0.625rem] text-muted-foreground">
                                                    {formatDateTime(
                                                        redemption.createdAt,
                                                    )}
                                                </span>
                                            </div>
                                            <span className="font-mono text-[0.625rem] text-muted-foreground">
                                                {redemption.reservationUuid?.slice(
                                                    0,
                                                    8,
                                                ) ?? '—'}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    )}

                    {tab === 'history' && (
                        <section className="cash-opening-row">
                            <div className="cash-opening-row-head">
                                <span className="cash-opening-icon">
                                    <History className="size-4" />
                                </span>
                                <div>
                                    <strong>Historique</strong>
                                    <small>
                                        Créations, modifications et changements
                                        de statut.
                                    </small>
                                </div>
                            </div>
                            {loading ? (
                                <p className="text-xs text-muted-foreground">
                                    Chargement de l’historique…
                                </p>
                            ) : detail === null ||
                              detail.history.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    Aucun événement enregistré.
                                </p>
                            ) : (
                                <ul className="space-y-2.5">
                                    {detail.history.map((entry, index) => (
                                        <li
                                            key={`${entry.at}-${index}`}
                                            className="flex items-start gap-3 rounded-[10px] bg-muted/40 px-3.5 py-2.5"
                                        >
                                            <CalendarClock
                                                className="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                                strokeWidth={1.8}
                                            />
                                            <div className="min-w-0">
                                                <p className="text-xs font-bold text-foreground">
                                                    {eventLabels[entry.event] ??
                                                        entry.event}
                                                </p>
                                                <p className="text-[0.625rem] text-muted-foreground">
                                                    {formatDateTime(entry.at)}
                                                    {entry.actor
                                                        ? ` · ${entry.actor}`
                                                        : ''}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}
                </div>
            </aside>
        </div>
    );
}
