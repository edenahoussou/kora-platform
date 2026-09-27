import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Bandeau d'alerte contextuel — liseré + icône + fond teinté, aligné sur
 * les callouts « Règle métier » des écrans Caisse. Partagé entre la fiche
 * départ et le manifeste.
 */
export function PanelAlert({
    tone,
    icon: Icon,
    children,
    className = '',
}: {
    tone: 'warning' | 'danger' | 'info' | 'success';
    icon: LucideIcon;
    children: ReactNode;
    className?: string;
}) {
    const tones = {
        warning:
            'border-gold bg-gold/10 [&>svg]:text-[color-mix(in_srgb,var(--gold)_75%,var(--foreground))]',
        danger: 'border-destructive bg-destructive/10 [&>svg]:text-destructive',
        info: 'border-[var(--digital)] bg-digital/10 [&>svg]:text-[var(--digital)]',
        success:
            'border-emerald-500 bg-emerald-500/10 [&>svg]:text-emerald-600',
    };

    return (
        <div
            role="alert"
            className={`flex items-start gap-3 rounded-[0_0.875rem_0.875rem_0] border-l-[3px] p-4 ${tones[tone]} ${className}`}
        >
            <Icon className="mt-0.5 size-4 shrink-0" strokeWidth={2} />
            <p className="text-[0.6875rem] leading-relaxed text-foreground">
                {children}
            </p>
        </div>
    );
}
