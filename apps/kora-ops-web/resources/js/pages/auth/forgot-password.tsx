import { Form, Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Mot de passe oublié" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <Form {...email.form()} className="flex flex-col gap-6">
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
                                autoComplete="off"
                                autoFocus
                                placeholder="email@koraexpress.com"
                                className="h-[3.375rem] rounded-[0.875rem] border-0 bg-muted text-sm shadow-[inset_0_0_0_1px_var(--border)] transition-[box-shadow,transform,background] focus-visible:translate-y-[-1px] focus-visible:bg-background focus-visible:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            type="submit"
                            className="kora-auth-submit relative mt-4 h-[3.25rem] w-full overflow-hidden rounded-[0.6875rem] bg-gold text-[0.8125rem] font-extrabold text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] transition-[color,background,transform] hover:translate-y-[-1px] hover:bg-[var(--kora)] hover:text-white"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <>
                                    <span className="relative">
                                        Envoyer le lien sécurisé
                                    </span>
                                    <ArrowRight className="absolute right-4 size-4" />
                                </>
                            )}
                        </Button>

                        <p className="text-center text-[0.75rem] text-muted-foreground">
                            Le lien expire après 15 minutes et invalide les
                            demandes précédentes.
                        </p>
                    </div>
                )}
            </Form>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Réinitialiser votre accès',
    description:
        "Saisissez votre identifiant professionnel. Un lien à usage unique sera envoyé à l'adresse enregistrée.",
};
