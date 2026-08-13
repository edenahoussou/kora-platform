import type { Meta, StoryObj } from '@storybook/react-vite';
import { AlertTriangle, CheckCircle2, Inbox, LoaderCircle } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

const LoadingState = () => (
    <Card>
        <CardHeader>
            <CardTitle>Chargement depart</CardTitle>
            <CardDescription>
                Skeletons pour listes et details operationnels.
            </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4">
            {[0, 1, 2].map((item) => (
                <div key={item} className="grid gap-3 rounded-lg border p-4">
                    <Skeleton className="h-4 w-28" />
                    <Skeleton className="h-3 w-9/12" />
                    <Skeleton className="h-9 w-full" />
                </div>
            ))}
        </CardContent>
    </Card>
);

const EmptyState = () => (
    <Card>
        <CardContent className="grid min-h-[340px] place-items-center p-8 text-center">
            <div className="grid max-w-sm justify-items-center gap-4">
                <div className="grid size-14 place-items-center rounded-xl bg-muted text-muted-foreground">
                    <Inbox className="size-7" />
                </div>
                <div>
                    <h3 className="font-semibold">Aucun depart trouve</h3>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        Ajuste la date ou le segment pour afficher les trajets
                        disponibles.
                    </p>
                </div>
                <Button variant="outline">Modifier la recherche</Button>
            </div>
        </CardContent>
    </Card>
);

const ErrorState = () => (
    <Card>
        <CardHeader>
            <CardTitle>Erreur recuperable</CardTitle>
            <CardDescription>
                Message court, action claire, pas de stack technique.
            </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4">
            <Alert variant="destructive">
                <AlertTriangle className="size-4" />
                <AlertTitle>Disponibilite indisponible</AlertTitle>
                <AlertDescription>
                    La verification siege n a pas abouti. Relance la recherche
                    dans quelques secondes.
                </AlertDescription>
            </Alert>
            <div className="flex flex-wrap gap-3">
                <Button>Reessayer</Button>
                <Button variant="outline">Retour aux departs</Button>
            </div>
        </CardContent>
    </Card>
);

const SuccessState = () => (
    <Card>
        <CardHeader>
            <CardTitle>Confirmation action</CardTitle>
            <CardDescription>
                Feedback apres sauvegarde ou action metier.
            </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4">
            <Alert>
                <CheckCircle2 className="size-4" />
                <AlertTitle>Manifeste mis a jour</AlertTitle>
                <AlertDescription>
                    Les modifications sont visibles pour le controle
                    embarquement.
                </AlertDescription>
            </Alert>
            <div className="flex items-center justify-between rounded-lg border bg-muted p-4">
                <div>
                    <strong className="block text-sm">KE 118</strong>
                    <span className="text-xs text-muted-foreground">
                        38 passagers confirmes
                    </span>
                </div>
                <Badge>Synchronise</Badge>
            </div>
        </CardContent>
    </Card>
);

const FeedbackStates = () => (
    <main className="min-h-screen bg-muted p-5 text-foreground">
        <div className="mx-auto grid w-[min(1180px,calc(100vw-2rem))] gap-5 lg:grid-cols-2">
            <LoadingState />
            <EmptyState />
            <ErrorState />
            <SuccessState />
            <Card className="lg:col-span-2">
                <CardContent className="flex flex-wrap items-center justify-between gap-4 p-6">
                    <div className="flex items-center gap-3">
                        <LoaderCircle className="size-5 animate-spin text-digital" />
                        <div>
                            <strong className="block text-sm">
                                Synchronisation offline
                            </strong>
                            <span className="text-xs text-muted-foreground">
                                12 operations en file locale
                            </span>
                        </div>
                    </div>
                    <Button variant="secondary">Voir la file</Button>
                </CardContent>
            </Card>
        </div>
    </main>
);

const meta = {
    title: 'KORA OPS/Feedback States',
    component: FeedbackStates,
    parameters: {
        layout: 'fullscreen',
    },
} satisfies Meta<typeof FeedbackStates>;

export default meta;

type Story = StoryObj<typeof meta>;

export const EmptyLoadingErrorSuccess: Story = {};
