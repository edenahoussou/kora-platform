import { App } from '@inertiajs/react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import type { ComponentProps } from 'react';
import { ItemPanel } from '@/components/programming/item-panel';
import { PlanningCalendar } from '@/components/programming/planning-calendar';
import {
    ProgrammingButton as Button,
    freshSlot,
    PrimaryButton,
    ResourceFields,
    Surface,
} from '@/components/programming/shared';
import { StopTimetable } from '@/components/programming/stop-timetable';
import type {
    PlanningItem,
    References,
    Slot,
} from '@/components/programming/types';
import ProgrammeEditor from '@/pages/programming/programme-editor';
import TemplateEditor from '@/pages/programming/template-editor';

const refs: References = {
    lines: [
        {
            uuid: 'cotonou-lome',
            label: 'Cotonou → Lomé',
            code: 'COT-LOM',
            duration: 180,
            bidirectional: true,
            stops: [
                { uuid: 'cotonou', label: 'Cotonou', country: 'BJ' },
                { uuid: 'lome', label: 'Lomé', country: 'TG' },
            ],
        },
        {
            uuid: 'lome-cotonou',
            label: 'Lomé → Cotonou',
            code: 'LOM-COT',
            duration: 180,
            bidirectional: false,
            stops: [
                { uuid: 'lome', label: 'Lomé', country: 'TG' },
                { uuid: 'cotonou', label: 'Cotonou', country: 'BJ' },
            ],
        },
    ],
    agencies: [
        { uuid: 'ganhi', label: 'Agence Ganhi' },
        { uuid: 'lome', label: 'Agence Lomé' },
    ],
    buses: [
        {
            uuid: 'bus-1',
            label: 'BJ 9254 RB',
            capacity: 54,
            model: 'Yutong',
            status: 'active',
        },
        {
            uuid: 'bus-2',
            label: 'BJ 6821 RC',
            capacity: 42,
            model: 'Yutong',
            status: 'active',
        },
    ],
    people: [
        { uuid: 'driver-1', label: 'Jean Dossou', roles: ['bus_driver'] },
        { uuid: 'driver-2', label: 'Paul Assogba', roles: ['bus_driver'] },
        { uuid: 'conductor', label: 'Marc Adjagba', roles: ['conductor'] },
        { uuid: 'mechanic', label: 'Luc Sossou', roles: ['mechanic'] },
    ],
    timezone: 'Africa/Porto-Novo',
    timezones: ['Africa/Porto-Novo', 'Africa/Lome', 'Africa/Abidjan'],
};
const base: Slot = {
    ...freshSlot(refs.timezone),
    key: 'morning',
    line_uuid: 'cotonou-lome',
    agency_uuid: 'ganhi',
    time: '07:00',
    duration_minutes: 180,
    stops: [
        {
            stop_uuid: 'cotonou',
            arrival_minutes: null,
            departure_minutes: 0,
            timezone: 'Africa/Porto-Novo',
        },
        {
            stop_uuid: 'lome',
            arrival_minutes: 180,
            departure_minutes: null,
            timezone: 'Africa/Lome',
        },
    ],
};
const items: PlanningItem[] = Array.from({ length: 28 }, (_, i) => {
    const date = `2026-09-${String(7 + (i % 7)).padStart(2, '0')}`;
    const direction = i < 14 ? 'outbound' : 'return';
    const time = i % 2 === 0 ? '07:00' : '14:00';
    const state = (['published', 'ready', 'unassigned', 'blocked'] as const)[
        Math.floor(i / 3) % 4
    ];

    return {
        uuid: `item-${i}`,
        revision: 1,
        date,
        scheduledAt: `${date}T${time}:00+01:00`,
        arrivalAt: `${date}T18:00:00Z`,
        snapshot: {
            ...base,
            time,
            direction,
            line_uuid:
                direction === 'outbound' ? 'cotonou-lome' : 'lome-cotonou',
            bus_uuid: state === 'unassigned' ? null : 'bus-1',
        },
        programmeUuid: 'programme',
        programmeName: 'Septembre · Ligne côtière',
        isException: i === 6,
        protected: state === 'published',
        manual: false,
        departureUuid: null,
        departureStatus: null,
        state,
        blockers:
            state === 'blocked'
                ? [
                    'Le bus BJ 9254 RB est déjà engagé sur un autre départ de 06:30 à 10:00.',
                ]
                : [],
        warnings: [
            'Équipage incomplet : affectation possible avant l’exploitation.',
        ],
    };
});
function Workspace({
    view = 'week',
    empty = false,
}: {
    view?: string;
    empty?: boolean;
}) {
    const [selection, setSelection] = useState<string[]>([]);
    const [item, setItem] = useState<PlanningItem | null>(null);

    return (
        <main className="min-h-screen bg-mist p-5 text-foreground md:p-9">
            <header className="mb-7 flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p className="text-[0.6875rem] font-semibold text-muted-foreground">
                        KORA OPS / Exploitation / Programmation
                    </p>
                    <h1 className="mt-2 text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                        Programmation
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                        Préparez vos départs à partir de modèles, affectez les
                        ressources et publiez votre planning.
                    </p>
                </div>
                <PrimaryButton>Préparer une période</PrimaryButton>
            </header>
            <nav
                aria-label="Programmation"
                className="mb-6 flex gap-1 border-b border-border"
            >
                <button
                    className="border-b-2 border-gold px-4 py-3 text-xs font-extrabold"
                    aria-current="page"
                >
                    Planning
                </button>
                <button className="px-4 py-3 text-xs font-extrabold text-muted-foreground">
                    Modèles
                </button>
                <button className="px-4 py-3 text-xs font-extrabold text-muted-foreground">
                    Programmes
                </button>
            </nav>
            <div className="mb-5 flex flex-wrap gap-3">
                <Button variant="outline">7–13 septembre 2026</Button>
                <Button variant="outline">Toutes les liaisons</Button>
                <Button variant="outline">Tous les états</Button>
            </div>
            <Surface>
                <PlanningCalendar
                    items={empty ? [] : items}
                    refs={refs}
                    from="2026-09-07"
                    until={view === 'month' ? '2026-10-04' : '2026-09-13'}
                    view={view}
                    selected={selection}
                    onSelect={(id) =>
                        setSelection((s) =>
                            s.includes(id)
                                ? s.filter((v) => v !== id)
                                : [...s, id],
                        )
                    }
                    onOpen={setItem}
                    onDay={() => undefined}
                />
            </Surface>
            {selection.length > 0 && (
                <div
                    role="status"
                    className="fixed bottom-5 left-1/2 flex -translate-x-1/2 gap-4 rounded-2xl bg-primary px-6 py-4 text-primary-foreground shadow-lg"
                >
                    {selection.length} départs sélectionnés
                    <Button
                        variant="secondary"
                        onClick={() => setSelection([])}
                    >
                        Désélectionner
                    </Button>
                </div>
            )}
            {item && (
                <ItemPanel
                    item={item}
                    refs={refs}
                    canCancel
                    onClose={() => setItem(null)}
                />
            )}
        </main>
    );
}
function Timetable() {
    const [slot, setSlot] = useState(base);

    return (
        <main className="mx-auto min-h-screen max-w-4xl bg-background p-6 text-foreground">
            <h1 className="mb-6 text-2xl font-semibold">
                Horaires et ressources
            </h1>
            <Surface>
                <StopTimetable
                    slot={slot}
                    references={refs}
                    onChange={(s) => setSlot({ ...slot, ...s })}
                />
                <div className="mt-6">
                    <ResourceFields
                        slot={slot}
                        refs={refs}
                        onChange={(s) => setSlot({ ...slot, ...s })}
                    />
                </div>
            </Surface>
        </main>
    );
}
const meta = {
    title: 'KORA OPS/Programmation',
    component: Workspace,
    parameters: { layout: 'fullscreen' },
} satisfies Meta<typeof Workspace>;
export default meta;
type Story = StoryObj<typeof meta>;
export const Semaine: Story = {};
export const Mois: Story = { args: { view: 'month' } };
export const Liste: Story = { args: { view: 'list' } };
export const Vide: Story = { args: { empty: true } };
export const Horaires: Story = { render: () => <Timetable /> };
export const Mobile: Story = {
    render: () => (
        <div className="flex min-h-screen justify-center bg-muted p-4">
            <iframe
                title="Planning sur écran mobile"
                src="/iframe.html?id=kora-ops-programmation--liste&viewMode=story"
                width="390"
                height="844"
                className="max-w-full rounded-2xl border border-border bg-background"
            />
        </div>
    ),
};
export const Nuit: Story = { globals: { theme: 'dark' } };

// Render the application editors themselves so preview styles cannot drift.
function EditorPreview({ period = false }: { period?: boolean }) {
    const props = period
        ? {
            references: refs,
            templates: [
                {
                    uuid: 'coastal-template',
                    name: 'Ligne côtière · Semaine type',
                    status: 'available',
                    revision: 1,
                    draft: null,
                    programmeCount: 1,
                    versions: [
                        {
                            uuid: 'version-1',
                            number: 1,
                            slots: [base],
                            createdAt: '2026-08-31',
                        },
                    ],
                },
            ],
        }
        : {
            references: refs,
            template: null,
            initial: { name: 'Ligne côtière · Semaine type', slots: [base] },
        };
    const page: ComponentProps<typeof App>['initialPage'] = {
        component: period
            ? 'programming/programme-editor'
            : 'programming/template-editor',
        url: window.location.pathname + window.location.search,
        version: null,
        props: { ...props, errors: {} },
        flash: {},
        rememberedState: {},
        rescuedProps: [],
    };
    const Component = period ? ProgrammeEditor : TemplateEditor;

    return (
        <main className="min-h-screen bg-mist p-5 text-foreground md:p-8">
            <App
                initialPage={page}
                initialComponent={Component}
                resolveComponent={() => Component}
            />
        </main>
    );
}
export const ModeleApplication: Story = { render: () => <EditorPreview /> };
export const PeriodeApplication: Story = {
    render: () => <EditorPreview period />,
};
export const ModeleMobile: Story = {
    render: () => (
        <div className="flex min-h-screen justify-center bg-muted p-4">
            <iframe
                title="Éditeur de modèle sur écran mobile"
                src="/iframe.html?id=kora-ops-programmation--modele-application&viewMode=story"
                width="390"
                height="844"
                className="max-w-full rounded-2xl border border-border bg-background"
            />
        </div>
    ),
};
export const PeriodeNuit: Story = {
    render: () => <EditorPreview period />,
    globals: { theme: 'dark' },
};
