import { Link } from '@inertiajs/react';
import { ArrowUpRight, BusFront, Package, QrCode } from 'lucide-react';
import parcelsHeroImage from '@/assets/parcels-list-hero-kora.jpg';
import { COUNTER_AGENCY_HUB } from './mock-data';

interface ParcelRegistryHeroProps {
    pendingPreRegCount: number;
    todayMovementsCount: number;
}

export function ParcelRegistryHero({
    pendingPreRegCount,
    todayMovementsCount,
}: ParcelRegistryHeroProps) {
    return (
        <section className="relative isolate mb-6 min-h-[20rem] overflow-hidden rounded-[1.75rem] bg-[#001d4e] px-6 py-7 text-white shadow-[0_28px_65px_rgba(0,29,78,0.22)] ring-1 ring-black/10 sm:px-8 sm:py-8 lg:grid lg:grid-cols-[minmax(0,1.25fr)_minmax(18rem,0.75fr)] lg:items-center lg:gap-8 lg:px-10 dark:ring-white/10">
            {/* Editorial counter & KORA navy/gold fleet photograph */}
            <img
                src={parcelsHeroImage}
                alt="Comptoir colis et flotte KORA"
                className="pointer-events-none absolute inset-0 -z-20 h-full w-full object-cover object-[78%_42%]"
            />

            {/* Directional navy gradient: deep solid KORA navy on the left, softly revealing the KORA scene on the right */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(0,29,78,0.95)_0%,rgba(0,29,78,0.86)_60%,rgba(0,29,78,0.48)_100%)] lg:bg-[linear-gradient(95deg,#001d4e_0%,#001d4e_36%,rgba(0,29,78,0.92)_52%,rgba(0,29,78,0.45)_72%,rgba(0,29,78,0.12)_100%)]"
            />

            {/* Subtle KORA architectural rings & parcel-transit micro-animation on the navy canvas */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -top-28 -left-24 -z-10 size-[24rem] rounded-full border border-gold/15"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -bottom-44 left-[18%] -z-10 size-[30rem] rounded-full border border-white/[0.08]"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute top-7 right-[44%] -z-10 hidden items-center gap-2 opacity-75 xl:flex"
            >
                <span className="size-2 rounded-full bg-gold shadow-[0_0_0_5px_rgba(246,176,0,0.16)] motion-safe:animate-pulse" />
                <span className="h-px w-16 border-t border-dashed border-gold/45" />
                <span className="grid size-7 place-items-center rounded-lg border border-gold/30 bg-gold/15 text-gold shadow-[0_8px_18px_rgba(0,0,0,0.2)] motion-safe:animate-[kora-bus-float_2.8s_ease-in-out_infinite]">
                    <Package className="size-3.5" />
                </span>
                <span className="h-px w-14 border-t border-dashed border-white/25" />
                <span className="grid size-7 place-items-center rounded-lg border border-white/15 bg-white/10 text-white/90 motion-safe:animate-[kora-bus-float_3.4s_ease-in-out_infinite_0.4s]">
                    <BusFront className="size-3.5 text-gold" />
                </span>
            </div>

            {/* Left Column: Standard KORA Hero typography + 2 action buttons (Primary + Secondary) */}
            <div className="relative z-10 flex flex-col justify-between gap-7">
                <div>
                    <span className="mb-3 block text-[0.625rem] font-black tracking-[0.18em] text-gold uppercase">
                        KORA Colis · {COUNTER_AGENCY_HUB.city} ·{' '}
                        {todayMovementsCount} mouvements actifs
                    </span>
                    <h2 className="max-w-3xl text-[clamp(2.125rem,3.4vw,3.15rem)] leading-[1.02] font-extrabold tracking-[-0.045em] text-balance text-white">
                        Registre des colis,
                        <br />
                        <em className="font-normal text-gold not-italic">
                            chaque envoi sous contrôle.
                        </em>
                    </h2>
                    <p className="mt-4 max-w-[33rem] text-sm leading-[1.6] text-white/70">
                        Suivez en direct les expéditions au départ de{' '}
                        {COUNTER_AGENCY_HUB.name}, les arrivées à réceptionner
                        et les retraits au comptoir.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2.5">
                    <Link
                        href="/sale/parcels/new"
                        className="group inline-flex min-h-11 items-center gap-2 rounded-[0.75rem] bg-gold px-4 text-[0.75rem] font-extrabold text-[var(--kora)] shadow-[0_10px_24px_rgba(246,176,0,0.2)] transition-transform duration-300 hover:-translate-y-1 focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--kora)]"
                    >
                        <span>Enregistrer un colis</span>
                        <ArrowUpRight className="size-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                    </Link>

                    <Link
                        href="/sale/parcels/new?mode=lookup"
                        className="inline-flex min-h-11 items-center gap-2 rounded-[0.75rem] border border-white/20 bg-white/[0.07] px-4 text-[0.75rem] font-extrabold text-white backdrop-blur-xs transition-colors duration-300 hover:border-white/40 hover:bg-white/12 focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--kora)]"
                    >
                        <QrCode className="size-3.5 text-gold" />
                        <span>Finaliser un pré-enregistrement</span>
                        {pendingPreRegCount > 0 && (
                            <span className="inline-flex min-w-4 items-center justify-center rounded-full bg-gold px-1.5 py-0.5 text-[0.625rem] font-black text-[var(--kora)]">
                                {pendingPreRegCount}
                            </span>
                        )}
                    </Link>
                </div>
            </div>

            {/* Right Column kept open so the KORA counter & coaches photography breathes */}
            <div aria-hidden="true" className="hidden lg:block" />
        </section>
    );
}
