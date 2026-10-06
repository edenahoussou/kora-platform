import { Form } from '@inertiajs/react';
import {
    Activity,
    BusFront,
    Check,
    Layers,
    Sparkles,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { BusAmenityPicker } from '@/components/buses/bus-amenity-picker';
import { cn } from '@/lib/utils';
import fleet from '@/routes/fleet';

export type BusRecord = {
    uuid: string;
    registrationNumber: string;
    modelId: number | null;
    modelName: string | null;
    compositionId: number | null;
    compositionName: string | null;
    seatCount: number;
    amenityIds: number[];
    amenityLabels?: string[];
    status: string;
};

type Option = { id: number | string; label: string };

type AmenityOption = {
    id: number;
    code: string;
    label: string;
    icon: string | null;
};

type Props = {
    open: boolean;
    bus?: BusRecord | null;
    statuses: Record<string, string>;
    models: Option[];
    compositions: (Option & { seatCount: number })[];
    amenities: AmenityOption[];
    onOpenChange: (open: boolean) => void;
};

const labels: Record<string, string> = {
    active: 'Actif',
    draft: 'Brouillon',
    maintenance: 'En maintenance',
    retired: 'Retiré',
};

export function BusFormDialog({
    open,
    bus,
    statuses,
    models,
    compositions,
    amenities,
    onOpenChange,
}: Props) {
    const editing = Boolean(bus);
    const form = editing
        ? fleet.update.form({ bus: bus!.uuid })
        : fleet.store.form();

    const [selectedAmenityIds, setSelectedAmenityIds] = useState<number[]>(
        bus?.amenityIds ?? [],
    );
    const [createStatus, setCreateStatus] = useState<'active' | 'draft'>('active');
    const [showAmenities, setShowAmenities] = useState(true);

    if (!open) {
        return null;
    }

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) =>
                e.target === e.currentTarget && onOpenChange(false)
            }
        >
            <Form
                key={bus?.uuid ?? 'new'}
                {...form}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() => onOpenChange(false)}
                className="cash-modal cash-opening-modal w-[54rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="bus-modal-title"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    <BusFront className="size-5" />
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="section-kicker">
                                            Référentiel · Flotte
                                        </span>
                                        {editing &&
                                            bus?.registrationNumber && (
                                                <span className="rounded bg-gold/15 px-2 py-0.5 font-mono text-[11px] font-bold text-[var(--kora)]">
                                                    {bus.registrationNumber}
                                                </span>
                                            )}
                                    </div>
                                    <h3 id="bus-modal-title">
                                        {editing
                                            ? 'Modifier le bus'
                                            : 'Nouveau bus'}
                                    </h3>
                                    <p>
                                        Identifiez le véhicule, sa composition
                                        et ses commodités à bord.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => onOpenChange(false)}
                                className="cash-modal-close"
                                aria-label="Fermer"
                                disabled={processing}
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="cash-opening-list">
                            {/* Row 1 : Identification & Gabarit */}
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Layers className="size-4" />
                                    </span>
                                    <div>
                                        <strong>
                                            Identification & Gabarit
                                        </strong>
                                        <small>
                                            Immatriculation, modèle constructeur
                                            et capacité passagers
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <label className="field">
                                        <span>
                                            Immatriculation{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            className="control font-mono font-bold uppercase"
                                            name="registration_number"
                                            defaultValue={
                                                bus?.registrationNumber ?? ''
                                            }
                                            required
                                            placeholder="ex: BJ 9254 RB"
                                            aria-invalid={
                                                !!errors.registration_number
                                            }
                                        />
                                        {errors.registration_number ? (
                                            <small className="field-error-text">
                                                {errors.registration_number}
                                            </small>
                                        ) : (
                                            <small>
                                                Numéro officiel de la plaque.
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>
                                            Modèle constructeur{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <select
                                            className="control"
                                            name="bus_model_id"
                                            defaultValue={bus?.modelId ?? ''}
                                            required
                                            aria-invalid={!!errors.bus_model_id}
                                        >
                                            <option value="">
                                                Sélectionner un modèle…
                                            </option>
                                            {models.map((m) => (
                                                <option key={m.id} value={m.id}>
                                                    {m.label}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.bus_model_id ? (
                                            <small className="field-error-text">
                                                {errors.bus_model_id}
                                            </small>
                                        ) : (
                                            <small>
                                                Marque et modèle technique.
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>
                                            Gabarit & Sièges{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <select
                                            className="control"
                                            name="composition_id"
                                            defaultValue={
                                                bus?.compositionId ?? ''
                                            }
                                            required
                                            aria-invalid={
                                                !!errors.composition_id
                                            }
                                        >
                                            <option value="">
                                                Sélectionner un gabarit…
                                            </option>
                                            {compositions.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.label} · {c.seatCount}{' '}
                                                    places
                                                </option>
                                            ))}
                                        </select>
                                        {errors.composition_id ? (
                                            <small className="field-error-text">
                                                {errors.composition_id}
                                            </small>
                                        ) : (
                                            <small>
                                                Plan de sièges et capacité.
                                            </small>
                                        )}
                                    </label>
                                </div>
                            </div>

                            {/* Row 2 : Statut opérationnel avec interrupteur / toggle */}
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Activity className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Statut opérationnel</strong>
                                        <small>
                                            Disponibilité du bus pour les
                                            départs et rotations
                                        </small>
                                    </div>
                                </div>

                                {editing ? (
                                    <label className="field">
                                        <span>
                                            Statut{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <select
                                            className="control"
                                            name="status"
                                            defaultValue={
                                                bus?.status ?? 'active'
                                            }
                                            required
                                        >
                                            {Object.entries(statuses).map(
                                                ([val, lbl]) => (
                                                    <option
                                                        key={val}
                                                        value={val}
                                                    >
                                                        {labels[val] ?? lbl}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </label>
                                ) : (
                                    <div>
                                        <input
                                            type="hidden"
                                            name="status"
                                            value={createStatus}
                                        />
                                        <div className="flex items-center justify-between rounded-xl border border-border bg-card p-3">
                                            <div className="space-y-0.5">
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold transition-colors',
                                                            createStatus ===
                                                                'active'
                                                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                                                : 'bg-gold/15 text-[var(--kora)] dark:text-gold',
                                                        )}
                                                    >
                                                        {createStatus ===
                                                            'active'
                                                            ? 'Actif'
                                                            : 'Brouillon'}
                                                    </span>
                                                    <span className="text-xs font-semibold text-foreground">
                                                        {createStatus ===
                                                            'active'
                                                            ? 'Disponible pour exploitation immédiate'
                                                            : 'Enregistrement en préparation (non affecté)'}
                                                    </span>
                                                </div>
                                                <small className="block text-[11px] text-muted-foreground">
                                                    {createStatus === 'active'
                                                        ? 'Le bus pourra être immédiatement affecté à des programmations de départ.'
                                                        : 'Le bus est enregistré sans être affecté aux départs en vente.'}
                                                </small>
                                            </div>
                                            <button
                                                type="button"
                                                role="switch"
                                                aria-checked={
                                                    createStatus === 'active'
                                                }
                                                onClick={() =>
                                                    setCreateStatus((s) =>
                                                        s === 'active'
                                                            ? 'draft'
                                                            : 'active',
                                                    )
                                                }
                                                className={cn(
                                                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:ring-2 focus-visible:ring-gold/50 focus-visible:outline-none',
                                                    createStatus === 'active'
                                                        ? 'bg-emerald-500'
                                                        : 'bg-muted-foreground/30',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'pointer-events-none inline-block size-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out',
                                                        createStatus ===
                                                            'active'
                                                            ? 'translate-x-5'
                                                            : 'translate-x-0',
                                                    )}
                                                />
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Row 3 : Commodités avec TOGGLE pour afficher / masquer */}
                            {amenities.length > 0 && (
                                <div className="cash-opening-row">
                                    <div className="flex items-center justify-between">
                                        <div className="cash-opening-row-head">
                                            <span className="cash-opening-icon">
                                                <Sparkles className="size-4" />
                                            </span>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <strong>
                                                        Équipements & commodités
                                                        à bord
                                                    </strong>
                                                    <span className="rounded bg-gold/15 px-2 py-0.5 font-mono text-[10px] font-bold text-[var(--kora)]">
                                                        {
                                                            selectedAmenityIds.length
                                                        }{' '}
                                                        sélectionné
                                                        {selectedAmenityIds.length >
                                                            1
                                                            ? 's'
                                                            : ''}
                                                    </span>
                                                </div>
                                                <small>
                                                    Climatisation, Wi-Fi, prises
                                                    électriques, écran TV, etc.
                                                </small>
                                            </div>
                                        </div>

                                        {/* Toggle switch to expand / collapse amenities */}
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-semibold text-muted-foreground">
                                                {showAmenities
                                                    ? 'Déplié'
                                                    : 'Replié'}
                                            </span>
                                            <button
                                                type="button"
                                                role="switch"
                                                aria-checked={showAmenities}
                                                onClick={() =>
                                                    setShowAmenities((v) => !v)
                                                }
                                                className={cn(
                                                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:ring-2 focus-visible:ring-gold/50 focus-visible:outline-none',
                                                    showAmenities
                                                        ? 'bg-gold'
                                                        : 'bg-muted-foreground/30',
                                                )}
                                                aria-label="Afficher ou masquer les commodités"
                                            >
                                                <span
                                                    className={cn(
                                                        'pointer-events-none inline-block size-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out',
                                                        showAmenities
                                                            ? 'translate-x-5'
                                                            : 'translate-x-0',
                                                    )}
                                                />
                                            </button>
                                        </div>
                                    </div>

                                    {showAmenities ? (
                                        <div className="mt-2 border-t border-border/60 pt-3">
                                            <BusAmenityPicker
                                                amenities={amenities}
                                                selectedIds={
                                                    selectedAmenityIds
                                                }
                                                onChange={setSelectedAmenityIds}
                                            />
                                        </div>
                                    ) : (
                                        // Still submit hidden inputs when collapsed
                                        selectedAmenityIds.map((id) => (
                                            <input
                                                key={`hidden-collapsed-${id}`}
                                                type="hidden"
                                                name="amenity_ids[]"
                                                value={id}
                                            />
                                        ))
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Footer Buttons using Caisse style classes */}
                        <div className="cash-modal-actions mt-6">
                            <button
                                type="button"
                                onClick={() => onOpenChange(false)}
                                className="btn btn-ghost"
                                disabled={processing}
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="btn btn-primary"
                            >
                                <Check className="size-4" />
                                {processing
                                    ? 'Enregistrement…'
                                    : editing
                                        ? 'Enregistrer les changements'
                                        : 'Créer le bus'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
