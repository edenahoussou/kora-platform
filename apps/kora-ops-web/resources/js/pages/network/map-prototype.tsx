import { Head, usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import type { MapPoint } from '@/components/network/map-picker';
import { MapPicker } from '@/components/network/map-picker';
import { edit as editMapSettings } from '@/routes/network/map-settings';
import type { BreadcrumbItem } from '@/types';

const initialPoints: MapPoint[] = [
    {
        id: 'cotonou',
        label: 'Cotonou (origine)',
        latitude: 6.3703,
        longitude: 2.3912,
    },
    { id: 'porto-novo', label: 'Porto-Novo', latitude: null, longitude: null },
    { id: 'lome', label: 'Lomé', latitude: null, longitude: null },
    { id: 'accra', label: 'Accra', latitude: null, longitude: null },
    { id: 'abidjan', label: 'Abidjan', latitude: null, longitude: null },
];

/**
 * Prototype visuel pour l'étude d'intégration cartographique (ticket
 * "Étudier l'intégration cartographique des lignes et arrêts"). Ne persiste
 * rien côté serveur — sert uniquement à valider l'UX de positionnement des
 * arrêts avant de la brancher sur le vrai formulaire ligne/arrêt.
 */
export default function MapPrototypePage() {
    const { mapConfig } = usePage().props;
    const [points, setPoints] = useState<MapPoint[]>(initialPoints);

    return (
        <>
            <Head title="Carte — prototype" />

            <div className="mb-6">
                <Heading
                    title="Prototype carte lignes & arrêts"
                    description="Démonstration non persistée : positionnement des arrêts, trajectoire, distances calculées depuis l'origine."
                />
            </div>

            <p className="map-picker-hint mb-4">
                <Info className="size-3.5" />
                Fournisseur actif : <strong>{mapConfig.provider}</strong> — à
                changer dans{' '}
                <a
                    href={editMapSettings.url()}
                    className="font-semibold text-[var(--kora)] underline"
                >
                    Paramètres carte
                </a>
                .
            </p>

            <MapPicker
                points={points}
                onPointsChange={setPoints}
                provider={mapConfig.provider}
                osmTileUrl={mapConfig.osm.tileUrl}
                osmAttribution={mapConfig.osm.attribution}
                googleApiKey={mapConfig.google.apiKey}
            />
        </>
    );
}

MapPrototypePage.layout = {
    breadcrumbs: [
        { title: 'Lignes & arrêts', href: '/network' } satisfies BreadcrumbItem,
        {
            title: 'Prototype carte',
            href: '/network/map-prototype',
        } satisfies BreadcrumbItem,
    ],
};
