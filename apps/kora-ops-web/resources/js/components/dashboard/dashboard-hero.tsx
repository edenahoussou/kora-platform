import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    BusFront,
    CircleCheck,
    Gauge,
    Route,
} from 'lucide-react';
import type {
    HeroAction,
    NetworkReadings,
} from '@/components/dashboard/dashboard-data';
import { reports } from '@/routes';
import type { Auth } from '@/types/auth';

const LONG_DATE_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

function greetingFor(hour: number): string {
    if (hour < 18) {
        return 'Bonjour';
    }

    return 'Bonsoir';
}

type DashboardHeroProps = {
    heroAction: HeroAction;
    network: NetworkReadings;
};

const readings = [
    { key: 'punctuality', label: 'Ponctualité', icon: Gauge },
    { key: 'tracked', label: 'Départs suivis', icon: Activity },
    { key: 'toWatch', label: 'À suivre', icon: Route },
] as const;

export function DashboardHero({ heroAction, network }: DashboardHeroProps) {
    const { auth } = usePage().props as { auth: Auth };
    const user = auth?.user ?? null;
    const now = new Date();
    const longDate = LONG_DATE_FORMATTER.format(now);
    const greeting = greetingFor(now.getHours());
    const firstName = user?.name?.split(' ')[0] ?? '';

    return (
        <section className="kora-dashboard-hero relative isolate min-h-[20rem] overflow-hidden rounded-[1.75rem] bg-[var(--kora)] px-6 py-7 text-white shadow-[0_28px_65px_rgba(0,29,78,0.22)] ring-1 ring-black/10 sm:px-8 sm:py-8 lg:grid lg:grid-cols-[minmax(0,1.3fr)_minmax(23rem,0.7fr)] lg:items-center lg:gap-8 lg:px-10 dark:ring-white/10">
            <div className="pointer-events-none absolute -right-28 -bottom-48 -z-10 size-[34rem] rounded-full border border-gold/20 sm:-right-20" />
            <div className="pointer-events-none absolute top-8 -right-8 -z-10 size-56 rounded-full border border-white/10" />

            <div className="relative z-10 flex flex-col justify-between gap-8">
                <div>
                    <span className="mb-3 block text-[0.625rem] font-black tracking-[0.18em] text-gold uppercase">
                        Réseau KORA · {longDate}
                    </span>
                    <h1 className="max-w-6xl text-[clamp(2.125rem,3.5vw,3.3125rem)] leading-[1.02] font-extrabold tracking-[-0.045em] text-balance">
                        {greeting} {firstName},
                        <br />
                        <em className="font-normal text-gold not-italic">
                            le réseau est sous contrôle.
                        </em>
                    </h1>
                    <p className="mt-4 max-w-[37.5rem] text-sm leading-[1.6] text-white/65">
                        Une lecture immédiate des opérations, des priorités et
                        de la performance de votre périmètre.
                    </p>
                </div>

                <div className="mt-6 flex flex-wrap gap-2.5">
                    <Link
                        href={heroAction.href}
                        className="group inline-flex min-h-11 items-center gap-2 rounded-[0.75rem] bg-gold px-4 text-[0.75rem] font-extrabold text-[var(--kora)] shadow-[0_10px_24px_rgba(246,176,0,0.2)] transition-transform duration-300 hover:-translate-y-1 focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--kora)]"
                    >
                        {heroAction.label}
                        <ArrowUpRight className="size-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                    </Link>
                    <Link
                        href={reports()}
                        className="inline-flex min-h-11 items-center gap-2 rounded-[0.75rem] border border-white/20 bg-white/[0.07] px-4 text-[0.75rem] font-extrabold text-white transition-colors duration-300 hover:border-white/40 hover:bg-white/12 focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--kora)]"
                    >
                        Consulter les rapports
                    </Link>
                </div>
            </div>

            <div className="relative z-10 mt-6 overflow-hidden rounded-[1.375rem] border border-white/13 bg-white/[0.075] p-6 shadow-[inset_0_1px_rgba(255,255,255,0.1)] backdrop-blur-md lg:mt-0">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <span className="block text-[0.625rem] font-black tracking-[0.12em] text-white/45 uppercase">
                            État du réseau
                        </span>
                        <strong className="mt-2 block text-lg tracking-[-0.03em]">
                            Cadence maîtrisée
                        </strong>
                    </div>
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-white/12 bg-white/[0.07] px-2.5 py-1.5 text-[0.625rem] font-bold text-white/75">
                        <CircleCheck className="size-3.5 text-gold" />
                        Opérationnel
                    </span>
                </div>

                <div className="route-orbit relative mt-3 mb-3 h-[5.6875rem] rounded-b-[50%] border-b-2 border-white/12">
                    <span className="absolute bottom-[-0.375rem] left-[4%] size-[0.6875rem] rounded-full border-[3px] border-[var(--kora)] bg-white" />
                    <span className="absolute bottom-[-0.375rem] left-[49%] size-[0.6875rem] rounded-full border-[3px] border-[var(--kora)] bg-gold" />
                    <span className="absolute right-[4%] bottom-[-0.375rem] size-[0.6875rem] rounded-full border-[3px] border-[var(--kora)] bg-white" />
                    <span className="absolute bottom-2 left-[58%] grid size-[1.625rem] place-items-center rounded-[0.5rem] bg-gold text-[var(--kora)] shadow-[0_9px_20px_rgba(246,176,0,0.24)] motion-safe:animate-[kora-bus-float_2.8s_ease-in-out_infinite]">
                        <BusFront className="size-4" />
                    </span>
                </div>

                <div className="mt-7 grid grid-cols-3 gap-3">
                    {readings.map(({ key, label, icon: Icon }) => (
                        <div
                            key={key}
                            className="border-l border-white/12 pl-3 first:border-l-0 first:pl-0"
                        >
                            <Icon className="mb-2 size-3.5 text-gold" />
                            <strong className="block text-[0.9375rem]">
                                {network[key]}
                            </strong>
                            <small className="mt-1 block text-[0.5625rem] text-white/45">
                                {label}
                            </small>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
