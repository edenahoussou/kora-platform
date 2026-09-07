import { cn } from '@/lib/utils';

type StatusTone = 'navy' | 'gold' | 'blue' | 'muted' | 'destructive';

type Props = {
    label: string;
    tone?: StatusTone;
    className?: string;
};

const toneStyles: Record<StatusTone, string> = {
    navy: 'text-white bg-[var(--kora)] dark:bg-[var(--digital)]/20 dark:text-[var(--digital)]',
    gold: 'text-[var(--kora)] bg-gold/20 dark:text-gold dark:bg-gold/15',
    blue: 'text-[var(--kora)] bg-[var(--digital)]/9 dark:text-[var(--digital)] dark:bg-[var(--digital)]/15',
    muted: 'text-muted-foreground bg-muted dark:bg-muted/40',
    destructive: 'text-destructive bg-destructive/12 dark:bg-destructive/20',
};

const dotStyles: Record<StatusTone, string> = {
    navy: 'bg-gold',
    gold: 'bg-gold',
    blue: 'bg-[var(--digital)]',
    muted: 'bg-muted-foreground',
    destructive: 'bg-destructive',
};

export function KoraStatusBadge({ label, tone = 'blue', className }: Props) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-[0.5625rem] font-extrabold tracking-[0.02em]',
                toneStyles[tone],
                className,
            )}
        >
            <span className={cn('size-1.5 rounded-full', dotStyles[tone])} />
            {label}
        </span>
    );
}
