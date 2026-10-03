import {
    Camera,
    CheckCircle2,
    Edit3,
    Lock,
    MapPin,
    Sparkles,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { AvatarCropDialog } from './avatar-crop-dialog';
import type { UserProfileData } from './profile-mock-data';

interface ProfileHeaderProps {
    profile: UserProfileData;
    isEditing: boolean;
    onToggleEdit: () => void;
    onUpdateAvatar: (url: string) => void;
}

/**
 * En-tête de profil KORA OPS aux standards du centre de commande opérationnel.
 */
export function ProfileHeader({
    profile,
    isEditing,
    onToggleEdit,
    onUpdateAvatar,
}: ProfileHeaderProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [avatarHover, setAvatarHover] = useState(false);
    const [cropImageSrc, setCropImageSrc] = useState<string | null>(null);
    const [isCropOpen, setIsCropOpen] = useState(false);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const result = event.target?.result as string;
            setCropImageSrc(result);
            setIsCropOpen(true);

            // Réinitialiser la valeur de l'input pour permettre de resélectionner le même fichier si besoin
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        };
        reader.readAsDataURL(file);
    };

    return (
        <section className="relative isolate overflow-hidden rounded-[1.75rem] bg-[var(--kora)] px-6 py-7 text-white shadow-[0_28px_65px_rgba(0,29,78,0.22)] ring-1 ring-black/10 sm:px-8 sm:py-8 lg:px-10 dark:ring-white/10">
            {/* Boîte de dialogue de cadrage photo */}
            <AvatarCropDialog
                open={isCropOpen}
                imageSrc={cropImageSrc}
                onClose={() => setIsCropOpen(false)}
                onSave={onUpdateAvatar}
            />

            {/* KORA Geometric circular wireframes */}
            <div className="pointer-events-none absolute -right-28 -bottom-48 -z-10 size-[34rem] rounded-full border border-gold/20 sm:-right-20" />
            <div className="pointer-events-none absolute top-8 -right-8 -z-10 size-56 rounded-full border border-white/10" />

            <div className="relative z-10 flex flex-col justify-between gap-6 md:flex-row md:items-center">
                {/* Left: Avatar & Identity */}
                <div className="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                    {/* Interactive Avatar */}
                    <div
                        className="group relative cursor-pointer"
                        onMouseEnter={() => setAvatarHover(true)}
                        onMouseLeave={() => setAvatarHover(false)}
                        onClick={() => fileInputRef.current?.click()}
                        role="button"
                        tabIndex={0}
                        aria-label="Changer la photo de profil"
                    >
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={handleFileChange}
                        />

                        <div className="relative flex size-20 sm:size-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-2 border-gold/40 bg-white/10 shadow-lg transition-transform duration-200 group-hover:scale-105">
                            {profile.avatar ? (
                                <img
                                    src={profile.avatar}
                                    alt={profile.name}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <span className="font-mono text-2xl sm:text-3xl font-extrabold text-white">
                                    {profile.name
                                        .split(' ')
                                        .map((n) => n[0])
                                        .slice(0, 2)
                                        .join('')}
                                </span>
                            )}

                            {/* Hover overlay for upload */}
                            <div
                                className={cn(
                                    'absolute inset-0 flex flex-col items-center justify-center bg-black/60 transition-opacity duration-200',
                                    avatarHover
                                        ? 'opacity-100'
                                        : 'pointer-events-none opacity-0',
                                )}
                            >
                                <Camera className="size-5 text-gold" />
                                <span className="mt-1 text-[9px] font-bold text-white uppercase">
                                    Cadrer
                                </span>
                            </div>
                        </div>

                        {/* Live active beacon */}
                        <span className="absolute -bottom-1 -right-1 flex size-4">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span className="relative inline-flex size-4 rounded-full border-2 border-[var(--kora)] bg-emerald-500" />
                        </span>
                    </div>

                    {/* Identity Details */}
                    <div className="space-y-1.5">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-xl sm:text-2xl font-extrabold tracking-[-0.03em] text-white">
                                {profile.name}
                            </h1>
                            <Badge className="border border-gold/40 bg-gold/15 font-mono text-[11px] font-bold text-gold">
                                <Sparkles className="mr-1 size-3" />
                                {profile.roleBadge}
                            </Badge>
                        </div>

                        <p className="text-sm font-medium text-white/80">
                            {profile.jobTitle}
                        </p>

                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 pt-1 text-xs text-white/70">
                            <div className="flex items-center gap-1.5">
                                <MapPin className="size-3.5 text-gold" />
                                <span>{profile.agency}</span>
                            </div>

                            <div className="flex items-center gap-1.5">
                                <CheckCircle2 className="size-3.5 text-emerald-400" />
                                <span>{profile.statusLabel}</span>
                            </div>

                            <div className="flex items-center gap-1.5">
                                <Lock className="size-3.5 text-blue-300" />
                                <span>Email vérifié</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Right: Action Button */}
                <div className="flex items-center">
                    <Button
                        type="button"
                        onClick={onToggleEdit}
                        className={cn(
                            'group inline-flex min-h-10 items-center gap-2 rounded-xl px-4 text-xs font-extrabold shadow-sm transition-all duration-200 active:scale-[0.98]',
                            isEditing
                                ? 'bg-gold text-[var(--kora)] hover:bg-[#e5a400]'
                                : 'border border-white/20 bg-white/10 text-white hover:bg-white/20',
                        )}
                    >
                        {isEditing ? (
                            <>
                                <X className="size-4" />
                                <span>Quitter l'édition</span>
                            </>
                        ) : (
                            <>
                                <Edit3 className="size-4" />
                                <span>Modifier les informations</span>
                            </>
                        )}
                    </Button>
                </div>
            </div>
        </section>
    );
}
