import {
    Globe,
    Info,
    Laptop,
    LogOut,
    MapPin,
    Smartphone,
    Tablet,
} from 'lucide-react';
import { useState } from 'react';
import ProfileSessionController from '@/actions/App/Http/Controllers/Settings/ProfileSessionController';
import { SettingsSection } from '@/components/administration/settings/settings-ui';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { PanelAlert } from '@/components/panel-alert';
import { Button } from '@/components/ui/button';
import { PasswordConfirmDialog } from './password-confirm-dialog';
import type { UserSessionItem } from './profile-types';

interface ProfileSessionsTabProps {
    sessions: UserSessionItem[];
}

type PendingRevocation = {
    title: string;
    description: string;
    url: string;
} | null;

/**
 * Onglet Sessions & Postes d'exploitation KORA OPS.
 */
export function ProfileSessionsTab({ sessions }: ProfileSessionsTabProps) {
    const [pending, setPending] = useState<PendingRevocation>(null);
    const [showRevokeAllModal, setShowRevokeAllModal] = useState(false);

    const currentSession = sessions.find((s) => s.isCurrent);
    const otherSessions = sessions.filter((s) => !s.isCurrent);

    const revokeLabel = (session: UserSessionItem): string =>
        session.kind === 'terminal'
            ? 'la session terminal'
            : session.kind === 'device'
              ? "l'appareil de confiance"
              : 'la session navigateur';

    const handleRevoke = (session: UserSessionItem) => {
        const url =
            session.kind === 'terminal'
                ? ProfileSessionController.destroyTerminal.url(session.id)
                : session.kind === 'device'
                  ? ProfileSessionController.destroyDevice.url(session.id)
                  : ProfileSessionController.destroySession.url(session.id);

        setPending({
            title: `Déconnecter ${revokeLabel(session)} ?`,
            description: `« ${session.device} » perdra immédiatement son accès à KORA OPS. Confirmez votre identité par mot de passe.`,
            url,
        });
    };

    const getDeviceIcon = (type: UserSessionItem['type']) => {
        switch (type) {
            case 'mobile':
                return <Smartphone className="size-4 text-digital" />;
            case 'pos':
                return <Tablet className="size-4 text-gold" />;
            default:
                return <Laptop className="size-4 text-digital" />;
        }
    };

    const kindBadge = (session: UserSessionItem) => {
        if (session.kind === 'terminal') {
            return <KoraStatusBadge label="P501" tone="gold" />;
        }

        if (session.kind === 'device') {
            return <KoraStatusBadge label="Confiance" tone="blue" />;
        }

        return null;
    };

    return (
        <div className="space-y-6">
            {/* Alerte contextuelle de surveillance */}
            <PanelAlert tone="info" icon={Info}>
                <span className="font-bold">
                    Contrôle télématique et sécurité des accès :{' '}
                </span>
                Chaque connexion depuis un poste d'agence ou un terminal P501
                fait l'objet d'un suivi horodaté. Toute déconnexion à distance
                exige la confirmation de votre mot de passe.
            </PanelAlert>

            {/* Session courante */}
            {currentSession && (
                <SettingsSection
                    title="Session courante"
                    description="Appareil actuellement utilisé pour administrer KORA OPS."
                    actions={<KoraStatusBadge label="En ligne" tone="blue" />}
                >
                    <div className="space-y-4">
                        <div className="flex items-center gap-3.5">
                            <span className="grid size-10 place-items-center rounded-xl bg-muted text-foreground">
                                {getDeviceIcon(currentSession.type)}
                            </span>
                            <div>
                                <h3 className="text-sm font-bold text-foreground">
                                    {currentSession.device}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    {currentSession.browser ??
                                        'Navigateur inconnu'}
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-3 pt-2 text-xs sm:grid-cols-2">
                            {currentSession.ip && (
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <Globe className="size-3.5" />
                                    <span>Adresse IP :</span>
                                    <strong className="font-mono text-foreground">
                                        {currentSession.ip}
                                    </strong>
                                </div>
                            )}

                            {currentSession.location && (
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <MapPin className="size-3.5" />
                                    <span>Localisation :</span>
                                    <strong className="text-foreground">
                                        {currentSession.location}
                                    </strong>
                                </div>
                            )}
                        </div>
                    </div>
                </SettingsSection>
            )}

            {/* Autres sessions & appareils */}
            <SettingsSection
                title="Autres appareils connectés"
                description="Postes d'agences, appareils de confiance et terminaux mobiles autorisés."
                actions={
                    otherSessions.length > 0 ? (
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => setShowRevokeAllModal(true)}
                            className="rounded-xl text-xs"
                        >
                            <LogOut className="mr-1.5 size-3.5" />
                            Déconnecter les autres sessions
                        </Button>
                    ) : undefined
                }
            >
                <div className="-m-5 divide-y divide-border/60">
                    {otherSessions.length === 0 ? (
                        <div className="p-6 text-center text-xs text-muted-foreground">
                            Aucune autre session ou appareil actif.
                        </div>
                    ) : (
                        otherSessions.map((session) => (
                            <div
                                key={`${session.kind}-${session.id}`}
                                className="flex flex-col gap-3 px-5 py-4 text-xs sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="grid size-9 place-items-center rounded-xl bg-muted text-foreground">
                                        {getDeviceIcon(session.type)}
                                    </span>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-bold text-foreground">
                                                {session.device}
                                            </span>
                                            {kindBadge(session)}
                                        </div>
                                        <p className="text-[11px] text-muted-foreground">
                                            {[
                                                session.browser,
                                                session.ip,
                                                session.location,
                                            ]
                                                .filter(Boolean)
                                                .join(' • ') || '—'}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between gap-3 sm:justify-end">
                                    <span className="text-[11px] text-muted-foreground">
                                        {session.lastActive ?? '—'}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleRevoke(session)}
                                        className="rounded-lg text-xs text-destructive hover:bg-destructive/10"
                                    >
                                        {session.kind === 'device'
                                            ? 'Retirer'
                                            : 'Déconnecter'}
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </SettingsSection>

            {/* Dialog de confirmation mot de passe — révocation unitaire */}
            <PasswordConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPending(null);
                    }
                }}
                title={pending?.title ?? ''}
                description={pending?.description ?? ''}
                confirmLabel="Confirmer la déconnexion"
                action={{ url: pending?.url ?? '', method: 'delete' }}
            />

            {/* Dialog de confirmation mot de passe — déconnexion globale */}
            <PasswordConfirmDialog
                open={showRevokeAllModal}
                onOpenChange={setShowRevokeAllModal}
                title="Déconnecter toutes les autres sessions ?"
                description="Toutes les sessions de navigateur ouvertes sur d'autres postes seront clôturées. Confirmez votre identité par mot de passe."
                confirmLabel="Tout déconnecter"
                action={{
                    url: ProfileSessionController.destroyOthers.url(),
                    method: 'delete',
                }}
            />
        </div>
    );
}
