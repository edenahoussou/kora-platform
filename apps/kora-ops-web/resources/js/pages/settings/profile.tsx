import { Head, router } from '@inertiajs/react';
import {
    Laptop,
    ShieldCheck,
    SlidersHorizontal,
    User,
} from 'lucide-react';
import { useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { ProfileGeneralTab } from '@/components/profile/profile-general-tab';
import { ProfileHeader } from '@/components/profile/profile-header';
import { ProfilePreferencesTab } from '@/components/profile/profile-preferences-tab';
import { ProfileSecurityTab } from '@/components/profile/profile-security-tab';
import { ProfileSessionsTab } from '@/components/profile/profile-sessions-tab';
import type { ProfilePageProps } from '@/components/profile/profile-types';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/profile';

type ProfileTab = 'general' | 'security' | 'sessions' | 'preferences';

/**
 * Hub Profil Utilisateur KORA OPS.
 */
export default function Profile({
    profile,
    security,
    sessions,
    preferences,
}: ProfilePageProps) {
    const queryTab =
        typeof window !== 'undefined'
            ? (new URLSearchParams(window.location.search).get('tab') as ProfileTab | null)
            : null;

    const [activeTab, setActiveTab] = useState<ProfileTab>(
        queryTab && ['general', 'security', 'sessions', 'preferences'].includes(queryTab)
            ? queryTab
            : 'general',
    );
    const [isEditing, setIsEditing] = useState(false);

    const handleAvatarUpload = async (dataUrl: string) => {
        const response = await fetch(dataUrl);
        const blob = await response.blob();
        const file = new File([blob], 'avatar.png', {
            type: blob.type || 'image/png',
        });

        router.post(
            ProfileController.updateAvatar.url(),
            { avatar: file },
            { forceFormData: true, preserveScroll: true },
        );
    };

    const tabs: Array<{
        id: ProfileTab;
        label: string;
        description: string;
        icon: typeof User;
        badge?: string;
    }> = [
            {
                id: 'general',
                label: 'Informations personnelles',
                description: 'Identité et coordonnées',
                icon: User,
            },
            {
                id: 'security',
                label: 'Sécurité & 2FA',
                description: 'Mot de passe et passkeys',
                icon: ShieldCheck,
                badge: security.twoFactorEnabled ? '2FA Actif' : undefined,
            },
            {
                id: 'sessions',
                label: 'Sessions & Postes',
                description: 'Terminaux et connexions',
                icon: Laptop,
                badge:
                    sessions.length > 0
                        ? `${sessions.length} active${sessions.length > 1 ? 's' : ''}`
                        : undefined,
            },
            {
                id: 'preferences',
                label: 'Préférences',
                description: 'Thème et notifications',
                icon: SlidersHorizontal,
            },
        ];

    return (
        <>
            <Head title="Profil — KORA OPS" />

            <div className="space-y-6">
                {/* 1. KORA OPS Hero Banner */}
                <ProfileHeader
                    profile={profile}
                    isEditing={isEditing}
                    onToggleEdit={() => setIsEditing(!isEditing)}
                    onUpdateAvatar={handleAvatarUpload}
                />

                {/* 2. Navigation Tabs (Identique aux SettingsTabs KORA) */}
                <nav
                    className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1.5 rounded-2xl border border-border bg-card p-1.5 shadow-[0_7px_28px_rgba(0,29,78,0.04)]"
                    aria-label="Navigation profil"
                >
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        const isActive = activeTab === tab.id;

                        return (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => setActiveTab(tab.id)}
                                className={cn(
                                    'group flex items-center gap-3 rounded-xl px-3.5 py-2.5 transition-[background-color,box-shadow,transform] duration-200 outline-none focus-visible:ring-[3px] focus-visible:ring-gold/40 text-left',
                                    isActive
                                        ? 'bg-kora text-white shadow-[0_10px_26px_rgba(0,29,78,0.22)]'
                                        : 'text-foreground hover:-translate-y-px hover:bg-muted/70',
                                )}
                            >
                                <span
                                    className={cn(
                                        'grid size-9 shrink-0 place-items-center rounded-lg transition-colors duration-200',
                                        isActive
                                            ? 'bg-gold text-kora'
                                            : 'bg-muted text-muted-foreground group-hover:bg-gold/15 group-hover:text-gold',
                                    )}
                                >
                                    <Icon className="size-4" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-xs font-extrabold tracking-[-0.01em]">
                                        {tab.label}
                                    </span>
                                    <span
                                        className={cn(
                                            'mt-0.5 block truncate text-[10px] leading-4',
                                            isActive
                                                ? 'text-white/60'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {tab.description}
                                    </span>
                                </span>
                                {isActive && (
                                    <span className="size-1.5 shrink-0 rounded-full bg-gold" />
                                )}
                            </button>
                        );
                    })}
                </nav>

                {/* 3. Tab Content */}
                <div className="pt-2">
                    {activeTab === 'general' && (
                        <ProfileGeneralTab
                            profile={profile}
                            isEditing={isEditing}
                            onSaveProfile={() => setIsEditing(false)}
                            onCancelEdit={() => setIsEditing(false)}
                        />
                    )}

                    {activeTab === 'security' && (
                        <div className="space-y-6">
                            <ProfileSecurityTab security={security} />
                            <DeleteUser />
                        </div>
                    )}

                    {activeTab === 'sessions' && (
                        <ProfileSessionsTab sessions={sessions} />
                    )}

                    {activeTab === 'preferences' && (
                        <ProfilePreferencesTab preferences={preferences} />
                    )}
                </div>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Paramètres',
            href: edit(),
        },
        {
            title: 'Profil',
            href: edit(),
        },
    ],
};
