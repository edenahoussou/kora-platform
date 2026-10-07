import { Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    CreditCard,
    History,
    Ticket,
    Users,
    UserRoundCheck,
    Wallet,
} from 'lucide-react';
import { formatFcfa } from '@/components/company-accounts/company-account-types';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import companyPortal from '@/routes/company-portal';

type Props = {
    account: {
        uuid: string;
        name: string;
        reference: string;
        type: 'company' | 'ngo' | 'embassy';
    };
    credit: {
        monthlyLimit: number;
        available: number;
        periodKey: string;
    };
    stats: {
        travelersTotal: number;
        travelersActive: number;
        monthlyReservations: number;
    };
};

const typeLabels: Record<Props['account']['type'], string> = {
    company: 'Entreprise',
    ngo: 'ONG',
    embassy: 'Ambassade',
};

const typeTones: Record<Props['account']['type'], 'navy' | 'gold' | 'blue'> = {
    company: 'navy',
    ngo: 'blue',
    embassy: 'gold',
};

export default function CompanyPortalIndex({ account, credit, stats }: Props) {
    const creditUsed = Math.min(
        100,
        credit.monthlyLimit
            ? ((credit.monthlyLimit - credit.available) / credit.monthlyLimit) *
                  100
            : 0,
    );

    const sections = [
        {
            title: 'Acheter des billets',
            description:
                'Réservez des trajets pour les membres de votre organisation, réglés par votre crédit mensuel.',
            icon: Ticket,
            href: companyPortal.sale.index.url(),
        },
        {
            title: 'Membres voyageurs',
            description:
                'Déclarez les employés autorisés à voyager et gérez leur fiche.',
            icon: Users,
            href: companyPortal.travelers.index.url(),
        },
        {
            title: 'Historique des achats',
            description:
                'Retrouvez vos réservations, voyageurs et billets émis.',
            icon: History,
            href: companyPortal.reservations.index.url(),
        },
        {
            title: 'Relevés de facturation',
            description:
                'Consultez vos relevés mensuels consolidés et les factures normalisées.',
            icon: CalendarClock,
            href: companyPortal.statements.index.url(),
        },
    ];

    return (
        <>
            <Head title="Espace entreprise" />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Espace entreprise
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                                {account.name}
                            </h1>
                            <KoraStatusBadge
                                label={typeLabels[account.type]}
                                tone={typeTones[account.type]}
                            />
                        </div>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Référence {account.reference} — gérez vos achats de
                            billets réglés par crédit mensuel.
                        </p>
                    </div>
                </div>

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1">
                    <div className="cash-ledger-card cash-ledger-card-cash">
                        <span className="grid size-9 place-items-center rounded-xl bg-gold/10 text-gold">
                            <CreditCard className="size-4" />
                        </span>
                        <span className="mt-4 block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                            Plafond mensuel
                        </span>
                        <strong className="mt-1 block text-lg font-black text-foreground">
                            {formatFcfa(credit.monthlyLimit)}
                        </strong>
                    </div>
                    <div className="cash-ledger-card cash-ledger-card-cash">
                        <span className="grid size-9 place-items-center rounded-xl bg-emerald-500/10 text-emerald-600">
                            <Wallet className="size-4" />
                        </span>
                        <span className="mt-4 block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                            Crédit disponible
                        </span>
                        <strong className="mt-1 block text-lg font-black text-foreground">
                            {formatFcfa(credit.available)}
                        </strong>
                        <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-gold transition-[width] duration-500"
                                style={{ width: `${creditUsed}%` }}
                            />
                        </div>
                    </div>
                    <div className="cash-ledger-card cash-ledger-card-cash">
                        <span className="grid size-9 place-items-center rounded-xl bg-gold/10 text-gold">
                            <UserRoundCheck className="size-4" />
                        </span>
                        <span className="mt-4 block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                            Membres voyageurs
                        </span>
                        <strong className="mt-1 block text-lg font-black text-foreground">
                            {stats.travelersTotal}
                        </strong>
                        <p className="mt-1 text-[0.6875rem] font-bold text-muted-foreground">
                            dont {stats.travelersActive} actif
                            {stats.travelersActive > 1 ? 's' : ''}
                        </p>
                    </div>
                    <div className="cash-ledger-card cash-ledger-card-cash">
                        <span className="grid size-9 place-items-center rounded-xl bg-muted text-foreground">
                            <Ticket className="size-4" />
                        </span>
                        <span className="mt-4 block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                            Réservations du mois
                        </span>
                        <strong className="mt-1 block text-lg font-black text-foreground">
                            {stats.monthlyReservations}
                        </strong>
                        <p className="mt-1 text-[0.6875rem] font-bold text-muted-foreground">
                            période {credit.periodKey}
                        </p>
                    </div>
                </section>

                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro">
                    <div>
                        <span className="section-kicker">Services</span>
                        <h2>Votre espace</h2>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    {sections.map((section) => {
                        const content = (
                            <>
                                <span className="grid size-9 place-items-center rounded-xl bg-gold/10 text-gold">
                                    <section.icon className="size-4" />
                                </span>
                                <div className="mt-4">
                                    <strong className="block text-sm font-extrabold text-foreground">
                                        {section.title}
                                    </strong>
                                    <p className="mt-1 text-[0.75rem] leading-5 text-muted-foreground">
                                        {section.description}
                                    </p>
                                </div>
                                {section.href === null && (
                                    <span className="mt-3 inline-block text-[0.625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                        Bientôt disponible
                                    </span>
                                )}
                            </>
                        );

                        return section.href === null ? (
                            <div
                                key={section.title}
                                className={cn(
                                    'cash-ledger-card opacity-70',
                                    'select-none',
                                )}
                                aria-disabled="true"
                            >
                                {content}
                            </div>
                        ) : (
                            <Link
                                key={section.title}
                                href={section.href}
                                className="cash-ledger-card cash-ledger-card-cash block"
                            >
                                {content}
                            </Link>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
