import {
    ArrowUpRight,
    ReceiptText,
    Ticket,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type QuickActionItem = {
    index: string;
    title: string;
    description: string;
    icon: LucideIcon;
    brandClass: string;
    pageId: string;
};

const actions: QuickActionItem[] = [
    {
        index: '01',
        title: 'Vente au guichet',
        description:
            'Recherche de départ, choix des sièges et émission de billets passagers.',
        icon: Ticket,
        brandClass: 'bg-gold/15 text-gold',
        pageId: 'sale',
    },
    {
        index: '02',
        title: 'Ma session caisse',
        description:
            'Fond de roulement, journal des encaissements physiques et clôture du jour.',
        icon: Wallet,
        brandClass: 'bg-emerald-500/10 text-emerald-600',
        pageId: 'cash',
    },
    {
        index: '03',
        title: 'Déclarer un versement',
        description:
            'Justificatif de versement bancaire ou mobile money vers le compte KORA.',
        icon: ReceiptText,
        brandClass: 'bg-digital/10 text-digital',
        pageId: 'settlements',
    },
    {
        index: '04',
        title: 'Suivi des commissions',
        description:
            'Relevé de vos commissions contractuelles acquises en direct sur chaque vente.',
        icon: TrendingUp,
        brandClass: 'bg-kora text-white dark:bg-gold/20 dark:text-gold',
        pageId: 'commissions',
    },
];

type Props = {
    onNavigate: (pageId: string) => void;
};

export function KoraPointQuickActions({ onNavigate }: Props) {
    return (
        <section
            aria-labelledby="point-quick-actions-title"
            className="cash-reveal cash-reveal-delay-3"
        >
            <div className="cash-section-intro">
                <div>
                    <span className="section-kicker text-gold">
                        Raccourcis guichetier
                    </span>
                    <h2 id="point-quick-actions-title">Agir sans détour</h2>
                </div>
            </div>

            <div className="grid grid-flow-row-dense grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {actions.map((action) => {
                    const Icon = action.icon;

                    return (
                        <button
                            key={action.title}
                            type="button"
                            onClick={() => onNavigate(action.pageId)}
                            className="cash-ledger-card cash-ledger-card-cash group relative flex flex-col justify-between text-left transition-all hover:-translate-y-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                        >
                            <div className="cash-ledger-head w-full">
                                <div className="cash-ledger-identity w-full">
                                    <span
                                        className={cn(
                                            'cash-ledger-brand',
                                            action.brandClass,
                                        )}
                                    >
                                        <Icon
                                            className="size-4"
                                            strokeWidth={1.8}
                                        />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between">
                                            <span className="cash-ledger-title">
                                                {action.title}
                                            </span>
                                            <span className="font-mono text-[9px] font-black tracking-wider text-gold">
                                                {action.index}
                                            </span>
                                        </div>
                                        <small className="line-clamp-2">
                                            {action.description}
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-3 flex items-center justify-between border-t border-border/40 pt-2 text-[10px] font-black tracking-wider text-muted-foreground uppercase group-hover:text-gold">
                                <span>Accéder</span>
                                <ArrowUpRight className="size-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                            </div>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}
