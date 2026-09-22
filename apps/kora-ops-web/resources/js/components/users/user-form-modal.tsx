import { useForm } from '@inertiajs/react';
import {
    Building2,
    Check,
    Info,
    Shield,
    UserCheck,
    UserCog,
    UserPlus,
    X,
} from 'lucide-react';
import { useEffect, useMemo } from 'react';
import PhoneInput from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import users from '@/routes/users';
import type {
    AdministrationAgency,
    AdministrationRole,
    AdministrationUser,
} from '@/types/administration';

type Props = {
    open: boolean;
    user: AdministrationUser | null;
    roles: AdministrationRole[];
    agencies: AdministrationAgency[];
    onOpenChange: (open: boolean) => void;
};

type FormValues = {
    name: string;
    email: string;
    phone: string;
    country_code: string;
    birth_date: string;
    status: 'active' | 'suspended';
    role: string;
    agency_uuid: string;
};

const emptyValues: FormValues = {
    name: '',
    email: '',
    phone: '',
    country_code: 'BJ',
    birth_date: '',
    status: 'active',
    role: '',
    agency_uuid: '',
};

function valuesFromUser(user: AdministrationUser | null): FormValues {
    if (!user) {
        return emptyValues;
    }

    return {
        name: user.name,
        email: user.email,
        phone: user.phone ?? '',
        country_code: user.country_code ?? 'BJ',
        birth_date: user.birth_date ?? '',
        status: user.status === 'locked' ? 'active' : user.status,
        role: user.role?.code ?? '',
        agency_uuid: user.agency?.uuid ?? '',
    };
}

export function UserFormModal({
    open,
    user,
    roles,
    agencies,
    onOpenChange,
}: Props) {
    const isEditing = Boolean(user);
    const form = useForm<FormValues>(valuesFromUser(user));

    useEffect(() => {
        if (open) {
            form.setData(valuesFromUser(user));
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user?.uuid]);

    const selectedRole = useMemo(
        () => roles.find((r) => r.code === form.data.role) ?? null,
        [roles, form.data.role],
    );

    const requiresAgency = selectedRole
        ? selectedRole.category === 'agency' ||
        selectedRole.category === 'terrain'
        : false;

    const forbidsAgency = selectedRole
        ? selectedRole.category === 'central'
        : false;

    // Quand le rôle change, si central, vider agency_uuid
    const handleRoleChange = (roleCode: string) => {
        const found = roles.find((r) => r.code === roleCode);
        form.setData((data) => ({
            ...data,
            role: roleCode,
            agency_uuid: found?.category === 'central' ? '' : data.agency_uuid,
        }));
    };

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

        form.transform((data) => ({
            ...data,
            phone: data.phone.trim() || null,
            country_code: data.country_code.trim().toUpperCase() || null,
            birth_date: data.birth_date || null,
            agency_uuid: forbidsAgency ? null : data.agency_uuid || null,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEditing && user) {
            form.patch(users.update.url({ user: user.uuid }), options);
        } else {
            form.post(users.store.url(), options);
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
                className="cash-modal cash-opening-modal max-w-[42rem]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="user-modal-title"
                onSubmit={onSubmit}
            >
                {/* Header */}
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            {isEditing ? (
                                <UserCog className="size-5 text-gold" />
                            ) : (
                                <UserPlus className="size-5 text-gold" />
                            )}
                        </span>
                        <div>
                            <span className="section-kicker">
                                Administration · Utilisateurs
                            </span>
                            <h3 id="user-modal-title">
                                {isEditing
                                    ? 'Modifier l’utilisateur'
                                    : 'Nouvel utilisateur OPS'}
                            </h3>
                            <p>
                                {isEditing
                                    ? `Mise à jour du profil et des accès de ${user?.name}.`
                                    : 'Créez un compte opérationnel avec mot de passe temporaire à usage unique.'}
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

                <div className="cash-opening-list">
                    {/* Section 1 : Identité & Contact */}
                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <UserCheck className="size-4" />
                            </span>
                            <div>
                                <strong>Identité & Contact</strong>
                                <small>
                                    Informations personnelles et
                                    professionnelles du collaborateur.
                                </small>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6">
                            <label className="field">
                                <span>
                                    Nom complet{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <input
                                    className="control"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="Ex: Jean Kouassi"
                                    aria-invalid={Boolean(form.errors.name)}
                                    autoComplete="off"
                                    required
                                />
                                {form.errors.name ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.name}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Prénom et nom de famille officiels.
                                    </small>
                                )}
                            </label>

                            <label className="field">
                                <span>
                                    Adresse email{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <input
                                    type="email"
                                    className="control"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    placeholder="j.kouassi@koraexpress.app"
                                    aria-invalid={Boolean(form.errors.email)}
                                    autoComplete="off"
                                    required
                                />
                                {form.errors.email ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.email}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Identifiant principal pour la connexion
                                        Web et mobile.
                                    </small>
                                )}
                            </label>
                        </div>

                        <div>
                            <label className="field">
                                <span>Numéro de téléphone</span>
                                <PhoneInput
                                    className="control phone-field-control"
                                    defaultCountry="BJ"
                                    international
                                    labels={phoneLabels}
                                    value={form.data.phone || undefined}
                                    onChange={(value) =>
                                        form.setData('phone', value ?? '')
                                    }
                                    onCountryChange={(country) => {
                                        if (country) {
                                            form.setData(
                                                'country_code',
                                                country,
                                            );
                                        }
                                    }}
                                    placeholder="+229 97 00 00 00"
                                    numberInputProps={{
                                        autoComplete: 'tel',
                                        'aria-invalid': Boolean(
                                            form.errors.phone,
                                        ),
                                    }}
                                />
                                {form.errors.phone ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.phone}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Format international avec indicatif pays
                                        pour la réception des OTP et alertes.
                                    </small>
                                )}
                            </label>
                        </div>

                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6">
                            <label className="field">
                                <span>Code pays (ISO-2)</span>
                                <input
                                    className="control font-mono uppercase"
                                    maxLength={2}
                                    value={form.data.country_code}
                                    onChange={(e) =>
                                        form.setData(
                                            'country_code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="BJ"
                                    aria-invalid={Boolean(
                                        form.errors.country_code,
                                    )}
                                />
                                {form.errors.country_code ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.country_code}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Code national à 2 lettres synchronisé
                                        avec le pays du numéro (ex: BJ, TG, CI).
                                    </small>
                                )}
                            </label>

                            <label className="field">
                                <span>Date de naissance</span>
                                <input
                                    type="date"
                                    className="control text-xs"
                                    value={form.data.birth_date}
                                    max={new Date().toISOString().split('T')[0]}
                                    onChange={(e) =>
                                        form.setData(
                                            'birth_date',
                                            e.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.birth_date,
                                    )}
                                />
                                {form.errors.birth_date ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.birth_date}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Date de naissance pour la gestion RH et
                                        audit de conformité.
                                    </small>
                                )}
                            </label>
                        </div>
                    </div>

                    {/* Section 2 : Rôle, Agence & Statut */}
                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Shield className="size-4" />
                            </span>
                            <div>
                                <strong>Périmètre & Habilitations</strong>
                                <small>
                                    Rôle opérationnel, affectation géographique
                                    et statut du compte.
                                </small>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-5">
                            <label className="field">
                                <span>
                                    Rôle attribué{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <select
                                    className="control"
                                    value={form.data.role}
                                    onChange={(e) =>
                                        handleRoleChange(e.target.value)
                                    }
                                    aria-invalid={Boolean(form.errors.role)}
                                    required
                                >
                                    <option value="" disabled>
                                        Sélectionner un rôle…
                                    </option>
                                    {roles.map((role) => (
                                        <option
                                            key={role.code}
                                            value={role.code}
                                        >
                                            {role.label} (
                                            {role.category === 'central'
                                                ? 'Siège / Central'
                                                : role.category === 'agency'
                                                    ? 'Agence'
                                                    : 'Terrain'}
                                            )
                                        </option>
                                    ))}
                                </select>
                                {form.errors.role ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.role}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Détermine les autorisations et le niveau
                                        d’accès KORA.
                                    </small>
                                )}
                            </label>

                            <label className="field">
                                <span>
                                    Agence de rattachement
                                    {requiresAgency && (
                                        <b className="ml-1 font-black text-destructive">
                                            *
                                        </b>
                                    )}
                                </span>
                                <select
                                    className="control"
                                    value={form.data.agency_uuid}
                                    disabled={forbidsAgency || !form.data.role}
                                    onChange={(e) =>
                                        form.setData(
                                            'agency_uuid',
                                            e.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.agency_uuid,
                                    )}
                                >
                                    <option value="">
                                        {forbidsAgency
                                            ? 'Non applicable (rôle central)'
                                            : !form.data.role
                                                ? 'Sélectionnez d’abord un rôle…'
                                                : 'Sélectionner une agence…'}
                                    </option>
                                    {agencies.map((agency) => (
                                        <option
                                            key={agency.uuid}
                                            value={agency.uuid}
                                        >
                                            {agency.code} · {agency.name}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.agency_uuid ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.agency_uuid}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        {requiresAgency
                                            ? 'Obligatoire pour les fonctions d’agence et de terrain.'
                                            : forbidsAgency
                                                ? 'Les profils centraux ne sont rattachés à aucune agence physique.'
                                                : 'Sélectionnez un rôle pour déterminer l’obligation d’agence.'}
                                    </small>
                                )}
                            </label>
                        </div>

                        {/* Alertes contextuelles dynamiques */}
                        {selectedRole && (
                            <div className="my-3.5">
                                {requiresAgency && (
                                    <div className="flex items-start gap-2.5 rounded-xl border border-gold/30 bg-gold/10 p-3 text-xs text-foreground">
                                        <Building2 className="mt-0.5 size-4 shrink-0 text-gold" />
                                        <p className="text-[0.6875rem] leading-relaxed">
                                            <strong>
                                                Rôle opérationnel terrain/agence
                                                ({selectedRole.label}) :
                                            </strong>{' '}
                                            L’agence choisie délimitera les
                                            caisses, lignes, départs et ventes
                                            accessibles à cet utilisateur.
                                        </p>
                                    </div>
                                )}
                                {forbidsAgency && (
                                    <div className="flex items-start gap-2.5 rounded-xl border border-blue-500/25 bg-blue-500/10 p-3 text-xs text-foreground">
                                        <Info className="mt-0.5 size-4 shrink-0 text-blue-500" />
                                        <p className="text-[0.6875rem] leading-relaxed">
                                            <strong>
                                                Rôle de direction centrale (
                                                {selectedRole.label}) :
                                            </strong>{' '}
                                            Cet utilisateur opère sur l’ensemble
                                            du réseau national et international
                                            sans restriction d’agence locale.
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-5 pt-1">
                            <label className="field">
                                <span>
                                    Statut initial du compte{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <select
                                    className="control"
                                    value={form.data.status}
                                    onChange={(e) =>
                                        form.setData(
                                            'status',
                                            e.target
                                                .value as FormValues['status'],
                                        )
                                    }
                                    aria-invalid={Boolean(form.errors.status)}
                                    required
                                >
                                    <option value="active">
                                        Actif (Accès autorisé)
                                    </option>
                                    <option value="suspended">
                                        Suspendu (Accès temporairement bloqué)
                                    </option>
                                </select>
                                {form.errors.status ? (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {form.errors.status}
                                    </small>
                                ) : (
                                    <small className="text-muted-foreground">
                                        Un compte actif permet la connexion
                                        immédiate avec le mot de passe
                                        temporaire.
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
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-primary gap-2 font-black shadow-md hover:shadow-lg"
                        disabled={form.processing}
                    >
                        <Check className="size-4" />
                        {form.processing
                            ? 'Enregistrement…'
                            : isEditing
                                ? 'Mettre à jour l’utilisateur'
                                : 'Créer l’utilisateur'}
                    </button>
                </div>
            </form>
        </div>
    );
}
