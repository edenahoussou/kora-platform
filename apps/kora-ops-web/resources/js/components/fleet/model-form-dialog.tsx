import { Form } from '@inertiajs/react';
import {
    Armchair,
    Check,
    Factory,
    Info,
    Layers,
    Power,
    Tag,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import modelsRoutes from '@/routes/models';

export type ModelRecord = {
    uuid: string;
    brand: string;
    name: string;
    defaultSeatCount: number;
    busesCount: number;
    status: string;
    createdAt: string | null;
    updatedAt: string | null;
};

type Props = {
    open: boolean;
    model?: ModelRecord | null;
    onOpenChange: (open: boolean) => void;
};

function StatusToggle({ initialStatus }: { initialStatus: string }) {
    const [status, setStatus] = useState<'active' | 'archived'>(
        initialStatus === 'archived' ? 'archived' : 'active',
    );
    const active = status === 'active';

    return (
        <div>
            <input type="hidden" name="status" value={status} />
            <div className="flex items-center justify-between rounded-xl border border-border bg-card p-3">
                <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                        <span
                            className={cn(
                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold transition-colors',
                                active
                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {active ? 'Actif' : 'Archivé'}
                        </span>
                        <span className="text-xs font-semibold text-foreground">
                            {active
                                ? 'Sélectionnable pour les nouveaux bus'
                                : 'Masqué lors de la création de bus'}
                        </span>
                    </div>
                    <small className="block text-[11px] text-muted-foreground">
                        {active
                            ? 'Le modèle apparaît dans le formulaire de création de véhicule.'
                            : 'Le modèle reste attaché aux bus existants mais n’est plus proposé.'}
                    </small>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={active}
                    aria-label="Statut du modèle"
                    onClick={() =>
                        setStatus((s) =>
                            s === 'active' ? 'archived' : 'active',
                        )
                    }
                    className={cn(
                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:ring-2 focus-visible:ring-gold/50 focus-visible:outline-none',
                        active ? 'bg-emerald-500' : 'bg-muted-foreground/30',
                    )}
                >
                    <span
                        className={cn(
                            'pointer-events-none inline-block size-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out',
                            active ? 'translate-x-5' : 'translate-x-0',
                        )}
                    />
                </button>
            </div>
        </div>
    );
}

export function ModelFormDialog({ open, model, onOpenChange }: Props) {
    const editing = Boolean(model);
    const form = editing
        ? modelsRoutes.update.form({ model: model!.uuid })
        : modelsRoutes.store.form();

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
                key={model?.uuid ?? 'new'}
                {...form}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() => onOpenChange(false)}
                className="cash-modal cash-opening-modal w-[46rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="model-modal-title"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    <Layers className="size-5" />
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="section-kicker">
                                            Référentiel · Flotte
                                        </span>
                                        {editing && (
                                            <span className="rounded bg-gold/15 px-2 py-0.5 font-mono text-[11px] font-bold text-[var(--kora)]">
                                                {model?.brand}
                                            </span>
                                        )}
                                    </div>
                                    <h3 id="model-modal-title">
                                        {editing
                                            ? 'Modifier le modèle'
                                            : 'Nouveau modèle'}
                                    </h3>
                                    <p>
                                        Gabarit constructeur réutilisable pour
                                        les bus du parc KORA.
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
                                            Identification du modèle
                                        </strong>
                                        <small>
                                            Marque constructeur et dénomination
                                            commerciale
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field">
                                        <span className="field-label">
                                            <Factory className="size-3.5" />
                                            Marque{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            className="control"
                                            name="brand"
                                            defaultValue={model?.brand ?? ''}
                                            required
                                            placeholder="ex: Yutong, Toyota"
                                            aria-invalid={!!errors.brand}
                                        />
                                        {errors.brand ? (
                                            <small className="field-error-text">
                                                {errors.brand}
                                            </small>
                                        ) : (
                                            <small>
                                                Constructeur ou assembleur du
                                                véhicule.
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span className="field-label">
                                            <Tag className="size-3.5" />
                                            Modèle{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            className="control"
                                            name="name"
                                            defaultValue={model?.name ?? ''}
                                            required
                                            placeholder="ex: ZK6129, Hiace"
                                            aria-invalid={!!errors.name}
                                        />
                                        {errors.name ? (
                                            <small className="field-error-text">
                                                {errors.name}
                                            </small>
                                        ) : (
                                            <small>
                                                Unique par marque : la paire
                                                marque + modèle identifie le
                                                véhicule.
                                            </small>
                                        )}
                                    </label>
                                </div>
                            </div>

                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Armchair className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Gabarit & statut</strong>
                                        <small>
                                            Capacité nominale et disponibilité
                                            dans le référentiel
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field">
                                        <span className="field-label">
                                            <Armchair className="size-3.5" />
                                            Places par défaut{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            className="control w-full min-w-40"
                                            type="number"
                                            name="default_seat_count"
                                            defaultValue={
                                                model?.defaultSeatCount ?? ''
                                            }
                                            required
                                            min={1}
                                            max={200}
                                            placeholder="ex: 55"
                                            aria-invalid={
                                                !!errors.default_seat_count
                                            }
                                        />
                                        {errors.default_seat_count ? (
                                            <small className="field-error-text">
                                                {errors.default_seat_count}
                                            </small>
                                        ) : (
                                            <small>
                                                Capacité nominale du
                                                constructeur, indicative à la
                                                création de bus.
                                            </small>
                                        )}
                                    </label>

                                    <div className="field">
                                        <span className="field-label">
                                            <Power className="size-3.5" />
                                            Statut opérationnel{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <StatusToggle
                                            key={model?.uuid ?? 'new'}
                                            initialStatus={
                                                model?.status ?? 'active'
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-start gap-3.5 rounded-[0.875rem] border border-[rgba(246,176,0,0.3)] bg-[rgba(246,176,0,0.06)] p-4">
                                <Info className="mt-0.5 size-4 shrink-0 text-gold" />
                                <p className="text-[0.75rem] leading-relaxed text-muted-foreground">
                                    {editing
                                        ? 'Archiver ce modèle le retire des prochains enregistrements de bus, sans impacter les véhicules qui l’utilisent déjà.'
                                        : 'La paire marque + modèle doit être unique : elle sera proposée dans le formulaire de création de chaque bus du parc.'}
                                </p>
                            </div>
                        </div>

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
                                      : 'Créer le modèle'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
