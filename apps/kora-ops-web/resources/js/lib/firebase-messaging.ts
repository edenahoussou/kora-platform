import { getApp, getApps, initializeApp } from 'firebase/app';
import type { FirebaseOptions } from 'firebase/app';
import { getMessaging, isSupported, onMessage } from 'firebase/messaging';
import type { Messaging } from 'firebase/messaging';

export type OpsFirebaseConfig = FirebaseOptions & { vapidKey: string };

let messagingPromise: Promise<Messaging | null> | null = null;

export async function getOpsMessaging(
    config: OpsFirebaseConfig,
): Promise<Messaging | null> {
    if (!(await isSupported())) {
        return null;
    }

    messagingPromise ??= Promise.resolve(
        getApps().length ? getApp() : initializeApp(config),
    ).then((app) => getMessaging(app));

    return messagingPromise;
}

export function listenForOpsPush(
    messaging: Messaging,
    onNotification: () => void,
): () => void {
    return onMessage(messaging, () => onNotification());
}
