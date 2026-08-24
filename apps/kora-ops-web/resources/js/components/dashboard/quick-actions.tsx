import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { quickActions } from '@/components/dashboard/dashboard-data';

const accentClasses = {
    gold: 'bg-gold/18 text-[var(--kora)]',
    blue: 'bg-digital/10 text-digital',
    navy: 'bg-[var(--kora)] text-white',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
};

export function QuickActions() {
    return (
        <section aria-labelledby="quick-actions-title">
            <div className="mb-4">
                <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.15em] text-gold uppercase">
                    Raccourcis métier
                </span>
                <h2
                    id="quick-actions-title"
                    className="text-[1.25rem] font-extrabold tracking-[-0.035em] text-foreground"
                >
                    Agir sans détour
                </h2>
            </div>

            <div className="grid grid-flow-row-dense grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {quickActions.map((action, index) => {
                    const Icon = action.icon;

                    return (
                        <Link
                            key={action.title}
                            href={action.href}
                            className="group relative min-h-[7.375rem] overflow-hidden rounded-[1rem] border border-border/65 bg-card p-4 shadow-[0_12px_34px_rgba(0,29,78,0.05)] transition-[transform,box-shadow,border-color] duration-300 hover:-translate-y-1 hover:border-gold/70 hover:shadow-[0_20px_40px_rgba(0,29,78,0.1)] focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none"
                        >
                            <span className="absolute -right-8 -bottom-8 size-20 rounded-full border-[0.75rem] border-digital/5 transition-transform duration-500 group-hover:scale-125" />
                            <span className="relative flex items-start justify-between gap-3">
                                <span className="text-[0.625rem] font-black tracking-[0.12em] text-gold">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <span
                                    className={`grid size-8 place-items-center rounded-[0.625rem] ${accentClasses[action.accent]}`}
                                >
                                    <Icon className="size-4" />
                                </span>
                            </span>
                            <strong className="relative mt-4 block text-[0.75rem] font-extrabold text-foreground">
                                {action.title}
                            </strong>
                            <span className="relative mt-1.5 block max-w-[15rem] text-[0.625rem] leading-5 text-muted-foreground">
                                {action.description}
                            </span>
                            <ArrowUpRight className="absolute right-4 bottom-4 size-4 text-digital opacity-0 transition-all duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:opacity-100" />
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}
