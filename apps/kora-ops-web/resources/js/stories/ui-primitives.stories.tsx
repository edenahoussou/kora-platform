import type { Meta, StoryObj } from '@storybook/react-vite';
import {
    Bell,
    CalendarDays,
    Check,
    ChevronsUpDown,
    MoreHorizontal,
    Route,
    Search,
} from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Toggle } from '@/components/ui/toggle';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const PrimitiveBoard = () => (
    <TooltipProvider>
        <div className="grid w-[min(1080px,calc(100vw-2rem))] gap-5 rounded-[var(--radius)] bg-background p-5 text-foreground">
            <section className="grid grid-flow-dense grid-cols-1 gap-5 lg:grid-cols-12">
                <Card className="lg:col-span-5">
                    <CardHeader>
                        <CardTitle>Commandes principales</CardTitle>
                        <CardDescription>
                            Variants shadcn/ui mappes sur les tokens KORA.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3">
                        <Button>Créer un départ</Button>
                        <Button variant="secondary">Préparer</Button>
                        <Button variant="outline">Exporter</Button>
                        <Button variant="ghost">Annuler</Button>
                        <Button variant="destructive">Bloquer</Button>
                        <Button size="icon" aria-label="Rechercher">
                            <Search />
                        </Button>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-4">
                    <CardHeader>
                        <CardTitle>Statuts métier</CardTitle>
                        <CardDescription>
                            Badge, avatar et menus restent composables.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-3">
                        <Badge>Confirmé</Badge>
                        <Badge variant="secondary">À préparer</Badge>
                        <Badge variant="outline">Ouvert</Badge>
                        <Badge variant="destructive">Refusé</Badge>
                        <Avatar>
                            <AvatarFallback>EA</AvatarFallback>
                        </Avatar>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="Actions"
                                >
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>
                                    Départ KE 118
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem>Ouvrir</DropdownMenuItem>
                                <DropdownMenuItem>
                                    Exporter manifeste
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-3">
                    <CardHeader>
                        <CardTitle>Contrôles</CardTitle>
                        <CardDescription>
                            Etats actifs, focus et disabled.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="flex items-center gap-2">
                            <Checkbox id="manifest-ready" defaultChecked />
                            <Label htmlFor="manifest-ready">
                                Manifeste prêt
                            </Label>
                        </div>
                        <ToggleGroup
                            type="single"
                            defaultValue="bus"
                            className="justify-start"
                        >
                            <ToggleGroupItem value="bus" aria-label="Bus">
                                <Route />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="date" aria-label="Date">
                                <CalendarDays />
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <Toggle aria-label="Alertes" pressed>
                            <Bell />
                        </Toggle>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-7">
                    <CardHeader>
                        <CardTitle>Formulaire segment</CardTitle>
                        <CardDescription>
                            Labels au-dessus, focus ring gold, dark mode
                            support.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="origin">Origine</Label>
                            <Input id="origin" defaultValue="Cotonou" />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="destination">Destination</Label>
                            <Select defaultValue="parakou">
                                <SelectTrigger
                                    id="destination"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="parakou">
                                        Parakou
                                    </SelectItem>
                                    <SelectItem value="kandi">Kandi</SelectItem>
                                    <SelectItem value="malanville">
                                        Malanville
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="date">Date</Label>
                            <Input
                                id="date"
                                type="date"
                                defaultValue="2026-08-15"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-5">
                    <CardHeader>
                        <CardTitle>Feedback</CardTitle>
                        <CardDescription>
                            Dialog, tooltip, alert et skeleton.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <Alert>
                            <Check className="size-4" />
                            <AlertTitle>Prix final garanti</AlertTitle>
                            <AlertDescription>
                                Le tarif sera figé au moment du devis serveur.
                            </AlertDescription>
                        </Alert>
                        <div className="flex flex-wrap gap-3">
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        Ouvrir modal
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Créer une ligne Bus
                                        </DialogTitle>
                                        <DialogDescription>
                                            Les arrêts seront ordonnés avant
                                            publication commerciale.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <Button variant="outline">
                                            Annuler
                                        </Button>
                                        <Button>Enregistrer</Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="secondary"
                                        size="icon"
                                        aria-label="Disponibilité"
                                    >
                                        <ChevronsUpDown />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    Disponibilité par segment O/D
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <div className="grid gap-2">
                            <Skeleton className="h-3 w-11/12" />
                            <Skeleton className="h-3 w-8/12" />
                            <Skeleton className="h-12 w-full rounded-md" />
                        </div>
                    </CardContent>
                </Card>
            </section>
        </div>
    </TooltipProvider>
);

const meta = {
    title: 'Design System/UI Primitives',
    component: PrimitiveBoard,
    parameters: {
        layout: 'fullscreen',
    },
} satisfies Meta<typeof PrimitiveBoard>;

export default meta;

type Story = StoryObj<typeof meta>;

export const ShadcnCompatible: Story = {};
