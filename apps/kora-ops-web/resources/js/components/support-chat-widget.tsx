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
            visitor?: {
                name: string;
                email: string;
                phone?: string;
                hash?: string;
            };
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
    const { supportChat } = usePage().props;
    const visitor = supportChat?.visitor;

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

        // Renseigné avant le téléchargement du script : tawk.to
        // transmet l'identité au dashboard même sans Secure Mode.
        if (visitor?.name || visitor?.email) {
            window.Tawk_API.visitor = {
                name: visitor.name ?? '',
                email: visitor.email ?? '',
                ...(visitor.phone ? { phone: visitor.phone } : {}),
                ...(supportChat.visitorHash
                    ? { hash: supportChat.visitorHash }
                    : {}),
            };
        }

        window.Tawk_API.onLoad = () => {
            const attributes: Record<string, string> = {};

            if (visitor?.phone) {
                attributes['telephone'] = visitor.phone;
            }

            if (visitor?.jobTitle) {
                attributes['poste'] = visitor.jobTitle;
            }

            if (visitor?.agency) {
                attributes['agence'] = visitor.agency;
            }

            if (visitor?.role) {
                attributes['role'] = visitor.role;
            }

            if (Object.keys(attributes).length > 0) {
                window.Tawk_API?.setAttributes?.(attributes, (error) => {
                    if (error) {
                        console.warn('[tawk.to] setAttributes', error);
                    }
                });
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
        visitor?.name,
        visitor?.email,
        visitor?.phone,
        visitor?.jobTitle,
        visitor?.agency,
        visitor?.role,
    ]);

    return null;
}
