import {
    Briefcase,
    Building2,
    Check,
    CheckCircle2,
    Copy,
    Globe,
    Info,
    Lock,
    Mail,
    MapPin,
    Phone,
    Save,
    User as UserIcon,
    X,
} from 'lucide-react';
import { useState } from 'react';
import PhoneInput from 'react-phone-number-input';
import 'react-phone-number-input/style.css';
import { toast } from 'sonner';
import { PanelAlert } from '@/components/panel-alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { UserProfileData } from './profile-mock-data';

interface ProfileGeneralTabProps {
    profile: UserProfileData;
    isEditing: boolean;
    onSaveProfile: (updated: Partial<UserProfileData>) => void;
    onCancelEdit: () => void;
}

/**
 * Onglet Informations Générales KORA OPS avec icônes de labels, hints et alertes.
 */
export function ProfileGeneralTab({
    profile,
    isEditing,
    onSaveProfile,
    onCancelEdit,
}: ProfileGeneralTabProps) {
    const [name, setName] = useState(profile.name);
    const [phone, setPhone] = useState(profile.phone);
    const [jobTitle, setJobTitle] = useState(profile.jobTitle);
    const [department, setDepartment] = useState(profile.department);
    const [location, setLocation] = useState(profile.location);
    const [timezone, setTimezone] = useState(profile.timezone);
    const [isSaving, setIsSaving] = useState(false);
    const [copiedId, setCopiedId] = useState(false);

    const handleCopyId = () => {
        navigator.clipboard.writeText(profile.id);
        setCopiedId(true);
        toast.success('Identifiant KORA copié', {
            description: profile.id,
        });
        setTimeout(() => setCopiedId(false), 2000);
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSaving(true);

        setTimeout(() => {
            onSaveProfile({
                name,
                phone: phone || profile.phone,
                jobTitle,
                department,
                location,
                timezone,
            });
            setIsSaving(false);
            toast.success('Profil mis à jour avec succès', {
                description: 'Vos coordonnées ont été enregistrées.',
            });
        }, 300);
    };

    return (
        <form onSubmit={handleSave} className="space-y-6">
            {/* Alerte contextuelle d'audit & gouvernance */}
            <PanelAlert tone="info" icon={Info}>
                <span className="font-bold">Gouvernance de l'identité : </span>
                Les informations professionnelles sont visibles sur les manifestes de départ et la console de régulation. L'adresse email officielle est verrouillée au niveau du compte racine.
            </PanelAlert>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* Formulaire Principal (8 cols) */}
                <div className="space-y-6 lg:col-span-8">
                    <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                        <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                            <div className="flex items-center gap-3">
                                <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                                <div>
                                    <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                                        Coordonnées professionnelles
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Informations opérationnelles associées à vos actions sur le réseau.
                                    </p>
                                </div>
                            </div>
                        </header>

                        <div className="p-5 sm:p-6 space-y-5">
                            {/* Nom & Prénoms */}
                            <div className="space-y-1.5">
                                <Label htmlFor="fullName" className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                    <UserIcon className="size-3.5 text-muted-foreground" />
                                    <span>Nom & Prénoms complets</span>
                                    {isEditing && (
                                        <span className="rounded-full bg-amber-500/10 px-1.5 py-0.2 text-[9px] font-bold text-amber-700 dark:text-amber-300">
                                            Requis
                                        </span>
                                    )}
                                </Label>
                                {isEditing ? (
                                    <Input
                                        id="fullName"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        required
                                        className="h-10 rounded-xl"
                                        placeholder="Ex: Eden Ahoussou"
                                    />
                                ) : (
                                    <div className="flex items-center justify-between rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-semibold text-foreground">
                                        <span>{profile.name}</span>
                                        <Badge variant="outline" className="text-[10px]">
                                            Identité validée
                                        </Badge>
                                    </div>
                                )}
                                <span className="block text-[11px] leading-4 text-muted-foreground">
                                    Nom officiel figurant sur les feuilles de route, reçus de vente et rapports d'exploitation.
                                </span>
                            </div>

                            {/* Téléphone Professionnel */}
                            <div className="space-y-1.5">
                                <Label htmlFor="phone" className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                    <Phone className="size-3.5 text-muted-foreground" />
                                    <span>Téléphone mobile professionnel</span>
                                    {isEditing && (
                                        <span className="rounded-full bg-amber-500/10 px-1.5 py-0.2 text-[9px] font-bold text-amber-700 dark:text-amber-300">
                                            Requis
                                        </span>
                                    )}
                                </Label>
                                {isEditing ? (
                                    <div className="kora-phone-wrapper">
                                        <PhoneInput
                                            international
                                            defaultCountry="BJ"
                                            value={phone}
                                            onChange={(val) => setPhone(val || '')}
                                            className="flex h-10 w-full rounded-xl border border-input bg-background px-3 text-sm font-medium shadow-xs focus-within:ring-2 focus-within:ring-gold"
                                        />
                                    </div>
                                ) : (
                                    <div className="flex items-center justify-between rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                        <div className="flex items-center gap-2">
                                            <Phone className="size-4 text-digital" />
                                            <span>{profile.phone}</span>
                                        </div>
                                        <Badge variant="outline" className="border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-[10px]">
                                            Ligne active
                                        </Badge>
                                    </div>
                                )}
                                <span className="block text-[11px] leading-4 text-muted-foreground">
                                    Ligne mobile utilisée pour les alertes d'exploitation urgentes et la coordination réseau.
                                </span>
                            </div>

                            {/* Poste & Département */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="jobTitle" className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                        <Briefcase className="size-3.5 text-muted-foreground" />
                                        <span>Fonction / Rôle</span>
                                    </Label>
                                    {isEditing ? (
                                        <Input
                                            id="jobTitle"
                                            value={jobTitle}
                                            onChange={(e) => setJobTitle(e.target.value)}
                                            className="h-10 rounded-xl"
                                            placeholder="Ex: Superviseur d'Exploitation"
                                        />
                                    ) : (
                                        <div className="rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                            {profile.jobTitle}
                                        </div>
                                    )}
                                    <span className="block text-[11px] leading-4 text-muted-foreground">
                                        Titre du poste opérationnel.
                                    </span>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="department" className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                        <Building2 className="size-3.5 text-muted-foreground" />
                                        <span>Direction de rattachement</span>
                                    </Label>
                                    {isEditing ? (
                                        <Input
                                            id="department"
                                            value={department}
                                            onChange={(e) => setDepartment(e.target.value)}
                                            className="h-10 rounded-xl"
                                            placeholder="Ex: Direction des Opérations"
                                        />
                                    ) : (
                                        <div className="rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                            {profile.department}
                                        </div>
                                    )}
                                    <span className="block text-[11px] leading-4 text-muted-foreground">
                                        Entité organisationnelle.
                                    </span>
                                </div>
                            </div>

                            {/* Site & Fuseau Horaire */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="location" className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                        <MapPin className="size-3.5 text-muted-foreground" />
                                        <span>Gare ou siège de rattachement</span>
                                    </Label>
                                    {isEditing ? (
                                        <Input
                                            id="location"
                                            value={location}
                                            onChange={(e) => setLocation(e.target.value)}
                                            className="h-10 rounded-xl"
                                            placeholder="Ex: Cotonou, Gare Principale"
                                        />
                                    ) : (
                                        <div className="flex items-center gap-2 rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                            <MapPin className="size-4 text-muted-foreground" />
                                            <span className="truncate">{profile.location}</span>
                                        </div>
                                    )}
                                    <span className="block text-[11px] leading-4 text-muted-foreground">
                                        Site physique principal d'affectation.
                                    </span>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="timezone" className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                        <Globe className="size-3.5 text-muted-foreground" />
                                        <span>Fuseau horaire d'exploitation</span>
                                    </Label>
                                    {isEditing ? (
                                        <Input
                                            id="timezone"
                                            value={timezone}
                                            onChange={(e) => setTimezone(e.target.value)}
                                            className="h-10 rounded-xl"
                                            placeholder="Africa/Porto-Novo (GMT+1)"
                                        />
                                    ) : (
                                        <div className="flex items-center gap-2 rounded-xl border border-border bg-muted/40 px-3.5 py-2.5 text-sm font-medium text-foreground">
                                            <Globe className="size-4 text-muted-foreground" />
                                            <span>{profile.timezone}</span>
                                        </div>
                                    )}
                                    <span className="block text-[11px] leading-4 text-muted-foreground">
                                        Référence horaire pour les départs et clôtures.
                                    </span>
                                </div>
                            </div>
                        </div>

                        {isEditing && (
                            <footer className="flex items-center justify-end gap-3 border-t border-border/70 bg-muted/30 px-5 py-4">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={onCancelEdit}
                                    className="rounded-xl text-xs"
                                >
                                    <X className="mr-1.5 size-4" />
                                    Annuler
                                </Button>

                                <Button
                                    type="submit"
                                    disabled={isSaving}
                                    className="rounded-xl bg-kora text-white hover:bg-[#002d75] dark:bg-gold dark:text-kora text-xs font-bold px-5"
                                >
                                    <Save className="mr-1.5 size-4" />
                                    {isSaving ? 'Enregistrement...' : 'Enregistrer les modifications'}
                                </Button>
                            </footer>
                        )}
                    </section>
                </div>

                {/* Right Column: Identity Governance & Security (4 cols) */}
                <div className="space-y-6 lg:col-span-4">
                    {/* Carte Email Verrouillé */}
                    <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                        <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                            <div className="flex items-center gap-2.5">
                                <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                                <h3 className="text-[14px] font-extrabold tracking-[-0.01em] text-foreground">
                                    Identité officielle
                                </h3>
                            </div>
                            <Badge variant="outline" className="border-emerald-500/30 bg-emerald-500/10 font-mono text-[10px] text-emerald-600 dark:text-emerald-400">
                                Certifié DSI
                            </Badge>
                        </header>

                        <div className="p-5 space-y-4">
                            <div className="rounded-xl border border-border bg-muted/40 p-3.5 space-y-1">
                                <span className="flex items-center gap-1.5 text-[10px] font-bold text-muted-foreground uppercase">
                                    <Mail className="size-3 text-muted-foreground" />
                                    <span>Adresse e-mail certifiée</span>
                                </span>
                                <span className="font-mono text-xs font-bold text-foreground block truncate">
                                    {profile.email}
                                </span>
                                <span className="block text-[10px] text-muted-foreground pt-1">
                                    Utilisée pour la double authentification et les notifications d'audit.
                                </span>
                            </div>

                            <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-3.5 text-xs text-amber-800 dark:text-amber-300 space-y-1">
                                <div className="flex items-center gap-1.5 font-bold">
                                    <Lock className="size-3.5" />
                                    <span>Modification verrouillée</span>
                                </div>
                                <p className="text-[11px] leading-relaxed text-muted-foreground">
                                    Pour des impératifs de conformité e-MECeF et d'audit, l'adresse de messagerie ne peut être modifiée que par l'équipe d'administration système.
                                </p>
                            </div>

                            <div className="flex items-center justify-between rounded-xl border border-border bg-muted/20 px-3 py-2 text-xs">
                                <div>
                                    <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                        Identifiant unique KORA (UUID)
                                    </span>
                                    <span className="font-mono text-[11px] text-foreground">{profile.id}</span>
                                </div>
                                <button
                                    type="button"
                                    onClick={handleCopyId}
                                    className="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
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
                    </section>

                    {/* Carte Permissions */}
                    <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                        <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                            <div className="flex items-center gap-2.5">
                                <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                                <h3 className="text-[14px] font-extrabold tracking-[-0.01em] text-foreground">
                                    Habilitations actives
                                </h3>
                            </div>
                            <span className="font-mono text-xs text-muted-foreground">
                                {profile.permissions.length} actives
                            </span>
                        </header>

                        <ul className="divide-y divide-border/60">
                            {profile.permissions.map((perm) => (
                                <li key={perm.code} className="flex items-center justify-between px-5 py-3 text-xs">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle2 className="size-3.5 text-emerald-500 shrink-0" />
                                        <span className="font-medium text-foreground">{perm.label}</span>
                                    </div>
                                    <Badge variant="outline" className="text-[10px] font-mono shrink-0">
                                        {perm.category}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </form>
    );
}
