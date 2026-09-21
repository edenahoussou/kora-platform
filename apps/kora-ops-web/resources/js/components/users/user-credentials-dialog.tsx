import { Check, Copy, KeyRound, ShieldAlert, X } from 'lucide-react';
import { useState } from 'react';

type Props = {
    open: boolean;
    temporaryPassword?: string | null;
    otpChallengeUuid?: string | null;
    userName?: string | null;
    userEmail?: string | null;
    onClose: () => void;
};

export function UserCredentialsDialog({
    open,
    temporaryPassword,
    otpChallengeUuid,
    userName,
    userEmail,
    onClose,
}: Props) {
    const [copied, setCopied] = useState(false);

    if (!open || (!temporaryPassword && !otpChallengeUuid)) {
        return null;
    }

    const copyCredentials = async () => {
        const lines: string[] = [];

        if (userName) {
            lines.push(`Utilisateur : ${userName}`);
        }

        if (userEmail) {
            lines.push(`Email : ${userEmail}`);
        }

        if (temporaryPassword) {
            lines.push(`Mot de passe temporaire : ${temporaryPassword}`);
        }

        if (otpChallengeUuid) {
            lines.push(`Référence OTP : ${otpChallengeUuid}`);
        }

        const textToCopy = lines.join('\n');

        try {
            await navigator.clipboard.writeText(textToCopy);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch {
            // Fallback si clipboard API échoue
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        }
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(event) =>
                event.target === event.currentTarget && onClose()
            }
        >
            <div
                className="cash-modal max-w-[32rem]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="credentials-modal-title"
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-gold/20 text-gold">
                            <KeyRound className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker text-gold">
                                Sécurité & Identifiants
                            </span>
                            <h3
                                id="credentials-modal-title"
                                className="text-lg font-black text-foreground"
                            >
                                Identifiants générés
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                {userName
                                    ? `Accès initial pour ${userName}`
                                    : 'Nouveaux paramètres d’accès générés.'}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="cash-modal-close"
                        aria-label="Fermer"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <div className="space-y-4">
                    {temporaryPassword && (
                        <div className="rounded-xl border border-border bg-muted/30 p-4">
                            <span className="text-[0.6875rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Mot de passe temporaire
                            </span>
                            <div className="mt-2 flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3.5 py-2.5">
                                <span className="font-mono text-base font-black tracking-wider text-foreground select-all">
                                    {temporaryPassword}
                                </span>
                                <button
                                    type="button"
                                    onClick={copyCredentials}
                                    className="btn btn-ghost text-xs font-bold text-gold hover:text-gold"
                                    title="Copier les identifiants"
                                >
                                    {copied ? (
                                        <>
                                            <Check className="size-3.5 text-emerald-500" />
                                            <span className="text-emerald-500">
                                                Copié !
                                            </span>
                                        </>
                                    ) : (
                                        <>
                                            <Copy className="size-3.5" />
                                            <span>Copier</span>
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    )}

                    {otpChallengeUuid && (
                        <div className="rounded-xl border border-border bg-muted/20 p-3.5">
                            <span className="text-[0.6875rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Référence Challenge OTP
                            </span>
                            <p className="mt-1 font-mono text-xs font-semibold text-muted-foreground">
                                {otpChallengeUuid}
                            </p>
                            <small className="mt-1 block text-[0.6875rem] text-muted-foreground">
                                Le code OTP de validation a été envoyé
                                directement au numéro de l’agent.
                            </small>
                        </div>
                    )}

                    <div className="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs text-amber-800 dark:text-amber-300">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div>
                            <strong className="block font-bold">
                                Important pour la sécurité
                            </strong>
                            <p className="mt-0.5 text-[0.6875rem] leading-relaxed">
                                Ce mot de passe temporaire ne sera plus jamais
                                réaffiché. Transmettez-le de façon sécurisée à
                                l’utilisateur. Il lui sera demandé de définir un
                                mot de passe personnel dès sa première
                                connexion.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="cash-modal-actions mt-6">
                    <button
                        type="button"
                        onClick={onClose}
                        className="btn btn-primary w-full justify-center font-black shadow-md hover:shadow-lg"
                    >
                        J’ai bien noté les accès
                    </button>
                </div>
            </div>
        </div>
    );
}
