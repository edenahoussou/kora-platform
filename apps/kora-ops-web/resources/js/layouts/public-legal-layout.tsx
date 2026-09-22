import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { accountDeletion, privacyPolicy } from '@/routes';

export default function PublicLegalLayout({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <div className="min-h-svh bg-slate-50 text-[var(--kora)] dark:bg-slate-950 dark:text-slate-100">
            <header className="relative overflow-hidden bg-[var(--kora)] text-white">
                <div className="absolute -top-40 -right-32 size-[28rem] rounded-full bg-gold/10 blur-3xl" />
                <div className="absolute -bottom-56 -left-24 size-[26rem] rounded-full bg-digital/20 blur-3xl" />
                <div className="relative mx-auto flex w-full max-w-6xl items-center justify-between gap-6 px-5 py-5 sm:px-8 lg:px-10">
                    <Link href={privacyPolicy()} className="shrink-0">
                        <img
                            src="/kora-logo-light-v2.png"
                            alt="KORA EXPRESS"
                            className="h-12 w-auto object-contain object-left sm:h-14"
                        />
                    </Link>

                    <nav
                        aria-label="Navigation des pages légales"
                        className="hidden items-center gap-2 text-sm sm:flex"
                    >
                        <Link
                            href={privacyPolicy()}
                            className="rounded-full px-4 py-2 text-white/75 transition hover:bg-white/10 hover:text-white"
                        >
                            Confidentialité
                        </Link>
                        <Link
                            href={accountDeletion()}
                            className="rounded-full bg-gold px-4 py-2 font-semibold text-[var(--kora)] transition hover:bg-white"
                        >
                            Supprimer mon compte
                        </Link>
                    </nav>
                </div>

                <div className="relative mx-auto w-full max-w-6xl px-5 pt-12 pb-16 sm:px-8 lg:px-10 lg:pt-16 lg:pb-20">
                    <p className="mb-4 text-xs font-bold tracking-[0.18em] text-gold uppercase">
                        KORA APP PASSAGER BUS
                    </p>
                    <h1 className="max-w-3xl text-4xl leading-[1.02] font-extrabold tracking-[-0.045em] sm:text-5xl lg:text-6xl">
                        Vos données, vos choix.
                    </h1>
                    <p className="mt-5 max-w-2xl text-base leading-7 text-white/70 sm:text-lg">
                        Retrouvez les informations de confidentialité et les
                        démarches liées à votre compte KORA.
                    </p>
                </div>
            </header>

            <main className="mx-auto w-full max-w-6xl px-5 py-10 sm:px-8 sm:py-14 lg:px-10">
                {children}
            </main>

            <footer className="border-t border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
                <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 px-5 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
                    <p>
                        © {new Date().getFullYear()} KORA EXPRESS · KORA App
                        Passager Bus
                    </p>
                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                        <Link
                            href={privacyPolicy()}
                            className="transition hover:text-[var(--kora)] dark:hover:text-white"
                        >
                            Politique de confidentialité
                        </Link>
                        <Link
                            href={accountDeletion()}
                            className="inline-flex items-center gap-1 transition hover:text-[var(--kora)] dark:hover:text-white"
                        >
                            Suppression de compte
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
