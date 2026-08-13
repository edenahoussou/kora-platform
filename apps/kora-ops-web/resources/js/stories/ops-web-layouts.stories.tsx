import type { Meta, StoryObj } from '@storybook/react-vite';
import {
    BarChart3,
    Bus,
    CalendarClock,
    CircleDollarSign,
    ClipboardCheck,
    Menu,
    Search,
    Settings,
    TicketCheck,
    Users,
} from 'lucide-react';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';

const navItems = [
    { label: 'Tableau de bord', icon: BarChart3, active: true },
    { label: 'Departs', icon: CalendarClock, active: false },
    { label: 'Ventes', icon: TicketCheck, active: false },
    { label: 'Embarquement', icon: ClipboardCheck, active: false },
    { label: 'Caisse', icon: CircleDollarSign, active: false },
    { label: 'Parametres', icon: Settings, active: false },
];

const rows = [
    ['KE 118', 'Cotonou -> Parakou', '10:30', '7/42', 'Ouvert'],
    ['KE 124', 'Cotonou -> Kandi', '13:15', '18/42', 'Preparation'],
    ['KE 132', 'Parakou -> Cotonou', '18:00', '3/42', 'Controle'],
    ['KE 140', 'Cotonou -> Malanville', '21:30', '31/56', 'Ouvert'],
];

const Sidebar = () => (
    <aside className="hidden min-h-screen border-r bg-sidebar text-sidebar-foreground lg:grid lg:w-64 lg:grid-rows-[auto_1fr_auto]">
        <div className="flex items-center gap-3 p-5">
            <div className="grid size-10 place-items-center rounded-lg bg-primary text-primary-foreground">
                <Bus className="size-5" />
            </div>
            <div>
                <strong className="block text-sm">KORA OPS</strong>
                <span className="text-xs text-sidebar-foreground/55">
                    Console Bus MVP
                </span>
            </div>
        </div>
        <nav className="grid content-start gap-1 px-3">
            {navItems.map(({ active, icon: Icon, label }) => (
                <button
                    key={label}
                    type="button"
                    className={`flex items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition ${
                        active
                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                            : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                    }`}
                >
                    <Icon className="size-4" />
                    {label}
                </button>
            ))}
        </nav>
        <div className="p-4">
            <div className="rounded-lg border border-sidebar-border bg-sidebar-accent p-3 text-sm text-sidebar-foreground">
                <strong>Guichet Ganhi</strong>
                <p className="mt-1 text-xs text-sidebar-foreground/65">
                    2 caisses ouvertes
                </p>
            </div>
        </div>
    </aside>
);

const OpsWebLayouts = () => (
    <main className="min-h-screen bg-muted text-foreground">
        <div className="grid lg:grid-cols-[auto_1fr]">
            <Sidebar />
            <section className="min-w-0">
                <header className="sticky top-0 z-10 border-b bg-background/95 px-4 py-3 backdrop-blur">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <Button
                                variant="outline"
                                size="icon"
                                className="lg:hidden"
                                aria-label="Menu"
                            >
                                <Menu />
                            </Button>
                            <Breadcrumb>
                                <BreadcrumbList>
                                    <BreadcrumbItem>
                                        <BreadcrumbLink href="#">
                                            KORA OPS
                                        </BreadcrumbLink>
                                    </BreadcrumbItem>
                                    <BreadcrumbSeparator />
                                    <BreadcrumbItem>
                                        <BreadcrumbPage>Departs</BreadcrumbPage>
                                    </BreadcrumbItem>
                                </BreadcrumbList>
                            </Breadcrumb>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="hidden w-72 items-center gap-2 rounded-md border bg-muted px-3 md:flex">
                                <Search className="size-4 text-muted-foreground" />
                                <Input
                                    className="border-0 bg-transparent shadow-none focus-visible:ring-0"
                                    placeholder="Rechercher un depart"
                                />
                            </div>
                            <Avatar>
                                <AvatarFallback>EA</AvatarFallback>
                            </Avatar>
                        </div>
                    </div>
                </header>

                <div className="grid gap-5 p-5">
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {[
                            ['Departs jour', '46', '+4 vs hier'],
                            ['Sieges vendus', '1 842', '+12%'],
                            ['Ponctualite', '91,3%', 'reseau stable'],
                            ['Caisses', '8', '2 a cloturer'],
                        ].map(([label, value, hint]) => (
                            <Card key={label}>
                                <CardHeader className="pb-2">
                                    <CardDescription>{label}</CardDescription>
                                    <CardTitle className="text-3xl font-black text-primary">
                                        {value}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm text-muted-foreground">
                                    {hint}
                                </CardContent>
                            </Card>
                        ))}
                    </section>

                    <section className="grid gap-5 xl:grid-cols-[1fr_340px]">
                        <Card>
                            <CardHeader className="flex-row items-start justify-between gap-4">
                                <div>
                                    <CardTitle>Departs du jour</CardTitle>
                                    <CardDescription>
                                        Vue dense pour exploitation recurrente.
                                    </CardDescription>
                                </div>
                                <Button>Creer un depart</Button>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-hidden rounded-lg border">
                                    <div className="grid grid-cols-[90px_1.3fr_80px_90px_110px] bg-muted px-4 py-3 text-xs font-semibold text-muted-foreground">
                                        <span>Code</span>
                                        <span>Ligne</span>
                                        <span>Heure</span>
                                        <span>Sieges</span>
                                        <span>Statut</span>
                                    </div>
                                    {rows.map((row) => (
                                        <div
                                            key={row[0]}
                                            className="grid grid-cols-[90px_1.3fr_80px_90px_110px] items-center border-t bg-card px-4 py-3 text-sm"
                                        >
                                            <strong>{row[0]}</strong>
                                            <span>{row[1]}</span>
                                            <span>{row[2]}</span>
                                            <span>{row[3]}</span>
                                            <Badge
                                                variant={
                                                    row[4] === 'Ouvert'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {row[4]}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Embarquement KE 118</CardTitle>
                                <CardDescription>
                                    Controle passagers avant depart.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {[
                                    ['Awa Mensah', 'Siege 04', 'Boarded'],
                                    ['Joel K.', 'Siege 08', 'A verifier'],
                                    ['Nadia S.', 'Siege 11', 'Boarded'],
                                    ['Ibrahim T.', 'Siege 18', 'Absent'],
                                ].map(([name, seat, status]) => (
                                    <div
                                        key={name}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <Avatar>
                                                <AvatarFallback>
                                                    {name
                                                        .split(' ')
                                                        .map((part) => part[0])
                                                        .join('')
                                                        .slice(0, 2)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div>
                                                <strong className="block text-sm">
                                                    {name}
                                                </strong>
                                                <span className="text-xs text-muted-foreground">
                                                    {seat}
                                                </span>
                                            </div>
                                        </div>
                                        <Badge
                                            variant={
                                                status === 'Absent'
                                                    ? 'destructive'
                                                    : status === 'Boarded'
                                                      ? 'default'
                                                      : 'outline'
                                            }
                                        >
                                            {status}
                                        </Badge>
                                    </div>
                                ))}
                                <Separator />
                                <Button className="w-full">
                                    <Users />
                                    Ouvrir manifeste
                                </Button>
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </section>
        </div>
    </main>
);

const meta = {
    title: 'KORA OPS/Web Layouts',
    component: OpsWebLayouts,
    parameters: {
        layout: 'fullscreen',
    },
} satisfies Meta<typeof OpsWebLayouts>;

export default meta;

type Story = StoryObj<typeof meta>;

export const DashboardAndDepartures: Story = {};
