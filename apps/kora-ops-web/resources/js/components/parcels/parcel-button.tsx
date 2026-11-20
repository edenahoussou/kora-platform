import type * as React from 'react';
import { cn } from '@/lib/utils';

export type ParcelButtonVariant =
    'primary' | 'navy' | 'ghost' | 'danger' | 'destructive' | 'success';

const variantClass: Record<ParcelButtonVariant, string> = {
    primary: 'btn-primary',
    navy: 'btn-navy',
    ghost: 'btn-ghost',
    danger: 'btn-danger',
    destructive: 'btn-danger',
    success: 'btn-success',
};

type Props = React.ComponentProps<'button'> & {
    variant?: ParcelButtonVariant;
    size?: 'default' | 'sm';
};

/**
 * Bouton aligné sur le design system OPS réellement utilisé par Finance,
 * Exploitation et Settings (`.btn`, `.btn-primary`, `.btn-navy`…).
 *
 * Le composant `Button` shadcn reste disponible pour les primitives génériques,
 * mais les écrans Colis doivent utiliser ces classes pour rester cohérents.
 */
export function ParcelButton({
    variant = 'ghost',
    size = 'default',
    type = 'button',
    className,
    ...props
}: Props) {
    return (
        <button
            type={type}
            className={cn(
                'btn',
                variantClass[variant],
                size === 'sm' && 'btn-sm',
                className,
            )}
            {...props}
        />
    );
}
