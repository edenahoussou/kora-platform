import { router } from '@inertiajs/react';
import {
    Building2,
    Globe2,
    Loader2,
    MapPin,
    Plus,
    Save,
    Search,
    X,
} from 'lucide-react';
import { lazy, Suspense, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PlaceResult } from '../network/route-calculator';
import { searchPlaces } from '../network/route-calculator';

const StopMapPicker = lazy(() =>
    import('./stop-map-picker').then((module) => ({
        default: module.StopMapPicker,
    })),
);

export type PhysicalStopItem = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    country: string;
    latitude: number | null;
    longitude: number | null;
    status: string;
    stopsCount: number;
    lines: Array<{ uuid: string; code: string; name: string }>;
    updatedAt: string | null;
};

type StopEditorModalProps = {
    open: boolean;
    stop: PhysicalStopItem | null;
    onClose: () => void;
    cities?: string[];
    mapSettings?: {
        osmTileUrl?: string;
        osmAttribution?: string;
    };
};

const COUNTRY_OPTIONS = [
    { code: 'BJ', label: 'Bénin (BJ)' },
    { code: 'TG', label: 'Togo (TG)' },
    { code: 'NG', label: 'Nigéria (NG)' },
    { code: 'NE', label: 'Niger (NE)' },
    { code: 'BF', label: 'Burkina Faso (BF)' },
    { code: 'CI', label: 'Côte d’Ivoire (CI)' },
    { code: 'GH', label: 'Ghana (GH)' },
];

function StopEditorForm({
    stop,
    onClose,
    cities = [],
    mapSettings,
}: {
    stop: PhysicalStopItem | null;
    onClose: () => void;
    cities?: string[];
    mapSettings?: {
        osmTileUrl?: string;
        osmAttribution?: string;
    };
}) {
    const isEditing = stop !== null;

    const [name, setName] = useState(stop?.name ?? '');
    const [city, setCity] = useState(stop?.city ?? '');
    const [customCity, setCustomCity] = useState(
        stop !== null && cities.length > 0 && !cities.includes(stop.city),
    );

    const [placeQuery, setPlaceQuery] = useState('');
    const [placeResults, setPlaceResults] = useState<PlaceResult[]>([]);
    const [placesLoading, setPlacesLoading] = useState(false);
    const [emptyNoticeVisible, setEmptyNoticeVisible] = useState(false);
    const placesTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const emptyNoticeTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );

    // Recherche de lieux OpenStreetMap débounced (politique Nominatim ~1 req/s)
    const handlePlaceQueryChange = (query: string) => {
        setPlaceQuery(query);
        const trimmed = query.trim();

        if (placesTimeoutRef.current) {
            clearTimeout(placesTimeoutRef.current);
        }

        if (emptyNoticeTimeoutRef.current) {
            clearTimeout(emptyNoticeTimeoutRef.current);
        }

        setEmptyNoticeVisible(false);

        if (trimmed.length < 3) {
            setPlaceResults([]);
            setPlacesLoading(false);

            return;
        }

        setPlacesLoading(true);
        placesTimeoutRef.current = setTimeout(async () => {
            try {
                const results = await searchPlaces(trimmed);

                setPlaceResults(results);

                // Message « aucun lieu » masqué automatiquement pour ne pas couvrir les champs
                if (results.length === 0) {
                    setEmptyNoticeVisible(true);
                    emptyNoticeTimeoutRef.current = setTimeout(() => {
                        setEmptyNoticeVisible(false);
                    }, 4500);
                }
            } catch {
                setPlaceResults([]);
            } finally {
                setPlacesLoading(false);
            }
        }, 600);
    };

    const handlePlaceSelect = (place: PlaceResult) => {
        setName(place.label);
        setCity(place.city);
        setCustomCity(!cities.includes(place.city));

        if (COUNTRY_OPTIONS.some((c) => c.code === place.country)) {
            setCountry(place.country);
        }

        setLatitude(place.latitude);
        setLongitude(place.longitude);
        setPlaceQuery('');
        setPlaceResults([]);
        setPlacesLoading(false);
        setEmptyNoticeVisible(false);
    };
    const [country, setCountry] = useState(stop?.country ?? 'BJ');
    const [latitude, setLatitude] = useState<number | null>(
        stop?.latitude ?? null,
    );
    const [longitude, setLongitude] = useState<number | null>(
        stop?.longitude ?? null,
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});

        const payload = {
            name: name.trim(),
            city: city.trim(),
            country,
            latitude,
            longitude,
        };

        if (isEditing && stop) {
            router.put(`/stops/${stop.uuid}`, payload, {
                preserveScroll: true,
                onError: (errs) => {
                    setSubmitting(false);
                    setErrors(errs);
                },
                onSuccess: () => {
                    setSubmitting(false);
                    onClose();
                },
            });
        } else {
            router.post('/stops', payload, {
                preserveScroll: true,
                onError: (errs) => {
                    setSubmitting(false);
                    setErrors(errs);
                },
                onSuccess: () => {
                    setSubmitting(false);
                    onClose();
                },
            });
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4 py-2">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5 sm:col-span-2">
                    <label
                        htmlFor="stop-name"
                        className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                    >
                        <MapPin className="size-3.5 text-muted-foreground" />
                        Nom du point d’arrêt
                        <span className="text-destructive">*</span>
                    </label>
                    <div className="relative">
                        <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <input
                            id="stop-name"
                            type="text"
                            required
                            autoComplete="off"
                            value={name}
                            onChange={(e) => {
                                setName(e.target.value);
                                handlePlaceQueryChange(e.target.value);
                            }}
                            placeholder="Ex : Gare Centrale Jonquet — la saisie lance une recherche sur la carte"
                            className="w-full rounded-xl border border-border bg-background py-2 pr-3 pl-9 text-sm text-foreground shadow-xs focus:border-gold focus:outline-hidden"
                        />
                        {placeQuery.trim().length >= 3 &&
                            (placesLoading ||
                                placeResults.length > 0 ||
                                emptyNoticeVisible) && (
                                <div className="absolute top-full right-0 left-0 z-[1000] mt-1 max-h-64 overflow-y-auto rounded-xl border border-border bg-card p-1 shadow-xl">
                                    {placesLoading && (
                                        <div className="flex items-center gap-2 px-3 py-2.5 text-xs text-muted-foreground">
                                            <Loader2 className="size-3.5 animate-spin" />
                                            Recherche en cours…
                                        </div>
                                    )}
                                    {!placesLoading &&
                                        placeResults.length === 0 && (
                                            <div className="px-3 py-2.5 text-xs text-muted-foreground">
                                                Aucun lieu trouvé. Votre saisie
                                                sera utilisée comme nom du
                                                point.
                                            </div>
                                        )}
                                    {placeResults.map((place, index) => (
                                        <button
                                            key={`${place.latitude}-${place.longitude}-${index}`}
                                            type="button"
                                            onClick={() =>
                                                handlePlaceSelect(place)
                                            }
                                            className="flex w-full items-center gap-2 rounded-lg p-2.5 text-left text-xs transition-colors hover:bg-muted"
                                        >
                                            <span className="grid size-6 shrink-0 place-items-center rounded-md bg-gold/20 text-[var(--kora)] dark:text-gold">
                                                <MapPin className="size-3.5" />
                                            </span>
                                            <div className="min-w-0">
                                                <strong className="block truncate font-semibold text-foreground">
                                                    {place.label}
                                                </strong>
                                                <span className="block truncate text-[0.6875rem] text-muted-foreground">
                                                    {place.detail || place.city}
                                                    , {place.country}
                                                </span>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                    </div>
                    {errors.name && (
                        <p className="text-xs font-semibold text-destructive">
                            {errors.name}
                        </p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <label
                        htmlFor="stop-city"
                        className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                    >
                        <Building2 className="size-3.5 text-muted-foreground" />
                        Ville
                        <span className="text-destructive">*</span>
                    </label>
                    {!customCity && cities.length > 0 ? (
                        <select
                            id="stop-city"
                            required
                            value={city}
                            onChange={(e) => {
                                if (e.target.value === '__new__') {
                                    setCustomCity(true);
                                    setCity('');
                                } else {
                                    setCity(e.target.value);
                                }
                            }}
                            className="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground shadow-xs focus:border-gold focus:outline-hidden"
                        >
                            <option value="" disabled>
                                Sélectionnez une ville…
                            </option>
                            {cities.map((c) => (
                                <option key={c} value={c}>
                                    {c}
                                </option>
                            ))}
                            <option value="__new__">
                                Autre ville (nouvelle)…
                            </option>
                        </select>
                    ) : (
                        <div className="space-y-1.5">
                            <input
                                id="stop-city"
                                type="text"
                                required
                                value={city}
                                onChange={(e) => setCity(e.target.value)}
                                placeholder="Ex : Cotonou"
                                className="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground shadow-xs focus:border-gold focus:outline-hidden"
                            />
                            {cities.length > 0 && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setCustomCity(false);
                                        setCity('');
                                    }}
                                    className="text-[0.6875rem] font-bold text-gold hover:underline"
                                >
                                    Choisir une ville existante
                                </button>
                            )}
                        </div>
                    )}
                    {errors.city && (
                        <p className="text-xs font-semibold text-destructive">
                            {errors.city}
                        </p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <label
                        htmlFor="stop-country"
                        className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                    >
                        <Globe2 className="size-3.5 text-muted-foreground" />
                        Pays
                        <span className="text-destructive">*</span>
                    </label>
                    <select
                        id="stop-country"
                        required
                        value={country}
                        onChange={(e) => setCountry(e.target.value)}
                        className="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground shadow-xs focus:border-gold focus:outline-hidden"
                    >
                        {COUNTRY_OPTIONS.map((c) => (
                            <option key={c.code} value={c.code}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                    {errors.country && (
                        <p className="text-xs font-semibold text-destructive">
                            {errors.country}
                        </p>
                    )}
                </div>
            </div>

            {/* Section Coordonnées GPS & Carte */}
            <div className="space-y-2 pt-2">
                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                        <MapPin className="size-3.5 text-gold" />
                        Positionnement GPS sur carte
                    </label>
                    <span className="text-[0.6875rem] text-muted-foreground">
                        Cliquez ou glissez le repère pour ajuster
                    </span>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            htmlFor="stop-lat"
                            className="text-[0.6875rem] font-bold text-muted-foreground"
                        >
                            Latitude
                        </label>
                        <input
                            id="stop-lat"
                            type="number"
                            step="any"
                            value={latitude ?? ''}
                            onChange={(e) =>
                                setLatitude(
                                    e.target.value === ''
                                        ? null
                                        : parseFloat(e.target.value),
                                )
                            }
                            placeholder="Ex : 6.370300"
                            className="w-full rounded-lg border border-border bg-background px-2.5 py-1.5 font-mono text-xs text-foreground focus:border-gold focus:outline-hidden"
                        />
                        {errors.latitude && (
                            <p className="mt-1 text-xs font-semibold text-destructive">
                                {errors.latitude}
                            </p>
                        )}
                    </div>
                    <div>
                        <label
                            htmlFor="stop-lng"
                            className="text-[0.6875rem] font-bold text-muted-foreground"
                        >
                            Longitude
                        </label>
                        <input
                            id="stop-lng"
                            type="number"
                            step="any"
                            value={longitude ?? ''}
                            onChange={(e) =>
                                setLongitude(
                                    e.target.value === ''
                                        ? null
                                        : parseFloat(e.target.value),
                                )
                            }
                            placeholder="Ex : 2.391200"
                            className="w-full rounded-lg border border-border bg-background px-2.5 py-1.5 font-mono text-xs text-foreground focus:border-gold focus:outline-hidden"
                        />
                        {errors.longitude && (
                            <p className="mt-1 text-xs font-semibold text-destructive">
                                {errors.longitude}
                            </p>
                        )}
                    </div>
                </div>

                <Suspense
                    fallback={
                        <div className="grid h-64 place-items-center rounded-xl border border-border bg-muted/20 text-xs text-muted-foreground">
                            Chargement de la carte…
                        </div>
                    }
                >
                    <StopMapPicker
                        latitude={latitude}
                        longitude={longitude}
                        onChange={(lat, lng) => {
                            setLatitude(lat);
                            setLongitude(lng);
                        }}
                        tileUrl={mapSettings?.osmTileUrl}
                        attribution={mapSettings?.osmAttribution}
                        height={280}
                    />
                </Suspense>
            </div>

            {Object.entries(errors)
                .filter(
                    ([key]) =>
                        ![
                            'name',
                            'city',
                            'country',
                            'latitude',
                            'longitude',
                        ].includes(key),
                )
                .map(([key, message]) => (
                    <p
                        key={key}
                        className="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs font-semibold text-destructive"
                    >
                        {message}
                    </p>
                ))}

            <DialogFooter className="gap-3 pt-4 sm:gap-3">
                <button
                    type="button"
                    className="btn btn-ghost"
                    disabled={submitting}
                    onClick={onClose}
                >
                    <X className="size-4" />
                    Annuler
                </button>
                <button
                    type="submit"
                    disabled={submitting || !name.trim() || !city.trim()}
                    className="btn btn-primary"
                >
                    {submitting ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : isEditing ? (
                        <Save className="size-4" />
                    ) : (
                        <Plus className="size-4" />
                    )}
                    {submitting
                        ? 'Enregistrement…'
                        : isEditing
                          ? 'Enregistrer les modifications'
                          : 'Créer le point d’arrêt'}
                </button>
            </DialogFooter>
        </form>
    );
}

export function StopEditorModal({
    open,
    stop,
    onClose,
    cities,
    mapSettings,
}: StopEditorModalProps) {
    const isEditing = stop !== null;

    return (
        <Dialog
            open={open}
            onOpenChange={(v) => {
                if (!v) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <MapPin className="size-5 text-gold" />
                        {isEditing
                            ? `Modifier le point d’arrêt ${stop.code}`
                            : 'Nouveau point d’arrêt'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Renommez le point d’arrêt ou repositionnez ses coordonnées GPS sur la carte. Les modifications se répercuteront sur toutes les lignes associées.'
                            : 'Définissez le nom, la ville et les coordonnées GPS exactes du nouveau point d’arrêt partagé.'}
                    </DialogDescription>
                </DialogHeader>

                <StopEditorForm
                    key={stop?.uuid ?? 'new'}
                    stop={stop}
                    onClose={onClose}
                    cities={cities}
                    mapSettings={mapSettings}
                />
            </DialogContent>
        </Dialog>
    );
}
