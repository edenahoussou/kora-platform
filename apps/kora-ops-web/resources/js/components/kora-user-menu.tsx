import { Link } from '@inertiajs/react';
import {
    ChevronRight,
    Laptop,
    LogOut,
    MapPin,
    ShieldCheck,
    SlidersHorizontal,
    User as UserIcon,
} from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

interface KoraUserMenuContentProps {
    user: User;
    onLogout: () => void;
    onNavigate?: () => void;
}

const ROLE_LABELS: Record<string, string> = {
    system_admin: 'Administrateur système',
    general_management: 'Direction générale',
    operations_director: 'Directeur des opérations',
    commercial_director: 'Directeur commercial',
    daf: 'Direction admin. & financière',
    agency_manager: "Chef d'agence",
    agency_agent: "Agent d'agence",
    bus_driver: 'Conducteur',
    conductor: 'Convoyeur',
    mechanic: 'Mécanicien',
    passenger: 'Passager',
};

/**
 * Contenu unifié du menu déroulant utilisateur KORA OPS.
 * Partagé entre la Topbar (menu vers le bas) et la Sidebar (menu vers le haut).
 */
export function KoraUserMenuContent({
    user,
    onLogout,
    onNavigate,
}: KoraUserMenuContentProps) {
    const getInitials = useInitials();
    const initials = user?.name ? getInitials(user.name) : 'KO';

    // Rôle opérationnel déduit depuis auth.user.roles (liste de codes string)
    const firstRoleCode = Array.isArray(user?.roles) ? user.roles[0] : null;
    const roleLabel = firstRoleCode
        ? (ROLE_LABELS[firstRoleCode] ?? firstRoleCode)
        : 'Agent Réseau';

    const agency = (user as { agency?: { name?: string } | null })?.agency;
    const agencyName = agency?.name ?? 'Réseau KORA';

    return (
        <div className="w-72 sm:w-80">
            {/* Header d'identité riche Navy/Gold */}
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="relative overflow-hidden rounded-xl bg-gradient-to-br from-[var(--kora)] to-[#002d75] p-3.5 text-white shadow-sm">
                    {/* Filigrane géométrique discret */}
                    <div className="pointer-events-none absolute -right-6 -bottom-6 size-24 rounded-full border border-gold/20" />

                    <div className="relative z-10 flex items-center gap-3">
                        <Avatar className="size-11 shrink-0 rounded-xl border border-gold/40 shadow-sm">
                            <AvatarImage
                                src={user?.avatar ?? undefined}
                                alt={user?.name}
                            />
                            <AvatarFallback className="rounded-xl bg-gold font-mono text-xs font-black text-[var(--kora)]">
                                {initials}
                            </AvatarFallback>
                        </Avatar>

                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-1.5">
                                <h4 className="truncate text-sm font-extrabold tracking-tight text-white">
                                    {user?.name ?? 'Utilisateur'}
                                </h4>
                                <span
                                    className="size-2 shrink-0 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"
                                    title="Session active"
                                />
                            </div>

                            <p className="truncate text-[11px] text-white/75">
                                {user?.email ?? ''}
                            </p>

                            <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <KoraStatusBadge
                                    label={roleLabel}
                                    tone="gold"
                                    className="border border-gold/40 bg-gold/20 font-mono text-[9px] tracking-wider text-gold uppercase"
                                />
                                {agencyName && (
                                    <span className="inline-flex items-center gap-1 text-[10px] text-white/70">
                                        <MapPin className="size-2.5 text-gold" />
                                        <span className="max-w-[120px] truncate">
                                            {agencyName}
                                        </span>
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </DropdownMenuLabel>

            <DropdownMenuSeparator className="my-1.5 border-border/70" />

            {/* Raccourcis opérationnels vers le hub profil */}
            <DropdownMenuGroup className="space-y-0.5">
                <DropdownMenuItem asChild>
                    <Link
                        href={edit().url}
                        onClick={onNavigate}
                        className="group flex cursor-pointer items-center justify-between rounded-xl px-2.5 py-2 text-xs font-semibold text-foreground transition-colors hover:bg-muted/70"
                    >
                        <div className="flex items-center gap-2.5">
                            <span className="grid size-7 place-items-center rounded-lg bg-gold/15 text-gold transition-colors group-hover:bg-gold group-hover:text-kora">
                                <UserIcon className="size-3.5" />
                            </span>
                            <div>
                                <span className="block text-xs font-bold text-foreground">
                                    Mon profil opérationnel
                                </span>
                                <span className="block text-[10px] text-muted-foreground">
                                    Coordonnées, fonction & rattachement
                                </span>
                            </div>
                        </div>
                        <ChevronRight className="size-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    <Link
                        href={`${edit().url}?tab=security`}
                        onClick={onNavigate}
                        className="group flex cursor-pointer items-center justify-between rounded-xl px-2.5 py-2 text-xs font-semibold text-foreground transition-colors hover:bg-muted/70"
                    >
                        <div className="flex items-center gap-2.5">
                            <span className="grid size-7 place-items-center rounded-lg bg-digital/10 text-digital transition-colors group-hover:bg-digital group-hover:text-white">
                                <ShieldCheck className="size-3.5" />
                            </span>
                            <div>
                                <span className="block text-xs font-bold text-foreground">
                                    Sécurité & 2FA / Passkeys
                                </span>
                                <span className="block text-[10px] text-muted-foreground">
                                    Mot de passe, FIDO2 & double facteur
                                </span>
                            </div>
                        </div>
                        <ChevronRight className="size-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    <Link
                        href={`${edit().url}?tab=sessions`}
                        onClick={onNavigate}
                        className="group flex cursor-pointer items-center justify-between rounded-xl px-2.5 py-2 text-xs font-semibold text-foreground transition-colors hover:bg-muted/70"
                    >
                        <div className="flex items-center gap-2.5">
                            <span className="grid size-7 place-items-center rounded-lg bg-muted text-muted-foreground transition-colors group-hover:bg-foreground group-hover:text-background">
                                <Laptop className="size-3.5" />
                            </span>
                            <div>
                                <span className="block text-xs font-bold text-foreground">
                                    Postes & Sessions actives
                                </span>
                                <span className="block text-[10px] text-muted-foreground">
                                    Navigateurs, terminaux P501 & appareils
                                </span>
                            </div>
                        </div>
                        <ChevronRight className="size-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    <Link
                        href={`${edit().url}?tab=preferences`}
                        onClick={onNavigate}
                        className="group flex cursor-pointer items-center justify-between rounded-xl px-2.5 py-2 text-xs font-semibold text-foreground transition-colors hover:bg-muted/70"
                    >
                        <div className="flex items-center gap-2.5">
                            <span className="grid size-7 place-items-center rounded-lg bg-muted text-muted-foreground transition-colors group-hover:bg-foreground group-hover:text-background">
                                <SlidersHorizontal className="size-3.5" />
                            </span>
                            <div>
                                <span className="block text-xs font-bold text-foreground">
                                    Préférences & Affichage
                                </span>
                                <span className="block text-[10px] text-muted-foreground">
                                    Thème sombre, langue & alertes
                                </span>
                            </div>
                        </div>
                        <ChevronRight className="size-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>

            <DropdownMenuSeparator className="my-1.5 border-border/70" />

            {/* Bouton de déconnexion sécurisé */}
            <DropdownMenuItem asChild>
                <button
                    type="button"
                    onClick={onLogout}
                    data-test="logout-button"
                    className="group flex w-full cursor-pointer items-center justify-between rounded-xl px-2.5 py-2 text-xs font-bold text-destructive transition-colors hover:bg-destructive/10"
                >
                    <div className="flex items-center gap-2.5">
                        <span className="grid size-7 place-items-center rounded-lg bg-destructive/10 text-destructive transition-colors group-hover:bg-destructive group-hover:text-white">
                            <LogOut className="size-3.5" />
                        </span>
                        <span>Se déconnecter</span>
                    </div>
                    <span className="rounded-full bg-destructive/10 px-2 py-0.5 font-mono text-[9px] font-extrabold text-destructive">
                        Clôture
                    </span>
                </button>
            </DropdownMenuItem>
        </div>
    );
}
