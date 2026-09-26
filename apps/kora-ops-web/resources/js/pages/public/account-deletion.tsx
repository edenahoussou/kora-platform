import { Head, Link } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Clock3,
    Mail,
    ShieldCheck,
    Smartphone,
    Trash2,
} from 'lucide-react';
import { privacyPolicy } from '@/routes';

type Props = {
    supportEmail: string;
    lastUpdated: string;
};

export default function AccountDeletion({ supportEmail, lastUpdated }: Props) {
    const deletionRequestHref = `mailto:${supportEmail}?subject=${encodeURIComponent('Demande de suppression de compte KORA')}&body=${encodeURIComponent('Bonjour,\n\nJe demande la suppression de mon compte KORA App Passager Bus.\n\nTéléphone ou adresse e-mail du compte :\n\nMerci.')}`;

    return (
        <>
            <Head title="Suppression de compte" />

            <div className="mx-auto max-w-4xl">
                <div className="mb-8 flex items-start justify-between gap-5">
                    <div>
                        <div className="mb-4 inline-flex size-12 items-center justify-center rounded-2xl bg-red-500/10 text-red-600 dark:text-red-400">
                            <Trash2 className="size-6" />
                        </div>
                        <p className="mb-2 text-xs font-bold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400">
                            KORA App Passager Bus
                        </p>
                        <h2 className="text-3xl font-extrabold tracking-[-0.04em] sm:text-5xl">
                            Suppression de compte
                        </h2>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300">
                            Cette page vous permet de demander la suppression de
                            votre compte KORA sans devoir vous connecter au site
                            web.
                        </p>
                    </div>
                    <span className="hidden shrink-0 text-xs font-medium text-slate-500 sm:block dark:text-slate-400">
                        Mise à jour : {lastUpdated}
                    </span>
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    <section className="rounded-3xl bg-[var(--kora)] p-6 text-white shadow-[0_1.5rem_3rem_rgba(0,29,78,0.18)] sm:p-8">
                        <div className="flex size-11 items-center justify-center rounded-2xl bg-gold text-[var(--kora)]">
                            <Smartphone className="size-5" />
                        </div>
                        <h3 className="mt-6 text-xl font-bold tracking-[-0.02em]">
                            Depuis l’application
                        </h3>
                        <ol className="mt-5 grid gap-4 text-sm leading-6 text-white/75">
                            <Step number="01">
                                Ouvrez l’onglet <strong>Compte</strong> dans
                                KORA App Passager Bus.
                            </Step>
                            <Step number="02">
                                Ouvrez <strong>Paramètres</strong>, puis
                                choisissez <strong>Supprimer mon compte</strong>
                                .
                            </Step>
                            <Step number="03">
                                Confirmez votre demande après avoir lu les
                                conséquences de la suppression.
                            </Step>
                        </ol>
                    </section>

                    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_1.5rem_4rem_rgba(0,29,78,0.07)] sm:p-8 dark:border-white/10 dark:bg-slate-900 dark:shadow-none">
                        <div className="flex size-11 items-center justify-center rounded-2xl bg-gold/15 text-[var(--kora)] dark:text-gold">
                            <Mail className="size-5" />
                        </div>
                        <h3 className="mt-6 text-xl font-bold tracking-[-0.02em]">
                            Vous n’avez plus accès à l’application ?
                        </h3>
                        <p className="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Envoyez votre demande depuis l’adresse e-mail
                            associée au compte, ou indiquez le numéro de
                            téléphone utilisé sur KORA. Ne transmettez jamais
                            votre PIN.
                        </p>
                        <a
                            href={deletionRequestHref}
                            className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gold px-5 py-3 text-sm font-bold text-[var(--kora)] shadow-[0_0.75rem_1.5rem_rgba(246,176,0,0.2)] transition hover:-translate-y-0.5 hover:bg-[var(--kora)] hover:text-white"
                        >
                            Demander la suppression
                            <ChevronRight className="size-4" />
                        </a>
                        <p className="mt-3 text-center text-xs break-all text-slate-500 dark:text-slate-400">
                            {supportEmail}
                        </p>
                    </section>
                </div>

                <section className="mt-5 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-white/10 dark:bg-slate-900">
                    <div className="flex items-center gap-3">
                        <ShieldCheck className="size-5 text-digital" />
                        <h3 className="text-xl font-bold tracking-[-0.02em]">
                            Ce que la suppression implique
                        </h3>
                    </div>
                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                        <DeletionPoint>
                            Votre profil, vos accès et vos appareils de
                            confiance sont désactivés.
                        </DeletionPoint>
                        <DeletionPoint>
                            Vos données personnelles sont supprimées ou
                            anonymisées lorsque cela est possible.
                        </DeletionPoint>
                        <DeletionPoint>
                            Les réservations et billets en cours peuvent
                            nécessiter un traitement avant la clôture du compte.
                        </DeletionPoint>
                        <DeletionPoint>
                            Certaines données comptables, contractuelles ou de
                            sécurité peuvent être conservées lorsque la loi
                            l’impose.
                        </DeletionPoint>
                    </div>
                </section>

                <section className="mt-5 flex gap-4 rounded-3xl border border-amber-200 bg-amber-50 p-6 text-amber-950 sm:p-8 dark:border-amber-300/20 dark:bg-amber-300/10 dark:text-amber-100">
                    <Clock3 className="mt-0.5 size-5 shrink-0" />
                    <div>
                        <h3 className="font-bold">Après votre demande</h3>
                        <p className="mt-2 text-sm leading-6 opacity-80">
                            KORA peut vous demander une information de
                            vérification pour éviter qu’une autre personne ne
                            supprime votre compte. Une confirmation vous sera
                            envoyée une fois la demande traitée.
                        </p>
                    </div>
                </section>

                <p className="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
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
            </div>
        </>
    );
}

function Step({
    number,
    children,
}: {
    number: string;
    children: React.ReactNode;
}) {
    return (
        <li className="flex gap-3">
            <span className="font-mono text-xs font-bold text-gold">
                {number}
            </span>
            <span>{children}</span>
        </li>
    );
}

function DeletionPoint({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-white/[0.04]">
            <Check className="mt-0.5 size-4 shrink-0 text-digital" />
            <p className="text-sm leading-6 text-slate-600 dark:text-slate-300">
                {children}
            </p>
        </div>
    );
}
