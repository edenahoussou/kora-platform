import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    htmlFor?: string;
    icon: LucideIcon;
    required?: boolean;
    hint?: ReactNode;
    hintId?: string;
    children: ReactNode;
    className?: string;
};

/**
 * Label de formulaire Colis : icône Lucide, astérisque rouge obligatoire et
 * texte d'aide sous le label (même logique de lecture que les tooltips KPI).
 */
export function ParcelFieldLabel({
    htmlFor,
    icon: Icon,
    required = false,
    hint,
    hintId,
    children,
    className,
}: Props) {
    return (
        <div className={cn('grid gap-1', className)}>
            <label
                htmlFor={htmlFor}
                className="flex items-start gap-1.5 text-xs leading-5 font-bold text-foreground"
            >
                <Icon
                    className="mt-0.5 size-3.5 shrink-0 text-gold"
                    strokeWidth={2.2}
                    aria-hidden="true"
                />
                <span>{children}</span>
                {required && (
                    <>
                        <span
                            aria-hidden="true"
                            className="text-[0.875rem] leading-5 font-black text-destructive"
                        >
                            *
                        </span>
                        <span className="sr-only">obligatoire</span>
                    </>
                )}
            </label>
            {hint && (
                <p
                    id={hintId}
                    className="pl-5 text-[0.6875rem] leading-4 text-muted-foreground"
                >
                    {hint}
                </p>
            )}
        </div>
    );
}

export function ParcelRequiredNote() {
    return (
        <p className="text-[0.6875rem] text-muted-foreground">
            Les champs marqués{' '}
            <span className="font-black text-destructive">*</span> sont
            obligatoires.
        </p>
    );
}
