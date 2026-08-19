import { Form, Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Réinitialisation" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
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
                                autoComplete="email"
                                value={email}
                                readOnly
                                className="h-[3.375rem] rounded-[0.875rem] border-0 bg-muted text-sm shadow-[inset_0_0_0_1px_var(--border)]"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label
                                htmlFor="password"
                                className="text-[0.8125rem] font-extrabold text-foreground"
                            >
                                Nouveau mot de passe
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                placeholder="••••••••"
                                passwordrules={passwordRules}
                                className="h-[3.375rem] rounded-[0.875rem] border-0 bg-muted text-sm shadow-[inset_0_0_0_1px_var(--border)] transition-[box-shadow,transform,background] focus-visible:translate-y-[-1px] focus-visible:bg-background focus-visible:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label
                                htmlFor="password_confirmation"
                                className="text-[0.8125rem] font-extrabold text-foreground"
                            >
                                Confirmer le mot de passe
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                placeholder="••••••••"
                                passwordrules={passwordRules}
                                className="h-[3.375rem] rounded-[0.875rem] border-0 bg-muted text-sm shadow-[inset_0_0_0_1px_var(--border)] transition-[box-shadow,transform,background] focus-visible:translate-y-[-1px] focus-visible:bg-background focus-visible:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="kora-auth-submit relative mt-4 h-[3.25rem] w-full overflow-hidden rounded-[0.6875rem] bg-gold text-[0.8125rem] font-medium text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] transition-[color,background,transform] hover:translate-y-[-1px] hover:bg-[var(--kora)] hover:text-white"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <>
                                    <span className="relative">
                                        Réinitialiser mon mot de passe
                                    </span>
                                    <ArrowRight className="absolute right-4 size-4" />
                                </>
                            )}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Réinitialiser votre accès',
    description:
        'Définissez votre nouveau mot de passe pour retrouver votre compte KORA OPS.',
};
