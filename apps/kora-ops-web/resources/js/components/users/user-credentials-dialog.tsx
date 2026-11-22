import { useRef, useState } from 'react';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

export type UserCredentials = {
    temporaryPassword?: string;
    otpChallengeUuid?: string;
    userName?: string;
    userEmail?: string;
};

type Props = {
    credentials: UserCredentials | null;
    onClose: () => void;
    returnFocus: () => void;
};

export function UserCredentialsDialog({
    credentials,
    onClose,
    returnFocus,
}: Props) {
    const [copying, setCopying] = useState(false);
    const [copyError, setCopyError] = useState(false);
    const attempt = useRef(0);
    const dialog = useRef<HTMLDivElement>(null);

    const close = () => {
        attempt.current += 1;
        setCopying(false);
        setCopyError(false);
        onClose();
    };

    const copyAndClose = async () => {
        if (!credentials || copying) {
            return;
        }

        const { userName, userEmail, temporaryPassword, otpChallengeUuid } =
            credentials;
        const text = [
            userName && `Utilisateur : ${userName}`,
            userEmail && `E-mail : ${userEmail}`,
            temporaryPassword &&
                `Mot de passe provisoire : ${temporaryPassword}`,
            otpChallengeUuid && `Référence OTP : ${otpChallengeUuid}`,
        ]
            .filter(Boolean)
            .join('\n');

        const currentAttempt = ++attempt.current;
        setCopying(true);
        setCopyError(false);

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.className = 'fixed opacity-0';
                dialog.current?.append(textarea);

                try {
                    textarea.select();

                    if (!document.execCommand('copy')) {
                        throw new Error('La copie a échoué.');
                    }
                } finally {
                    textarea.remove();
                }
            }

            if (attempt.current === currentAttempt) {
                close();
            }
        } catch {
            if (attempt.current === currentAttempt) {
                setCopyError(true);
                setCopying(false);
            }
        }
    };

    return (
        <Dialog
            open={credentials !== null}
            onOpenChange={(open) => !open && close()}
        >
            <DialogContent
                ref={dialog}
                showClose={false}
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    returnFocus();
                }}
            >
                <DialogTitle>Identifiants générés</DialogTitle>
                <DialogDescription>
                    {credentials?.userName
                        ? `Accès pour ${credentials.userName}`
                        : 'Accès utilisateur'}
                </DialogDescription>
                {credentials?.temporaryPassword && (
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-sm font-semibold">
                            Mot de passe provisoire
                        </p>
                        <p className="mt-2 font-mono text-sm break-all select-all">
                            {credentials.temporaryPassword}
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            L’utilisateur devra le changer à sa première
                            connexion.
                        </p>
                    </div>
                )}
                {credentials?.otpChallengeUuid && (
                    <p className="text-sm break-all select-all">
                        Référence OTP {credentials.otpChallengeUuid}
                    </p>
                )}
                {copyError && (
                    <p role="alert" className="text-sm text-destructive">
                        La copie a échoué. Réessayez ou sélectionnez le texte
                        pour le copier manuellement.
                    </p>
                )}
                <DialogClose asChild>
                    <button
                        type="button"
                        className="btn btn-ghost absolute top-4 right-4 text-sm"
                        aria-label="Fermer le pop-up"
                    >
                        ×
                    </button>
                </DialogClose>
                <button
                    type="button"
                    disabled={copying}
                    onClick={copyAndClose}
                    className="btn btn-primary w-full justify-center"
                >
                    {copying ? 'Copie en cours…' : 'J’ai bien noté les accès'}
                </button>
            </DialogContent>
        </Dialog>
    );
}
