import type { Meta, StoryObj } from '@storybook/react-vite';
import { CheckCircle2, Fingerprint, Mail, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

const PhoneFrame = ({ children }: { children: ReactNode }) => (
    <div className="mx-auto w-[min(390px,calc(100vw-2rem))] rounded-[2rem] border bg-background p-3 shadow-[var(--shadow-kora)]">
        <div className="overflow-hidden rounded-[1.45rem] border bg-card text-card-foreground">
            {children}
        </div>
    </div>
);

const StepBadge = ({ children }: { children: ReactNode }) => (
    <Badge variant="secondary" className="bg-accent text-accent-foreground">
        {children}
    </Badge>
);

const AuthMobileFlow = () => (
    <main className="grid min-h-screen gap-6 bg-muted p-5 text-foreground lg:grid-cols-3">
        <PhoneFrame>
            <section className="grid min-h-[720px] content-between gap-8 p-6">
                <div className="space-y-8">
                    <div className="flex items-center justify-between">
                        <strong className="text-lg font-black text-primary">
                            KORA
                        </strong>
                        <StepBadge>Etape 1</StepBadge>
                    </div>
                    <div className="space-y-3">
                        <Mail className="size-10 text-digital" />
                        <h2 className="text-3xl font-black tracking-normal">
                            Connexion par email.
                        </h2>
                        <p className="text-sm leading-6 text-muted-foreground">
                            Le passager confirme son adresse avant de créer son
                            PIN mobile.
                        </p>
                    </div>
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="auth-email">Email</Label>
                            <Input
                                id="auth-email"
                                type="email"
                                defaultValue="passager@kora.test"
                            />
                        </div>
                        <Button className="w-full">Recevoir le code</Button>
                    </div>
                </div>
                <p className="text-center text-xs text-muted-foreground">
                    Aucun compte social requis pour le MVP.
                </p>
            </section>
        </PhoneFrame>

        <PhoneFrame>
            <section className="grid min-h-[720px] content-between gap-8 p-6">
                <div className="space-y-8">
                    <div className="flex items-center justify-between">
                        <strong className="text-lg font-black text-primary">
                            Vérification
                        </strong>
                        <StepBadge>OTP</StepBadge>
                    </div>
                    <Alert>
                        <ShieldCheck className="size-4" />
                        <AlertTitle>Code envoyé</AlertTitle>
                        <AlertDescription>
                            Valide 5 minutes, tentatives limitées.
                        </AlertDescription>
                    </Alert>
                    <div className="space-y-4">
                        <Label>Code email</Label>
                        <InputOTP maxLength={6} value="384921" readOnly>
                            <InputOTPGroup className="gap-2">
                                {[0, 1, 2, 3, 4, 5].map((index) => (
                                    <InputOTPSlot
                                        key={index}
                                        index={index}
                                        className="size-11 rounded-md border bg-background text-base font-bold"
                                    />
                                ))}
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <Button className="w-full">Vérifier</Button>
                </div>
                <Button variant="ghost" className="w-full">
                    Renvoyer le code
                </Button>
            </section>
        </PhoneFrame>

        <PhoneFrame>
            <section className="grid min-h-[720px] content-between gap-8 p-6">
                <div className="space-y-7">
                    <div className="flex items-center justify-between">
                        <strong className="text-lg font-black text-primary">
                            Sécurité
                        </strong>
                        <StepBadge>PIN</StepBadge>
                    </div>
                    <div className="grid place-items-center rounded-2xl bg-primary p-8 text-primary-foreground">
                        <Fingerprint className="size-14" />
                    </div>
                    <div className="space-y-3">
                        <h2 className="text-3xl font-black tracking-normal">
                            Créer un PIN.
                        </h2>
                        <p className="text-sm leading-6 text-muted-foreground">
                            Le PIN protège les sessions mobiles et les actions
                            sensibles.
                        </p>
                    </div>
                    <InputOTP maxLength={4} value="1186" readOnly>
                        <InputOTPGroup className="mx-auto gap-3">
                            {[0, 1, 2, 3].map((index) => (
                                <InputOTPSlot
                                    key={index}
                                    index={index}
                                    className="size-12 rounded-full border bg-background text-base font-black"
                                />
                            ))}
                        </InputOTPGroup>
                    </InputOTP>
                    <Separator />
                    <div className="grid gap-3 text-sm">
                        {[
                            'Role passenger assigne automatiquement',
                            'Device trusted pour 7 jours',
                            'Token Sanctum non expose dans l UI',
                        ].map((item) => (
                            <div key={item} className="flex items-center gap-2">
                                <CheckCircle2 className="size-4 text-digital" />
                                <span>{item}</span>
                            </div>
                        ))}
                    </div>
                </div>
                <Button className="w-full">Activer mon compte</Button>
            </section>
        </PhoneFrame>
    </main>
);

const meta = {
    title: 'KORA App/Auth Mobile',
    component: AuthMobileFlow,
    parameters: {
        layout: 'fullscreen',
    },
} satisfies Meta<typeof AuthMobileFlow>;

export default meta;

type Story = StoryObj<typeof meta>;

export const EmailOtpPin: Story = {};
