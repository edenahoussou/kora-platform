import { cn } from '@/lib/utils';

type StatusTone =
    | 'navy'
    | 'gold'
    | 'blue'
    | 'muted'
    | 'destructive'
    | 'emerald'
    | 'amber';

type StatusVariant = 'subtle' | 'solid';

type Props = {
    label: string;
    tone?: StatusTone;
    variant?: StatusVariant;
    className?: string;
};

const subtleToneStyles: Record<StatusTone, string> = {
    navy: 'text-white bg-kora dark:bg-digital/20 dark:text-digital',
    gold: 'text-kora bg-gold/20 dark:text-gold dark:bg-gold/15',
    blue: 'text-kora bg-digital/10 dark:text-digital dark:bg-digital/15',
    muted: 'text-muted-foreground bg-muted dark:bg-muted/40',
    destructive: 'text-destructive bg-destructive/12 dark:bg-destructive/20',
    emerald: 'text-emerald-700 bg-emerald-500/15 dark:text-emerald-400 dark:bg-emerald-500/20',
    amber: 'text-amber-700 bg-amber-500/15 dark:text-amber-400 dark:bg-amber-500/20',
};

const solidToneStyles: Record<StatusTone, string> = {
    navy: 'text-white bg-kora shadow-sm',
    gold: 'text-kora bg-gold font-black shadow-sm',
    blue: 'text-white bg-digital shadow-sm',
    muted: 'text-muted-foreground bg-muted border border-border shadow-sm',
    destructive: 'text-white bg-destructive shadow-sm',
    emerald: 'text-white bg-emerald-600 dark:bg-emerald-500 shadow-sm',
    amber: 'text-amber-950 bg-amber-400 font-extrabold shadow-sm',
};

const dotStyles: Record<StatusTone, string> = {
    navy: 'bg-gold',
    gold: 'bg-gold',
    blue: 'bg-digital',
    muted: 'bg-muted-foreground',
    destructive: 'bg-destructive',
    emerald: 'bg-emerald-400',
    amber: 'bg-amber-600',
};

const solidDotStyles: Record<StatusTone, string> = {
    navy: 'bg-gold',
    gold: 'bg-kora',
    blue: 'bg-white',
    muted: 'bg-muted-foreground',
    destructive: 'bg-white',
    emerald: 'bg-white',
    amber: 'bg-amber-950',
};

export function KoraStatusBadge({
    label,
    tone = 'blue',
    variant = 'subtle',
    className,
}: Props) {
    const isSolid = variant === 'solid';
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.5625rem] font-extrabold tracking-[0.02em]',
                isSolid ? solidToneStyles[tone] : subtleToneStyles[tone],
                className,
            )}
        >
            <span
                className={cn(
                    'size-1.5 rounded-full shrink-0',
                    isSolid ? solidDotStyles[tone] : dotStyles[tone],
                )}
            />
            <span>{label}</span>
        </span>
    );
}
