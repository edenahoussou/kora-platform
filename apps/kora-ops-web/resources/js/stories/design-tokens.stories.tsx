import type { Meta, StoryObj } from '@storybook/react-vite';

const colors = [
    ['KORA Navy', '#001D4E', 'Navigation, titres, surfaces de commande'],
    ['KORA Gold', '#F6B000', 'Accent unique, CTA, focus rings, selection'],
    ['Digital Blue', '#0057B8', 'Etats informatifs et visualisations'],
    ['Mist', '#F4F6F8', 'Arriere-plan et surfaces secondaires'],
    ['Graphite', '#111827', 'Texte principal light mode'],
];

const TokenGallery = () => (
    <div className="w-[min(960px,calc(100vw-2rem))] rounded-[var(--radius)] border bg-background p-6 text-foreground shadow-[var(--shadow-kora)]">
        <div className="grid gap-2 border-b pb-5">
            <p className="text-sm font-semibold text-digital">KORA OPS Web</p>
            <h1 className="max-w-3xl text-3xl font-black tracking-normal text-foreground md:text-5xl">
                Tokens shadcn/ui compatibles light et dark mode
            </h1>
            <p className="max-w-[65ch] text-sm leading-6 text-muted-foreground">
                Les composants consomment les variables semantiques Tailwind et
                gardent les codes KORA comme couche de marque.
            </p>
        </div>
        <div className="grid grid-flow-dense grid-cols-1 gap-4 pt-6 md:grid-cols-5">
            {colors.map(([name, value, role]) => (
                <article
                    key={name}
                    className="grid gap-3 rounded-lg border bg-card p-4 text-card-foreground"
                >
                    <span
                        className="h-20 rounded-md border"
                        style={{ backgroundColor: value }}
                    />
                    <div>
                        <strong className="block text-sm">{name}</strong>
                        <code className="font-mono text-xs text-muted-foreground">
                            {value}
                        </code>
                        <p className="mt-2 text-xs leading-5 text-muted-foreground">
                            {role}
                        </p>
                    </div>
                </article>
            ))}
        </div>
    </div>
);

const meta = {
    title: 'Design System/Tokens',
    component: TokenGallery,
    parameters: {
        layout: 'fullscreen',
    },
} satisfies Meta<typeof TokenGallery>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Palette: Story = {};
