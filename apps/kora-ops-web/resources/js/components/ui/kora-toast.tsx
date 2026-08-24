import {
    CheckCircle2,
    CircleAlert,
    CircleX,
    Info,
    LoaderCircle,
    X,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';

function KoraToaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            position="bottom-right"
            closeButton
            duration={5000}
            gap={10}
            visibleToasts={4}
            containerAriaLabel="Notifications KORA"
            icons={{
                success: <CheckCircle2 aria-hidden="true" />,
                info: <Info aria-hidden="true" />,
                warning: <CircleAlert aria-hidden="true" />,
                error: <CircleX aria-hidden="true" />,
                loading: <LoaderCircle aria-hidden="true" />,
                close: <X aria-hidden="true" />,
            }}
            toastOptions={{
                unstyled: true,
                classNames: {
                    toast: 'kora-toast',
                    title: 'kora-toast-title',
                    description: 'kora-toast-description',
                    icon: 'kora-toast-icon',
                    loader: 'kora-toast-loader',
                    closeButton: 'kora-toast-close',
                    content: 'kora-toast-content',
                    success: 'kora-toast-success',
                    info: 'kora-toast-info',
                    warning: 'kora-toast-warning',
                    error: 'kora-toast-error',
                    loading: 'kora-toast-loading',
                },
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as CSSProperties
            }
            {...props}
        />
    );
}

export { KoraToaster };
