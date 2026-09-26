import * as TooltipPrimitive from '@radix-ui/react-tooltip';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function KoraTooltip({
    content,
    children,
    side = 'top',
    sideOffset = 8,
    className,
}: {
    content: ReactNode;
    children: ReactNode;
    side?: 'top' | 'right' | 'bottom' | 'left';
    sideOffset?: number;
    className?: string;
}) {
    return (
        <TooltipPrimitive.Root>
            <TooltipPrimitive.Trigger asChild>
                {children}
            </TooltipPrimitive.Trigger>
            <TooltipPrimitive.Portal>
                <TooltipPrimitive.Content
                    side={side}
                    sideOffset={sideOffset}
                    className={cn(
                        'z-50 max-w-xs animate-in rounded-xl bg-gold px-3.5 py-2.5 text-[0.6875rem] leading-relaxed font-semibold text-[var(--kora)] shadow-[0_14px_34px_rgba(0,29,78,0.28)] fade-in-0 zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                        className,
                    )}
                >
                    {content}
                    <TooltipPrimitive.Arrow className="fill-gold" />
                </TooltipPrimitive.Content>
            </TooltipPrimitive.Portal>
        </TooltipPrimitive.Root>
    );
}
