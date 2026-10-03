import { Form } from '@inertiajs/react';
import { AlertTriangle, LockKeyhole, ShieldAlert, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import { PanelAlert } from '@/components/panel-alert';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

/**
 * Section Zone de danger KORA pour la suppression de compte.
 */
export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <section className="overflow-hidden rounded-2xl border border-destructive/30 bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]">
            <header className="flex items-center justify-between border-b border-border/70 px-5 py-4">
                <div className="flex items-center gap-3">
                    <span className="h-4 w-1 shrink-0 rounded-full bg-destructive" />
                    <div>
                        <h2 className="text-[15px] font-extrabold tracking-[-0.02em] text-destructive">
                            Zone de danger — Clôture du compte
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Suppression irréversible de votre accès et révocation des habilitations réseau.
                        </p>
                    </div>
                </div>
            </header>

            <div className="p-5 sm:p-6 space-y-4">
                <PanelAlert tone="danger" icon={AlertTriangle}>
                    <span className="font-bold">Attention : </span>
                    Cette action désactivera immédiatement votre profil, révoquera vos sessions actives sur tous les terminaux P501 et supprimera vos accès aux modules opérationnels.
                </PanelAlert>

                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-2">
                    <div className="space-y-0.5">
                        <span className="text-sm font-bold text-foreground">
                            Supprimer définitivement ce compte
                        </span>
                        <p className="text-xs text-muted-foreground">
                            Une confirmation par mot de passe sera exigée avant toute clôture.
                        </p>
                    </div>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button
                                variant="destructive"
                                size="sm"
                                data-test="delete-user-button"
                                className="rounded-xl text-xs font-bold"
                            >
                                <Trash2 className="mr-1.5 size-3.5" />
                                Clôturer le compte
                            </Button>
                        </DialogTrigger>

                        <DialogContent className="max-w-md rounded-2xl">
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2 text-base font-bold text-destructive">
                                    <ShieldAlert className="size-5" />
                                    <span>Confirmer la suppression du compte</span>
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground">
                                    Cette opération est irréversible. Pour valider la clôture définitive de votre accès KORA OPS, veuillez saisir votre mot de passe actuel.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                {...ProfileController.destroy.form()}
                                options={{
                                    preserveScroll: true,
                                }}
                                onError={() => passwordInput.current?.focus()}
                                resetOnSuccess
                                className="space-y-4 pt-2"
                            >
                                {({ resetAndClearErrors, processing, errors }) => (
                                    <>
                                        <div className="space-y-1.5">
                                            <Label
                                                htmlFor="password"
                                                className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                                            >
                                                <LockKeyhole className="size-3.5 text-muted-foreground" />
                                                <span>Mot de passe de confirmation</span>
                                            </Label>

                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                ref={passwordInput}
                                                placeholder="Saisissez votre mot de passe"
                                                autoComplete="current-password"
                                                className="h-10 rounded-xl"
                                            />

                                            <InputError message={errors.password} />
                                        </div>

                                        <DialogFooter className="gap-2 pt-2">
                                            <DialogClose asChild>
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() =>
                                                        resetAndClearErrors()
                                                    }
                                                    className="rounded-xl text-xs"
                                                >
                                                    Annuler
                                                </Button>
                                            </DialogClose>

                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                disabled={processing}
                                                className="rounded-xl text-xs font-bold"
                                                asChild
                                            >
                                                <button
                                                    type="submit"
                                                    data-test="confirm-delete-user-button"
                                                >
                                                    Supprimer définitivement
                                                </button>
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </section>
    );
}
