import type { Meta, StoryObj } from '@storybook/react-vite';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const Showcase = () => (
    <main className="w-full max-w-full overflow-x-hidden bg-background text-foreground">
        <nav className="mx-auto flex w-[min(1180px,calc(100vw-2rem))] items-center justify-between py-5">
            <strong className="text-lg font-black text-primary">
                KORA OPS
            </strong>
            <div className="hidden items-center gap-6 text-sm text-muted-foreground md:flex">
                <span>Départs</span>
                <span>Ventes</span>
                <span>Embarquement</span>
            </div>
            <Button size="sm">Ouvrir la console</Button>
        </nav>

        <section className="mx-auto grid min-h-[78dvh] w-[min(1180px,calc(100vw-2rem))] items-center gap-12 py-20 md:grid-cols-[1.12fr_.88fr]">
            <div>
                <h1 className="max-w-5xl text-[clamp(3rem,6vw,5.8rem)] leading-[0.95] font-black tracking-normal">
                    Piloter les départs Bus sans perdre le terrain de vue.
                </h1>
                <p className="mt-7 max-w-[62ch] text-base leading-7 text-muted-foreground">
                    Une interface dense et calme pour vendre, contrôler,
                    embarquer et suivre les opérations KORA BUS du guichet au
                    dernier arrêt.
                </p>
                <div className="mt-8 flex flex-wrap gap-3">
                    <Button size="lg">Voir les composants</Button>
                    <Button variant="outline" size="lg">
                        Comparer light et dark
                    </Button>
                </div>
            </div>
            <div className="grid gap-4 rounded-[calc(var(--radius)+0.75rem)] border bg-card p-4 shadow-[var(--shadow-kora)]">
                <div className="rounded-[var(--radius)] bg-primary p-6 text-primary-foreground">
                    <Badge className="bg-accent text-accent-foreground">
                        Réseau opérationnel
                    </Badge>
                    <div className="mt-10 grid grid-cols-3 gap-3">
                        {[
                            ['46', 'Départs'],
                            ['91,3', 'Ponctualité'],
                            ['8', 'Caisses'],
                        ].map(([value, label]) => (
                            <div
                                key={label}
                                className="rounded-lg bg-white/10 p-4"
                            >
                                <strong className="block font-mono text-3xl">
                                    {value}
                                </strong>
                                <span className="text-xs text-white/70">
                                    {label}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="grid grid-flow-dense grid-cols-2 gap-3">
                    <article className="rounded-lg border bg-muted p-4">
                        <strong>KE 118</strong>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Cotonou vers Parakou · 10:30
                        </p>
                    </article>
                    <article className="rounded-lg border bg-muted p-4">
                        <strong>7 places</strong>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Segment disponible
                        </p>
                    </article>
                </div>
            </div>
        </section>
    </main>
);

const meta = {
    title: 'KORA OPS/Showcase',
    component: Showcase,
    parameters: {
        layout: 'fullscreen',
    },
} satisfies Meta<typeof Showcase>;

export default meta;

type Story = StoryObj<typeof meta>;

export const CommandInterface: Story = {};
