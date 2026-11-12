import { Link, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ClipboardList,
    FileText,
    ImagePlus,
    Megaphone,
    PackageCheck,
    Plus,
    Star,
    Upload,
    UserRound,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import koraPoints from '@/routes/kora-points';

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type VisitAction = {
    uuid: string;
    label: string;
    assignee_uuid: string | null;
    assignee_name: string | null;
    due_date: string | null;
    status: string;
    realization_note: string | null;
    review_reason: string | null;
    realized_at: string | null;
    reviewed_at: string | null;
};

export type PointVisit = {
    uuid: string;
    type: string;
    score: number;
    report: string;
    status: string;
    visited_at: string | null;
    visitor_name: string | null;
    actions: VisitAction[];
    photos: {
        uuid: string;
        name: string;
        mime_type: string;
        download_url: string;
    }[];
    point_uuid?: string;
    point_name?: string;
};

export type PlvOrder = {
    uuid: string;
    point_uuid: string | null;
    point_name: string | null;
    support: string;
    quantity: number;
    status: string;
    created_at: string | null;
    delivered_at: string | null;
    installed_at: string | null;
    cancellation_reason: string | null;
    cancelled_at: string | null;
    creator_name: string | null;
};

export type VisitAssignee = { uuid: string; name: string; type: string };

type Props = {
    pointUuid: string;
    visits: Paginated<PointVisit>;
    plvOrders: Paginated<PlvOrder>;
    visitAssignees: VisitAssignee[];
    canManage: boolean;
    kind: 'visits' | 'plv';
};

type DraftAction = { label: string; assignee_uuid: string; due_date: string };
type VisitPhotoPreview = { id: number; file: File; url: string };

const visitPhotoTypes = ['image/jpeg', 'image/png', 'image/webp'];
const maxVisitPhotoSize = 100 * 1024 * 1024;
const maxVisitPhotoCount = 5;

const visitLabels: Record<string, string> = {
    quality: 'Visite qualité',
    commercial_follow_up: 'Suivi commercial',
    plv_control: 'Contrôle PLV',
};

const supportLabels: Record<string, string> = {
    affiches_a3: 'Affiches A3',
    autocollants: 'Autocollants',
    bache: 'Bâche',
    panneau: 'Panneau',
};

const statusLabels: Record<string, string> = {
    pending: 'À réaliser',
    returned: 'À reprendre',
    realized: 'À valider par KORA',
    validated: 'Validée',
    in_progress: 'En cours',
    closed: 'Clôturée',
    prepared: 'Préparée',
    delivered: 'Livrée',
    installed: 'Installée',
    cancelled: 'Annulée',
};

function dateLabel(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              ...(value.includes('T') ? { timeStyle: 'short' as const } : {}),
          }).format(date);
}

function RequiredMark() {
    return (
        <span className="ml-1 text-red-600" aria-hidden="true">
            *
        </span>
    );
}

function PaginationFooter({
    page,
    label,
}: {
    page: Paginated<unknown>;
    label: string;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label={label}
            className="flex flex-wrap justify-end gap-2 border-t border-border px-4 py-3"
        >
            {page.links.map((link, index) =>
                link.url ? (
                    <Link
                        key={`${link.label}-${index}`}
                        href={link.url}
                        preserveScroll
                        aria-current={link.active ? 'page' : undefined}
                        className={`rounded-md px-2.5 py-1.5 text-xs font-semibold ${link.active ? 'bg-[var(--kora)] text-white' : 'text-muted-foreground hover:bg-muted'}`}
                    >
                        {link.label
                            .replace(/&laquo;|&raquo;|<[^>]*>/g, '')
                            .replace(/Previous/i, 'Précédent')
                            .replace(/Next/i, 'Suivant')}
                    </Link>
                ) : null,
            )}
        </nav>
    );
}

function FieldLabel({
    htmlFor,
    icon: Icon,
    children,
    required = false,
}: {
    htmlFor: string;
    icon: typeof FileText;
    children: React.ReactNode;
    required?: boolean;
}) {
    return (
        <label
            htmlFor={htmlFor}
            className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-foreground"
        >
            <Icon
                className="size-3.5 text-muted-foreground"
                aria-hidden="true"
            />
            <span>
                {children}
                {required ? <RequiredMark /> : null}
            </span>
        </label>
    );
}

function ModalFrame({
    title,
    icon: Icon,
    onClose,
    children,
}: {
    title: string;
    icon: typeof FileText;
    onClose: () => void;
    children: React.ReactNode;
}) {
    const dialogRef = useRef<HTMLElement>(null);
    const closeRef = useRef(onClose);

    useEffect(() => {
        closeRef.current = onClose;
    }, [onClose]);

    useEffect(() => {
        const previousFocus =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        dialogRef.current
            ?.querySelector<HTMLElement>('input, select, textarea, button')
            ?.focus();
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                closeRef.current();
            }
        };
        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            previousFocus?.focus();
        };
    }, []);

    const keepFocusInside = (event: React.KeyboardEvent<HTMLElement>) => {
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = Array.from(
            dialogRef.current?.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            ) ?? [],
        );

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    return (
        <div
            className="fixed inset-0 z-[100] grid place-items-center overflow-y-auto bg-[#061a36]/60 p-3 backdrop-blur-sm sm:p-6"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <section
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby="field-operations-modal-title"
                onKeyDown={keepFocusInside}
                tabIndex={-1}
                className="w-full max-w-2xl overflow-hidden rounded-2xl border border-gold/30 bg-card shadow-[0_24px_90px_rgba(3,16,36,.34)] motion-safe:animate-in motion-safe:zoom-in-95 motion-safe:fade-in"
            >
                <header className="flex items-start justify-between gap-4 border-b border-border bg-gradient-to-r from-gold/10 to-transparent px-5 py-4 sm:px-6">
                    <div className="flex items-center gap-3">
                        <span className="grid size-10 place-items-center rounded-xl bg-gold/20 text-[var(--kora)]">
                            <Icon className="size-5" />
                        </span>
                        <div>
                            <h2
                                id="field-operations-modal-title"
                                className="text-base font-extrabold"
                            >
                                {title}
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                KORA POINT · Direction KORA
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        aria-label="Fermer la fenêtre"
                        onClick={onClose}
                        className="grid size-9 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <X className="size-4" />
                    </button>
                </header>
                <div className="max-h-[min(78vh,760px)] overflow-y-auto p-5 sm:p-6">
                    {children}
                </div>
            </section>
        </div>
    );
}

function ErrorText({ value }: { value?: string }) {
    return value ? (
        <p role="alert" className="mt-1 text-xs font-medium text-destructive">
            {value}
        </p>
    ) : null;
}

function VisitModal({
    pointUuid,
    assignees,
    onClose,
}: {
    pointUuid: string;
    assignees: VisitAssignee[];
    onClose: () => void;
}) {
    const [actions, setActions] = useState<DraftAction[]>([]);
    const [photoPreviews, setPhotoPreviews] = useState<VisitPhotoPreview[]>([]);
    const [photoClientErrors, setPhotoClientErrors] = useState<string[]>([]);
    const [isPhotoDragging, setIsPhotoDragging] = useState(false);
    const photoInputRef = useRef<HTMLInputElement>(null);
    const photoPreviewUrls = useRef<string[]>([]);
    const nextPhotoPreviewId = useRef(0);
    const form = useForm({
        visit_type: 'quality',
        score: '8',
        report: '',
        photos: [] as File[],
        actions: [] as DraftAction[],
    });

    useEffect(
        () => () => {
            photoPreviewUrls.current.forEach((url) => URL.revokeObjectURL(url));
        },
        [],
    );

    const acceptPhotos = (incomingFiles: FileList | File[]) => {
        const currentFiles = form.data.photos;
        const existingFileKeys = new Set(
            currentFiles.map(
                (file) => `${file.name}:${file.size}:${file.lastModified}`,
            ),
        );
        const acceptedFiles: File[] = [];
        const errors: string[] = [];

        Array.from(incomingFiles).forEach((file) => {
            const fileKey = `${file.name}:${file.size}:${file.lastModified}`;

            if (!visitPhotoTypes.includes(file.type)) {
                errors.push(`${file.name} : format JPEG, PNG ou WebP requis.`);

                return;
            }

            if (file.size > maxVisitPhotoSize) {
                errors.push(`${file.name} : taille maximale de 10 Mo.`);

                return;
            }

            if (existingFileKeys.has(fileKey)) {
                return;
            }

            if (
                currentFiles.length + acceptedFiles.length >=
                maxVisitPhotoCount
            ) {
                errors.push('Vous pouvez joindre au maximum 5 photos.');

                return;
            }

            existingFileKeys.add(fileKey);
            acceptedFiles.push(file);
        });

        if (acceptedFiles.length > 0) {
            const newPreviews = acceptedFiles.map((file) => {
                const url = URL.createObjectURL(file);
                photoPreviewUrls.current.push(url);

                return { id: nextPhotoPreviewId.current++, file, url };
            });

            setPhotoPreviews((current) => [...current, ...newPreviews]);
            form.setData('photos', [...currentFiles, ...acceptedFiles]);
        }

        setPhotoClientErrors(errors);

        if (photoInputRef.current) {
            photoInputRef.current.value = '';
        }
    };

    const removePhoto = (preview: VisitPhotoPreview) => {
        URL.revokeObjectURL(preview.url);
        photoPreviewUrls.current = photoPreviewUrls.current.filter(
            (url) => url !== preview.url,
        );
        setPhotoPreviews((current) =>
            current.filter((photo) => photo.id !== preview.id),
        );
        form.setData(
            'photos',
            form.data.photos.filter((file) => file !== preview.file),
        );
        setPhotoClientErrors([]);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, actions }));
        form.post(koraPoints.visits.store.url({ point: pointUuid }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const addAction = () =>
        setActions((current) => [
            ...current,
            {
                label: '',
                assignee_uuid: assignees[0]?.uuid ?? '',
                due_date: '',
            },
        ]);
    const updateAction = (
        index: number,
        key: keyof DraftAction,
        value: string,
    ) =>
        setActions((current) =>
            current.map((action, actionIndex) =>
                actionIndex === index ? { ...action, [key]: value } : action,
            ),
        );

    return (
        <ModalFrame
            title="Enregistrer une visite"
            icon={ClipboardList}
            onClose={onClose}
        >
            <form onSubmit={submit} className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <FieldLabel
                            htmlFor="visit-type"
                            icon={ClipboardList}
                            required
                        >
                            Type de visite
                        </FieldLabel>
                        <select
                            id="visit-type"
                            required
                            value={form.data.visit_type}
                            onChange={(event) =>
                                form.setData('visit_type', event.target.value)
                            }
                            className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <option value="quality">Visite qualité</option>
                            <option value="commercial_follow_up">
                                Suivi commercial
                            </option>
                            <option value="plv_control">Contrôle PLV</option>
                        </select>
                        <ErrorText value={form.errors.visit_type} />
                    </div>
                    <div>
                        <FieldLabel htmlFor="visit-score" icon={Star} required>
                            Note sur 10
                        </FieldLabel>
                        <input
                            id="visit-score"
                            required
                            type="number"
                            min="0"
                            max="10"
                            step="1"
                            value={form.data.score}
                            onChange={(event) =>
                                form.setData('score', event.target.value)
                            }
                            className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                        />
                        <ErrorText value={form.errors.score} />
                    </div>
                </div>
                <div>
                    <FieldLabel htmlFor="visit-report" icon={FileText} required>
                        Compte rendu
                    </FieldLabel>
                    <textarea
                        id="visit-report"
                        required
                        minLength={10}
                        maxLength={10000}
                        rows={4}
                        value={form.data.report}
                        onChange={(event) =>
                            form.setData('report', event.target.value)
                        }
                        className="w-full rounded-lg border border-input bg-background px-3 py-2.5 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                        placeholder="Observations, échanges et éléments constatés…"
                    />
                    <ErrorText value={form.errors.report} />
                </div>
                <div>
                    <FieldLabel htmlFor="visit-photos" icon={ImagePlus}>
                        Photos
                    </FieldLabel>
                    <input
                        ref={photoInputRef}
                        id="visit-photos"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        onChange={(event) =>
                            acceptPhotos(event.target.files ?? [])
                        }
                        className="sr-only"
                        aria-describedby="visit-photos-help visit-photos-errors"
                        aria-invalid={
                            photoClientErrors.length > 0 ||
                            Object.keys(form.errors).some((key) =>
                                key.startsWith('photos'),
                            )
                        }
                    />
                    <button
                        type="button"
                        disabled={form.processing}
                        onClick={() => photoInputRef.current?.click()}
                        onDragOver={(event) => {
                            event.preventDefault();
                            setIsPhotoDragging(true);
                        }}
                        onDragLeave={(event) => {
                            event.preventDefault();
                            setIsPhotoDragging(false);
                        }}
                        onDrop={(event) => {
                            event.preventDefault();
                            setIsPhotoDragging(false);
                            acceptPhotos(event.dataTransfer.files);
                        }}
                        aria-describedby="visit-photos-help visit-photos-errors"
                        className={`mt-1 flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-5 text-center transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${isPhotoDragging ? 'border-gold bg-gold/10' : 'border-border bg-muted/20 hover:border-gold/50 hover:bg-gold/5'}`}
                    >
                        <span className="grid size-10 place-items-center rounded-xl bg-gold/15 text-[var(--kora)]">
                            <Upload className="size-5" aria-hidden="true" />
                        </span>
                        <span className="text-xs font-semibold text-foreground">
                            Glissez-déposez vos photos ici
                        </span>
                        <span className="text-[0.7rem] text-muted-foreground">
                            ou cliquez pour parcourir vos fichiers
                        </span>
                    </button>
                    <p
                        id="visit-photos-help"
                        className="mt-1 text-[0.7rem] text-muted-foreground"
                    >
                        Facultatives · {form.data.photos.length}/5 · JPEG, PNG
                        ou WebP · 100 Mo maximum par fichier.
                    </p>
                    {photoClientErrors.length > 0 ||
                    Object.keys(form.errors).some((key) =>
                        key.startsWith('photos'),
                    ) ? (
                        <div
                            id="visit-photos-errors"
                            className="mt-1 space-y-1 text-xs font-medium text-destructive"
                            role="alert"
                        >
                            {photoClientErrors.map((error, index) => (
                                <p key={`${error}-${index}`}>{error}</p>
                            ))}
                            {Object.entries(form.errors)
                                .filter(([key]) => key.startsWith('photos'))
                                .map(([key, error]) => (
                                    <p key={key}>{error}</p>
                                ))}
                        </div>
                    ) : null}
                    {photoPreviews.length > 0 ? (
                        <ul className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {photoPreviews.map((preview) => (
                                <li
                                    key={preview.id}
                                    className="overflow-hidden rounded-xl border border-border bg-card"
                                >
                                    <img
                                        src={preview.url}
                                        alt={`Aperçu de ${preview.file.name}`}
                                        className="h-24 w-full bg-muted object-cover"
                                    />
                                    <div className="flex items-center justify-between gap-2 p-2">
                                        <span className="min-w-0">
                                            <span className="block truncate text-[0.68rem] font-semibold">
                                                {preview.file.name}
                                            </span>
                                            <span className="text-[0.65rem] text-muted-foreground">
                                                {(
                                                    preview.file.size / 1024
                                                ).toFixed(0)}{' '}
                                                Ko
                                            </span>
                                        </span>
                                        <button
                                            type="button"
                                            aria-label={`Retirer ${preview.file.name}`}
                                            disabled={form.processing}
                                            onClick={() => removePhoto(preview)}
                                            className="grid size-7 shrink-0 place-items-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:outline-2 focus-visible:outline-ring"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                    {form.processing && form.progress ? (
                        <div
                            className="mt-3"
                            role="progressbar"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={Math.round(
                                form.progress.percentage ?? 0,
                            )}
                            aria-label="Progression de l’envoi des photos de visite"
                            aria-live="polite"
                        >
                            <div className="mb-1 flex items-center justify-between text-[0.7rem] font-semibold text-muted-foreground">
                                <span>Envoi de la visite…</span>
                                <span>
                                    {Math.round(form.progress.percentage ?? 0)}
                                    {' %'}
                                </span>
                            </div>
                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-gradient-to-r from-gold to-[var(--kora)] transition-[width] duration-300 ease-out"
                                    style={{
                                        width: `${Math.round(form.progress.percentage ?? 0)}%`,
                                    }}
                                />
                            </div>
                        </div>
                    ) : null}
                </div>
                <section className="rounded-xl border border-border bg-muted/20 p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 className="text-sm font-bold">
                                Actions de suivi
                            </h3>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Facultatives. La visite se clôturera après
                                validation de toutes les actions.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={addAction}
                            className="btn btn-secondary btn-sm"
                            disabled={assignees.length === 0}
                        >
                            <Plus className="size-3.5" /> Ajouter une action
                        </button>
                    </div>
                    {actions.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-border px-3 py-4 text-center text-xs text-muted-foreground">
                            Aucune action à suivre.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {actions.map((action, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-lg border border-border bg-background p-3 sm:grid-cols-2"
                                >
                                    <div className="flex items-center justify-between sm:col-span-2">
                                        <span className="text-xs font-bold">
                                            Action {index + 1}
                                        </span>
                                        <button
                                            type="button"
                                            aria-label={`Supprimer l’action ${index + 1}`}
                                            className="rounded p-1 text-muted-foreground hover:text-destructive"
                                            onClick={() =>
                                                setActions((current) =>
                                                    current.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <X className="size-4" />
                                        </button>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <FieldLabel
                                            htmlFor={`action-label-${index}`}
                                            icon={FileText}
                                            required
                                        >
                                            Libellé
                                        </FieldLabel>
                                        <input
                                            id={`action-label-${index}`}
                                            required
                                            minLength={3}
                                            maxLength={255}
                                            value={action.label}
                                            onChange={(event) =>
                                                updateAction(
                                                    index,
                                                    'label',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <FieldLabel
                                            htmlFor={`action-assignee-${index}`}
                                            icon={UserRound}
                                            required
                                        >
                                            Responsable
                                        </FieldLabel>
                                        <select
                                            id={`action-assignee-${index}`}
                                            required
                                            value={action.assignee_uuid}
                                            onChange={(event) =>
                                                updateAction(
                                                    index,
                                                    'assignee_uuid',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                                        >
                                            <option value="">Choisir</option>
                                            {assignees.map((assignee) => (
                                                <option
                                                    key={assignee.uuid}
                                                    value={assignee.uuid}
                                                >
                                                    {assignee.name} ·{' '}
                                                    {assignee.type}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <FieldLabel
                                            htmlFor={`action-date-${index}`}
                                            icon={CalendarDays}
                                            required
                                        >
                                            Échéance
                                        </FieldLabel>
                                        <input
                                            id={`action-date-${index}`}
                                            required
                                            type="date"
                                            min={new Date()
                                                .toISOString()
                                                .slice(0, 10)}
                                            value={action.due_date}
                                            onChange={(event) =>
                                                updateAction(
                                                    index,
                                                    'due_date',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                                        />
                                    </div>
                                    <ErrorText
                                        value={
                                            (
                                                form.errors as Record<
                                                    string,
                                                    string
                                                >
                                            )[`actions.${index}.label`] ??
                                            (
                                                form.errors as Record<
                                                    string,
                                                    string
                                                >
                                            )[`actions.${index}.assignee_uuid`]
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                    <ErrorText value={form.errors.actions} />
                </section>
                <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="btn btn-secondary"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn btn-primary"
                    >
                        <Check className="size-4" />
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Enregistrer la visite'}
                    </button>
                </div>
            </form>
        </ModalFrame>
    );
}

function PlvModal({
    pointUuid,
    onClose,
}: {
    pointUuid: string;
    onClose: () => void;
}) {
    const form = useForm({ support: 'affiches_a3', quantity: 1 });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(koraPoints.plvOrders.store.url({ point: pointUuid }), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <ModalFrame
            title="Préparer une commande PLV"
            icon={Megaphone}
            onClose={onClose}
        >
            <form onSubmit={submit} className="space-y-5">
                <p className="rounded-lg border border-gold/30 bg-gold/10 px-3 py-2 text-xs leading-5 text-foreground">
                    PLV signifie « publicité sur le lieu de vente ». La commande
                    commence au statut Préparée.
                </p>
                <div>
                    <FieldLabel htmlFor="plv-support" icon={Megaphone} required>
                        Support
                    </FieldLabel>
                    <select
                        id="plv-support"
                        required
                        value={form.data.support}
                        onChange={(event) =>
                            form.setData('support', event.target.value)
                        }
                        className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    >
                        <option value="affiches_a3">Affiches A3</option>
                        <option value="autocollants">Autocollants</option>
                        <option value="bache">Bâche</option>
                        <option value="panneau">Panneau</option>
                    </select>
                    <ErrorText value={form.errors.support} />
                </div>
                <div>
                    <FieldLabel
                        htmlFor="plv-quantity"
                        icon={PackageCheck}
                        required
                    >
                        Quantité
                    </FieldLabel>
                    <input
                        id="plv-quantity"
                        required
                        type="number"
                        min="1"
                        max="10000"
                        step="1"
                        value={form.data.quantity}
                        onChange={(event) =>
                            form.setData('quantity', Number(event.target.value))
                        }
                        className="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    />
                    <ErrorText value={form.errors.quantity} />
                </div>
                <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="btn btn-secondary"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn btn-primary"
                    >
                        <Check className="size-4" /> Préparer la commande
                    </button>
                </div>
            </form>
        </ModalFrame>
    );
}

function DataTable<T>({
    columns,
    data,
    emptyTitle,
    emptyMessage,
    icon,
    footer,
}: {
    columns: TableColumn<T>[];
    data: T[];
    emptyTitle: string;
    emptyMessage: string;
    icon: typeof ClipboardList;
    footer?: React.ReactNode;
}) {
    return (
        <KoraTable
            variant="cash"
            columns={columns}
            data={data}
            rowKey={(row) => (row as { uuid: string }).uuid}
            emptyTitle={emptyTitle}
            emptyMessage={emptyMessage}
            emptyIcon={icon}
            showHeader={false}
            footer={footer}
        />
    );
}

export function KoraPointFieldOperationsPanels({
    pointUuid,
    visits,
    plvOrders,
    visitAssignees,
    canManage,
    kind,
}: Props) {
    const [visitModalOpen, setVisitModalOpen] = useState(false);
    const [plvModalOpen, setPlvModalOpen] = useState(false);

    const visitColumns: TableColumn<PointVisit>[] = [
        {
            key: 'visited_at',
            header: 'Date',
            render: (visit) => dateLabel(visit.visited_at),
        },
        {
            key: 'type',
            header: 'Type',
            render: (visit) => (
                <span className="font-semibold">
                    {visitLabels[visit.type] ?? visit.type}
                </span>
            ),
        },
        {
            key: 'score',
            header: 'Note',
            align: 'right',
            render: (visit) => (
                <span className="font-bold tabular-nums">{visit.score}/10</span>
            ),
        },
        {
            key: 'status',
            header: 'Suivi',
            render: (visit) => (
                <span className="rounded-full bg-muted px-2 py-1 text-[0.68rem] font-semibold">
                    {statusLabels[visit.status] ?? visit.status}
                </span>
            ),
        },
        {
            key: 'report',
            header: 'Compte rendu',
            render: (visit) => (
                <details className="max-w-[26rem]">
                    <summary className="cursor-pointer font-medium text-primary">
                        Lire le compte rendu
                    </summary>
                    <p className="mt-2 text-xs leading-5 whitespace-pre-wrap text-muted-foreground">
                        {visit.report}
                    </p>
                    {visit.photos.length ? (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {visit.photos.map((photo) => (
                                <a
                                    key={photo.uuid}
                                    href={photo.download_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-md border border-border px-2 py-1 text-xs text-primary hover:bg-muted"
                                >
                                    {photo.name}
                                </a>
                            ))}
                        </div>
                    ) : null}
                    {visit.actions.length ? (
                        <ul className="mt-3 space-y-2 border-t border-border pt-2">
                            {visit.actions.map((action) => (
                                <li
                                    key={action.uuid}
                                    className="rounded-lg bg-muted/60 p-2 text-xs"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <strong>{action.label}</strong>
                                        <span>
                                            {statusLabels[action.status] ??
                                                action.status}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-muted-foreground">
                                        Responsable :{' '}
                                        {action.assignee_name ?? '—'} · Échéance
                                        : {dateLabel(action.due_date)}
                                    </p>
                                    {action.realization_note ? (
                                        <p className="mt-1">
                                            Réalisation :{' '}
                                            {action.realization_note}
                                        </p>
                                    ) : null}
                                    {action.review_reason ? (
                                        <p className="mt-1 text-amber-800">
                                            À reprendre : {action.review_reason}
                                        </p>
                                    ) : null}
                                    {canManage &&
                                    action.status === 'realized' ? (
                                        <div className="mt-2 flex gap-2">
                                            <button
                                                type="button"
                                                className="btn btn-primary btn-xs"
                                                onClick={() =>
                                                    router.post(
                                                        koraPoints.visits.actions.review.url(
                                                            {
                                                                visit: visit.uuid,
                                                                action: action.uuid,
                                                            },
                                                        ),
                                                        {
                                                            operation:
                                                                'validate',
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Check className="size-3" />{' '}
                                                Valider
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-secondary btn-xs"
                                                onClick={() => {
                                                    const reason =
                                                        window.prompt(
                                                            'Indiquez le motif du retour à corriger.',
                                                        );

                                                    if (reason) {
                                                        router.post(
                                                            koraPoints.visits.actions.review.url(
                                                                {
                                                                    visit: visit.uuid,
                                                                    action: action.uuid,
                                                                },
                                                            ),
                                                            {
                                                                operation:
                                                                    'return',
                                                                reason,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                Renvoyer
                                            </button>
                                        </div>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </details>
            ),
        },
        {
            key: 'visitor_name',
            header: 'Visiteur',
            render: (visit) => visit.visitor_name ?? '—',
        },
    ];

    const plvColumns: TableColumn<PlvOrder>[] = [
        {
            key: 'created_at',
            header: 'Date',
            render: (order) => dateLabel(order.created_at),
        },
        {
            key: 'support',
            header: 'Support',
            render: (order) => (
                <span className="font-semibold">
                    {supportLabels[order.support] ?? order.support}
                </span>
            ),
        },
        {
            key: 'quantity',
            header: 'Quantité',
            align: 'right',
            render: (order) => (
                <span className="font-bold tabular-nums">{order.quantity}</span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (order) => (
                <span className="rounded-full bg-muted px-2 py-1 text-[0.68rem] font-semibold">
                    {statusLabels[order.status] ?? order.status}
                </span>
            ),
        },
        {
            key: 'delivered_at',
            header: 'Livraison',
            render: (order) =>
                order.delivered_at ? dateLabel(order.delivered_at) : '—',
        },
        {
            key: 'installed_at',
            header: 'Installation',
            render: (order) => dateLabel(order.installed_at),
        },
        {
            key: 'actions',
            header: 'Actions',
            render: (order) =>
                canManage &&
                order.status !== 'installed' &&
                order.status !== 'cancelled' ? (
                    <div className="flex flex-wrap gap-1">
                        {order.status === 'prepared' ? (
                            <button
                                type="button"
                                className="btn btn-secondary btn-xs"
                                onClick={() =>
                                    router.patch(
                                        koraPoints.plvOrders.update.url({
                                            order: order.uuid,
                                        }),
                                        { operation: 'deliver' },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Marquer livrée
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="btn btn-secondary btn-xs"
                                onClick={() =>
                                    router.patch(
                                        koraPoints.plvOrders.update.url({
                                            order: order.uuid,
                                        }),
                                        { operation: 'install' },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Marquer installée
                            </button>
                        )}
                        <button
                            type="button"
                            className="btn btn-ghost btn-xs text-destructive"
                            onClick={() => {
                                const reason = window.prompt(
                                    'Motif obligatoire de l’annulation.',
                                );

                                if (reason) {
                                    router.patch(
                                        koraPoints.plvOrders.update.url({
                                            order: order.uuid,
                                        }),
                                        { operation: 'cancel', reason },
                                        { preserveScroll: true },
                                    );
                                }
                            }}
                        >
                            Annuler
                        </button>
                    </div>
                ) : order.cancellation_reason ? (
                    <span
                        className="max-w-52 text-xs text-muted-foreground"
                        title={order.cancellation_reason}
                    >
                        Motif : {order.cancellation_reason}
                    </span>
                ) : (
                    '—'
                ),
        },
    ];

    const pagination = (page: Paginated<unknown>, label: string) =>
        page.last_page <= 1 ? null : (
            <nav
                aria-label={label}
                className="flex flex-wrap justify-end gap-2 border-t border-border px-4 py-3"
            >
                {page.links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            preserveScroll
                            aria-current={link.active ? 'page' : undefined}
                            className={`rounded-md px-2.5 py-1.5 text-xs font-semibold ${link.active ? 'bg-[var(--kora)] text-white' : 'text-muted-foreground hover:bg-muted'}`}
                        >
                            {link.label
                                .replace(/&laquo;|&raquo;|<[^>]*>/g, '')
                                .replace(/Previous/i, 'Précédent')
                                .replace(/Next/i, 'Suivant')}
                        </Link>
                    ) : null,
                )}
            </nav>
        );

    return (
        <div className="space-y-4">
            <div className="cash-section-intro !my-0">
                <div>
                    <span className="section-kicker">KORA POINT · Terrain</span>
                    <h2
                        id={
                            kind === 'visits'
                                ? 'point-visits-heading'
                                : 'point-plv-heading'
                        }
                    >
                        {kind === 'visits'
                            ? 'Visites terrain'
                            : 'Commandes PLV'}
                    </h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {kind === 'visits'
                            ? 'Suivi des visites, comptes rendus, photos et actions.'
                            : 'Suivi de la préparation, de la livraison et de l’installation des supports.'}
                    </p>
                </div>
            </div>
            {kind === 'visits' ? (
                <section
                    aria-labelledby="point-visits-heading"
                    className="space-y-3"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-muted-foreground">
                            {visits.total} visite(s) enregistrée(s).
                        </p>
                        {canManage ? (
                            <button
                                type="button"
                                className="btn btn-primary btn-sm"
                                onClick={() => setVisitModalOpen(true)}
                            >
                                <Plus className="size-3.5" /> Ajouter une visite
                            </button>
                        ) : null}
                    </div>
                    <DataTable
                        columns={visitColumns}
                        data={visits.data}
                        emptyTitle="Aucune visite"
                        emptyMessage="Les visites réalisées sur ce Point apparaîtront ici."
                        icon={ClipboardList}
                        footer={pagination(visits, 'Pagination des visites')}
                    />
                </section>
            ) : null}
            {kind === 'plv' ? (
                <section
                    aria-labelledby="point-plv-heading"
                    className="space-y-3"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-muted-foreground">
                            {plvOrders.total} commande(s) enregistrée(s).
                        </p>
                        {canManage ? (
                            <button
                                type="button"
                                className="btn btn-primary btn-sm"
                                onClick={() => setPlvModalOpen(true)}
                            >
                                <Plus className="size-3.5" /> Nouvelle commande
                            </button>
                        ) : null}
                    </div>
                    <DataTable
                        columns={plvColumns}
                        data={plvOrders.data}
                        emptyTitle="Aucune commande PLV"
                        emptyMessage="Les commandes de supports PLV apparaîtront ici."
                        icon={Megaphone}
                        footer={pagination(
                            plvOrders,
                            'Pagination des commandes PLV',
                        )}
                    />
                </section>
            ) : null}
            {visitModalOpen ? (
                <VisitModal
                    pointUuid={pointUuid}
                    assignees={visitAssignees}
                    onClose={() => setVisitModalOpen(false)}
                />
            ) : null}
            {plvModalOpen ? (
                <PlvModal
                    pointUuid={pointUuid}
                    onClose={() => setPlvModalOpen(false)}
                />
            ) : null}
        </div>
    );
}

export function FieldOperationReadOnlyTable({
    kind,
    visits,
    plvOrders,
    page,
}: {
    kind: 'visits' | 'plv';
    visits?: Paginated<PointVisit>;
    plvOrders?: Paginated<PlvOrder>;
    page?: Paginated<unknown>;
}) {
    if (kind === 'visits' && visits) {
        return (
            <DataTable
                columns={[
                    {
                        key: 'point_name',
                        header: 'Point',
                        render: (visit) => (
                            <span className="font-semibold">
                                {visit.point_name ?? '—'}
                            </span>
                        ),
                    },
                    {
                        key: 'visited_at',
                        header: 'Date',
                        render: (visit) => dateLabel(visit.visited_at),
                    },
                    {
                        key: 'type',
                        header: 'Type',
                        render: (visit) =>
                            visitLabels[visit.type] ?? visit.type,
                    },
                    {
                        key: 'score',
                        header: 'Note',
                        align: 'right',
                        render: (visit) => `${visit.score}/10`,
                    },
                    {
                        key: 'report',
                        header: 'Compte rendu',
                        render: (visit) => (
                            <details className="max-w-sm">
                                <summary className="cursor-pointer text-primary">
                                    Lire
                                </summary>
                                <p className="mt-2 text-xs leading-5 whitespace-pre-wrap">
                                    {visit.report}
                                </p>
                                {visit.photos.map((photo) => (
                                    <a
                                        key={photo.uuid}
                                        className="ml-2 text-xs text-primary underline"
                                        href={photo.download_url}
                                    >
                                        {photo.name}
                                    </a>
                                ))}
                                <ul className="mt-2 space-y-1">
                                    {visit.actions.map((action) => (
                                        <li
                                            key={action.uuid}
                                            className="text-xs"
                                        >
                                            {action.label} ·{' '}
                                            {statusLabels[action.status] ??
                                                action.status}
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        ),
                    },
                ]}
                data={visits.data}
                emptyTitle="Aucune visite"
                emptyMessage="Les visites des Points de votre organisation apparaîtront ici."
                icon={ClipboardList}
                footer={
                    page ? (
                        <PaginationFooter
                            page={page}
                            label="Pagination des visites"
                        />
                    ) : null
                }
            />
        );
    }

    if (plvOrders) {
        return (
            <DataTable
                columns={[
                    {
                        key: 'point_name',
                        header: 'Point',
                        render: (order) => (
                            <span className="font-semibold">
                                {order.point_name ?? '—'}
                            </span>
                        ),
                    },
                    {
                        key: 'created_at',
                        header: 'Commande',
                        render: (order) => dateLabel(order.created_at),
                    },
                    {
                        key: 'support',
                        header: 'Support',
                        render: (order) =>
                            supportLabels[order.support] ?? order.support,
                    },
                    {
                        key: 'quantity',
                        header: 'Quantité',
                        align: 'right',
                        render: (order) => order.quantity,
                    },
                    {
                        key: 'status',
                        header: 'Statut',
                        render: (order) =>
                            statusLabels[order.status] ?? order.status,
                    },
                    {
                        key: 'delivered_at',
                        header: 'Livraison',
                        render: (order) => dateLabel(order.delivered_at),
                    },
                    {
                        key: 'installed_at',
                        header: 'Installation',
                        render: (order) => dateLabel(order.installed_at),
                    },
                    {
                        key: 'cancellation_reason',
                        header: 'Motif d’annulation',
                        render: (order) => order.cancellation_reason ?? '—',
                    },
                ]}
                data={plvOrders.data}
                emptyTitle="Aucune commande PLV"
                emptyMessage="Les commandes des Points de votre organisation apparaîtront ici."
                icon={Megaphone}
                footer={
                    page ? (
                        <PaginationFooter
                            page={page}
                            label="Pagination des commandes PLV"
                        />
                    ) : null
                }
            />
        );
    }

    return null;
}
