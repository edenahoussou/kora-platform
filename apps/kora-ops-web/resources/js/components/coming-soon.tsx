import { Head } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { BreadcrumbItem } from '@/types';

type ComingSoonProps = {
    title: string;
    description: string;
    module?: string;
    icon?: LucideIcon;
    breadcrumbs?: BreadcrumbItem[];
};

/**
 * Placeholder page for modules not yet implemented.
 * Used for V2 features and V1 stubs — keeps navigation functional
 * while signalling the feature is under construction.
 */
export function ComingSoon({
    title,
    description,
    module,
    icon: Icon,
    breadcrumbs = [],
}: ComingSoonProps) {
    const crumbs: BreadcrumbItem[] = [...breadcrumbs, { title, href: '' }];

    return (
        <>
            <Head title={title} />

            <div className="kora-page-head mb-6 flex items-end justify-between gap-4">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        {crumbs.map((crumb, i) => (
                            <span key={i}>
                                {i > 0 && ' / '}
                                {crumb.title}
                            </span>
                        ))}
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        {title}
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        {description}
                    </p>
                </div>
            </div>

            <div className="kora-panel flex min-h-[50vh] flex-col items-center justify-center rounded-[var(--kora-radius)] border border-border bg-card p-8 text-center shadow-[0_8px_30px_rgba(0,29,78,0.045)]">
                {Icon && (
                    <div className="mb-5 grid size-16 place-items-center rounded-2xl bg-gold/10 text-gold">
                        <Icon className="size-8" />
                    </div>
                )}
                <span className="mb-2 inline-block rounded-full bg-muted px-3 py-1 text-[0.6875rem] font-bold tracking-wider text-muted-foreground uppercase">
                    {module ?? 'Bientôt disponible'}
                </span>
                <h2 className="text-[1.25rem] font-bold text-foreground">
                    {title}
                </h2>
                <p className="mt-2 max-w-md text-[0.875rem] text-muted-foreground">
                    {description}
                </p>
                <p className="mt-4 text-[0.75rem] text-muted-foreground/70">
                    Ce module est en cours de conception et sera intégré
                    progressivement dans KORA OPS.
                </p>
            </div>
        </>
    );
}
