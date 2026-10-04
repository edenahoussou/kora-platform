import type { Method } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import { LockKeyhole, ShieldAlert } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

interface PasswordConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel?: string;
    /** Route cible de l'action sensible (DELETE/POST/PUT/PATCH). */
    action: { url: string; method: Method };
    onSuccess?: () => void;
}

/**
 * Boîte de dialogue de confirmation par mot de passe pour les actions
 * sensibles du hub profil (révocations, déconnexions à distance).
 */
export function PasswordConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirmer',
    action,
    onSuccess,
}: PasswordConfirmDialogProps) {
    const form = useForm({ password: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        form.submit(action.method, action.url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
                onSuccess?.();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    form.reset();
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="max-w-md rounded-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base font-bold">
                        <ShieldAlert className="size-5 text-amber-600" />
                        <span>{title}</span>
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground">
                        {description}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                    <div className="space-y-1.5">
                        <Label
                            htmlFor="sensitive-action-password"
                            className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                        >
                            <LockKeyhole className="size-3.5 text-muted-foreground" />
                            <span>Mot de passe de confirmation</span>
                        </Label>
                        <PasswordInput
                            id="sensitive-action-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            autoComplete="current-password"
                            placeholder="Saisissez votre mot de passe"
                            className="h-10 rounded-[10px]"
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => onOpenChange(false)}
                            className="rounded-xl text-xs"
                        >
                            Annuler
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            size="sm"
                            disabled={form.processing || !form.data.password}
                            className="rounded-xl text-xs font-bold"
                        >
                            {form.processing ? 'Vérification...' : confirmLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
