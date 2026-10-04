import { useForm } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    Check,
    CheckCircle2,
    Copy,
    Globe,
    Info,
    Lock,
    LockKeyhole,
    Mail,
    MapPin,
    Phone,
    Save,
    ShieldAlert,
    User as UserIcon,
    X,
} from 'lucide-react';
import { useState } from 'react';
import 'react-phone-number-input/style.css';
import { toast } from 'sonner';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import {
    SettingsField,
    SettingsPhoneField,
    SettingsSection,
    SettingsSelectField,
} from '@/components/administration/settings/settings-ui';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { PanelAlert } from '@/components/panel-alert';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { UserProfileData } from './profile-types';

interface ProfileGeneralTabProps {
    profile: UserProfileData;
    isEditing: boolean;
    onSaveProfile: () => void;
    onCancelEdit: () => void;
}

const timezoneOptions = [
    { value: 'Africa/Porto-Novo', label: 'Africa/Porto-Novo (GMT+1)' },
    { value: 'Africa/Lagos', label: 'Africa/Lagos (GMT+1)' },
    { value: 'Africa/Abidjan', label: 'Africa/Abidjan (GMT)' },
    { value: 'Africa/Dakar', label: 'Africa/Dakar (GMT)' },
    { value: 'Africa/Accra', label: 'Africa/Accra (GMT)' },
    { value: 'Africa/Douala', label: 'Africa/Douala (GMT+1)' },
    { value: 'Europe/Paris', label: 'Europe/Paris (CET)' },
    { value: 'UTC', label: 'UTC' },
];

/**
 * Onglet Informations Générales KORA OPS avec icônes de labels, hints et alertes.
 */
export function ProfileGeneralTab({
    profile,
    isEditing,
    onSaveProfile,
    onCancelEdit,
}: ProfileGeneralTabProps) {
    const [copiedId, setCopiedId] = useState(false);

    const form = useForm({
        name: profile.name,
        phone: profile.phone ?? '',
        job_title: profile.jobTitle ?? '',
        department: profile.department ?? '',
        location: profile.location ?? '',
        timezone: profile.timezone ?? 'Africa/Porto-Novo',
        password: '',
    });

    const phoneChanged = (form.data.phone || '') !== (profile.phone ?? '');

    const handleCopyId = () => {
        navigator.clipboard.writeText(profile.uuid);
        setCopiedId(true);
        toast.success('Identifiant KORA copié', {
            description: profile.uuid,
        });
        setTimeout(() => setCopiedId(false), 2000);
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((data) => ({
            name: data.name,
            phone: data.phone || null,
            job_title: data.job_title || null,
            department: data.department || null,
            location: data.location || null,
            timezone: data.timezone || null,
            ...(phoneChanged ? { password: data.password } : {}),
        }));

        form.patch(ProfileController.update.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('password');
                onSaveProfile();
            },
        });
    };

    return (
        <form onSubmit={handleSave} className="space-y-6">
            {/* Alerte contextuelle d'audit & gouvernance */}
            <PanelAlert tone="info" icon={Info}>
                <span className="font-bold">Gouvernance de l'identité : </span>
                Les informations professionnelles sont visibles sur les
                manifestes de départ et la console de régulation. L'adresse
                email officielle est verrouillée au niveau du compte racine.
            </PanelAlert>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* Formulaire Principal (8 cols) */}
                <div className="space-y-6 lg:col-span-8">
                    <SettingsSection
                        title="Coordonnées professionnelles"
                        description="Informations opérationnelles associées à vos actions sur le réseau."
                    >
                        <div className="space-y-5">
                            {/* Nom & Prénoms */}
                            {isEditing ? (
                                <SettingsField
                                    label="Nom & Prénoms complets"
                                    icon={UserIcon}
                                    value={form.data.name}
                                    onChange={(v) => form.setData('name', v)}
                                    error={form.errors.name}
                                    disabled={form.processing}
                                    required
                                    placeholder="Ex: Eden Ahoussou"
                                    hint="Nom officiel figurant sur les feuilles de route, reçus de vente et rapports d'exploitation."
                                />
                            ) : (
                                <div className="grid content-start gap-1.5">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                        <UserIcon className="size-3.5 text-muted-foreground" />
                                        Nom & Prénoms complets
                                    </span>
                                    <div className="flex items-center justify-between rounded-[10px] border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-semibold text-foreground">
                                        <span>{profile.name}</span>
                                        <KoraStatusBadge
                                            label="Identité validée"
                                            tone="blue"
                                        />
                                    </div>
                                    <span className="text-[11px] leading-4 text-muted-foreground">
                                        Nom officiel figurant sur les feuilles
                                        de route, reçus de vente et rapports
                                        d'exploitation.
                                    </span>
                                </div>
                            )}

                            {/* Téléphone Professionnel */}
                            {isEditing ? (
                                <SettingsPhoneField
                                    label="Téléphone mobile professionnel"
                                    icon={Phone}
                                    value={form.data.phone}
                                    onChange={(v) =>
                                        form.setData('phone', v ?? '')
                                    }
                                    error={form.errors.phone}
                                    disabled={form.processing}
                                    hint="Ligne mobile utilisée pour les alertes d'exploitation urgentes et la coordination réseau."
                                />
                            ) : (
                                <div className="grid content-start gap-1.5">
                                    <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                        <Phone className="size-3.5 text-muted-foreground" />
                                        Téléphone mobile professionnel
                                    </span>
                                    <div className="flex items-center justify-between rounded-[10px] border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                        <div className="flex items-center gap-2">
                                            <Phone className="size-4 text-digital" />
                                            <span>{profile.phone ?? '—'}</span>
                                        </div>
                                        {profile.phone && (
                                            <KoraStatusBadge
                                                label="Ligne active"
                                                tone="blue"
                                            />
                                        )}
                                    </div>
                                    <span className="text-[11px] leading-4 text-muted-foreground">
                                        Ligne mobile utilisée pour les alertes
                                        d'exploitation urgentes et la
                                        coordination réseau.
                                    </span>
                                </div>
                            )}

                            {/* Poste & Département */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {isEditing ? (
                                    <SettingsField
                                        label="Fonction / Rôle"
                                        icon={Briefcase}
                                        value={form.data.job_title}
                                        onChange={(v) =>
                                            form.setData('job_title', v)
                                        }
                                        error={form.errors.job_title}
                                        disabled={form.processing}
                                        placeholder="Ex: Superviseur d'Exploitation"
                                        hint="Titre du poste opérationnel."
                                    />
                                ) : (
                                    <div className="grid content-start gap-1.5">
                                        <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                            <Briefcase className="size-3.5 text-muted-foreground" />
                                            Fonction / Rôle
                                        </span>
                                        <div className="rounded-[10px] border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                            {profile.jobTitle ?? '—'}
                                        </div>
                                        <span className="text-[11px] leading-4 text-muted-foreground">
                                            Titre du poste opérationnel.
                                        </span>
                                    </div>
                                )}

                                {isEditing ? (
                                    <SettingsField
                                        label="Direction de rattachement"
                                        icon={Building2}
                                        value={form.data.department}
                                        onChange={(v) =>
                                            form.setData('department', v)
                                        }
                                        error={form.errors.department}
                                        disabled={form.processing}
                                        placeholder="Ex: Direction des Opérations"
                                        hint="Entité organisationnelle."
                                    />
                                ) : (
                                    <div className="grid content-start gap-1.5">
                                        <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                            <Building2 className="size-3.5 text-muted-foreground" />
                                            Direction de rattachement
                                        </span>
                                        <div className="rounded-[10px] border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                            {profile.department ?? '—'}
                                        </div>
                                        <span className="text-[11px] leading-4 text-muted-foreground">
                                            Entité organisationnelle.
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Site & Fuseau Horaire */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {isEditing ? (
                                    <SettingsField
                                        label="Gare ou siège de rattachement"
                                        icon={MapPin}
                                        value={form.data.location}
                                        onChange={(v) =>
                                            form.setData('location', v)
                                        }
                                        error={form.errors.location}
                                        disabled={form.processing}
                                        placeholder="Ex: Cotonou, Gare Principale"
                                        hint="Site physique principal d'affectation."
                                    />
                                ) : (
                                    <div className="grid content-start gap-1.5">
                                        <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                            <MapPin className="size-3.5 text-muted-foreground" />
                                            Gare ou siège de rattachement
                                        </span>
                                        <div className="flex items-center gap-2 rounded-[10px] border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                            <MapPin className="size-4 text-muted-foreground" />
                                            <span className="truncate">
                                                {profile.location ??
                                                    profile.agency}
                                            </span>
                                        </div>
                                        <span className="text-[11px] leading-4 text-muted-foreground">
                                            Site physique principal
                                            d'affectation.
                                        </span>
                                    </div>
                                )}

                                {isEditing ? (
                                    <SettingsSelectField
                                        label="Fuseau horaire d'exploitation"
                                        icon={Globe}
                                        value={form.data.timezone}
                                        onChange={(v) =>
                                            form.setData('timezone', v)
                                        }
                                        error={form.errors.timezone}
                                        disabled={form.processing}
                                        options={timezoneOptions}
                                        hint="Référence horaire pour les départs et clôtures."
                                    />
                                ) : (
                                    <div className="grid content-start gap-1.5">
                                        <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                            <Globe className="size-3.5 text-muted-foreground" />
                                            Fuseau horaire d'exploitation
                                        </span>
                                        <div className="flex items-center gap-2 rounded-[10px] border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                            <Globe className="size-4 text-muted-foreground" />
                                            <span>
                                                {profile.timezone ??
                                                    'Africa/Porto-Novo'}
                                            </span>
                                        </div>
                                        <span className="text-[11px] leading-4 text-muted-foreground">
                                            Référence horaire pour les départs
                                            et clôtures.
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Confirmation par mot de passe — exigée si le téléphone change */}
                            {isEditing && phoneChanged && (
                                <div className="space-y-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4">
                                    <div className="flex items-start gap-2.5">
                                        <ShieldAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                        <p className="text-[11px] leading-relaxed text-amber-800 dark:text-amber-300">
                                            <span className="font-bold">
                                                Action sensible détectée :{' '}
                                            </span>
                                            la modification du numéro de
                                            téléphone sert aux alertes et à la
                                            vérification d'identité. Confirmez
                                            votre mot de passe pour autoriser ce
                                            changement.
                                        </p>
                                    </div>
                                    <div className="grid content-start gap-1.5">
                                        <Label
                                            htmlFor="password"
                                            className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                                        >
                                            <LockKeyhole className="size-3.5 text-muted-foreground" />
                                            <span>
                                                Mot de passe de confirmation
                                            </span>
                                            <span className="rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-extrabold tracking-wide text-amber-700 dark:text-amber-300">
                                                Requis
                                            </span>
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            value={form.data.password}
                                            onChange={(e) =>
                                                form.setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                            autoComplete="current-password"
                                            className="h-10 rounded-[10px]"
                                            placeholder="Saisissez votre mot de passe"
                                        />
                                        {form.errors.password && (
                                            <span className="text-[11px] font-semibold text-destructive">
                                                {form.errors.password}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {isEditing && (
                            <footer className="mt-5 flex items-center justify-end gap-3 border-t border-border/70 pt-4">
                                <Button
                                    type="button"
                                    variant="kora-secondary"
                                    size="kora"
                                    onClick={onCancelEdit}
                                >
                                    <X className="size-3.5" />
                                    Annuler
                                </Button>

                                <Button
                                    type="submit"
                                    variant="kora"
                                    size="kora"
                                    disabled={form.processing}
                                >
                                    <Save className="size-3.5" />
                                    {form.processing
                                        ? 'Enregistrement…'
                                        : 'Enregistrer'}
                                </Button>
                            </footer>
                        )}
                    </SettingsSection>
                </div>

                {/* Right Column: Identity Governance & Security (4 cols) */}
                <div className="space-y-6 lg:col-span-4">
                    {/* Carte Email Verrouillé */}
                    <SettingsSection
                        title="Identité officielle"
                        actions={
                            <KoraStatusBadge label="Certifié DSI" tone="navy" />
                        }
                    >
                        <div className="space-y-4">
                            <div className="space-y-1 rounded-xl border border-border bg-muted/40 p-3.5">
                                <span className="flex items-center gap-1.5 text-[10px] font-bold text-muted-foreground uppercase">
                                    <Mail className="size-3 text-muted-foreground" />
                                    <span>Adresse e-mail certifiée</span>
                                </span>
                                <span className="block truncate font-mono text-xs font-bold text-foreground">
                                    {profile.email}
                                </span>
                                <span className="block pt-1 text-[10px] text-muted-foreground">
                                    Utilisée pour la double authentification et
                                    les notifications d'audit.
                                </span>
                            </div>

                            <div className="space-y-1 rounded-xl border border-amber-500/30 bg-amber-500/5 p-3.5 text-xs text-amber-800 dark:text-amber-300">
                                <div className="flex items-center gap-1.5 font-bold">
                                    <Lock className="size-3.5" />
                                    <span>Modification verrouillée</span>
                                </div>
                                <p className="text-[11px] leading-relaxed text-muted-foreground">
                                    Pour des impératifs de conformité e-MECeF et
                                    d'audit, l'adresse de messagerie ne peut
                                    être modifiée que par l'équipe
                                    d'administration système.
                                </p>
                            </div>

                            <div className="flex items-center justify-between rounded-xl border border-border bg-muted/20 px-3 py-2 text-xs">
                                <div>
                                    <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                        Identifiant unique KORA (UUID)
                                    </span>
                                    <span className="font-mono text-[11px] text-foreground">
                                        {profile.uuid}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    onClick={handleCopyId}
                                    className="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    title="Copier l'UUID"
                                >
                                    {copiedId ? (
                                        <Check className="size-3.5 text-emerald-500" />
                                    ) : (
                                        <Copy className="size-3.5" />
                                    )}
                                </button>
                            </div>
                        </div>
                    </SettingsSection>

                    {/* Carte Permissions */}
                    <SettingsSection
                        title="Habilitations actives"
                        actions={
                            <span className="font-mono text-xs text-muted-foreground">
                                {profile.permissions.length} actives
                            </span>
                        }
                    >
                        <ul className="-m-5 max-h-80 divide-y divide-border/60 overflow-y-auto">
                            {profile.permissions.map((perm) => (
                                <li
                                    key={perm.code}
                                    className="flex items-center justify-between px-5 py-3 text-xs"
                                >
                                    <div className="flex items-center gap-2">
                                        <CheckCircle2 className="size-3.5 shrink-0 text-emerald-500" />
                                        <span className="font-medium text-foreground">
                                            {perm.label}
                                        </span>
                                    </div>
                                    <KoraStatusBadge
                                        label={perm.category}
                                        tone="muted"
                                    />
                                </li>
                            ))}
                        </ul>
                    </SettingsSection>
                </div>
            </div>
        </form>
    );
}
