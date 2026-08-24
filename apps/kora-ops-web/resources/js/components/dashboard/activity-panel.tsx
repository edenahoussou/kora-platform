import { Link } from '@inertiajs/react';
import { ArrowRight, BarChart3 } from 'lucide-react';
import {
    dashboardActivities,
    weeklyActivity,
    weeklyLabels,
} from '@/components/dashboard/dashboard-data';
import { alerts, reports } from '@/routes';

export function ActivityPanel() {
    return (
        <article className="rounded-[1.125rem] border border-border/65 bg-card p-5 shadow-[0_12px_35px_rgba(0,29,78,0.045)] sm:p-6">
            <div className="mb-8 flex items-start justify-between gap-4">
                <div>
                    <div className="mb-2 flex items-center gap-2 text-[0.5625rem] font-black tracking-[0.13em] text-digital uppercase">
                        <BarChart3 className="size-3.5" />
                        Activité réseau
                    </div>
                    <h2 className="text-[0.9375rem] font-extrabold tracking-[-0.02em] text-foreground">
                        Activité sur les 7 derniers jours
                    </h2>
                    <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                        Ventes et objectifs consolidés
                    </p>
                </div>
                <Link
                    href={reports()}
                    className="text-[0.625rem] font-extrabold text-digital transition-colors hover:text-[var(--kora)] focus-visible:rounded focus-visible:ring-2 focus-visible:ring-gold"
                >
                    Voir le rapport
                </Link>
            </div>

            <div className="flex h-[14.375rem] items-end gap-2.5 border-b border-border/80 bg-[repeating-linear-gradient(to_top,transparent_0,transparent_54px,var(--border)_55px)] sm:gap-4">
                {weeklyActivity.map((value, index) => (
                    <div
                        key={weeklyLabels[index]}
                        className="group relative flex min-w-0 flex-1 items-end"
                    >
                        <span
                            className={`w-full origin-bottom rounded-t-[0.4375rem] transition-transform duration-500 group-hover:scale-x-110 ${index === 5 ? 'bg-gradient-to-t from-gold to-[#ffd76b]' : 'bg-gradient-to-t from-[var(--kora)] to-digital'}`}
                            style={{ height: `${value}%` }}
                        />
                        <span className="absolute bottom-[-1.5rem] left-1/2 -translate-x-1/2 text-[0.5625rem] font-semibold text-muted-foreground">
                            {weeklyLabels[index]}
                        </span>
                    </div>
                ))}
            </div>

            <div className="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-[0.625rem] text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    <i className="size-2 rounded-[0.1875rem] bg-digital" />
                    Activité réalisée
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <i className="size-2 rounded-[0.1875rem] bg-gold" />
                    Meilleure journée
                </span>
            </div>
        </article>
    );
}

const activityToneClasses = {
    urgent: {
        rail: 'bg-gold',
        icon: 'bg-gold/18 text-[var(--kora)]',
    },
    control: {
        rail: 'bg-digital',
        icon: 'bg-digital/10 text-digital',
    },
    success: {
        rail: 'bg-[var(--kora)]',
        icon: 'bg-[var(--kora)] text-white',
    },
    parcel: {
        rail: 'bg-gold',
        icon: 'bg-gold text-[var(--kora)]',
    },
};

export function PriorityPanel() {
    return (
        <aside className="overflow-hidden rounded-[1.125rem] border border-border/65 bg-gradient-to-br from-card to-muted/70 shadow-[0_22px_54px_rgba(0,29,78,0.08)]">
            <div className="border-b border-border/70 px-5 py-5 sm:px-6">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <span className="mb-2 block text-[0.5625rem] font-black tracking-[0.13em] text-gold uppercase">
                            Fil de contrôle
                        </span>
                        <h2 className="text-[1.0625rem] font-extrabold tracking-[-0.025em] text-foreground">
                            À suivre maintenant
                        </h2>
                        <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                            Priorités de votre périmètre
                        </p>
                    </div>
                    <span className="grid size-8 place-items-center rounded-[0.625rem] bg-gold/15 text-[var(--kora)]">
                        <span className="size-1.5 rounded-full bg-gold shadow-[0_0_0_4px_rgba(246,176,0,0.13)]" />
                    </span>
                </div>
            </div>

            <div className="grid gap-2 px-3.5 py-3.5">
                {dashboardActivities.map((activity) => {
                    const Icon = activity.icon;
                    const tone = activityToneClasses[activity.tone];

                    return (
                        <button
                            key={activity.title}
                            type="button"
                            className="group relative grid min-h-[4.75rem] grid-cols-[2.5rem_minmax(0,1fr)_3rem] items-center gap-3 overflow-hidden rounded-[0.9375rem] border border-border/70 bg-card px-3 text-left shadow-[0_7px_20px_rgba(0,29,78,0.045)] transition-[transform,box-shadow,border-color] duration-300 hover:translate-x-1 hover:border-gold/60 hover:shadow-[0_14px_32px_rgba(0,29,78,0.1)] focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none"
                        >
                            <span
                                className={`absolute top-3.5 bottom-3.5 left-0 w-0.5 rounded-r-full ${tone.rail}`}
                            />
                            <span
                                className={`grid size-10 place-items-center rounded-[0.8125rem] ${tone.icon}`}
                            >
                                <Icon className="size-4" />
                            </span>
                            <span className="min-w-0">
                                <em className="mb-1 block truncate text-[0.5rem] font-black tracking-[0.1em] text-digital uppercase not-italic">
                                    {activity.category}
                                </em>
                                <strong className="block truncate text-[0.6875rem] font-extrabold text-foreground">
                                    {activity.title}
                                </strong>
                                <small className="mt-1 block truncate text-[0.5625rem] text-muted-foreground">
                                    {activity.detail}
                                </small>
                            </span>
                            <span className="grid justify-items-end gap-2 text-[0.5625rem] text-muted-foreground">
                                <time>{activity.time}</time>
                                <ArrowRight className="size-3.5 -translate-x-1 opacity-0 transition-all duration-300 group-hover:translate-x-0 group-hover:opacity-100" />
                            </span>
                        </button>
                    );
                })}
            </div>

            <Link
                href={alerts()}
                className="flex min-h-12 items-center justify-between border-t border-border/70 bg-muted/60 px-5 text-[0.625rem] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-inset sm:px-6"
            >
                <span>Centre des alertes</span>
                <span className="font-extrabold text-digital">
                    Tout consulter →
                </span>
            </Link>
        </aside>
    );
}
