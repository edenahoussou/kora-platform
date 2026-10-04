import { router } from '@inertiajs/react';
import {
    Calendar,
    Check,
    Copy,
    ExternalLink,
    FileText,
    Globe,
    IdCard,
    Loader2,
    Mail,
    Phone,
    Smartphone,
    Ticket as TicketIcon,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type { PassengerSummary } from '@/types/passengers';

type Props = {
    open: boolean;
    passenger: PassengerSummary | null;
    onClose: () => void;
};

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

export function PassengerDetailDrawer({ open, passenger, onClose }: Props) {
    const [copiedPhone, setCopiedPhone] = useState(false);
    const [fetchedDetail, setFetchedDetail] = useState<PassengerSummary | null>(
        null,
    );

    const hasTickets = Boolean(
        passenger?.tickets && passenger.tickets.length > 0,
    );
    const hasFetched = Boolean(
        fetchedDetail && fetchedDetail.phone === passenger?.phone,
    );
    const isLoadingTickets = Boolean(
        open && passenger && !hasTickets && !hasFetched,
    );

    useEffect(() => {
        if (
            !open ||
            !passenger ||
            (passenger.tickets && passenger.tickets.length > 0)
        ) {
            return;
        }

        let isMounted = true;

        fetch(`/passengers/${encodeURIComponent(passenger.phone)}`, {
            headers: { Accept: 'application/json' },
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data: PassengerSummary | null) => {
                if (isMounted && data) {
                    setFetchedDetail(data);
                }
            })
            .catch(() => {
                // Silently ignore fallback to passenger prop
            });

        return () => {
            isMounted = false;
        };
    }, [open, passenger]);

    // Fermeture avec la touche Échap
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && open) {
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [open, onClose]);

    if (!open || !passenger) {
        return null;
    }

    const current =
        fetchedDetail && fetchedDetail.phone === passenger.phone
            ? fetchedDetail
            : passenger;

    const copyPhone = () => {
        if (!current.phone) {
            return;
        }

        navigator.clipboard.writeText(current.phone).then(() => {
            setCopiedPhone(true);
            setTimeout(() => setCopiedPhone(false), 2000);
        });
    };

    const tickets = current.tickets ?? [];

    return (
        <div className="fixed inset-0 z-50 flex animate-in justify-end bg-black/40 backdrop-blur-xs transition-opacity fade-in-0">
            {/* Backdrop cliquable */}
            <div
                className="absolute inset-0"
                onClick={onClose}
                aria-hidden="true"
            />

            {/* Panneau latéral */}
            <aside
                className="relative flex h-full w-full max-w-xl animate-in flex-col bg-card shadow-2xl ring-1 ring-black/[0.08] duration-200 slide-in-from-right"
                role="dialog"
                aria-modal="true"
                aria-labelledby="drawer-passenger-title"
            >
                {/* Header du drawer */}
                <div className="flex items-start justify-between border-b border-black/[0.06] p-6 dark:border-white/[0.06]">
                    <div className="flex items-center gap-4">
                        <span className="grid size-14 shrink-0 place-items-center rounded-2xl bg-gold/15 text-lg font-black text-gold">
                            {getInitials(current.fullName)}
                        </span>
                        <div>
                            <div className="flex items-center gap-2">
                                <h2
                                    id="drawer-passenger-title"
                                    className="text-lg font-black tracking-tight text-foreground"
                                >
                                    {current.fullName}
                                </h2>
                                <KoraStatusBadge
                                    label={current.statusLabel}
                                    tone={
                                        current.status === 'active'
                                            ? 'blue'
                                            : 'gold'
                                    }
                                />
                            </div>
                            <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                {current.hasAccount ? (
                                    <span className="inline-flex items-center gap-1 rounded-md bg-digital/10 px-2 py-0.5 font-medium text-digital">
                                        <Smartphone className="size-3" />
                                        Compte KORA App
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 font-medium text-muted-foreground">
                                        <TicketIcon className="size-3" />
                                        Voyageur Guichet
                                    </span>
                                )}
                                {current.nationality && (
                                    <span className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 font-medium text-muted-foreground">
                                        <Globe className="size-3" />
                                        {current.nationalityLabel} (
                                        {current.nationality})
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        aria-label="Fermer"
                    >
                        <X className="size-5" />
                    </button>
                </div>

                {/* Corps défilable */}
                <div className="flex-1 space-y-6 overflow-y-auto p-6">
                    {/* Statistiques clés voyageur */}
                    <div className="grid grid-cols-3 gap-3">
                        <div className="rounded-xl border border-black/[0.05] bg-muted/40 p-3.5 dark:border-white/[0.05]">
                            <span className="text-[0.6875rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Billets émis
                            </span>
                            <div className="mt-1 flex items-baseline gap-1">
                                <strong className="text-xl font-black text-foreground">
                                    {current.ticketsCount}
                                </strong>
                                <span className="text-xs text-muted-foreground">
                                    voyage{current.ticketsCount > 1 ? 's' : ''}
                                </span>
                            </div>
                        </div>

                        <div className="rounded-xl border border-black/[0.05] bg-muted/40 p-3.5 dark:border-white/[0.05]">
                            <span className="text-[0.6875rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Total Dépenses
                            </span>
                            <div className="mt-1">
                                <strong className="text-lg font-black text-foreground">
                                    {current.totalSpendLabel}
                                </strong>
                            </div>
                        </div>

                        <div className="rounded-xl border border-black/[0.05] bg-muted/40 p-3.5 dark:border-white/[0.05]">
                            <span className="text-[0.6875rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Dernier départ
                            </span>
                            <div className="mt-1 truncate">
                                <strong className="text-xs font-bold text-foreground">
                                    {current.lastRoute ?? '—'}
                                </strong>
                                <small className="block text-[0.6875rem] text-muted-foreground">
                                    {current.lastSeenRelative ??
                                        current.lastSeenAt ??
                                        '—'}
                                </small>
                            </div>
                        </div>
                    </div>

                    {/* Section Coordonnées & Contact */}
                    <div className="rounded-2xl border border-black/[0.06] bg-card p-5 shadow-xs dark:border-white/[0.06]">
                        <h3 className="mb-3 flex items-center gap-2 text-xs font-black tracking-wider text-muted-foreground uppercase">
                            <Phone className="size-3.5 text-gold" />
                            Coordonnées & Contact
                        </h3>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <span className="block text-[0.6875rem] text-muted-foreground">
                                    Numéro de téléphone
                                </span>
                                <div className="mt-0.5 flex items-center gap-2">
                                    <span className="font-mono text-sm font-bold text-foreground">
                                        {current.phone}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={copyPhone}
                                        className="inline-flex items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-[0.6875rem] text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                    >
                                        {copiedPhone ? (
                                            <>
                                                <Check className="size-3 text-emerald-600" />
                                                <span className="text-emerald-600">
                                                    Copié
                                                </span>
                                            </>
                                        ) : (
                                            <>
                                                <Copy className="size-3" />
                                                Copier
                                            </>
                                        )}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <span className="block text-[0.6875rem] text-muted-foreground">
                                    Adresse e-mail
                                </span>
                                <div className="mt-0.5 flex items-center gap-1.5 font-medium text-foreground">
                                    <Mail className="size-3.5 text-muted-foreground" />
                                    <span className="truncate text-sm">
                                        {current.email ?? 'Non renseignée'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Section Pièce d'Identité & État civil */}
                    <div className="rounded-2xl border border-black/[0.06] bg-card p-5 shadow-xs dark:border-white/[0.06]">
                        <h3 className="mb-3 flex items-center gap-2 text-xs font-black tracking-wider text-muted-foreground uppercase">
                            <IdCard className="size-3.5 text-gold" />
                            Pièce d’identité & Nationalité
                        </h3>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <span className="block text-[0.6875rem] text-muted-foreground">
                                    Type de document
                                </span>
                                <strong className="mt-0.5 block text-sm font-semibold text-foreground">
                                    {current.documentTypeLabel}
                                </strong>
                            </div>

                            <div>
                                <span className="block text-[0.6875rem] text-muted-foreground">
                                    Numéro de pièce
                                </span>
                                <span className="mt-0.5 block font-mono text-sm font-bold text-foreground">
                                    {current.documentNumber ?? 'Non communiqué'}
                                </span>
                            </div>

                            <div>
                                <span className="block text-[0.6875rem] text-muted-foreground">
                                    Nationalité
                                </span>
                                <span className="mt-0.5 block text-sm font-semibold text-foreground">
                                    {current.nationalityLabel}
                                </span>
                            </div>

                            <div>
                                <span className="block text-[0.6875rem] text-muted-foreground">
                                    Date de naissance
                                </span>
                                <span className="mt-0.5 block text-sm font-semibold text-foreground">
                                    {current.birthDate ? (
                                        <span className="flex items-center gap-1.5">
                                            <Calendar className="size-3.5 text-muted-foreground" />
                                            {new Intl.DateTimeFormat('fr-FR', {
                                                day: 'numeric',
                                                month: 'long',
                                                year: 'numeric',
                                            }).format(
                                                new Date(current.birthDate),
                                            )}
                                        </span>
                                    ) : (
                                        'Non renseignée'
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Section Historique des billets */}
                    <div className="rounded-2xl border border-black/[0.06] bg-card p-5 shadow-xs dark:border-white/[0.06]">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="flex items-center gap-2 text-xs font-black tracking-wider text-muted-foreground uppercase">
                                <FileText className="size-3.5 text-gold" />
                                Billets récents ({tickets.length})
                            </h3>
                            {isLoadingTickets && (
                                <span className="flex items-center gap-1 text-[0.6875rem] text-muted-foreground">
                                    <Loader2 className="size-3 animate-spin" />
                                    Chargement…
                                </span>
                            )}
                        </div>

                        {tickets.length === 0 ? (
                            <div className="py-6 text-center text-xs text-muted-foreground">
                                Aucun billet trouvé pour ce voyageur.
                            </div>
                        ) : (
                            <div className="divide-y divide-black/[0.04] dark:divide-white/[0.04]">
                                {tickets.map((t) => (
                                    <div
                                        key={t.uuid}
                                        className="group flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-xs font-black text-foreground">
                                                    {t.shortCode}
                                                </span>
                                                <span className="rounded bg-muted px-1.5 py-0.5 text-[0.625rem] font-bold text-muted-foreground">
                                                    Siège {t.seatCode}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'rounded-full px-2 py-0.5 text-[0.625rem] font-bold',
                                                        t.status === 'used'
                                                            ? 'bg-emerald-500/10 text-emerald-600'
                                                            : t.status ===
                                                                'issued'
                                                              ? 'bg-digital/10 text-digital'
                                                              : 'bg-muted text-muted-foreground',
                                                    )}
                                                >
                                                    {t.statusLabel}
                                                </span>
                                            </div>
                                            <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                <span className="font-semibold text-foreground">
                                                    {t.route}
                                                </span>
                                                <span>·</span>
                                                <span>{t.departureTime}</span>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between gap-3 sm:justify-end">
                                            <span className="font-bold text-foreground">
                                                {t.amountLabel}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    onClose();
                                                    router.visit(
                                                        `/tickets?q=${encodeURIComponent(t.shortCode)}`,
                                                    );
                                                }}
                                                className="inline-flex items-center gap-1 rounded-lg bg-muted/60 px-2 py-1 text-xs font-semibold text-muted-foreground transition hover:bg-gold/15 hover:text-gold"
                                                title="Consulter le billet"
                                            >
                                                Billet
                                                <ExternalLink className="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Footer du Drawer */}
                <div className="border-t border-black/[0.06] p-4 text-right dark:border-white/[0.06]">
                    <button
                        type="button"
                        onClick={onClose}
                        className="btn btn-ghost text-xs font-bold"
                    >
                        Fermer la fiche
                    </button>
                </div>
            </aside>
        </div>
    );
}
