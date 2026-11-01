import {
    CircleCheck,
    ReceiptText,
    Ticket,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { FinancialHealth, KoraPointProfile } from '../mock/kora-point-mock-data';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    onStartSale: () => void;
    onViewSettlements: () => void;
};

function greetingFor(hour: number): string {
    if (hour < 18) {
        return 'Bonjour';
    }

    return 'Bonsoir';
}

export function KoraPointHeroBanner({
    point,
    health,
    onStartSale,
    onViewSettlements,
}: Props) {
    const now = new Date();
    const greeting = greetingFor(now.getHours());
    const firstName = point.manager_name ? point.manager_name.split(' ')[0] : 'Agent';
    const usage = Math.min(100, Math.max(0, health.usage_percentage));

    return (
        <section
            aria-label="Bandeau d'accueil KORA POINT"
            className="kora-dashboard-hero relative isolate overflow-hidden rounded-[1.75rem] bg-[var(--kora)] px-6 py-7 text-white shadow-[0_28px_65px_rgba(0,29,78,0.22)] ring-1 ring-black/10 sm:px-8 sm:py-8 lg:grid lg:grid-cols-[minmax(0,1.3fr)_minmax(22rem,0.7fr)] lg:items-center lg:gap-8 lg:px-10 dark:ring-white/10"
        >
            {/* Background Ornaments (Style Admin Dashboard) */}
            <div className="pointer-events-none absolute -right-28 -bottom-48 -z-10 size-[34rem] rounded-full border border-gold/20 sm:-right-20" />
            <div className="pointer-events-none absolute top-8 -right-8 -z-10 size-56 rounded-full border border-white/10" />

            {/* Left Content: Welcome, Subtitle & Actions */}
            <div className="relative z-10 flex flex-col justify-between gap-6">
                <div>
                    {/* Golden Kicker - High Contrast Accessible Gold (No blue class) */}
                    <span className="mb-3 block text-[0.625rem] font-black tracking-[0.18em] text-gold uppercase">
                        POINT VOYAGE · {point.code} · {point.name}
                        {point.organization ? ` · Réseau ${point.organization.name}` : ' · Autonome'}
                    </span>

                    {/* Title with Contrasting Font Weights (Style Admin Dashboard) */}
                    <h1 className="text-[clamp(1.85rem,3.2vw,2.75rem)] leading-[1.05] font-extrabold tracking-[-0.045em] text-white">
                        {greeting} {firstName},
                        <br />
                        <em className="font-normal text-gold not-italic">
                            {health.sales_allowed
                                ? 'votre guichet de vente est prêt.'
                                : 'ventes actuellement suspendues.'}
                        </em>
                    </h1>

                    <p className="mt-3.5 max-w-[37.5rem] text-sm leading-[1.6] text-white">
                        Émettez les billets Bus KORA, encaissez les voyageurs et suivez vos commissions acquises en temps réel.
                        Votre fond de roulement et vos encaissements physiques sont tracés en direct.
                    </p>
                </div>

                {/* Action Buttons styled like Ops Admin Dashboard Hero */}
                <div className="flex flex-wrap items-center gap-2.5 pt-1">
                    <button
                        type="button"
                        onClick={onStartSale}
                        disabled={!health.sales_allowed}
                        className="group inline-flex min-h-11 items-center gap-2 rounded-[0.75rem] bg-gold px-4 text-[0.75rem] font-extrabold text-[var(--kora)] shadow-[0_10px_24px_rgba(246,176,0,0.2)] transition-transform duration-300 hover:-translate-y-0.5 disabled:opacity-50 disabled:pointer-events-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--kora)]"
                    >
                        <Ticket className="size-4" /> Vendre un billet
                    </button>

                    <button
                        type="button"
                        onClick={onViewSettlements}
                        className="inline-flex min-h-11 items-center gap-2 rounded-[0.75rem] border border-white/20 bg-white/[0.08] px-4 text-[0.75rem] font-extrabold text-white transition-colors duration-300 hover:border-white/40 hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--kora)]"
                    >
                        <ReceiptText className="size-4 text-gold" /> Situation reversements
                    </button>
                </div>
            </div>

            {/* Right Status Card: Pure White High-Contrast Financial Snapshot */}
            <div className="relative z-10 mt-6 overflow-hidden rounded-[1.375rem] border border-white/20 bg-white/[0.08] p-6 shadow-[inset_0_1px_rgba(255,255,255,0.15)] backdrop-blur-md lg:mt-0">
                <div className="flex items-center justify-between gap-3 border-b border-white/20 pb-3">
                    <span className="text-[0.6875rem] font-black tracking-[0.14em] text-white uppercase">
                        Santé financière
                    </span>
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs font-bold text-white">
                        <CircleCheck
                            className={cn(
                                'size-3.5',
                                health.sales_allowed ? 'text-emerald-400' : 'text-rose-400',
                            )}
                        />
                        {health.sales_allowed ? 'Opérationnel' : 'Bloqué'}
                    </span>
                </div>

                <div className="mt-3.5">
                    <span className="block text-xs font-bold text-white">
                        Solde disponible
                    </span>
                    <strong className="mt-1 block font-mono text-3xl font-black tracking-tight text-emerald-400">
                        {health.available_balance.toLocaleString('fr-FR')} FCFA
                    </strong>
                </div>

                <div className="mt-3.5 grid grid-cols-2 gap-3 border-t border-white/20 pt-3 text-xs">
                    <div>
                        <span className="block text-[0.6875rem] font-bold text-white uppercase tracking-wider">
                            Plafond accordé
                        </span>
                        <strong className="mt-1 block font-mono text-base font-black text-white">
                            {health.credit_limit.toLocaleString('fr-FR')} FCFA
                        </strong>
                    </div>
                    <div>
                        <span className="block text-[0.6875rem] font-bold text-white uppercase tracking-wider">
                            Encours net
                        </span>
                        <strong className="mt-1 block font-mono text-base font-black text-gold">
                            {health.current_exposure.toLocaleString('fr-FR')} FCFA
                        </strong>
                    </div>
                </div>

                <div className="mt-3.5 border-t border-white/20 pt-3">
                    <div className="flex items-center justify-between text-xs font-bold text-white">
                        <span>Consommation ({usage} %)</span>
                        <span className="text-white">{health.next_due_label}</span>
                    </div>
                    <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-white/25">
                        <div
                            className={cn(
                                'h-full rounded-full transition-all duration-500',
                                usage >= 100
                                    ? 'bg-rose-500'
                                    : usage >= 80
                                    ? 'bg-amber-400'
                                    : 'bg-gold',
                            )}
                            style={{ width: `${usage}%` }}
                        />
                    </div>
                    <div className="mt-1.5 flex items-center justify-between text-[0.625rem] font-bold text-white">
                        <span>Alerte 80 %</span>
                        <span>Blocage 100 %</span>
                    </div>
                </div>
            </div>
        </section>
    );
}
