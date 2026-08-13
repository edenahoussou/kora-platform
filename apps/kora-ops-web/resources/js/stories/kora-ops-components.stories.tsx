import type { Meta, StoryObj } from '@storybook/react-vite';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const KpiCard = ({
    label,
    value,
    trend,
}: {
    label: string;
    value: string;
    trend: string;
}) => (
    <Card className="overflow-hidden">
        <CardHeader className="pb-3">
            <CardDescription>{label}</CardDescription>
            <CardTitle className="text-3xl font-black tracking-normal text-primary">
                {value}
            </CardTitle>
        </CardHeader>
        <CardContent className="flex items-center justify-between text-sm">
            <span className="text-muted-foreground">{trend}</span>
            <span className="rounded-md bg-accent px-2 py-1 font-mono text-xs font-semibold text-accent-foreground">
                LIVE
            </span>
        </CardContent>
    </Card>
);

const DepartureCard = () => (
    <Card className="border-accent/60">
        <CardContent className="grid gap-5 p-5 md:grid-cols-[90px_1fr_150px] md:items-center">
            <div>
                <span className="text-xs font-semibold text-muted-foreground">
                    Départ
                </span>
                <time className="block text-3xl font-black text-primary">
                    10:30
                </time>
                <span className="text-xs text-muted-foreground">3 h 45</span>
            </div>
            <div className="grid gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <strong>KE 118</strong>
                    <Badge>KORA Premium</Badge>
                </div>
                <div className="relative h-2 rounded-full bg-border">
                    <span className="absolute top-1/2 left-0 size-3 -translate-y-1/2 rounded-full border-2 border-digital bg-background" />
                    <span className="absolute top-1/2 right-0 size-3 -translate-y-1/2 rounded-full border-2 border-digital bg-background" />
                    <span className="absolute top-1/2 left-[58%] grid size-7 -translate-y-1/2 place-items-center rounded-md bg-accent font-mono text-[10px] font-black text-accent-foreground shadow-[var(--shadow-kora)]">
                        K
                    </span>
                </div>
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>Cotonou</span>
                    <span>Parakou</span>
                </div>
            </div>
            <div className="grid gap-2 md:justify-items-end">
                <span className="text-sm text-muted-foreground">
                    <strong className="text-digital">7 places</strong>{' '}
                    disponibles
                </span>
                <strong className="text-xl text-primary">10 500 FCFA</strong>
                <Button size="sm">Choisir</Button>
            </div>
        </CardContent>
    </Card>
);

const OperationsBoard = () => (
    <div className="w-[min(1180px,calc(100vw-2rem))] space-y-5 rounded-[var(--radius)] bg-muted p-5 text-foreground">
        <section className="grid grid-flow-dense grid-cols-1 gap-4 md:grid-cols-4">
            <KpiCard
                label="Départs aujourd'hui"
                value="46"
                trend="+4 vs hier"
            />
            <KpiCard
                label="Ponctualité réseau"
                value="91,3%"
                trend="L2 stable"
            />
            <KpiCard label="Billets émis" value="1 842" trend="+12%" />
            <KpiCard label="Caisses ouvertes" value="8" trend="2 à clôturer" />
        </section>

        <section className="grid grid-cols-1 gap-5 lg:grid-cols-[1.45fr_.7fr]">
            <div className="grid gap-3">
                <DepartureCard />
                <DepartureCard />
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Résumé sélection</CardTitle>
                    <CardDescription>Cotonou vers Parakou</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 text-sm">
                    {[
                        ['Référence', 'KE 118'],
                        ['Service', 'KORA Premium'],
                        ['Siège', 'À choisir'],
                        ['Tarif', '10 500 FCFA'],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="flex items-center justify-between border-b pb-3 last:border-b-0"
                        >
                            <span className="text-muted-foreground">
                                {label}
                            </span>
                            <strong>{value}</strong>
                        </div>
                    ))}
                    <Button className="mt-2 w-full">Continuer</Button>
                </CardContent>
            </Card>
        </section>
    </div>
);

const meta = {
    title: 'KORA OPS/Components',
    component: OperationsBoard,
    parameters: {
        layout: 'fullscreen',
    },
} satisfies Meta<typeof OperationsBoard>;

export default meta;

type Story = StoryObj<typeof meta>;

export const BusOperations: Story = {};
