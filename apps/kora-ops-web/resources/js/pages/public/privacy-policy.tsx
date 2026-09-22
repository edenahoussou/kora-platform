import { Head, Link } from '@inertiajs/react';
import {
    Banknote,
    BellRing,
    CheckCircle2,
    ChevronRight,
    Cookie,
    Database,
    LockKeyhole,
    Mail,
    ShieldCheck,
    Smartphone,
    type LucideIcon,
} from 'lucide-react';
import { accountDeletion } from '@/routes';

type Props = {
    supportEmail: string;
    lastUpdated: string;
};

const sections = [
    ['responsable', '1. Responsable du traitement'],
    ['collecte', '2. Données collectées'],
    ['utilisation', '3. Utilisation des données'],
    ['partage', '4. Partage des données'],
    ['conservation', '5. Conservation'],
    ['droits', '6. Vos droits'],
    ['securite', '7. Sécurité'],
    ['modifications', '8. Modifications'],
];

export default function PrivacyPolicy({ supportEmail, lastUpdated }: Props) {
    return (
        <>
            <Head title="Politique de confidentialité" />

            <div className="grid gap-10 lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start lg:gap-16">
                <aside className="lg:sticky lg:top-8">
                    <p className="mb-3 text-xs font-bold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400">
                        Sur cette page
                    </p>
                    <nav className="grid gap-1">
                        {sections.map(([id, label]) => (
                            <a
                                key={id}
                                href={`#${id}`}
                                className="rounded-lg px-3 py-2 text-sm text-slate-600 transition hover:bg-white hover:text-[var(--kora)] dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white"
                            >
                                {label}
                            </a>
                        ))}
                    </nav>
                </aside>

                <article className="min-w-0 rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_1.5rem_4rem_rgba(0,29,78,0.07)] sm:p-10 lg:p-14 dark:border-white/10 dark:bg-slate-900 dark:shadow-none">
                    <div className="flex flex-col gap-5 border-b border-slate-200 pb-8 sm:flex-row sm:items-start sm:justify-between dark:border-white/10">
                        <div>
                            <div className="mb-4 inline-flex size-12 items-center justify-center rounded-2xl bg-gold/15 text-[var(--kora)] dark:text-gold">
                                <ShieldCheck className="size-6" />
                            </div>
                            <h2 className="text-3xl font-extrabold tracking-[-0.04em] sm:text-4xl">
                                Politique de confidentialité
                            </h2>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                                Cette politique explique comment KORA traite les
                                données des personnes qui utilisent KORA App
                                Passager Bus pour rechercher un départ, réserver
                                et consulter un billet.
                            </p>
                        </div>
                        <p className="shrink-0 text-xs font-medium text-slate-500 dark:text-slate-400">
                            Mise à jour : {lastUpdated}
                        </p>
                    </div>

                    <div className="divide-y divide-slate-200 dark:divide-white/10">
                        <section
                            id="responsable"
                            className="scroll-mt-8 py-8 first:pt-10"
                        >
                            <SectionTitle
                                icon={Mail}
                                title="1. Responsable du traitement"
                            />
                            <p className="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                                KORA EXPRESS est responsable du traitement des
                                données utilisées dans KORA App Passager Bus.
                                Pour toute question relative à vos données, vous
                                pouvez écrire à{' '}
                                <a
                                    href={`mailto:${supportEmail}`}
                                    className="font-semibold text-digital underline decoration-digital/30 underline-offset-4 hover:decoration-digital"
                                >
                                    {supportEmail}
                                </a>
                                .
                            </p>
                        </section>

                        <section id="collecte" className="scroll-mt-8 py-8">
                            <SectionTitle
                                icon={Database}
                                title="2. Données collectées"
                            />
                            <p className="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                                Nous collectons uniquement les informations
                                nécessaires au fonctionnement du service :
                            </p>
                            <InfoGrid
                                items={[
                                    {
                                        icon: Smartphone,
                                        title: 'Compte et identité',
                                        description:
                                            'Nom, numéro de téléphone, adresse e-mail et informations de profil fournies lors de l’activation du compte.',
                                    },
                                    {
                                        icon: CheckCircle2,
                                        title: 'Réservations et billets',
                                        description:
                                            'Itinéraire, départ, passagers, siège, statut de réservation et informations nécessaires à l’émission du billet.',
                                    },
                                    {
                                        icon: Banknote,
                                        title: 'Paiement',
                                        description:
                                            'Informations utiles au suivi du paiement et du remboursement. KORA ne stocke pas le code secret de votre moyen de paiement.',
                                    },
                                    {
                                        icon: LockKeyhole,
                                        title: 'Sécurité technique',
                                        description:
                                            'Données de session, appareil de confiance et journaux de sécurité nécessaires à la protection du compte.',
                                    },
                                ]}
                            />
                        </section>

                        <section id="utilisation" className="scroll-mt-8 py-8">
                            <SectionTitle
                                icon={BellRing}
                                title="3. Utilisation des données"
                            />
                            <BulletList
                                items={[
                                    'créer et sécuriser votre compte voyageur ;',
                                    'calculer un devis tarifaire, gérer une réservation et émettre un billet ;',
                                    'confirmer les paiements, traiter les remboursements et répondre aux demandes d’assistance ;',
                                    'vous envoyer les informations indispensables au voyage et les alertes liées à votre compte ;',
                                    'détecter les usages abusifs, prévenir la fraude et respecter nos obligations légales.',
                                ]}
                            />
                        </section>

                        <section id="partage" className="scroll-mt-8 py-8">
                            <SectionTitle
                                icon={ShieldCheck}
                                title="4. Partage des données"
                            />
                            <p className="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                                Les données peuvent être communiquées, dans la
                                mesure nécessaire, aux prestataires qui
                                participent à la fourniture du service :
                                opérateurs de paiement, services de
                                notification, transporteurs et prestataires
                                techniques. Ces partenaires n’utilisent pas vos
                                données pour les vendre à des fins
                                publicitaires. Nous pouvons également les
                                communiquer lorsque la loi, une autorité
                                compétente ou la sécurité des voyageurs l’exige.
                            </p>
                        </section>

                        <section id="conservation" className="scroll-mt-8 py-8">
                            <SectionTitle
                                icon={Database}
                                title="5. Conservation"
                            />
                            <p className="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                                Les données sont conservées pendant la durée
                                nécessaire à la gestion du compte, des
                                réservations, des billets, des paiements et des
                                demandes d’assistance. Après une demande de
                                suppression, les données personnelles sont
                                supprimées ou anonymisées lorsque cela est
                                possible. Certaines traces comptables,
                                contractuelles ou de sécurité peuvent être
                                conservées pendant la durée imposée par la loi
                                ou nécessaire à la défense de nos droits.
                            </p>
                        </section>

                        <section id="droits" className="scroll-mt-8 py-8">
                            <SectionTitle
                                icon={CheckCircle2}
                                title="6. Vos droits"
                            />
                            <p className="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                                Vous pouvez demander l’accès, la rectification,
                                la suppression ou la limitation du traitement de
                                vos données, dans les limites prévues par la
                                réglementation applicable. La procédure de
                                suppression est détaillée sur la page dédiée :{' '}
                                <Link
                                    href={accountDeletion()}
                                    className="inline-flex items-center gap-1 font-semibold text-digital underline decoration-digital/30 underline-offset-4 hover:decoration-digital"
                                >
                                    supprimer un compte KORA
                                    <ChevronRight className="size-3.5" />
                                </Link>
                                .
                            </p>
                        </section>

                        <section id="securite" className="scroll-mt-8 py-8">
                            <SectionTitle
                                icon={LockKeyhole}
                                title="7. Sécurité"
                            />
                            <p className="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                                KORA applique des mesures techniques et
                                organisationnelles destinées à protéger les
                                données contre l’accès non autorisé, la perte,
                                l’altération ou la divulgation. Aucun service
                                connecté à Internet ne peut toutefois garantir
                                une sécurité absolue : contactez-nous rapidement
                                si vous constatez une activité inhabituelle sur
                                votre compte.
                            </p>
                        </section>

                        <section
                            id="modifications"
                            className="scroll-mt-8 py-8 last:pb-2"
                        >
                            <SectionTitle
                                icon={Cookie}
                                title="8. Modifications"
                            />
                            <p className="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                                Cette politique peut évoluer pour tenir compte
                                des changements du service ou de la
                                réglementation. La date de mise à jour affichée
                                en haut de la page indique la version en
                                vigueur.
                            </p>
                        </section>
                    </div>
                </article>
            </div>
        </>
    );
}

function SectionTitle({
    icon: Icon,
    title,
}: {
    icon: LucideIcon;
    title: string;
}) {
    return (
        <h3 className="flex items-center gap-3 text-lg font-bold tracking-[-0.02em]">
            <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-digital dark:bg-white/10">
                <Icon className="size-4" />
            </span>
            {title}
        </h3>
    );
}

function InfoGrid({
    items,
}: {
    items: Array<{
        icon: LucideIcon;
        title: string;
        description: string;
    }>;
}) {
    return (
        <div className="mt-6 grid gap-3 sm:grid-cols-2">
            {items.map(({ icon: Icon, title, description }) => (
                <div
                    key={title}
                    className="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.04]"
                >
                    <Icon className="size-5 text-gold" />
                    <h4 className="mt-4 text-sm font-bold">{title}</h4>
                    <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">
                        {description}
                    </p>
                </div>
            ))}
        </div>
    );
}

function BulletList({ items }: { items: string[] }) {
    return (
        <ul className="mt-4 grid gap-3 text-sm leading-7 text-slate-600 dark:text-slate-300">
            {items.map((item) => (
                <li key={item} className="flex gap-3">
                    <CheckCircle2 className="mt-1 size-4 shrink-0 text-digital" />
                    <span>{item}</span>
                </li>
            ))}
        </ul>
    );
}
