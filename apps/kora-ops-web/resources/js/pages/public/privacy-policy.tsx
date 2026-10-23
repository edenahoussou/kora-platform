import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { accountDeletion } from '@/routes';

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

const toc = [
    ['responsable', '1. Responsable du traitement'],
    ['champ', '2. Champ d’application'],
    ['donnees', '3. Données collectées'],
    ['finalites', '4. Finalités du traitement'],
    ['destinataires', '5. Destinataires et sous-traitants'],
    ['transferts', '6. Transferts hors du Bénin'],
    ['conservation', '7. Durées de conservation'],
    ['securite', '8. Sécurité'],
    ['droits', '9. Vos droits'],
    ['mineurs', '10. Mineurs'],
    ['cookies', '11. Cookies et traceurs'],
    ['modifications', '12. Modifications'],
    ['reclamation', '13. Réclamation'],
    ['contact', '14. Contact'],
];

const collectedData: Array<[string, string, string]> = [
    [
        'Compte et identité',
        'Nom, numéro de téléphone, adresse e-mail, informations de profil',
        'Création et sécurisation du compte voyageur',
    ],
    [
        'Voyages et billets',
        'Itinéraire, départ, siège, passagers, statut de réservation, pièce d’identité et statut vaccinal fièvre jaune si saisis',
        'Devis tarifaire, réservation, émission et contrôle du billet',
    ],
    [
        'Paiement',
        'Référence de transaction, opérateur et statut du paiement',
        'Confirmation du paiement et suivi comptable. KORA ne stocke jamais le code secret de votre moyen de paiement',
    ],
    [
        'Données techniques',
        'Jeton de notification push, appareil de confiance, journaux de session et de sécurité',
        'Notifications de voyage, protection du compte et prévention de la fraude',
    ],
    [
        'Communications',
        'Contenu des échanges avec le support (e-mail, WhatsApp)',
        'Traitement des demandes d’assistance',
    ],
];

const processors: Array<[string, string]> = [
    [
        'KKiaPay, FedaPay et MTN MoMo',
        'Traitement des paiements mobile money et carte',
    ],
    ['Google Firebase', 'Notifications push sur l’application'],
    ['WhatsApp (via WAHA)', 'Notifications liées aux billets et au compte'],
    ['Brevo', 'Envoi des e-mails transactionnels'],
    ['OVH', 'Hébergement de la plateforme'],
];

const retention: Array<[string, string]> = [
    [
        'Compte et profil',
        'Durée de vie du compte, puis suppression ou anonymisation sous 30 jours après une demande',
    ],
    [
        'Réservations, billets et pièces de paiement',
        '10 ans (obligations comptables et fiscales, référentiel OHADA)',
    ],
    ['Journaux techniques et de sécurité', '12 mois maximum'],
    [
        'Demandes d’effacement',
        'Trace de traitement conservée sans données personnelles du demandeur',
    ],
];

export default function PrivacyPolicy({ supportEmail, company }: Props) {
    return (
        <>
            <Head title="Politique de confidentialité" />

            <div className="grid gap-10 lg:grid-cols-[13rem_minmax(0,1fr)] lg:items-start">
                <aside className="hidden lg:sticky lg:top-8 lg:block">
                    <p className="mb-3 text-xs font-bold tracking-[0.14em] text-slate-500 uppercase">
                        Sommaire
                    </p>
                    <nav className="grid gap-0.5">
                        {toc.map(([id, label]) => (
                            <a
                                key={id}
                                href={`#${id}`}
                                className="rounded-lg px-3 py-1.5 text-sm text-slate-600 transition hover:bg-white hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white"
                            >
                                {label}
                            </a>
                        ))}
                    </nav>
                </aside>

                <article className="min-w-0 rounded-2xl border border-slate-200 bg-white px-6 py-8 sm:px-10 sm:py-12 dark:border-white/10 dark:bg-slate-900">
                    <header className="border-b border-slate-200 pb-8 dark:border-white/10">
                        <h1 className="text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl">
                            Politique de confidentialité
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
                    </header>

                    <Section
                        id="responsable"
                        title="1. Responsable du traitement"
                    >
                        <p>
                            Le responsable du traitement des données
                            personnelles est <strong>{company.name}</strong>,
                            société représentée par{' '}
                            <strong>{company.director}</strong>.
                        </p>
                        <dl className="mt-4 grid gap-1.5 text-sm">
                            <LegalRow
                                label="Siège social"
                                value={company.address}
                            />
                            {company.rccm && (
                                <LegalRow label="RCCM" value={company.rccm} />
                            )}
                            {company.nim && (
                                <LegalRow label="NIM" value={company.nim} />
                            )}
                            <LegalRow label="Téléphone" value={company.phone} />
                            <LegalRow
                                label="Contact données personnelles"
                                value={
                                    <a
                                        href={`mailto:${supportEmail}`}
                                        className="font-medium text-digital underline decoration-digital/30 underline-offset-4 hover:decoration-digital"
                                    >
                                        {supportEmail}
                                    </a>
                                }
                            />
                        </dl>
                    </Section>

                    <Section id="champ" title="2. Champ d’application">
                        <p>
                            La présente politique décrit la manière dont{' '}
                            {company.name} collecte, utilise et protège les
                            données personnelles des utilisateurs de
                            l’application mobile KORA App Passager Bus, utilisée
                            pour rechercher un départ, réserver et consulter un
                            billet de transport.
                        </p>
                    </Section>

                    <Section id="donnees" title="3. Données collectées">
                        <p>
                            Nous collectons uniquement les données nécessaires
                            au fonctionnement du service :
                        </p>
                        <DataTable
                            head={['Catégorie', 'Données', 'Finalité']}
                            rows={collectedData}
                        />
                    </Section>

                    <Section id="finalites" title="4. Finalités du traitement">
                        <p>Vos données sont utilisées pour :</p>
                        <ul>
                            <li>
                                créer et sécuriser votre compte voyageur
                                (authentification par code à usage unique) ;
                            </li>
                            <li>
                                calculer un devis tarifaire, gérer vos
                                réservations et émettre vos billets ;
                            </li>
                            <li>
                                confirmer vos paiements et traiter les demandes
                                d’assistance ;
                            </li>
                            <li>
                                vous envoyer les informations indispensables au
                                voyage et les alertes liées à votre compte ;
                            </li>
                            <li>
                                prévenir la fraude et respecter nos obligations
                                légales, comptables et fiscales.
                            </li>
                        </ul>
                    </Section>

                    <Section
                        id="destinataires"
                        title="5. Destinataires et sous-traitants"
                    >
                        <p>
                            Vos données peuvent être communiquées, dans la seule
                            mesure nécessaire, aux prestataires qui participent
                            à la fourniture du service :
                        </p>
                        <DataTable
                            head={['Prestataire', 'Rôle']}
                            rows={processors}
                        />
                        <p>
                            Vos données ne sont <strong>jamais vendues</strong>{' '}
                            ni louées à des tiers à des fins publicitaires.
                            Elles peuvent également être communiquées lorsque la
                            loi ou une autorité compétente l’exige.
                        </p>
                    </Section>

                    <Section
                        id="transferts"
                        title="6. Transferts hors du Bénin"
                    >
                        <p>
                            Certains prestataires listés à l’article 5 peuvent
                            traiter les données sur des serveurs situés hors du
                            Bénin. Ces transferts sont limités aux données
                            strictement nécessaires à la fourniture du service
                            concerné et s’opèrent sous des garanties
                            contractuelles appropriées.
                        </p>
                    </Section>

                    <Section
                        id="conservation"
                        title="7. Durées de conservation"
                    >
                        <DataTable
                            head={['Données', 'Durée']}
                            rows={retention}
                        />
                        <p>
                            À l’issue d’une demande de suppression, les données
                            personnelles sont supprimées ou anonymisées lorsque
                            cela est possible. Les traces comptables,
                            contractuelles ou fiscales dont la conservation est
                            imposée par la loi sont conservées sous forme
                            anonymisée ou archivée.
                        </p>
                    </Section>

                    <Section id="securite" title="8. Sécurité">
                        <p>
                            {company.name} applique des mesures techniques et
                            organisationnelles destinées à protéger vos données
                            : chiffrement des échanges (TLS), chiffrement des
                            identifiants d’intégration, signature
                            cryptographique des billets, accès au back-office
                            restreint par rôles et journalisation des opérations
                            sensibles. Aucun service connecté à Internet ne peut
                            toutefois garantir une sécurité absolue :
                            signalez-nous rapidement toute activité inhabituelle
                            sur votre compte.
                        </p>
                    </Section>

                    <Section id="droits" title="9. Vos droits">
                        <p>
                            Dans les limites prévues par la réglementation en
                            vigueur en République du Bénin, vous disposez des
                            droits d’accès, de rectification, de suppression et
                            de limitation du traitement de vos données
                            personnelles.
                        </p>
                        <p>
                            Vous pouvez exercer ces droits via la{' '}
                            <Link
                                href={accountDeletion()}
                                className="inline-flex items-center gap-1 font-semibold text-digital underline decoration-digital/30 underline-offset-4 hover:decoration-digital"
                            >
                                page de suppression de compte
                                <ChevronRight className="size-3.5" />
                            </Link>{' '}
                            ou en écrivant à{' '}
                            <a
                                href={`mailto:${supportEmail}`}
                                className="font-semibold text-digital underline decoration-digital/30 underline-offset-4 hover:decoration-digital"
                            >
                                {supportEmail}
                            </a>
                            . Nous pouvons vous demander un élément de
                            vérification afin de confirmer votre identité. Nous
                            répondons à toute demande dans un délai maximum de{' '}
                            <strong>30 jours</strong>.
                        </p>
                    </Section>

                    <Section id="mineurs" title="10. Mineurs">
                        <p>
                            Le service s’adresse aux personnes majeures. Si un
                            mineur utilise l’application, son compte et ses
                            réservations relèvent de la responsabilité du
                            titulaire de la ligne téléphonique ou du
                            représentant légal associé au voyage.
                        </p>
                    </Section>

                    <Section id="cookies" title="11. Cookies et traceurs">
                        <p>
                            L’application KORA App Passager Bus n’utilise pas de
                            cookies ni de traceurs publicitaires. Seuls les
                            identifiants techniques nécessaires aux
                            notifications push et à la sécurité de votre compte
                            sont enregistrés.
                        </p>
                    </Section>

                    <Section id="modifications" title="12. Modifications">
                        <p>
                            La présente politique peut évoluer pour tenir compte
                            des changements du service ou de la réglementation.
                            La version et la date d’entrée en vigueur affichées
                            en haut du document font foi. En cas de changement
                            substantiel, les utilisateurs en seront informés via
                            l’application.
                        </p>
                    </Section>

                    <Section id="reclamation" title="13. Réclamation">
                        <p>
                            Si vous estimez que le traitement de vos données ne
                            respecte pas la réglementation applicable (notamment
                            la loi n° 2017-20 portant code du numérique en
                            République du Bénin), vous pouvez introduire une
                            réclamation auprès de l’Autorité de Protection des
                            Données à caractère Personnel (APDP) du Bénin, après
                            nous avoir contactés pour permettre un règlement
                            amiable.
                        </p>
                    </Section>

                    <Section id="contact" title="14. Contact" last>
                        <p>
                            Pour toute question relative à vos données
                            personnelles :{' '}
                            <a
                                href={`mailto:${supportEmail}`}
                                className="font-semibold text-digital underline decoration-digital/30 underline-offset-4 hover:decoration-digital"
                            >
                                {supportEmail}
                            </a>{' '}
                            · {company.phone} · {company.address}, Bénin.
                        </p>
                    </Section>
                </article>
            </div>
        </>
    );
}

function Section({
    id,
    title,
    last = false,
    children,
}: {
    id: string;
    title: string;
    last?: boolean;
    children: React.ReactNode;
}) {
    return (
        <section
            id={id}
            className={`scroll-mt-8 py-7 ${last ? '' : 'border-b border-slate-100 dark:border-white/5'}`}
        >
            <h2 className="text-lg font-bold tracking-[-0.02em] text-slate-900 dark:text-white">
                {title}
            </h2>
            <div className="mt-3.5 space-y-3 text-sm leading-7 text-slate-600 dark:text-slate-300 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-5">
                {children}
            </div>
        </section>
    );
}

function LegalRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex flex-wrap gap-x-2">
            <dt className="font-semibold text-slate-900 dark:text-white">
                {label} :
            </dt>
            <dd>{value}</dd>
        </div>
    );
}

function DataTable({ head, rows }: { head: string[]; rows: string[][] }) {
    return (
        <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-white/10">
            <table className="w-full min-w-[34rem] text-left text-sm">
                <thead>
                    <tr className="border-b border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/[0.03]">
                        {head.map((h) => (
                            <th
                                key={h}
                                className="px-4 py-2.5 font-semibold text-slate-900 dark:text-white"
                            >
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-white/5">
                    {rows.map((row) => (
                        <tr key={row[0]}>
                            {row.map((cell, i) => (
                                <td
                                    key={i}
                                    className={`px-4 py-3 align-top leading-6 ${i === 0 ? 'font-semibold text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-300'}`}
                                >
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
