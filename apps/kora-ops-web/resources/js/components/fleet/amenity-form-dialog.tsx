import { Form } from '@inertiajs/react';
import { Check, Power, Smile, Sparkles, Tag, X } from 'lucide-react';
import amenitiesRoutes from '@/routes/amenities';

export type AmenityRecord = {
    uuid: string;
    code: string;
    label: string;
    icon: string | null;
    busesCount: number;
    status: string;
    createdAt: string | null;
    updatedAt: string | null;
};

type Props = {
    open: boolean;
    amenity?: AmenityRecord | null;
    onOpenChange: (open: boolean) => void;
};

export function AmenityFormDialog({ open, amenity, onOpenChange }: Props) {
    const editing = Boolean(amenity);
    const form = editing
        ? amenitiesRoutes.update.form({ amenity: amenity!.uuid })
        : amenitiesRoutes.store.form();

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
                key={amenity?.uuid ?? 'new'}
                {...form}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() => onOpenChange(false)}
                className="cash-modal cash-opening-modal w-[44rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="amenity-modal-title"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    <Sparkles className="size-5" />
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="section-kicker">
                                            Référentiel · Flotte
                                        </span>
                                        {editing && amenity?.code && (
                                            <span className="rounded bg-gold/15 px-2 py-0.5 font-mono text-[11px] font-bold text-[var(--kora)]">
                                                {amenity.code}
                                            </span>
                                        )}
                                    </div>
                                    <h3 id="amenity-modal-title">
                                        {editing
                                            ? 'Modifier la commodité'
                                            : 'Nouvelle commodité'}
                                    </h3>
                                    <p>
                                        Équipement réutilisable rattachable aux
                                        bus du réseau KORA.
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
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Tag className="size-4" />
                                    </span>
                                    <div>
                                        <strong>
                                            Paramètres de la commodité
                                        </strong>
                                        <small>
                                            Code technique, libellé voyageur et
                                            icône d'illustration
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field">
                                        <span>
                                            Identifiant technique{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            className="control font-mono lowercase"
                                            name="code"
                                            defaultValue={amenity?.code ?? ''}
                                            required
                                            placeholder="ex: wifi, air_conditioning"
                                            pattern="[a-z0-9_\-]+"
                                            aria-invalid={!!errors.code}
                                        />
                                        {errors.code ? (
                                            <small className="field-error-text">
                                                {errors.code}
                                            </small>
                                        ) : (
                                            <small>
                                                Minuscules, chiffres et tirets.
                                                Utilisé par l'app mobile.
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span>
                                            Libellé affiché{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            className="control"
                                            name="label"
                                            defaultValue={amenity?.label ?? ''}
                                            required
                                            placeholder="ex: Wi-Fi à bord"
                                            aria-invalid={!!errors.label}
                                        />
                                        {errors.label ? (
                                            <small className="field-error-text">
                                                {errors.label}
                                            </small>
                                        ) : (
                                            <small>
                                                Nom visible par les passagers.
                                            </small>
                                        )}
                                    </label>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field">
                                        <span>
                                            Icône d'illustration{' '}
                                            <em>(Optionnel)</em>
                                        </span>
                                        <div className="relative">
                                            <Smile className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <input
                                                className="control pl-9 font-mono"
                                                name="icon"
                                                defaultValue={
                                                    amenity?.icon ?? ''
                                                }
                                                placeholder="ex: wifi, snowflake"
                                            />
                                        </div>
                                        <small>
                                            Nom d'icône pour les badges dans
                                            l'application mobile.
                                        </small>
                                    </label>

                                    <label className="field">
                                        <span>
                                            Statut opérationnel{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <div className="relative">
                                            <Power className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <select
                                                className="control pl-9"
                                                name="status"
                                                defaultValue={
                                                    amenity?.status ?? 'active'
                                                }
                                                required
                                            >
                                                <option value="active">
                                                    Active
                                                </option>
                                                <option value="archived">
                                                    Archivée
                                                </option>
                                            </select>
                                        </div>
                                        <small>
                                            Archivée : masquée des nouveaux bus
                                            mais conservée en historique.
                                        </small>
                                    </label>
                                </div>
                            </div>
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
                                        : 'Créer la commodité'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
