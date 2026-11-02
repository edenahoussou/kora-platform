import { Head, Link } from '@inertiajs/react';
import { Building2, FileText } from 'lucide-react';
import {
    MetricCard,
    PartnerCard,
    PartnerPage,
} from '@/components/kora-points/partner/partner-ui';
import type { PartnerOrganization } from '@/types/kora-point-partner';

const countries: Record<PartnerOrganization['country'], string> = {
    BJ: 'Bénin',
    TG: 'Togo',
    CI: 'Côte d’Ivoire',
};

export default function PartnerOrganizationPage({
    organization,
}: {
    organization: PartnerOrganization;
}) {
    const calendar = organization.settlement_calendar;
    const frequency =
        calendar?.frequency === 'daily'
            ? 'Quotidien'
            : calendar?.frequency === 'monthly'
              ? 'Mensuel'
              : 'Hebdomadaire';

    return (
        <>
            <Head title={`Mon organisation — ${organization.name}`} />
            <PartnerPage
                title="Mon organisation"
                description="Consultez les informations administratives et les conditions commerciales définies par KORA OPS."
                organizationName={organization.name}
            >
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <MetricCard
                        label="Plafond global"
                        value={`${new Intl.NumberFormat('fr-FR').format(organization.ceiling_amount)} XOF`}
                    />
                    <MetricCard
                        label="Alloué aux Points"
                        value={`${new Intl.NumberFormat('fr-FR').format(organization.allocated_amount)} XOF`}
                    />
                    <MetricCard
                        label="Disponible à répartir"
                        value={`${new Intl.NumberFormat('fr-FR').format(organization.available_allocation)} XOF`}
                    />
                </div>

                <PartnerCard title="Informations administratives">
                    <dl className="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Organisation
                            </dt>
                            <dd className="mt-1 flex items-center gap-2 text-sm font-semibold">
                                <Building2 className="size-4 text-primary" />
                                {organization.name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Code
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {organization.code}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Pays
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {countries[organization.country]}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Téléphone officiel
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {organization.phone || 'Non renseigné'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Adresse
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {organization.address || 'Non renseignée'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Identifiant d’immatriculation commerciale
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {organization.registration_identifier ||
                                    'Non renseigné'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Identifiant fiscal
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {organization.tax_identifier || 'Non renseigné'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Statut
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {organization.status === 'active'
                                    ? 'Active'
                                    : organization.status === 'suspended'
                                      ? 'Suspendue'
                                      : 'Clôturée'}
                            </dd>
                        </div>
                    </dl>
                </PartnerCard>

                <PartnerCard title="Conditions commerciales">
                    <dl className="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-3">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Commission
                            </dt>
                            <dd className="mt-1 text-sm font-semibold">
                                {(
                                    organization.commission_basis_points / 100
                                ).toFixed(2)}{' '}
                                %
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Fréquence des reversements
                            </dt>
                            <dd className="mt-1 text-sm font-semibold">
                                {frequency}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Heure de reversement
                            </dt>
                            <dd className="mt-1 text-sm font-semibold">
                                {calendar?.time || '—'}
                            </dd>
                        </div>
                    </dl>
                    <p className="border-t border-border px-5 py-3 text-xs leading-5 text-muted-foreground">
                        Les Points rattachés héritent de la commission, du pays
                        et de la fréquence de reversement. Le superviseur
                        répartit le plafond global entre les Points.
                    </p>
                </PartnerCard>

                <Link
                    href="/kora-partner/documents"
                    className="flex items-center gap-3 rounded-xl border border-border bg-card p-4 text-sm font-semibold hover:bg-muted/40"
                >
                    <FileText className="size-5 text-primary" /> Gérer les
                    documents de l’organisation
                </Link>
            </PartnerPage>
        </>
    );
}
