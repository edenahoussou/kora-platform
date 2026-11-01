import { Ticket, ArrowRight, ShieldCheck, AlertTriangle } from 'lucide-react';
import type {
    KoraPointProfile,
    FinancialHealth,
} from '../mock/kora-point-mock-data';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    onStartSale: () => void;
    onViewSettlements: () => void;
};

const LONG_DATE_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

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
    const dateStr = LONG_DATE_FORMATTER.format(now);
    const greeting = greetingFor(now.getHours());
    const firstName = point.manager_name.split(' ')[0];

    return (
        <section
            aria-label="Bandeau d'accueil KORA POINT"
            className="relative isolate min-h-[17rem] overflow-hidden rounded-[1.75rem] bg-[var(--kora)] px-6 py-7 text-white shadow-xl ring-1 ring-black/10 sm:px-8 sm:py-8 lg:grid lg:grid-cols-[1.4fr_0.6fr] lg:items-center lg:gap-8 lg:px-10 dark:ring-white/10"
        >
            {/* Background Ornaments */}
            <div className="pointer-events-none absolute -right-24 -bottom-40 -z-10 size-[32rem] rounded-full border border-gold/20" />
            <div className="pointer-events-none absolute top-4 -right-8 -z-10 size-52 rounded-full border border-white/10" />

            {/* Left Content */}
            <div className="relative z-10 flex flex-col justify-between gap-6">
                <div>
                    <div className="mb-2 flex flex-wrap items-center gap-2">
                        <span className="text-[0.625rem] font-black tracking-[0.2em] text-gold uppercase">
                            KORA POINT · {dateStr}
                        </span>
                        <span className="rounded-full bg-white/10 px-2 py-0.5 text-[0.625rem] font-bold text-white/80">
                            {point.name}
                        </span>
                    </div>

                    <h1 className="text-[clamp(1.75rem,3.2vw,2.75rem)] leading-[1.08] font-black tracking-tight text-white">
                        {greeting} {firstName},<br />
                        <span className="font-serif font-normal text-gold italic">
                            {health.sales_allowed
                                ? 'votre guichet de vente est prêt.'
                                : 'ventes actuellement suspendues.'}
                        </span>
                    </h1>

                    <p className="mt-3 max-w-xl text-xs leading-relaxed text-white/75 sm:text-sm">
                        Émettez les billets Bus KORA, encaissez les voyageurs et
                        suivez vos commissions acquises en temps réel.
                    </p>
                </div>

                {/* Primary Action Buttons */}
                <div className="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        onClick={onStartSale}
                        disabled={!health.sales_allowed}
                        className="inline-flex h-11 items-center gap-2 rounded-xl bg-gold px-5 text-xs font-black tracking-wider text-kora uppercase shadow-lg transition hover:bg-gold/90 focus:ring-2 focus:ring-gold focus:ring-offset-2 focus:ring-offset-kora focus:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <Ticket className="size-4" />
                        <span>Vendre un billet</span>
                        <ArrowRight className="size-3.5" />
                    </button>

                    <button
                        type="button"
                        onClick={onViewSettlements}
                        className="inline-flex h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 text-xs font-bold text-white backdrop-blur-sm transition hover:bg-white/20"
                    >
                        <span>Situation reversements</span>
                    </button>
                </div>
            </div>

            {/* Right Status Card */}
            <div className="mt-6 rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur-md lg:mt-0">
                <div className="flex items-center justify-between border-b border-white/10 pb-3">
                    <span className="text-xs font-extrabold tracking-wider text-white/70 uppercase">
                        État du guichet
                    </span>
                    {health.sales_allowed ? (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/25 px-2.5 py-0.5 text-[0.625rem] font-black text-emerald-300">
                            <ShieldCheck className="size-3" />
                            Opérationnel
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-rose-500/30 px-2.5 py-0.5 text-[0.625rem] font-black text-rose-200">
                            <AlertTriangle className="size-3" />
                            Bloqué
                        </span>
                    )}
                </div>

                <div className="mt-3 space-y-2.5 text-xs">
                    <div className="flex items-center justify-between">
                        <span className="text-white/70">Plafond accordé :</span>
                        <strong className="font-mono text-white">
                            {health.credit_limit.toLocaleString('fr-FR')} FCFA
                        </strong>
                    </div>
                    <div className="flex items-center justify-between">
                        <span className="text-white/70">Encours courant :</span>
                        <strong className="font-mono text-gold">
                            {health.current_exposure.toLocaleString('fr-FR')}{' '}
                            FCFA
                        </strong>
                    </div>
                    <div className="flex items-center justify-between border-t border-white/10 pt-2">
                        <span className="text-white/70">
                            Disponible restant :
                        </span>
                        <strong className="font-mono text-emerald-400">
                            {health.available_balance.toLocaleString('fr-FR')}{' '}
                            FCFA
                        </strong>
                    </div>
                </div>
            </div>
        </section>
    );
}
