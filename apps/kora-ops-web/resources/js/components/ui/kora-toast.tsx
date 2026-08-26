import type { CSSProperties } from 'react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';

function KoraToaster({ ...props }: ToasterProps) {
    const { resolvedAppearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={resolvedAppearance}
            position="bottom-right"
            closeButton
            duration={5000}
            gap={10}
            visibleToasts={4}
            containerAriaLabel="Notifications KORA"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--success-bg': 'var(--popover)',
                    '--success-text': 'var(--popover-foreground)',
                    '--success-border': 'color-mix(in srgb, var(--digital) 30%, var(--border))',
                    '--info-bg': 'var(--popover)',
                    '--info-text': 'var(--popover-foreground)',
                    '--info-border': 'color-mix(in srgb, var(--digital) 30%, var(--border))',
                    '--warning-bg': 'var(--popover)',
                    '--warning-text': 'var(--popover-foreground)',
                    '--warning-border': 'color-mix(in srgb, var(--gold) 30%, var(--border))',
                    '--error-bg': 'var(--popover)',
                    '--error-text': 'var(--popover-foreground)',
                    '--error-border': 'color-mix(in srgb, var(--destructive) 30%, var(--border))',
                } as CSSProperties
            }
            {...props}
        />
    );
}

export { KoraToaster };
