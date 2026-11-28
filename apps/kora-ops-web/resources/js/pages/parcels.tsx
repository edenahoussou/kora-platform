import { Head, usePage } from '@inertiajs/react';
import { CircleAlert, Loader2, PackagePlus } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { ParcelIntakeDialog } from '@/components/parcels/parcel-intake-dialog';
import { ParcelListPanel } from '@/components/parcels/parcel-list-panel';
import type {
    LaravelValidationErrors,
    ParcelConfiguration,
    ParcelConfigurationResponse,
    ParcelIntakePayload,
    ParcelIntakeResponse,
    ParcelIntakeResult,
    ParcelQuote,
} from '@/components/parcels/types';
import { configuration as configurationRoute } from '@/routes/parcels/data';
import type { BreadcrumbItem } from '@/types';

function xsrfToken(): string {
    const value = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';

    return decodeURIComponent(value);
}

function responseMessage(payload: unknown, fallback: string): string {
    if (
        payload !== null &&
        typeof payload === 'object' &&
        'message' in payload &&
        typeof payload.message === 'string'
    ) {
        return payload.message;
    }

    return fallback;
}

function validationErrors(payload: unknown): LaravelValidationErrors {
    if (
        payload !== null &&
        typeof payload === 'object' &&
        'errors' in payload &&
        payload.errors !== null &&
        typeof payload.errors === 'object'
    ) {
        return payload.errors as LaravelValidationErrors;
    }

    return {};
}

export default function ParcelsPage() {
    const { url } = usePage();
    const isKoraPoint = url.startsWith('/kora-point');
    const [configuration, setConfiguration] =
        useState<ParcelConfiguration | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [forbidden, setForbidden] = useState(false);
    const [intakeOpen, setIntakeOpen] = useState(false);
    const [creatingParcel, setCreatingParcel] = useState(false);
    const [intakeError, setIntakeError] = useState<string | null>(null);
    const [intakeErrors, setIntakeErrors] = useState<LaravelValidationErrors>(
        {},
    );
    const [parcelListRefresh, setParcelListRefresh] = useState(0);

    const loadConfiguration = async () => {
        setLoading(true);
        setLoadError(null);
        setForbidden(false);

        try {
            const response = await fetch(configurationRoute.url(), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const payload = (await response
                .json()
                .catch(() => null)) as ParcelConfigurationResponse | null;

            if (!response.ok || payload === null) {
                setForbidden(response.status === 403);
                setLoadError(
                    responseMessage(
                        payload,
                        'Impossible de charger les lieux et corridors utiles au guichet.',
                    ),
                );

                return;
            }

            setConfiguration(payload.data);
        } catch {
            setLoadError(
                'Le réseau est instable. Les données du guichet Colis n’ont pas pu être chargées.',
            );
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        // Initial server-state hydration; failures are stored for the UI.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        void loadConfiguration();
    }, []);

    const createParcel = async (
        payload: ParcelIntakePayload,
    ): Promise<ParcelIntakeResult | null> => {
        setCreatingParcel(true);
        setIntakeError(null);
        setIntakeErrors({});

        try {
            const response = await fetch('/parcels/data/intake', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(payload),
            });
            const body = (await response
                .json()
                .catch(() => null)) as ParcelIntakeResponse | null;

            if (!response.ok || body === null) {
                setIntakeErrors(validationErrors(body));
                setIntakeError(
                    responseMessage(
                        body,
                        'Le colis n’a pas été créé. Vérifiez les données et la capacité du départ.',
                    ),
                );

                return null;
            }

            toast.success(`Colis ${body.data.reference} créé.`);
            setParcelListRefresh((current) => current + 1);

            return body.data;
        } catch {
            setIntakeError(
                'Le réseau est instable. Le colis n’a pas été créé.',
            );

            return null;
        } finally {
            setCreatingParcel(false);
        }
    };

    const quoteParcel = useCallback(
        async (payload: {
            deposit_hub_uuid: string;
            destination_hub_uuid: string;
            weight_grams: number;
        }): Promise<ParcelQuote | null> => {
            try {
                const response = await fetch('/parcels/data/quote', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify(payload),
                });
                const body = (await response.json().catch(() => null)) as {
                    data?: ParcelQuote;
                } | null;

                if (!response.ok || body?.data === undefined) {
                    return null;
                }

                return body.data;
            } catch {
                return null;
            }
        },
        [],
    );

    const openIntake = () => {
        setIntakeError(null);
        setIntakeErrors({});
        setIntakeOpen(true);
    };

    const hubs = configuration?.hubs ?? [];
    const corridors = configuration?.corridors ?? [];
    const stops = configuration?.options.stops ?? [];
    const canIntake = configuration?.permissions.canIntake === true;

    return (
        <>
            <Head title="Colis — Guichet" />

            <div className="cash-page">
                <div className="kora-page-head mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 flex items-center gap-1.5 text-[0.6875rem] text-muted-foreground">
                            <span>KORA OPS</span>
                            <span>/</span>
                            <span>{isKoraPoint ? 'KORA POINT' : 'Vente'}</span>
                            <span>/</span>
                            <span className="font-bold text-foreground">
                                Colis
                            </span>
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Enregistrement des colis
                        </h1>
                        <p className="mt-1.5 max-w-2xl text-[0.8125rem] leading-6 text-muted-foreground">
                            Enregistrez, encaissez et suivez les colis de votre
                            périmètre. Les actions de réception et de remise
                            restent contrôlées par le serveur.
                        </p>
                    </div>

                    {canIntake && (
                        <ParcelButton
                            type="button"
                            variant="primary"
                            onClick={openIntake}
                        >
                            <PackagePlus className="size-4" />
                            Créer un colis
                        </ParcelButton>
                    )}
                </div>

                {loading ? (
                    <div className="grid min-h-[16rem] place-items-center rounded-[18px] border border-border bg-card">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            Chargement du guichet Colis…
                        </div>
                    </div>
                ) : forbidden ? (
                    <div className="rounded-[18px] border border-amber-300/70 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                        <div className="flex items-center gap-2 font-bold">
                            <CircleAlert className="size-4" />
                            Périmètre Colis non autorisé
                        </div>
                        <p className="mt-1.5">
                            Votre compte ne dispose pas de l’accès opérationnel
                            Colis. Contactez l’administration.
                        </p>
                    </div>
                ) : loadError ? (
                    <div className="rounded-[18px] border border-destructive/30 bg-destructive/10 px-5 py-4 text-sm text-destructive">
                        {loadError}
                    </div>
                ) : (
                    <ParcelListPanel
                        hubs={hubs}
                        corridors={corridors}
                        canIntake={canIntake}
                        refreshKey={parcelListRefresh}
                        onCreate={openIntake}
                    />
                )}
            </div>

            <ParcelIntakeDialog
                open={intakeOpen}
                onOpenChange={(open) => {
                    if (!creatingParcel) {
                        setIntakeOpen(open);

                        if (!open) {
                            setIntakeError(null);
                            setIntakeErrors({});
                        }
                    }
                }}
                hubs={hubs}
                corridors={corridors}
                stops={stops}
                busy={creatingParcel}
                generalError={intakeError}
                validationErrors={intakeErrors}
                onCreate={createParcel}
                onQuote={quoteParcel}
            />
        </>
    );
}

ParcelsPage.layout = {
    breadcrumbs: [
        {
            title: 'Vente',
            href: '/sale',
        },
        {
            title: 'Colis',
            href: '/parcels',
        } satisfies BreadcrumbItem,
    ],
};
