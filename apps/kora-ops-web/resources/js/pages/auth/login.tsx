import { Form, Head, useForm } from '@inertiajs/react';
import { ArrowRight, Check, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { email } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const [forgotOpen, setForgotOpen] = useState(false);

    return (
        <>
            <Head title="Connexion" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="email"
                                    className="text-[0.8125rem] font-extrabold text-foreground"
                                >
                                    Identifiant professionnel
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@koraexpress.com"
                                    className="h-[3.375rem] rounded-[0.875rem] border-0 bg-muted text-sm shadow-[inset_0_0_0_1px_var(--border)] transition-[box-shadow,transform,background] focus-visible:translate-y-[-1px] focus-visible:bg-background focus-visible:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="password"
                                    className="text-[0.8125rem] font-extrabold text-foreground"
                                >
                                    Mot de passe
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="••••••••"
                                    className="h-[3.375rem] rounded-[0.875rem] border-0 bg-muted text-sm shadow-[inset_0_0_0_1px_var(--border)] transition-[box-shadow,transform,background] focus-visible:translate-y-[-1px] focus-visible:bg-background focus-visible:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center justify-between gap-4 text-sm">
                                <label
                                    htmlFor="remember"
                                    className="flex items-center gap-2 font-medium text-muted-foreground"
                                >
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    Rester connecté
                                </label>
                                {canResetPassword && (
                                    <button
                                        type="button"
                                        onClick={() => setForgotOpen(true)}
                                        className="text-sm font-extrabold text-digital no-underline hover:text-digital/80"
                                        tabIndex={5}
                                    >
                                        Mot de passe oublié
                                    </button>
                                )}
                            </div>

                            <Button
                                type="submit"
                                className="kora-auth-submit relative mt-4 h-[3.25rem] w-full overflow-hidden rounded-[0.6875rem] bg-gold text-[0.8125rem] font-medium text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] transition-[color,background,transform] hover:translate-y-[-1px] hover:bg-[var(--kora)] hover:text-white"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <>
                                        <span className="relative">
                                            Accéder à KORA OPS
                                        </span>
                                        <ArrowRight className="absolute right-4 size-4" />
                                    </>
                                )}
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="mt-6 flex items-center gap-3 border-t border-border pt-[1.125rem]">
                <i className="grid size-[1.9375rem] shrink-0 place-items-center rounded-[0.625rem] bg-gold/20 text-[0.625rem] font-black text-[var(--kora)] not-italic">
                    ✓
                </i>
                <div>
                    <strong className="block text-[0.5625rem] font-extrabold text-foreground">
                        Accès protégé
                    </strong>
                    <small className="mt-0.5 block text-[0.5rem] text-muted-foreground">
                        Votre session et vos actions sensibles sont sécurisées.
                    </small>
                </div>
            </div>

            {canResetPassword && (
                <ForgotPasswordDialog
                    open={forgotOpen}
                    onOpenChange={setForgotOpen}
                />
            )}
        </>
    );
}

function ForgotPasswordDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, post, processing, errors, recentlySuccessful } =
        useForm({
            email: '',
        });
    const [sent, setSent] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(email().url, {
            onSuccess: () => setSent(true),
        });
    };

    const handleClose = (next: boolean) => {
        onOpenChange(next);

        if (!next) {
            setTimeout(() => {
                setSent(false);
                setData('email', '');
            }, 200);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogPortal>
                <DialogOverlay className="kora-recovery-overlay" />
            </DialogPortal>
            <DialogContent
                className="kora-recovery-modal max-w-[30rem] rounded-[1.75rem] border-border/50 bg-card p-9 shadow-[0_30px_80px_rgba(0,29,78,0.35)] [&>button]:hidden"
                overlay={false}
            >
                {sent || recentlySuccessful ? (
                    <div className="flex flex-col items-center text-center">
                        <div className="mb-5 grid size-16 place-items-center rounded-[1.25rem] bg-gold text-[1.875rem] font-black text-[var(--kora)]">
                            <Check className="size-7" />
                        </div>
                        <span className="mb-1 block text-xs font-black tracking-[0.15em] text-gold uppercase">
                            Demande confirmée
                        </span>
                        <DialogTitle className="mt-1 text-[1.625rem] font-extrabold tracking-[-0.03em] text-foreground">
                            Consultez votre messagerie
                        </DialogTitle>
                        <DialogDescription className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            Un lien sécurisé a été envoyé à{' '}
                            <strong className="font-bold text-foreground">
                                {data.email}
                            </strong>
                            . Il reste valable pendant 15 minutes.
                        </DialogDescription>
                        <Button
                            type="button"
                            onClick={() => handleClose(false)}
                            className="mt-6 h-[3.25rem] w-full rounded-[0.6875rem] bg-gold text-[0.8125rem] font-medium text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] transition-[color,background,transform] hover:translate-y-[-1px] hover:bg-[var(--kora)] hover:text-white"
                        >
                            Retour à la connexion
                        </Button>
                    </div>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <button
                            type="button"
                            onClick={() => handleClose(false)}
                            className="absolute top-[1.125rem] right-[1.125rem] grid size-[2.375rem] place-items-center rounded-[0.75rem] bg-muted text-foreground transition-colors hover:bg-muted/80"
                            aria-label="Fermer"
                        >
                            <X className="size-4" />
                        </button>

                        <div className="mb-1 flex items-center gap-3">
                            <span className="grid size-[2.6875rem] place-items-center rounded-[0.875rem] bg-gold text-[0.625rem] font-black text-[var(--kora)] shadow-[0_11px_25px_rgba(246,176,0,0.26)]">
                                OPS
                            </span>
                            <i className="h-0.5 w-[3.875rem] bg-gradient-to-r from-gold to-transparent" />
                        </div>

                        <span className="block text-xs font-black tracking-[0.15em] text-gold uppercase">
                            Accès sécurisé
                        </span>
                        <DialogTitle className="mt-1 text-[1.625rem] font-extrabold tracking-[-0.03em] text-foreground">
                            Réinitialiser votre accès
                        </DialogTitle>
                        <DialogDescription className="text-sm leading-relaxed text-muted-foreground">
                            Saisissez votre identifiant professionnel. Un lien à
                            usage unique sera envoyé à l'adresse enregistrée.
                        </DialogDescription>

                        <div className="grid gap-2">
                            <Label
                                htmlFor="reset-email"
                                className="text-[0.8125rem] font-extrabold text-foreground"
                            >
                                Identifiant professionnel *
                            </Label>
                            <Input
                                id="reset-email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                placeholder="email@koraexpress.com"
                                className="h-[3.375rem] rounded-[0.875rem] border-0 bg-muted text-sm shadow-[inset_0_0_0_1px_var(--border)] transition-[box-shadow,transform,background] focus-visible:translate-y-[-1px] focus-visible:bg-background focus-visible:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="h-[3.25rem] w-full rounded-[0.6875rem] bg-gold text-[0.8125rem] font-medium text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] transition-[color,background,transform] hover:translate-y-[-1px] hover:bg-[var(--kora)] hover:text-white"
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                'Envoyer le lien sécurisé'
                            )}
                        </Button>

                        <p className="text-center text-[0.75rem] text-muted-foreground">
                            Le lien expire après 15 minutes et invalide les
                            demandes précédentes.
                        </p>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}

Login.layout = {
    title: 'Bon retour parmi nous.',
    description:
        'Connectez-vous pour retrouver votre agence, vos opérations et les priorités du réseau.',
};
