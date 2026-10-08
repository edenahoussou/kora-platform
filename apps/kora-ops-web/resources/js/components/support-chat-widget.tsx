import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    isSupportChatHidden,
    onSupportChatVisibilityChange,
} from '@/lib/support-chat';

declare global {
    interface Window {
        Tawk_API?: {
            onLoad?: () => void;
            hideWidget?: () => void;
            showWidget?: () => void;
            maximize?: () => void;
            setAttributes?: (
                attributes: Record<string, string>,
                callback?: (error: unknown) => void,
            ) => void;
        };
        Tawk_LoadStart?: Date;
    }
}

/**
 * Injecte le widget tawk.to lorsque l'intégration est configurée
 * dans Paramètres → Intégrations → Support client. L'utilisateur
 * peut masquer la bulle depuis le menu Aide du topbar.
 */
export function SupportChatWidget() {
    const { auth, supportChat } = usePage().props;
    const user = auth?.user;

    useEffect(() => {
        if (
            !supportChat?.enabled ||
            !supportChat.propertyId ||
            !supportChat.widgetId
        ) {
            return;
        }

        const embedUrl = `https://embed.tawk.to/${supportChat.propertyId}/${supportChat.widgetId}`;
        const unsubscribe = onSupportChatVisibilityChange((hidden) => {
            if (hidden) {
                window.Tawk_API?.hideWidget?.();
            } else {
                window.Tawk_API?.showWidget?.();
                window.Tawk_API?.maximize?.();
            }
        });

        if (document.querySelector(`script[src="${embedUrl}"]`)) {
            return unsubscribe;
        }

        window.Tawk_API = window.Tawk_API ?? {};
        window.Tawk_LoadStart = new Date();
        window.Tawk_API.onLoad = () => {
            if (user?.name || user?.email) {
                window.Tawk_API?.setAttributes?.(
                    {
                        name: user.name ?? '',
                        email: user.email ?? '',
                        ...(supportChat.visitorHash
                            ? { hash: supportChat.visitorHash }
                            : {}),
                    },
                    () => undefined,
                );
            }

            if (isSupportChatHidden()) {
                window.Tawk_API?.hideWidget?.();
            }
        };

        const script = document.createElement('script');
        script.async = true;
        script.src = embedUrl;
        script.charset = 'UTF-8';
        script.setAttribute('crossorigin', '*');
        document.body.appendChild(script);

        return unsubscribe;
    }, [
        supportChat?.enabled,
        supportChat?.propertyId,
        supportChat?.widgetId,
        supportChat?.visitorHash,
        user?.name,
        user?.email,
    ]);

    return null;
}
