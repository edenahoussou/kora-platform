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
        <div className="min-h-svh bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <header className="border-b border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
                <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-6 px-5 py-4 sm:px-8">
                    <Link href={privacyPolicy()} className="shrink-0">
                        <img
                            src="/kora-logo-invoice.png"
                            alt="KORA EXPRESS"
                            className="h-10 w-auto object-contain object-left"
                        />
                    </Link>

                    <nav
                        aria-label="Navigation des pages légales"
                        className="flex items-center gap-1 text-sm"
                    >
                        <Link
                            href={privacyPolicy()}
                            className="rounded-lg px-3.5 py-2 font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        >
                            Confidentialité
                        </Link>
                        <Link
                            href={accountDeletion()}
                            className="rounded-lg px-3.5 py-2 font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        >
                            Suppression de compte
                        </Link>
                    </nav>
                </div>
            </header>

            <main className="mx-auto w-full max-w-5xl px-5 py-10 sm:px-8 sm:py-14">
                {children}
            </main>

            <footer className="border-t border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
                <div className="mx-auto flex w-full max-w-5xl flex-col gap-4 px-5 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <p>© {new Date().getFullYear()} KORA EXPRESS</p>
                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                        <Link
                            href={privacyPolicy()}
                            className="transition hover:text-slate-900 dark:hover:text-white"
                        >
                            Politique de confidentialité
                        </Link>
                        <Link
                            href={accountDeletion()}
                            className="inline-flex items-center gap-1 transition hover:text-slate-900 dark:hover:text-white"
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
