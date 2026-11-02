import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export function PartnerPage({
    title,
    description,
    organizationName,
    children,
}: {
    title: string;
    description: string;
    organizationName: string;
    children: ReactNode;
}) {
    return (
        <div className="cash-page w-full max-w-full space-y-6">
            <header className="border-b border-border pb-5">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground">
                    {organizationName} <span className="px-1">/</span> Espace
                    partenaire
                </p>
                <h1 className="mt-2 text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    {title}
                </h1>
                <p className="mt-1.5 max-w-3xl text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
            </header>
            {children}
        </div>
    );
}

export function MetricCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: ReactNode;
    hint?: string;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
            <p className="text-sm font-medium text-muted-foreground">{label}</p>
            <p className="mt-3 text-2xl font-bold tracking-tight text-foreground">
                {value}
            </p>
            {hint ? (
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            ) : null}
        </section>
    );
}

export function PartnerCard({
    title,
    action,
    children,
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                <h2 className="text-sm font-semibold text-foreground">
                    {title}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

export function Money({ amount }: { amount: number }) {
    return <>{new Intl.NumberFormat('fr-FR').format(amount)} XOF</>;
}

export function DateLabel({ value }: { value: string | null }) {
    if (!value) {
        return <>—</>;
    }

    return (
        <>
            {new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(
                new Date(value),
            )}
        </>
    );
}

export function EmptyState({ children }: { children: ReactNode }) {
    return (
        <p className="px-5 py-10 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

export function Pagination({
    links,
}: {
    links: { url: string | null; label: string; active: boolean }[];
}) {
    return (
        <nav
            className="flex flex-wrap justify-end gap-1 border-t border-border px-4 py-3"
            aria-label="Pagination"
        >
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={`${link.label}-${index}`}
                        href={link.url}
                        className={`rounded-md px-3 py-1.5 text-xs ${link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={`${link.label}-${index}`}
                        className="rounded-md px-3 py-1.5 text-xs text-muted-foreground/50"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}

export const tableHeadClass =
    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground';
export const tableCellClass = 'px-4 py-3 text-sm text-foreground';
export const inputClass =
    'mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20';
export const labelClass = 'block text-sm font-medium text-foreground';
