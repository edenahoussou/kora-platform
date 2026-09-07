import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, MapPinned } from 'lucide-react';
import Heading from '@/components/heading';
import mapSettings from '@/routes/network/map-settings';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    envDefaultProvider: string;
    adminOverrideProvider: string | null;
    resolvedProvider: string;
    providers: Record<string, string>;
    googleConfigured: boolean;
};

export default function MapSettingsPage({
    envDefaultProvider,
    adminOverrideProvider,
    resolvedProvider,
    providers,
    googleConfigured,
}: PageProps) {
    const form = useForm<{ provider: string | null }>({
        provider: adminOverrideProvider,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(mapSettings.update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Fournisseur cartographique" />

            <div className="mb-6">
                <Heading
                    title="Fournisseur cartographique"
                    description="Choisit le fournisseur utilisé pour positionner les arrêts sur la carte (lignes & arrêts). OpenStreetMap est le défaut sans configuration."
                />
            </div>

            <form onSubmit={submit} className="grid max-w-xl gap-5">
                <div className="grid gap-3">
                    {Object.entries(providers).map(([value, label]) => {
                        const disabled =
                            value === 'google' && !googleConfigured;
                        const selected =
                            (form.data.provider ?? envDefaultProvider) ===
                            value;

                        return (
                            <label
                                key={value}
                                className={
                                    'map-picker-point' +
                                    (selected ? ' map-picker-point-active' : '')
                                }
                                style={{
                                    gridTemplateColumns: '20px 1fr auto',
                                    opacity: disabled ? 0.55 : 1,
                                    cursor: disabled
                                        ? 'not-allowed'
                                        : 'pointer',
                                }}
                            >
                                <input
                                    type="radio"
                                    name="provider"
                                    value={value}
                                    checked={selected}
                                    disabled={disabled}
                                    onChange={() =>
                                        form.setData('provider', value)
                                    }
                                />
                                <span className="map-picker-point-label">
                                    {label}
                                    {value === envDefaultProvider && (
                                        <em className="ml-2 text-[9px] font-normal text-muted-foreground not-italic">
                                            (défaut .env)
                                        </em>
                                    )}
                                    {disabled && (
                                        <span className="block text-[9px] font-normal text-muted-foreground">
                                            MAP_GOOGLE_MAPS_API_KEY non
                                            configurée
                                        </span>
                                    )}
                                </span>
                                {selected && (
                                    <CheckCircle2 className="size-4 text-[var(--kora)]" />
                                )}
                            </label>
                        );
                    })}

                    <button
                        type="button"
                        className="btn btn-ghost justify-self-start"
                        onClick={() => form.setData('provider', null)}
                        disabled={form.data.provider === null}
                    >
                        Revenir au défaut .env ({envDefaultProvider})
                    </button>
                </div>

                <p className="map-picker-hint">
                    <MapPinned className="size-3.5" />
                    Fournisseur actuellement actif :{' '}
                    <strong>{providers[resolvedProvider]}</strong>
                </p>

                <div className="flex gap-3">
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Enregistrement…' : 'Enregistrer'}
                    </button>
                </div>
            </form>
        </>
    );
}

MapSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Lignes & arrêts', href: '/network' } satisfies BreadcrumbItem,
        {
            title: 'Fournisseur cartographique',
            href: '/network/settings/map',
        } satisfies BreadcrumbItem,
    ],
};
