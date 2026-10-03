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
import { toast } from 'sonner';
import { PanelAlert } from '@/components/panel-alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { UserSessionItem } from './profile-mock-data';
import {
    INITIAL_SESSIONS_DATA,
} from './profile-mock-data';

/**
 * Onglet Sessions & Postes d'exploitation KORA OPS.
 */
export function ProfileSessionsTab() {
    const [sessions, setSessions] =
        useState<UserSessionItem[]>(INITIAL_SESSIONS_DATA);
    const [showRevokeAllModal, setShowRevokeAllModal] = useState(false);
    const [revokingId, setRevokingId] = useState<string | null>(null);

    const currentSession = sessions.find((s) => s.isCurrent);
    const otherSessions = sessions.filter((s) => !s.isCurrent);

    const handleRevokeSession = (id: string, deviceName: string) => {
        setRevokingId(id);
        setTimeout(() => {
            setSessions((prev) => prev.filter((s) => s.id !== id));
            setRevokingId(null);
            toast.success(`Session « ${deviceName} » révoquée`);
        }, 300);
    };

    const handleRevokeAllOthers = () => {
        setShowRevokeAllModal(false);
        setSessions((prev) => prev.filter((s) => s.isCurrent));
        toast.success('Toutes les autres sessions ont été clôturées');
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

    return (
        <div className="space-y-6">
            {/* Alerte contextuelle de surveillance */}
            <PanelAlert tone="info" icon={Info}>
                <span className="font-bold">Contrôle télématique et sécurité des accès : </span>
                Chaque connexion depuis un poste d'agence ou un terminal P501 fait l'objet d'un suivi horodaté. Les sessions inactives depuis plus de 24 heures sont invalidées automatiquement.
            </PanelAlert>

            {/* Session courante */}
            {currentSession && (
                <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                    <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                            <div>
                                <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                                    Session courante
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Appareil actuellement utilisé pour administrer KORA OPS.
                                </p>
                            </div>
                        </div>
                        <Badge variant="outline" className="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-mono text-[10px]">
                            En ligne
                        </Badge>
                    </header>

                    <div className="p-5 sm:p-6 space-y-4">
                        <div className="flex items-center gap-3.5">
                            <span className="grid size-10 place-items-center rounded-xl bg-muted text-foreground">
                                {getDeviceIcon(currentSession.type)}
                            </span>
                            <div>
                                <h3 className="text-sm font-bold text-foreground">
                                    {currentSession.device}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    {currentSession.browser}
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-3 pt-2 sm:grid-cols-2 text-xs">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Globe className="size-3.5" />
                                <span>Adresse IP :</span>
                                <strong className="font-mono text-foreground">{currentSession.ip}</strong>
                            </div>

                            <div className="flex items-center gap-2 text-muted-foreground">
                                <MapPin className="size-3.5" />
                                <span>Localisation :</span>
                                <strong className="text-foreground">{currentSession.location}</strong>
                            </div>
                        </div>
                    </div>
                </section>
            )}

            {/* Autres sessions */}
            <section className="overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
                <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <span className="h-4 w-1 shrink-0 rounded-full bg-gold" />
                        <div>
                            <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                                Autres appareils connectés
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Postes d'agences et terminaux mobiles autorisés.
                            </p>
                        </div>
                    </div>

                    {otherSessions.length > 0 && (
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
                    )}
                </header>

                <div className="divide-y divide-border/60">
                    {otherSessions.length === 0 ? (
                        <div className="p-6 text-center text-xs text-muted-foreground">
                            Aucune autre session active.
                        </div>
                    ) : (
                        otherSessions.map((session) => (
                            <div key={session.id} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 text-xs">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-9 place-items-center rounded-xl bg-muted text-foreground">
                                        {getDeviceIcon(session.type)}
                                    </span>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-bold text-foreground text-sm">
                                                {session.device}
                                            </span>
                                            {session.type === 'pos' && (
                                                <Badge variant="outline" className="text-[9px] font-mono text-gold border-gold/40">
                                                    P501
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-[11px] text-muted-foreground">
                                            {session.browser} • {session.ip} • {session.location}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between sm:justify-end gap-3">
                                    <span className="text-[11px] text-muted-foreground">
                                        {session.lastActive}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={revokingId === session.id}
                                        onClick={() => handleRevokeSession(session.id, session.device)}
                                        className="rounded-lg text-xs text-destructive hover:bg-destructive/10"
                                    >
                                        {revokingId === session.id ? 'Fermeture...' : 'Déconnecter'}
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </section>

            {/* Modal Déconnexion globale */}
            <Dialog open={showRevokeAllModal} onOpenChange={setShowRevokeAllModal}>
                <DialogContent className="max-w-md rounded-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-base font-bold">Déconnecter toutes les autres sessions ?</DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Tous les accès ouverts sur d'autres navigateurs et terminaux d'agence seront clôturés.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setShowRevokeAllModal(false)} className="rounded-xl text-xs">
                            Annuler
                        </Button>
                        <Button type="button" variant="destructive" onClick={handleRevokeAllOthers} className="rounded-xl text-xs font-bold">
                            Confirmer la déconnexion
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
