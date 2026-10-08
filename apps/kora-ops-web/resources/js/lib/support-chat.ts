const HIDDEN_KEY = 'kora-support-chat-hidden';
const VISIBILITY_EVENT = 'kora:support-chat-visibility';

export function isSupportChatHidden(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(HIDDEN_KEY) === 'true';
}

export function setSupportChatHidden(hidden: boolean): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(HIDDEN_KEY, String(hidden));
    window.dispatchEvent(
        new CustomEvent(VISIBILITY_EVENT, { detail: { hidden } }),
    );
}

export function onSupportChatVisibilityChange(
    listener: (hidden: boolean) => void,
): () => void {
    const handler = (event: Event) => {
        listener(
            (event as CustomEvent<{ hidden: boolean }>).detail?.hidden ?? false,
        );
    };

    window.addEventListener(VISIBILITY_EVENT, handler);

    return () => window.removeEventListener(VISIBILITY_EVENT, handler);
}
