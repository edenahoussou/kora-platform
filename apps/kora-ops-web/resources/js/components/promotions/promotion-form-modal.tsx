import { useForm } from '@inertiajs/react';
import { AlertTriangle, Check, Tag, X } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import promotions from '@/routes/promotions';
import type {
    PromotionConflict,
    PromotionFormValues,
    PromotionLineOption,
    PromotionRecord,
} from './promotion-types';
import { formatPeriod } from './promotion-types';

type Props = {
    open: boolean;
    promotion: PromotionRecord | null;
    lines: PromotionLineOption[];
    scopes: Record<string, string>;
    benefitTypes: Record<string, string>;
    channels: Record<string, string>;
    onOpenChange: (open: boolean) => void;
};

const emptyValues: PromotionFormValues = {
    name: '',
    code: '',
    benefit_type: 'percentage',
    benefit_value: '',
    scope_type: 'network',
    channel: '',
    line_id: '',
    origin_stop_id: '',
    destination_stop_id: '',
    priority: '100',
    starts_at: '',
    ends_at: '',
    max_uses: '',
    force: false,
};

function toLocalInput(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function valuesFromPromotion(
    promotion: PromotionRecord | null,
): PromotionFormValues {
    if (!promotion) {
        return emptyValues;
    }

    return {
        name: promotion.name,
        code: promotion.code,
        benefit_type: promotion.benefitType,
        benefit_value: String(promotion.benefitValue),
        scope_type: promotion.scopeType,
        channel: promotion.channel ?? '',
        line_id: promotion.line?.uuid ?? '',
        origin_stop_id: promotion.originStopUuid ?? '',
        destination_stop_id: promotion.destinationStopUuid ?? '',
        priority: String(promotion.priority),
        starts_at: toLocalInput(promotion.startsAt),
        ends_at: toLocalInput(promotion.endsAt),
        max_uses: promotion.maxUses !== null ? String(promotion.maxUses) : '',
        force: false,
    };
}

function parseConflicts(raw: string | undefined): PromotionConflict[] {
    if (!raw) {
        return [];
    }

    try {
        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function PromotionFormModal({
    open,
    promotion,
    lines,
    scopes,
    benefitTypes,
    channels,
    onOpenChange,
}: Props) {
    const isEditing = promotion !== null;
    const form = useForm<PromotionFormValues>(valuesFromPromotion(promotion));

    useEffect(() => {
        if (open) {
            form.setData(valuesFromPromotion(promotion));
            form.clearErrors();
        }
        // The modal intentionally resets only when opened for another promotion.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, promotion?.uuid]);

    const selectedLine = useMemo(
        () => lines.find((line) => line.uuid === form.data.line_id) ?? null,
        [lines, form.data.line_id],
    );

    if (!open) {
        return null;
    }

    const close = () => {
        if (
            form.isDirty &&
            !window.confirm('Fermer sans enregistrer les modifications ?')
        ) {
            return;
        }

        onOpenChange(false);
    };

    const submit = (force: boolean) => {
        form.transform((data) => ({
            ...data,
            force,
            benefit_value:
                data.benefit_value === '' ? null : data.benefit_value,
            channel: data.scope_type === 'channel' ? data.channel : null,
            line_id:
                data.scope_type === 'line' || data.scope_type === 'segment'
                    ? data.line_id
                    : null,
            origin_stop_id:
                data.scope_type === 'segment' ? data.origin_stop_id : null,
            destination_stop_id:
                data.scope_type === 'segment' ? data.destination_stop_id : null,
            ends_at: data.ends_at === '' ? null : data.ends_at,
            max_uses: data.max_uses === '' ? null : data.max_uses,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEditing) {
            form.put(
                promotions.update.url({ promotion: promotion.uuid }),
                options,
            );
        } else {
            form.post(promotions.store.url(), options);
        }
    };

    const onSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        submit(false);
    };

    // errors.conflicts はサーバが JSON 文字列で返す競合一覧(フォーム外キー)
    const conflicts = parseConflicts(
        (form.errors as Record<string, string | undefined>).conflicts,
    );

    const needsLine =
        form.data.scope_type === 'line' || form.data.scope_type === 'segment';
    const isSegment = form.data.scope_type === 'segment';
    const isChannel = form.data.scope_type === 'channel';
    const isArchived = promotion?.status === 'archived';

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(event) =>
                event.target === event.currentTarget && close()
            }
        >
            <form
                className="cash-modal cash-opening-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="promotion-modal-title"
                onSubmit={onSubmit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">
                            Réseau commercial · Promotions
                        </span>
                        <h3 id="promotion-modal-title">
                            {isEditing
                                ? 'Modifier la promotion'
                                : 'Nouvelle promotion'}
                        </h3>
                        <p>
                            Un code promotionnel déclenche une remise au moment
                            du devis — jamais sur les ventes déjà confirmées.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={close}
                        className="cash-modal-close"
                        aria-label="Fermer"
                        disabled={form.processing}
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
                                <strong>Offre</strong>
                                <small>
                                    Le code est saisi par l’agent ou le passager
                                    au moment du devis.
                                </small>
                            </div>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>
                                    Nom de l’offre <b>*</b>
                                </span>
                                <input
                                    className="control"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    placeholder="Vacances Nord"
                                    aria-invalid={!!form.errors.name}
                                />
                                {form.errors.name && (
                                    <small className="field-error-text">
                                        {form.errors.name}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>
                                    Code promo <b>*</b>
                                </span>
                                <input
                                    className="control"
                                    value={form.data.code}
                                    onChange={(event) =>
                                        form.setData(
                                            'code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="NORD10"
                                    aria-invalid={!!form.errors.code}
                                />
                                {form.errors.code && (
                                    <small className="field-error-text">
                                        {form.errors.code}
                                    </small>
                                )}
                            </label>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>
                                    Type d’avantage <b>*</b>
                                </span>
                                <select
                                    className="control"
                                    value={form.data.benefit_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'benefit_type',
                                            event.target
                                                .value as PromotionFormValues['benefit_type'],
                                        )
                                    }
                                    aria-invalid={!!form.errors.benefit_type}
                                >
                                    {Object.entries(benefitTypes).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                {form.errors.benefit_type && (
                                    <small className="field-error-text">
                                        {form.errors.benefit_type}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>
                                    {form.data.benefit_type === 'percentage'
                                        ? 'Pourcentage (%)'
                                        : 'Montant (FCFA)'}{' '}
                                    <b>*</b>
                                </span>
                                <input
                                    className="control"
                                    type="number"
                                    min="0"
                                    step={
                                        form.data.benefit_type === 'percentage'
                                            ? '0.5'
                                            : '50'
                                    }
                                    value={form.data.benefit_value}
                                    onChange={(event) =>
                                        form.setData(
                                            'benefit_value',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={
                                        form.data.benefit_type === 'percentage'
                                            ? '10'
                                            : '1000'
                                    }
                                    aria-invalid={!!form.errors.benefit_value}
                                />
                                {form.errors.benefit_value && (
                                    <small className="field-error-text">
                                        {form.errors.benefit_value}
                                    </small>
                                )}
                            </label>
                        </div>
                    </div>

                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Tag className="size-4" />
                            </span>
                            <div>
                                <strong>Périmètre</strong>
                                <small>
                                    Définit sur quelles ventes le code peut
                                    s’appliquer.
                                </small>
                            </div>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>
                                    Périmètre <b>*</b>
                                </span>
                                <select
                                    className="control"
                                    value={form.data.scope_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'scope_type',
                                            event.target
                                                .value as PromotionFormValues['scope_type'],
                                        )
                                    }
                                    aria-invalid={!!form.errors.scope_type}
                                >
                                    {Object.entries(scopes).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                {form.errors.scope_type && (
                                    <small className="field-error-text">
                                        {form.errors.scope_type}
                                    </small>
                                )}
                            </label>
                            {isChannel && (
                                <label className="field">
                                    <span>
                                        Canal <b>*</b>
                                    </span>
                                    <select
                                        className="control"
                                        value={form.data.channel}
                                        onChange={(event) =>
                                            form.setData(
                                                'channel',
                                                event.target
                                                    .value as PromotionFormValues['channel'],
                                            )
                                        }
                                        aria-invalid={!!form.errors.channel}
                                    >
                                        <option value="">
                                            Choisir un canal
                                        </option>
                                        {Object.entries(channels).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    {form.errors.channel && (
                                        <small className="field-error-text">
                                            {form.errors.channel}
                                        </small>
                                    )}
                                </label>
                            )}
                            {needsLine && (
                                <label className="field">
                                    <span>
                                        Ligne <b>*</b>
                                    </span>
                                    <select
                                        className="control"
                                        value={form.data.line_id}
                                        onChange={(event) => {
                                            form.setData((data) => ({
                                                ...data,
                                                line_id: event.target.value,
                                                origin_stop_id: '',
                                                destination_stop_id: '',
                                            }));
                                        }}
                                        aria-invalid={!!form.errors.line_id}
                                    >
                                        <option value="">
                                            Choisir une ligne
                                        </option>
                                        {lines.map((line) => (
                                            <option
                                                key={line.uuid}
                                                value={line.uuid}
                                            >
                                                {line.code} · {line.name}
                                            </option>
                                        ))}
                                    </select>
                                    {form.errors.line_id && (
                                        <small className="field-error-text">
                                            {form.errors.line_id}
                                        </small>
                                    )}
                                </label>
                            )}
                            {isSegment && selectedLine && (
                                <>
                                    <label className="field">
                                        <span>
                                            Arrêt d’origine <b>*</b>
                                        </span>
                                        <select
                                            className="control"
                                            value={form.data.origin_stop_id}
                                            onChange={(event) =>
                                                form.setData(
                                                    'origin_stop_id',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                !!form.errors.origin_stop_id
                                            }
                                        >
                                            <option value="">
                                                Choisir un arrêt
                                            </option>
                                            {selectedLine.stops.map((stop) => (
                                                <option
                                                    key={stop.uuid}
                                                    value={stop.uuid}
                                                >
                                                    {stop.name} ({stop.city})
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors.origin_stop_id && (
                                            <small className="field-error-text">
                                                {form.errors.origin_stop_id}
                                            </small>
                                        )}
                                    </label>
                                    <label className="field">
                                        <span>
                                            Arrêt de destination <b>*</b>
                                        </span>
                                        <select
                                            className="control"
                                            value={
                                                form.data.destination_stop_id
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'destination_stop_id',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                !!form.errors
                                                    .destination_stop_id
                                            }
                                        >
                                            <option value="">
                                                Choisir un arrêt
                                            </option>
                                            {selectedLine.stops.map((stop) => (
                                                <option
                                                    key={stop.uuid}
                                                    value={stop.uuid}
                                                >
                                                    {stop.name} ({stop.city})
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors.destination_stop_id && (
                                            <small className="field-error-text">
                                                {
                                                    form.errors
                                                        .destination_stop_id
                                                }
                                            </small>
                                        )}
                                    </label>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Tag className="size-4" />
                            </span>
                            <div>
                                <strong>Application</strong>
                                <small>
                                    Période de validité, priorité de
                                    déclenchement et limite d’utilisations.
                                </small>
                            </div>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>
                                    Début <b>*</b>
                                </span>
                                <input
                                    className="control"
                                    type="datetime-local"
                                    value={form.data.starts_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'starts_at',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!form.errors.starts_at}
                                />
                                {form.errors.starts_at && (
                                    <small className="field-error-text">
                                        {form.errors.starts_at}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>Fin (optionnelle)</span>
                                <input
                                    className="control"
                                    type="datetime-local"
                                    value={form.data.ends_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'ends_at',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!form.errors.ends_at}
                                />
                                {form.errors.ends_at && (
                                    <small className="field-error-text">
                                        {form.errors.ends_at}
                                    </small>
                                )}
                            </label>
                        </div>
                        <div className="cash-opening-fields">
                            <label className="field">
                                <span>
                                    Priorité <b>*</b>
                                </span>
                                <input
                                    className="control"
                                    type="number"
                                    min="1"
                                    value={form.data.priority}
                                    onChange={(event) =>
                                        form.setData(
                                            'priority',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!form.errors.priority}
                                />
                                {form.errors.priority && (
                                    <small className="field-error-text">
                                        {form.errors.priority}
                                    </small>
                                )}
                            </label>
                            <label className="field">
                                <span>Utilisations max (optionnel)</span>
                                <input
                                    className="control"
                                    type="number"
                                    min="1"
                                    value={form.data.max_uses}
                                    onChange={(event) =>
                                        form.setData(
                                            'max_uses',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Illimité"
                                    aria-invalid={!!form.errors.max_uses}
                                />
                                {form.errors.max_uses && (
                                    <small className="field-error-text">
                                        {form.errors.max_uses}
                                    </small>
                                )}
                            </label>
                        </div>
                    </div>

                    {conflicts.length > 0 && (
                        <div className="rounded-[14px] border border-gold/40 bg-gold/10 p-4">
                            <div className="flex items-start gap-3">
                                <AlertTriangle
                                    className="mt-0.5 size-4 shrink-0 text-gold"
                                    strokeWidth={1.8}
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-extrabold text-foreground">
                                        {conflicts.length} conflit
                                        {conflicts.length > 1 ? 's' : ''} de
                                        période/priorité détecté
                                        {conflicts.length > 1 ? 's' : ''}
                                    </p>
                                    <ul className="mt-2 space-y-1.5">
                                        {conflicts.map((conflict) => (
                                            <li
                                                key={conflict.uuid}
                                                className="text-[0.68rem] text-muted-foreground"
                                            >
                                                <strong className="text-foreground">
                                                    {conflict.name}
                                                </strong>{' '}
                                                ({conflict.code}) · priorité{' '}
                                                {conflict.priority} ·{' '}
                                                {formatPeriod(conflict)}
                                            </li>
                                        ))}
                                    </ul>
                                    <button
                                        type="button"
                                        className="btn btn-navy mt-3"
                                        disabled={form.processing}
                                        onClick={() => submit(true)}
                                    >
                                        <Check className="size-4" /> Enregistrer
                                        quand même
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                <div className="cash-modal-foot">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={close}
                        disabled={form.processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={form.processing || isArchived}
                    >
                        {form.processing
                            ? 'Enregistrement…'
                            : isEditing
                              ? 'Enregistrer les modifications'
                              : 'Créer la promotion'}
                    </button>
                </div>
            </form>
        </div>
    );
}
