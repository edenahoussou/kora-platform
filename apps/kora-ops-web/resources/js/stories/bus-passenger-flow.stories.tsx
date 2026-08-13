import type { Meta, StoryObj } from '@storybook/react-vite';
import { Armchair, CalendarDays, MapPin, Search } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';

const departures = [
    {
        code: 'KE 118',
        time: '10:30',
        service: 'Premium',
        duration: '3 h 45',
        seats: '7',
        fare: '10 500 FCFA',
    },
    {
        code: 'KE 124',
        time: '13:15',
        service: 'Standard',
        duration: '4 h 10',
        seats: '18',
        fare: '8 500 FCFA',
    },
    {
        code: 'KE 132',
        time: '18:00',
        service: 'Premium',
        duration: '3 h 55',
        seats: '3',
        fare: '12 000 FCFA',
    },
];

const seats = Array.from({ length: 32 }, (_, index) => {
    const seatNumber = index + 1;
    const blocked = [3, 4, 17, 28].includes(seatNumber);
    const occupied = [2, 7, 8, 11, 15, 21, 22, 31].includes(seatNumber);

    return {
        code: seatNumber.toString().padStart(2, '0'),
        status: blocked ? 'blocked' : occupied ? 'occupied' : 'available',
    };
});

const Seat = ({ code, status }: { code: string; status: string }) => {
    const className =
        status === 'available'
            ? 'border-digital bg-background text-digital'
            : status === 'occupied'
              ? 'border-primary bg-primary text-primary-foreground'
              : 'border-border bg-muted text-muted-foreground';

    return (
        <button
            type="button"
            className={`grid aspect-square place-items-center rounded-md border text-xs font-bold transition ${className}`}
            aria-label={`Siege ${code} ${status}`}
        >
            {code}
        </button>
    );
};

const DepartureResult = ({
    code,
    time,
    service,
    duration,
    seats,
    fare,
}: (typeof departures)[number]) => (
    <Card className="py-0">
        <CardContent className="grid gap-4 p-4 md:grid-cols-[76px_1fr_auto] md:items-center">
            <div>
                <time className="block text-2xl font-black text-primary">
                    {time}
                </time>
                <span className="text-xs text-muted-foreground">
                    {duration}
                </span>
            </div>
            <div className="grid gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <strong>{code}</strong>
                    <Badge
                        variant={
                            service === 'Premium' ? 'default' : 'secondary'
                        }
                    >
                        {service}
                    </Badge>
                </div>
                <div className="grid grid-cols-[auto_1fr_auto] items-center gap-3 text-xs text-muted-foreground">
                    <span>Cotonou</span>
                    <span className="h-1 rounded-full bg-border">
                        <span className="block h-1 w-3/5 rounded-full bg-accent" />
                    </span>
                    <span>Parakou</span>
                </div>
            </div>
            <div className="grid gap-1 md:justify-items-end">
                <span className="text-sm text-muted-foreground">
                    {seats} places
                </span>
                <strong className="text-lg text-primary">{fare}</strong>
                <Button size="sm">Choisir</Button>
            </div>
        </CardContent>
    </Card>
);

const BusPassengerFlow = () => (
    <main className="min-h-screen bg-muted p-5 text-foreground">
        <div className="mx-auto grid w-[min(1180px,calc(100vw-2rem))] gap-5 lg:grid-cols-[0.85fr_1.15fr]">
            <section className="grid content-start gap-5">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl font-black">
                            Rechercher un Bus
                        </CardTitle>
                        <CardDescription>
                            Flux passager: origine, destination, date puis
                            disponibilite par segment.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="grid gap-2">
                            <Label>Origine</Label>
                            <Select defaultValue="cotonou">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cotonou">
                                        Cotonou
                                    </SelectItem>
                                    <SelectItem value="bohicon">
                                        Bohicon
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Destination</Label>
                            <Select defaultValue="parakou">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="parakou">
                                        Parakou
                                    </SelectItem>
                                    <SelectItem value="kandi">Kandi</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="travel-date">Date</Label>
                            <Input
                                id="travel-date"
                                type="date"
                                defaultValue="2026-08-18"
                            />
                        </div>
                        <Button>
                            <Search />
                            Rechercher
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Resume trajet</CardTitle>
                        <CardDescription>
                            Donnees publiques: codes et libelles uniquement.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm">
                        {[
                            ['Ligne', 'COT-PKO'],
                            ['Segment', 'Cotonou vers Parakou'],
                            ['Date', '18 aout 2026'],
                            ['Passagers', '1 adulte'],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="flex items-center justify-between gap-3"
                            >
                                <span className="text-muted-foreground">
                                    {label}
                                </span>
                                <strong>{value}</strong>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </section>

            <section className="grid gap-5">
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle>Departures disponibles</CardTitle>
                            <CardDescription>
                                Preview de tarif, pas encore devis fige.
                            </CardDescription>
                        </div>
                        <Badge variant="outline">3 resultats</Badge>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {departures.map((departure) => (
                            <DepartureResult
                                key={departure.code}
                                {...departure}
                            />
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Armchair className="size-5 text-digital" />
                            Plan sieges KE 118
                        </CardTitle>
                        <CardDescription>
                            Disponibilite calculee pour Cotonou vers Parakou.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 lg:grid-cols-[1fr_220px]">
                        <div className="grid grid-cols-4 gap-2 rounded-xl border bg-background p-4">
                            {seats.map((seat) => (
                                <Seat key={seat.code} {...seat} />
                            ))}
                        </div>
                        <div className="grid content-start gap-4 text-sm">
                            <div className="flex items-center gap-2">
                                <MapPin className="size-4 text-digital" />
                                <span>Cotonou vers Parakou</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <CalendarDays className="size-4 text-digital" />
                                <span>18 aout 2026, 10:30</span>
                            </div>
                            <Separator />
                            {[
                                [
                                    'Disponible',
                                    '20',
                                    'bg-background border-digital',
                                ],
                                ['Occupe', '8', 'bg-primary border-primary'],
                                ['Bloque', '4', 'bg-muted border-border'],
                            ].map(([label, value, swatch]) => (
                                <div
                                    key={label}
                                    className="flex items-center justify-between"
                                >
                                    <span className="flex items-center gap-2">
                                        <span
                                            className={`size-3 rounded-sm border ${swatch}`}
                                        />
                                        {label}
                                    </span>
                                    <strong>{value}</strong>
                                </div>
                            ))}
                            <Button>Continuer</Button>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </div>
    </main>
);

const meta = {
    title: 'KORA App/Bus Passenger Flow',
    component: BusPassengerFlow,
    parameters: {
        layout: 'fullscreen',
    },
} satisfies Meta<typeof BusPassengerFlow>;

export default meta;

type Story = StoryObj<typeof meta>;

export const SearchResultsAndSeats: Story = {};
