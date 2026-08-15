import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * KORA OPS split auth layout.
 *
 * Navy left panel (logo, tagline, network animation, trust row) and a
 * centered form panel on the right. Reproduces the validated maquette at
 * `docs/maquettes/kora-ops-web/`. The navy panel collapses below `lg`,
 * leaving a single-column form on small screens.
 */
export default function AuthKoraLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="kora-auth-page relative grid min-h-svh grid-cols-1 bg-background lg:grid-cols-[1.08fr_0.92fr] lg:overflow-hidden">
            {/* Navy brand panel */}
            <section
                aria-hidden="true"
                className="kora-auth-brand relative hidden flex-col justify-between overflow-hidden px-[clamp(2.75rem,6vw,5.625rem)] py-12 text-white lg:flex"
            >
                <div className="relative z-10 flex items-center justify-between">
                    <img
                        src="/kora-logo-light-v2.png"
                        alt="KORA EXPRESS"
                        className="kora-auth-logo h-24 w-[14.6875rem] object-contain object-left"
                    />
                    <span className="rounded-full border border-white/15 px-3 py-2 text-[0.5625rem] font-black tracking-[0.14em] text-white/65 uppercase">
                        KORA OPS
                    </span>
                </div>

                <div className="kora-auth-copy relative z-10 mt-[2vh] max-w-[40rem]">
                    <span className="mb-3 block text-xs font-black tracking-[0.15em] text-gold uppercase">
                        Le réseau en mouvement
                    </span>
                    <h1 className="m-0 max-w-[47.5rem] text-[clamp(2.8125rem,5.1vw,4.875rem)] leading-[0.94] font-extrabold tracking-[-0.045em]">
                        Pilotez chaque trajet.
                        <br />
                        <em className="inline-block text-gold not-italic">
                            Maîtrisez l'ensemble.
                        </em>
                    </h1>
                    <p className="mt-5 max-w-[42.5rem] text-[0.9375rem] leading-relaxed text-white/70">
                        Du premier billet au dernier arrêt, gardez les
                        opérations KORA EXPRESS fluides, sûres et parfaitement
                        coordonnées.
                    </p>

                    <div className="kora-auth-network relative z-10 mt-[2.125rem] max-w-[40.625rem] rounded-[1.25rem] border border-white/10 bg-white/5 p-5 shadow-[inset_0_1px_rgba(255,255,255,0.07)] backdrop-blur-md">
                        <div className="kora-auth-track relative mx-3 h-[3.375rem] border-b-2 border-white/20">
                            <span className="kora-auth-stop kora-auth-stop-first absolute bottom-[-7px] left-0 size-3 rounded-full border-[3px] border-[var(--kora)] bg-gold shadow-[0_0_0_1px_rgba(255,255,255,0.4)]">
                                <b className="absolute bottom-4 text-[0.5rem] whitespace-nowrap text-white/55">
                                    Cotonou
                                </b>
                            </span>
                            <span className="kora-auth-stop kora-auth-stop-middle absolute bottom-[-7px] left-[48%] size-3 rounded-full border-[3px] border-[var(--kora)] bg-white shadow-[0_0_0_1px_rgba(255,255,255,0.4)]">
                                <b className="absolute bottom-4 left-1/2 -translate-x-1/2 text-[0.5rem] whitespace-nowrap text-white/55">
                                    Parakou
                                </b>
                            </span>
                            <span className="kora-auth-stop kora-auth-stop-last absolute right-0 bottom-[-7px] size-3 rounded-full border-[3px] border-[var(--kora)] bg-white shadow-[0_0_0_1px_rgba(255,255,255,0.4)]">
                                <b className="absolute right-0 bottom-4 text-[0.5rem] whitespace-nowrap text-white/55">
                                    Malanville
                                </b>
                            </span>
                            <i className="kora-auth-vehicle absolute bottom-[7px] left-[38%] grid size-[1.8125rem] place-items-center rounded-[0.5625rem] bg-gold text-[0.625rem] font-black text-[var(--kora)] not-italic shadow-[0_9px_20px_rgba(246,176,0,0.24)]">
                                K
                            </i>
                        </div>
                        <div className="mt-[1.375rem] grid grid-cols-3 gap-2.5">
                            <span className="border-l border-white/10 pl-3 text-[0.5rem] text-white/50">
                                <b className="mb-1 block text-[0.9375rem] font-bold text-white">
                                    46
                                </b>
                                départs aujourd'hui
                            </span>
                            <span className="border-l border-white/10 pl-3 text-[0.5rem] text-white/50">
                                <b className="mb-1 block text-[0.9375rem] font-bold text-white">
                                    91,3%
                                </b>
                                à l'heure
                            </span>
                            <span className="border-l border-white/10 pl-3 text-[0.5rem] text-white/50">
                                <b className="mb-1 block text-[0.9375rem] font-bold text-white">
                                    31
                                </b>
                                bus disponibles
                            </span>
                        </div>
                    </div>
                </div>

                <div className="kora-auth-trust relative z-10 flex gap-[1.125rem] text-[0.625rem] text-white/70">
                    <span className="before:mr-2 before:font-black before:text-gold before:content-['✓']">
                        Accès sécurisé
                    </span>
                    <span className="before:mr-2 before:font-black before:text-gold before:content-['✓']">
                        Traçabilité complète
                    </span>
                    <span className="before:mr-2 before:font-black before:text-gold before:content-['✓']">
                        Données maîtrisées
                    </span>
                </div>
            </section>

            {/* Form panel */}
            <section className="kora-auth-panel relative grid place-items-center bg-background px-[clamp(1.875rem,6vw,6.25rem)] py-12">
                <div className="kora-auth-card relative z-10 w-full max-w-[30rem]">
                    {/* Mobile logo (visible below lg) */}
                    <Link
                        href={home()}
                        className="mb-8 flex justify-center lg:hidden"
                    >
                        <img
                            src="/kora-logo-light-v2.png"
                            alt="KORA EXPRESS"
                            className="h-16 w-auto"
                        />
                    </Link>

                    <div className="mb-6 flex items-center gap-3">
                        <span className="grid size-[2.6875rem] place-items-center rounded-[0.875rem] bg-gold text-[0.625rem] font-black text-[var(--kora)] shadow-[0_11px_25px_rgba(246,176,0,0.26)]">
                            OPS
                        </span>
                        <i className="h-0.5 w-[3.875rem] bg-gradient-to-r from-gold to-transparent" />
                    </div>

                    <span className="mb-1 block text-xs font-black tracking-[0.15em] text-gold uppercase">
                        Espace professionnel
                    </span>
                    <h2 className="mt-1 text-[1.9375rem] leading-tight font-extrabold tracking-[-0.03em] text-foreground">
                        {title}
                    </h2>
                    <p className="mt-2 mb-7 text-sm leading-relaxed text-muted-foreground">
                        {description}
                    </p>

                    {children}
                </div>
            </section>
        </div>
    );
}
