import { Form } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    Calendar,
    Check,
    Info,
    MapPin,
    Route,
    Sparkles,
    Tag,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import fares from '@/routes/fares';

export type FareRecord = {
    uuid: string;
    lineCode: string;
    lineName: string;
    originName: string;
    destinationName: string;
    serviceClassCode: string;
    serviceClassName: string;
    baseAmount: number;
    currency: string;
    effectiveFrom: string;
    effectiveUntil: string | null;
    status: string;
};

type StopOption = {
    uuid: string;
    name: string;
    stopOrder: number;
};

type LineOption = {
    uuid: string;
    code: string;
    name: string;
    stops: StopOption[];
};

type Props = {
    open: boolean;
    fare?: FareRecord | null;
    lines: LineOption[];
    onOpenChange: (open: boolean) => void;
};

function formatMoney(amount: number | string): string {
    const num = typeof amount === 'string' ? Number(amount) : amount;

    if (!num || isNaN(num)) {
        return '—';
    }

    const formatted = new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    }).format(num);

    return `${formatted} FCFA`;
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (isNaN(date.getTime())) {
        return iso;
    }

    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/** Formulaire modal de gestion de grille tarifaire (style caisse unifié). */
export function FareFormDialog({ open, fare, lines, onOpenChange }: Props) {
    const editing = Boolean(fare);
    const form = editing
        ? fares.update.form({ fare: fare!.uuid })
        : fares.store.form();

    const [selectedLineUuid, setSelectedLineUuid] = useState<string>(
        fare?.lineCode
            ? (lines.find((l) => l.code === fare.lineCode)?.uuid ?? '')
            : '',
    );

    const [selectedOriginUuid, setSelectedOriginUuid] = useState<string>(
        fare?.originName
            ? (lines
                  .flatMap((l) => l.stops)
                  .find((s) => s.name === fare.originName)?.uuid ?? '')
            : '',
    );

    const [selectedDestinationUuid, setSelectedDestinationUuid] =
        useState<string>(
            fare?.destinationName
                ? (lines
                      .flatMap((l) => l.stops)
                      .find((s) => s.name === fare.destinationName)?.uuid ?? '')
                : '',
        );

    const [baseAmount, setBaseAmount] = useState<string>(
        fare?.baseAmount ? String(fare.baseAmount) : '',
    );
    const [effectiveFrom, setEffectiveFrom] = useState<string>(
        fare?.effectiveFrom ?? '',
    );
    const [effectiveUntil, setEffectiveUntil] = useState<string>(
        fare?.effectiveUntil ?? '',
    );

    const selectedLine = useMemo(
        () => lines.find((l) => l.uuid === selectedLineUuid),
        [lines, selectedLineUuid],
    );

    const availableStops = useMemo(
        () => selectedLine?.stops ?? [],
        [selectedLine],
    );

    const selectedOrigin = useMemo(
        () => availableStops.find((s) => s.uuid === selectedOriginUuid),
        [availableStops, selectedOriginUuid],
    );

    const destinationStops = useMemo(() => {
        if (!selectedOrigin) {
            return availableStops;
        }

        return availableStops.filter(
            (s) => s.stopOrder > selectedOrigin.stopOrder,
        );
    }, [availableStops, selectedOrigin]);

    const originStops = useMemo(() => availableStops, [availableStops]);

    const previewLineCode = selectedLine?.code ?? fare?.lineCode ?? '';
    const previewOriginName =
        availableStops.find((s) => s.uuid === selectedOriginUuid)?.name ??
        fare?.originName ??
        '';
    const previewDestinationName =
        availableStops.find((s) => s.uuid === selectedDestinationUuid)?.name ??
        fare?.destinationName ??
        '';

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
                key={fare?.uuid ?? 'new'}
                {...form}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() => onOpenChange(false)}
                className="cash-modal cash-opening-modal w-[48rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="fare-modal-title"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    <Tag className="size-5" />
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="section-kicker">
                                            Exploitation · Tarification
                                        </span>
                                        {editing && fare?.lineCode && (
                                            <span className="rounded bg-gold/15 px-2 py-0.5 font-mono text-[11px] font-bold text-[var(--kora)]">
                                                {fare.lineCode}
                                            </span>
                                        )}
                                    </div>
                                    <h3 id="fare-modal-title">
                                        {editing
                                            ? 'Modifier la tarification'
                                            : 'Nouvelle grille tarifaire'}
                                    </h3>
                                    <p>
                                        {editing
                                            ? 'Ajustez le montant et la période de validité du segment.'
                                            : 'Définissez la ligne, le segment O/D et le prix unitaire applicable.'}
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
                            {/* Alerte contextuelle informative */}
                            {!editing ? (
                                <div className="flex items-start gap-3 rounded-xl border border-gold/30 bg-gold/10 p-3.5 text-xs">
                                    <Info className="mt-0.5 size-4 shrink-0 text-gold" />
                                    <div className="space-y-0.5">
                                        <strong className="block font-bold text-foreground">
                                            Création en mode brouillon
                                        </strong>
                                        <p className="text-[11px] leading-relaxed text-muted-foreground">
                                            Définissez la ligne, le tronçon
                                            Origine → Destination, le prix
                                            unitaire et la période d'effet. La
                                            grille sera créée en{' '}
                                            <strong>brouillon</strong> et devra
                                            être activée explicitement pour être
                                            vendable au guichet.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex items-start gap-3 rounded-xl border border-border/80 bg-muted/40 p-3.5 text-xs">
                                    <Info className="mt-0.5 size-4 shrink-0 text-gold" />
                                    <div className="space-y-0.5">
                                        <strong className="block font-bold text-foreground">
                                            {fare?.status === 'active'
                                                ? 'Tarif actif — modification sensible'
                                                : fare?.status === 'draft'
                                                  ? 'Tarif en brouillon — ajustement libre'
                                                  : 'Tarif archivé — consultation'}
                                        </strong>
                                        <p className="text-[11px] leading-relaxed text-muted-foreground">
                                            {fare?.status === 'active'
                                                ? "Modifier le prix ou la période s'applique immédiatement aux prochaines réservations. Les billets déjà émis conservent leur montant d'origine."
                                                : fare?.status === 'draft'
                                                  ? 'Vous pouvez ajuster librement le montant et la validité avant activation.'
                                                  : 'Ce tarif est archivé et n’est plus proposé à la vente.'}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* Section 1 : Itinéraire & Segment */}
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Route className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Itinéraire & Segment</strong>
                                        <small>
                                            Ligne exploitée et tronçon Origine →
                                            Destination
                                        </small>
                                    </div>
                                </div>

                                {editing ? (
                                    <div className="flex flex-col gap-2 rounded-xl border border-border/70 bg-card p-3.5 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <span className="rounded-md bg-gold/15 px-2 py-1 font-mono text-xs font-bold text-[var(--kora)]">
                                                {fare?.lineCode}
                                            </span>
                                            <span className="text-xs font-semibold text-foreground">
                                                {fare?.lineName}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-xs font-medium text-foreground">
                                            <span className="inline-flex items-center gap-1 text-muted-foreground">
                                                <MapPin className="size-3 text-gold" />
                                                {fare?.originName}
                                            </span>
                                            <ArrowRight className="size-3.5 text-muted-foreground" />
                                            <span className="inline-flex items-center gap-1 font-semibold text-foreground">
                                                <MapPin className="size-3 text-[var(--kora)]" />
                                                {fare?.destinationName}
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-4">
                                        <label className="field">
                                            <span className="field-label">
                                                Ligne commerciale{' '}
                                                <b className="font-black text-destructive">
                                                    *
                                                </b>
                                            </span>
                                            <select
                                                className="control"
                                                name="line_id"
                                                value={selectedLineUuid}
                                                onChange={(e) => {
                                                    setSelectedLineUuid(
                                                        e.target.value,
                                                    );
                                                    setSelectedOriginUuid('');
                                                    setSelectedDestinationUuid(
                                                        '',
                                                    );
                                                }}
                                                required
                                            >
                                                <option value="">
                                                    Sélectionner une ligne
                                                </option>
                                                {lines.map((line) => (
                                                    <option
                                                        key={line.uuid}
                                                        value={line.uuid}
                                                    >
                                                        {line.code} ·{' '}
                                                        {line.name}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.line_id ? (
                                                <small className="field-error-text">
                                                    {errors.line_id}
                                                </small>
                                            ) : (
                                                <small>
                                                    Détermine les arrêts
                                                    ordonnés disponibles pour le
                                                    tronçon.
                                                </small>
                                            )}
                                        </label>

                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <label className="field">
                                                <span className="field-label">
                                                    Arrêt d'origine{' '}
                                                    <b className="font-black text-destructive">
                                                        *
                                                    </b>
                                                </span>
                                                <select
                                                    className="control"
                                                    name="origin_stop_id"
                                                    value={selectedOriginUuid}
                                                    onChange={(e) => {
                                                        setSelectedOriginUuid(
                                                            e.target.value,
                                                        );
                                                        setSelectedDestinationUuid(
                                                            '',
                                                        );
                                                    }}
                                                    required
                                                    disabled={
                                                        availableStops.length ===
                                                        0
                                                    }
                                                >
                                                    <option value="">
                                                        {availableStops.length ===
                                                        0
                                                            ? 'Choisir une ligne d’abord'
                                                            : 'Sélectionner l’origine'}
                                                    </option>
                                                    {originStops.map((stop) => (
                                                        <option
                                                            key={stop.uuid}
                                                            value={stop.uuid}
                                                        >
                                                            {stop.name}
                                                        </option>
                                                    ))}
                                                </select>
                                                {errors.origin_stop_id ? (
                                                    <small className="field-error-text">
                                                        {errors.origin_stop_id}
                                                    </small>
                                                ) : (
                                                    <small>
                                                        Point d'embarquement du
                                                        passager.
                                                    </small>
                                                )}
                                            </label>

                                            <label className="field">
                                                <span className="field-label">
                                                    Arrêt de destination{' '}
                                                    <b className="font-black text-destructive">
                                                        *
                                                    </b>
                                                </span>
                                                <select
                                                    className="control"
                                                    name="destination_stop_id"
                                                    value={
                                                        selectedDestinationUuid
                                                    }
                                                    onChange={(e) =>
                                                        setSelectedDestinationUuid(
                                                            e.target.value,
                                                        )
                                                    }
                                                    required
                                                    disabled={
                                                        availableStops.length ===
                                                            0 ||
                                                        !selectedOriginUuid
                                                    }
                                                >
                                                    <option value="">
                                                        {availableStops.length ===
                                                        0
                                                            ? 'Choisir une ligne d’abord'
                                                            : !selectedOriginUuid
                                                              ? 'Choisir l’origine d’abord'
                                                              : destinationStops.length ===
                                                                  0
                                                                ? 'Aucun arrêt aval disponible'
                                                                : 'Sélectionner la destination'}
                                                    </option>
                                                    {destinationStops.map(
                                                        (stop) => (
                                                            <option
                                                                key={stop.uuid}
                                                                value={
                                                                    stop.uuid
                                                                }
                                                            >
                                                                {stop.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                {errors.destination_stop_id ? (
                                                    <small className="field-error-text">
                                                        {
                                                            errors.destination_stop_id
                                                        }
                                                    </small>
                                                ) : (
                                                    <small>
                                                        Arrêt situé en aval de
                                                        l’origine dans
                                                        l'itinéraire.
                                                    </small>
                                                )}
                                            </label>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Section 2 : Tarification & Montant */}
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Banknote className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Tarif applicable</strong>
                                        <small>
                                            Prix unitaire standard par passager
                                            (TTC)
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4">
                                    <label className="field">
                                        <span className="field-label">
                                            Prix de base{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <div className="relative flex items-center">
                                            <input
                                                className="control pr-16 font-mono text-base font-bold tracking-tight"
                                                name="base_amount"
                                                type="number"
                                                min="1"
                                                step="1"
                                                inputMode="numeric"
                                                value={baseAmount}
                                                onChange={(e) =>
                                                    setBaseAmount(
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                                placeholder="ex: 8500"
                                                aria-invalid={
                                                    !!errors.base_amount
                                                }
                                            />
                                            <span className="pointer-events-none absolute right-3 inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-extrabold text-muted-foreground">
                                                FCFA
                                            </span>
                                        </div>
                                        {errors.base_amount ? (
                                            <small className="field-error-text">
                                                {errors.base_amount}
                                            </small>
                                        ) : (
                                            <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                                <span>
                                                    Montant entier en Francs CFA
                                                    (XOF), toutes taxes
                                                    comprises.
                                                </span>
                                                {baseAmount &&
                                                    !isNaN(
                                                        Number(baseAmount),
                                                    ) &&
                                                    Number(baseAmount) > 0 && (
                                                        <span className="font-mono font-bold text-foreground">
                                                            {formatMoney(
                                                                baseAmount,
                                                            )}
                                                        </span>
                                                    )}
                                            </div>
                                        )}
                                    </label>
                                </div>
                            </div>

                            {/* Section 3 : Période d'effet */}
                            <div className="cash-opening-row">
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        <Calendar className="size-4" />
                                    </span>
                                    <div>
                                        <strong>Période de validité</strong>
                                        <small>
                                            Fenêtre temporelle d'application de
                                            la grille tarifaire
                                        </small>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field">
                                        <span className="field-label">
                                            Date de début d'effet{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            className="control"
                                            name="effective_from"
                                            type="date"
                                            value={effectiveFrom}
                                            onChange={(e) =>
                                                setEffectiveFrom(e.target.value)
                                            }
                                            required
                                            aria-invalid={
                                                !!errors.effective_from
                                            }
                                        />
                                        {errors.effective_from ? (
                                            <small className="field-error-text">
                                                {errors.effective_from}
                                            </small>
                                        ) : (
                                            <small>
                                                Date à partir de laquelle le
                                                tarif sera actif.
                                            </small>
                                        )}
                                    </label>

                                    <label className="field">
                                        <span className="field-label">
                                            Date de fin d'effet{' '}
                                            <em>(Optionnel)</em>
                                        </span>
                                        <input
                                            className="control"
                                            name="effective_until"
                                            type="date"
                                            value={effectiveUntil}
                                            onChange={(e) =>
                                                setEffectiveUntil(
                                                    e.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                !!errors.effective_until
                                            }
                                        />
                                        {errors.effective_until ? (
                                            <small className="field-error-text">
                                                {errors.effective_until}
                                            </small>
                                        ) : (
                                            <small>
                                                Laisser vide pour une
                                                application permanente.
                                            </small>
                                        )}
                                    </label>
                                </div>
                            </div>

                            {/* Bandeau récapitulatif dynamique style Billet Caisse */}
                            <div className="rounded-xl border border-gold/30 bg-gold/5 p-4 transition-all">
                                <div className="flex items-center justify-between border-b border-gold/20 pb-2.5">
                                    <div className="flex items-center gap-2">
                                        <Sparkles className="size-4 text-gold" />
                                        <span className="text-[11px] font-black tracking-wider text-[var(--kora)] uppercase">
                                            Aperçu billetterie
                                        </span>
                                    </div>
                                    <span className="rounded bg-gold/20 px-2 py-0.5 text-[10px] font-extrabold text-[var(--kora)]">
                                        {editing
                                            ? 'Mise à jour'
                                            : 'Nouveau tarif (Brouillon)'}
                                    </span>
                                </div>
                                <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div>
                                        <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                            Ligne
                                        </span>
                                        <span className="font-mono text-xs font-bold text-foreground">
                                            {previewLineCode || '—'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                            Tronçon
                                        </span>
                                        <span className="truncate text-xs font-bold text-foreground">
                                            {previewOriginName &&
                                            previewDestinationName
                                                ? `${previewOriginName} → ${previewDestinationName}`
                                                : '—'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                            Prix unitaire
                                        </span>
                                        <span className="font-mono text-xs font-black text-foreground">
                                            {baseAmount
                                                ? formatMoney(baseAmount)
                                                : '—'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                            Validité
                                        </span>
                                        <span className="text-xs font-medium text-foreground">
                                            {effectiveFrom
                                                ? `${formatDate(effectiveFrom)}${effectiveUntil ? ` → ${formatDate(effectiveUntil)}` : ' → ∞'}`
                                                : '—'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Devise cachée (XOF) */}
                            <input
                                type="hidden"
                                name="currency"
                                value={fare?.currency ?? 'XOF'}
                            />
                        </div>

                        {/* Boutons d'action style Caisse */}
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
                                      ? 'Enregistrer les modifications'
                                      : 'Créer le tarif'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
