import { Coins, Package, PackageCheck, PackageOpen } from 'lucide-react';
import { ParcelKpi } from '@/components/parcels/parcel-kpi';

interface ParcelRegistryKpisProps {
    todayCount: number;
    outboundTodayCount: number;
    inboundTodayCount: number;
    toProcessCount: number;
    availableForPickupCount: number;
    todayRevenueFcfa: number;
}

function formatMoney(amount: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
}

export function ParcelRegistryKpis({
    todayCount,
    outboundTodayCount,
    inboundTodayCount,
    toProcessCount,
    availableForPickupCount,
    todayRevenueFcfa,
}: ParcelRegistryKpisProps) {
    return (
        <section className="cash-ledger-grid">
            <ParcelKpi
                icon={Package}
                tone="navy"
                label="Colis enregistrés"
                hint={`${outboundTodayCount} expéditions · ${inboundTodayCount} arrivées`}
                value={todayCount}
                tooltip={
                    <span>
                        Nombre total de colis actifs ou traités dans le
                        périmètre de votre agence (départs et arrivées).
                    </span>
                }
            />
            <ParcelKpi
                icon={PackageCheck}
                tone="gold"
                label="À traiter au guichet"
                hint="Pré-enregistrements & arrivées quai"
                value={toProcessCount}
                tooltip={
                    <span>
                        Colis pré-enregistrés en attente de pesée/encaissement
                        et colis arrivés à quai à réceptionner en magasin.
                    </span>
                }
            />
            <ParcelKpi
                icon={PackageOpen}
                tone="green"
                label="Disponibles au retrait"
                hint="Prêts pour remise contre PIN"
                value={availableForPickupCount}
                tooltip={
                    <span>
                        Colis réceptionnés à votre agence, notifiés au
                        destinataire et en attente de retrait physique.
                    </span>
                }
            />
            <ParcelKpi
                icon={Coins}
                tone="blue"
                label="Encaissements colis (jour)"
                hint="Espèces, Mobile Money & Comptes"
                value={formatMoney(todayRevenueFcfa)}
                tooltip={
                    <span>
                        Cumul des paiements confirmés aujourd’hui au comptoir
                        pour les envois de colis.
                    </span>
                }
            />
        </section>
    );
}
