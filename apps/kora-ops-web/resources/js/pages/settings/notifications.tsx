import { Head } from '@inertiajs/react';
import type { FirebaseOptions } from 'firebase/app';
import { BellRing, BellOff, LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { getOpsMessaging } from '@/lib/firebase-messaging';
import type { OpsFirebaseConfig } from '@/lib/firebase-messaging';
import { webPushConfiguration } from '@/routes/api/v1/notifications';
import { notifications as notificationsSettingsRoute } from '@/routes/settings';
import { update as updatePushPreference } from '@/routes/settings/notifications/push';

type ConfigResponse = {
    configured: boolean;
    firebase: OpsFirebaseConfig | null;
};

function cookie(name: string): string | null {
    const value = document.cookie
        .split('; ')
        .find((part) => part.startsWith(`${name}=`))
        ?.split('=')
        .slice(1)
        .join('=');

    return value ? decodeURIComponent(value) : null;
}

function browserFingerprint(): string {
    const key = 'kora-ops-web-push-device';
    let fingerprint = window.localStorage.getItem(key);

    if (!fingerprint) {
        fingerprint = crypto.randomUUID();
        window.localStorage.setItem(key, fingerprint);
    }

    return fingerprint;
}

export default function NotificationsSettings() {
    const [config, setConfig] = useState<ConfigResponse | null>(null);
    const [permission, setPermission] = useState<
        NotificationPermission | 'unsupported'
    >(() =>
        typeof window === 'undefined' ||
        !('Notification' in window) ||
        !('serviceWorker' in navigator)
            ? 'unsupported'
            : Notification.permission,
    );
    const [enabled, setEnabled] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.localStorage.getItem('kora-ops-web-push-enabled') === 'true',
    );
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        void fetch(webPushConfiguration.url(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(async (response) => {
                if (response.ok) {
                    setConfig((await response.json()) as ConfigResponse);
                } else {
                    setConfig({ configured: false, firebase: null });
                }
            })
            .catch(() => setConfig({ configured: false, firebase: null }));
    }, []);

    const saveToken = async (token: string | null) => {
        const response = await fetch(updatePushPreference.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': cookie('XSRF-TOKEN') ?? '',
            },
            body: JSON.stringify({
                device_fingerprint: browserFingerprint(),
                device_name:
                    navigator.userAgent.slice(0, 120) || 'Navigateur Web',
                push_token: token,
            }),
        });

        if (!response.ok) {
            throw new Error('Échec de mise à jour de votre abonnement push.');
        }
    };

    const enable = async () => {
        if (!config?.configured || !config.firebase) {
            return;
        }

        setBusy(true);
        setMessage('');

        try {
            const result = await Notification.requestPermission();
            setPermission(result);

            if (result !== 'granted') {
                setMessage(
                    result === 'denied'
                        ? 'Les notifications sont bloquées dans les réglages de votre navigateur.'
                        : 'Autorisez les notifications pour activer les alertes push.',
                );

                return;
            }

            const workerConfig: FirebaseOptions = {
                apiKey: config.firebase.apiKey,
                authDomain: config.firebase.authDomain,
                projectId: config.firebase.projectId,
                storageBucket: config.firebase.storageBucket,
                messagingSenderId: config.firebase.messagingSenderId,
                appId: config.firebase.appId,
            };
            const workerUrl = `/firebase-messaging-sw.js?firebaseConfig=${encodeURIComponent(JSON.stringify(workerConfig))}`;
            const registration =
                await navigator.serviceWorker.register(workerUrl);
            const messaging = await getOpsMessaging(config.firebase);

            if (!messaging) {
                throw new Error(
                    'Ce navigateur ne prend pas en charge les notifications push.',
                );
            }

            const { getToken } = await import('firebase/messaging');
            const token = await getToken(messaging, {
                vapidKey: config.firebase.vapidKey,
                serviceWorkerRegistration: registration,
            });

            if (!token) {
                throw new Error(
                    'Firebase n’a pas pu créer un abonnement pour ce navigateur.',
                );
            }

            await saveToken(token);
            window.localStorage.setItem('kora-ops-web-push-enabled', 'true');
            setEnabled(true);
            setMessage(
                'Les notifications push sont activées sur ce navigateur.',
            );
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'Impossible d’activer les notifications push.',
            );
        } finally {
            setBusy(false);
        }
    };

    const disable = async () => {
        setBusy(true);
        setMessage('');

        try {
            const messaging = config?.firebase
                ? await getOpsMessaging(config.firebase)
                : null;

            if (messaging) {
                const { deleteToken } = await import('firebase/messaging');
                await deleteToken(messaging);
            }

            await saveToken(null);
            window.localStorage.removeItem('kora-ops-web-push-enabled');
            setEnabled(false);
            setMessage(
                'Les notifications push ont été désactivées sur ce navigateur.',
            );
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'Impossible de désactiver les notifications push.',
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title="Notifications — Paramètres" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notifications"
                    description="Choisissez si ce navigateur peut recevoir les alertes OPS en push."
                />
                <Card>
                    <CardContent className="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div className="flex gap-4">
                            <span
                                className={`grid size-12 shrink-0 place-items-center rounded-xl ${enabled ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'}`}
                            >
                                {enabled ? (
                                    <BellRing className="size-5" />
                                ) : (
                                    <BellOff className="size-5" />
                                )}
                            </span>
                            <div>
                                <p className="font-semibold">
                                    Alertes push sur ce navigateur
                                </p>
                                <p className="mt-1 max-w-lg text-sm text-muted-foreground">
                                    Les alertes critiques et opérationnelles
                                    compatibles peuvent s’afficher même lorsque
                                    KORA OPS n’est pas au premier plan.
                                    L’historique reste disponible dans la
                                    cloche.
                                </p>
                            </div>
                        </div>
                        {enabled ? (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => void disable()}
                            >
                                {busy && (
                                    <LoaderCircle className="mr-2 size-4 animate-spin" />
                                )}
                                Désactiver
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                disabled={
                                    busy ||
                                    !config?.configured ||
                                    permission === 'unsupported' ||
                                    permission === 'denied'
                                }
                                onClick={() => void enable()}
                            >
                                {busy && (
                                    <LoaderCircle className="mr-2 size-4 animate-spin" />
                                )}
                                Activer les notifications push
                            </Button>
                        )}
                    </CardContent>
                </Card>
                {!config && (
                    <p className="text-sm text-muted-foreground">
                        Vérification de la configuration Firebase…
                    </p>
                )}
                {config && !config.configured && (
                    <p
                        role="status"
                        className="rounded-lg border border-border bg-muted/50 p-4 text-sm text-muted-foreground"
                    >
                        Les notifications push ne sont pas configurées pour cet
                        environnement. Le centre de notifications reste
                        disponible.
                    </p>
                )}
                {permission === 'denied' && (
                    <p
                        role="status"
                        className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-4 text-sm text-amber-800 dark:text-amber-300"
                    >
                        L’autorisation a été refusée dans le navigateur.
                        Modifiez ce choix dans les paramètres du site pour
                        pouvoir activer les alertes.
                    </p>
                )}
                {message && (
                    <p role="status" className="text-sm text-muted-foreground">
                        {message}
                    </p>
                )}
            </div>
        </>
    );
}

NotificationsSettings.layout = {
    breadcrumbs: [
        { title: 'Notifications', href: notificationsSettingsRoute() },
    ],
};
