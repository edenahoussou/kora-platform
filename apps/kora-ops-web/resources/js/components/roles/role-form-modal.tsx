import { useForm } from '@inertiajs/react';
import { AlertCircle, Check, Shield, Tag, X } from 'lucide-react';
import { useEffect } from 'react';
import roles from '@/routes/roles';
import type { AdministrationRole } from '@/types/administration';

type Props = {
    open: boolean;
    role: AdministrationRole | null;
    categories: Array<{ value: AdministrationRole['category']; label: string }>;
    onOpenChange: (open: boolean) => void;
};

type FormValues = {
    label: string;
    description: string;
    category: AdministrationRole['category'];
    permissions: string[];
};

export function RoleFormModal({ open, role, categories, onOpenChange }: Props) {
    const isEditing = Boolean(role);
    const isSystemRole = Boolean(role?.isSystem);

    const form = useForm<FormValues>({
        label: role?.label ?? '',
        description: role?.description ?? '',
        category: role?.category ?? 'agency',
        permissions: role?.permissions?.map((p) => p.code) ?? [],
    });

    useEffect(() => {
        if (open) {
            form.setData({
                label: role?.label ?? '',
                description: role?.description ?? '',
                category: role?.category ?? 'agency',
                permissions: role?.permissions?.map((p) => p.code) ?? [],
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, role?.code]);

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

    const onSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEditing && role) {
            form.patch(roles.update.url({ role: role.code }), options);
        } else {
            form.post(roles.store.url(), options);
        }
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(event) =>
                event.target === event.currentTarget && close()
            }
        >
            <form
                className="cash-modal cash-opening-modal max-w-[36rem]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="role-modal-title"
                onSubmit={onSubmit}
            >
                {/* Header */}
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <Shield className="size-5 text-gold" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Sécurité · Rôles & Accès
                            </span>
                            <h3 id="role-modal-title">
                                {isEditing
                                    ? 'Modifier le rôle'
                                    : 'Nouveau rôle personnalisé'}
                            </h3>
                            <p>
                                {isEditing
                                    ? `Mettez à jour les caractéristiques de ${role?.label}.`
                                    : 'Créez un profil de responsabilités opérationnelles sur-mesure pour KORA OPS.'}
                            </p>
                        </div>
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

                {isSystemRole && (
                    <div className="mb-4 flex items-start gap-2.5 rounded-xl border border-blue-500/30 bg-blue-500/10 p-3.5 text-xs text-blue-700 dark:text-blue-300">
                        <AlertCircle className="mt-0.5 size-4 shrink-0 text-blue-500" />
                        <div>
                            <strong className="block font-bold">
                                Rôle système protégé
                            </strong>
                            <p className="mt-0.5 text-[0.6875rem] leading-relaxed">
                                Les rôles natifs de la plateforme KORA BUS sont
                                immuables. Leurs libellés et périmètres sont
                                fixés par le système.
                            </p>
                        </div>
                    </div>
                )}

                <div className="cash-opening-list">
                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Tag className="size-4" />
                            </span>
                            <div>
                                <strong>Définition du rôle</strong>
                                <small>
                                    Désignation et niveau d’action dans
                                    l’organisation.
                                </small>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6">
                            <label className="field">
                                <span>
                                    Intitulé du rôle{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <input
                                    className="control"
                                    value={form.data.label}
                                    onChange={(e) =>
                                        form.setData('label', e.target.value)
                                    }
                                    placeholder="Ex: Superviseur d’embarquement"
                                    disabled={isSystemRole}
                                    aria-invalid={Boolean(form.errors.label)}
                                    required
                                />
                                {form.errors.label ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.label}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Nom affiché sur les profils et fiches
                                        agents.
                                    </small>
                                )}
                            </label>

                            <label className="field">
                                <span>
                                    Catégorie organisationnelle{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <select
                                    className="control"
                                    value={form.data.category}
                                    disabled={isSystemRole}
                                    onChange={(e) =>
                                        form.setData(
                                            'category',
                                            e.target
                                                .value as AdministrationRole['category'],
                                        )
                                    }
                                    aria-invalid={Boolean(form.errors.category)}
                                    required
                                >
                                    {categories.map((cat) => (
                                        <option
                                            key={cat.value}
                                            value={cat.value}
                                        >
                                            {cat.label}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.category ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.category}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Définit si le rôle requiert une agence
                                        (Terrain/Agence) ou s’exerce au Siège
                                        (Central).
                                    </small>
                                )}
                            </label>
                        </div>

                        <div className="grid grid-cols-1 gap-5 pt-1">
                            <label className="field">
                                <span>Description opérationnelle</span>
                                <textarea
                                    className="control h-24 resize-none"
                                    value={form.data.description}
                                    disabled={isSystemRole}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Décrivez les prérogatives et responsabilités confiées à ce rôle…"
                                    aria-invalid={Boolean(
                                        form.errors.description,
                                    )}
                                />
                                {form.errors.description ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.description}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Texte explicatif pour les
                                        administrateurs et auditeurs.
                                    </small>
                                )}
                            </label>
                        </div>
                    </div>
                </div>

                {/* Footer Buttons */}
                <div className="cash-modal-actions mt-6">
                    <button
                        type="button"
                        onClick={close}
                        className="btn btn-ghost"
                        disabled={form.processing}
                    >
                        {isSystemRole ? 'Fermer' : 'Annuler'}
                    </button>
                    {!isSystemRole && (
                        <button
                            type="submit"
                            className="btn btn-primary gap-2 font-black shadow-md hover:shadow-lg"
                            disabled={form.processing}
                        >
                            <Check className="size-4" />
                            {form.processing
                                ? 'Enregistrement…'
                                : isEditing
                                  ? 'Mettre à jour le rôle'
                                  : 'Créer le rôle'}
                        </button>
                    )}
                </div>
            </form>
        </div>
    );
}
