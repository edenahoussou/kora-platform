import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronRight } from 'lucide-react';
import { storeDeletionRequest } from '@/actions/App/Http/Controllers/PublicLegalController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { privacyPolicy } from '@/routes';

type Company = {
    name: string;
    director: string;
    rccm: string | null;
    nim: string | null;
    address: string;
    phone: string;
};

type Props = {
    supportEmail: string;
    company: Company;
};

export default function AccountDeletion({ supportEmail, company }: Props) {
    const submitted = Boolean(
        (usePage().props as { flash?: { deletion_submitted?: boolean } }).flash
            ?.deletion_submitted,
    );

    const { data, setData, post, processing, errors } = useForm({
        phone: '',
        name: '',
        email: '',
        reason: '',
        website: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(storeDeletionRequest.url());
    };

    return (
        <>
            <Head title="Suppression de compte" />

            <article className="mx-auto max-w-3xl">
                <header className="border-b border-slate-200 pb-8 dark:border-white/10">
                    <h1 className="text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                        Suppression de votre compte
                    </h1>
                    <dl className="mt-5 grid gap-1 text-sm text-slate-600 dark:text-slate-300">
                        <div className="flex flex-wrap gap-x-2">
                            <dt className="font-semibold text-slate-900 dark:text-white">
                                Éditeur :
                            </dt>
                            <dd>{company.name}</dd>
                        </div>
                        <div className="flex flex-wrap gap-x-2">
                            <dt className="font-semibold text-slate-900 dark:text-white">
                                Application concernée :
                            </dt>
                            <dd>KORA App Passager Bus</dd>
                        </div>
                        <div className="flex flex-wrap gap-x-2">
                            <dt className="font-semibold text-slate-900 dark:text-white">
                                Version :
                            </dt>
                            <dd>1.0 — En vigueur le 24 octobre 2026</dd>
                        </div>
                    </dl>
                    <p className="mt-5 text-sm leading-7 text-slate-600 dark:text-slate-300">
                        Vous pouvez demander la suppression de votre compte KORA
                        et de vos données personnelles à l’aide du formulaire
                        ci-dessous, sans avoir à vous connecter. Votre demande
                        est traitée dans un délai maximum de{' '}
                        <strong>30 jours</strong>.
                    </p>
                </header>

                {submitted ? (
                    <section className="py-8">
                        <h2 className="flex items-center gap-2.5 text-lg font-bold">
                            <Check className="size-5 text-emerald-600" />
                            Demande reçue
                        </h2>
                        <p className="mt-3.5 text-sm leading-7 text-slate-600 dark:text-slate-300">
                            Votre demande de suppression a bien été enregistrée.
                            Nous pouvons vous recontacter pour vérifier votre
                            identité. Une confirmation vous sera adressée une
                            fois le traitement effectué, dans un délai maximum
                            de 30 jours.
                        </p>
                    </section>
                ) : (
                    <section className="border-b border-slate-200 py-8 dark:border-white/10">
                        <h2 className="text-lg font-bold tracking-[-0.02em]">
                            1. Formulaire de demande
                        </h2>

                        <form onSubmit={submit} className="mt-6 grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    Numéro de téléphone du compte{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    required
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                    placeholder="+229 01 XX XX XX XX"
                                    autoComplete="tel"
                                />
                                <p className="text-xs text-slate-500">
                                    Celui utilisé pour créer votre compte ou
                                    réserver vos billets.
                                </p>
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    Nom complet{' '}
                                    <span className="font-normal text-slate-400">
                                        (optionnel)
                                    </span>
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    autoComplete="name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Adresse e-mail{' '}
                                    <span className="font-normal text-slate-400">
                                        (optionnel)
                                    </span>
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    autoComplete="email"
                                />
                                <p className="text-xs text-slate-500">
                                    Utilisée uniquement pour vous confirmer la
                                    réception et la clôture de la demande.
                                </p>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reason">
                                    Motif{' '}
                                    <span className="font-normal text-slate-400">
                                        (optionnel)
                                    </span>
                                </Label>
                                <Input
                                    id="reason"
                                    value={data.reason}
                                    onChange={(e) =>
                                        setData('reason', e.target.value)
                                    }
                                    placeholder="Ex. je n’utilise plus l’application"
                                />
                                <InputError message={errors.reason} />
                            </div>

                            {/* Honeypot anti-spam — invisible pour les humains */}
                            <input
                                type="text"
                                name="website"
                                value={data.website}
                                onChange={(e) =>
                                    setData('website', e.target.value)
                                }
                                className="hidden"
                                tabIndex={-1}
                                autoComplete="off"
                                aria-hidden="true"
                            />

                            <div>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Demander la suppression
                                </Button>
                            </div>
                        </form>

                        <p className="mt-6 text-xs leading-6 text-slate-500 dark:text-slate-400">
                            Ne transmettez jamais votre code PIN ni votre code
                            de vérification. Vous pouvez aussi écrire depuis
                            l’adresse e-mail associée au compte à{' '}
                            <a
                                href={`mailto:${supportEmail}`}
                                className="font-medium text-digital underline decoration-digital/30 underline-offset-4 hover:decoration-digital"
                            >
                                {supportEmail}
                            </a>
                            .
                        </p>
                    </section>
                )}

                <section className="border-b border-slate-200 py-8 dark:border-white/10">
                    <h2 className="text-lg font-bold tracking-[-0.02em]">
                        {submitted
                            ? 'Ce que la suppression implique'
                            : '2. Ce que la suppression implique'}
                    </h2>
                    <ul className="mt-4 list-disc space-y-2.5 pl-5 text-sm leading-7 text-slate-600 dark:text-slate-300">
                        <li>
                            <strong className="text-slate-900 dark:text-white">
                                Supprimé ou anonymisé :
                            </strong>{' '}
                            profil, accès au compte, appareils de confiance,
                            coordonnées des fiches voyageur et historique
                            d’authentification.
                        </li>
                        <li>
                            <strong className="text-slate-900 dark:text-white">
                                Conservé :
                            </strong>{' '}
                            réservations, billets et pièces de paiement sous
                            forme anonymisée, pendant 10 ans (obligations
                            comptables et fiscales).
                        </li>
                        <li>
                            <strong className="text-slate-900 dark:text-white">
                                Avant clôture :
                            </strong>{' '}
                            les réservations ou billets en cours peuvent
                            nécessiter un traitement préalable ; nous vous
                            contacterons si nécessaire.
                        </li>
                        <li>
                            <strong className="text-slate-900 dark:text-white">
                                Irréversible :
                            </strong>{' '}
                            une fois traitée, la suppression est définitive — le
                            compte ne peut pas être restauré.
                        </li>
                    </ul>
                </section>

                <section className="border-b border-slate-200 py-8 dark:border-white/10">
                    <h2 className="text-lg font-bold tracking-[-0.02em]">
                        {submitted
                            ? 'Après votre demande'
                            : '3. Après votre demande'}
                    </h2>
                    <ul className="mt-4 list-disc space-y-2.5 pl-5 text-sm leading-7 text-slate-600 dark:text-slate-300">
                        <li>
                            Nous pouvons vous demander une information de
                            vérification pour éviter qu’une autre personne ne
                            supprime votre compte.
                        </li>
                        <li>
                            Une confirmation vous est adressée une fois la
                            demande traitée, dans un délai maximum de 30 jours.
                        </li>
                        <li>
                            Pour toute question :{' '}
                            <a
                                href={`mailto:${supportEmail}`}
                                className="font-medium text-digital underline decoration-digital/30 underline-offset-4 hover:decoration-digital"
                            >
                                {supportEmail}
                            </a>{' '}
                            · {company.phone}.
                        </li>
                    </ul>
                </section>

                <p className="py-8 text-sm text-slate-500 dark:text-slate-400">
                    Pour connaître les règles applicables à vos données,
                    consultez la{' '}
                    <Link
                        href={privacyPolicy()}
                        className="inline-flex items-center gap-1 font-semibold text-digital underline decoration-digital/30 underline-offset-4 hover:decoration-digital"
                    >
                        politique de confidentialité
                        <ChevronRight className="size-3.5" />
                    </Link>
                    .
                </p>
            </article>
        </>
    );
}
