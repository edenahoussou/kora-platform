import { Form } from '@inertiajs/react';
import { Building2, Check, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import agencies from '@/routes/agencies';

export type AgencyRecord = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    country: string;
    headUserId: number | null;
    headName: string | null;
    status: string;
    createdAt: string | null;
    updatedAt: string | null;
};

export type HeadCandidate = {
    id: number;
    name: string;
};

type AgencyFormDialogProps = {
    open: boolean;
    agency?: AgencyRecord | null;
    statuses: Record<string, string>;
    headCandidates: HeadCandidate[];
    onOpenChange: (open: boolean) => void;
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    draft: 'Brouillon',
    suspended: 'Suspendue',
    archived: 'Archivée',
};

export function AgencyFormDialog({
    open,
    agency,
    statuses,
    headCandidates,
    onOpenChange,
}: AgencyFormDialogProps) {
    const isEditing = Boolean(agency);
    const title = isEditing ? 'Modifier l’agence' : 'Nouvelle agence';
    const description = isEditing
        ? 'Mettez à jour les informations du site physique KORA.'
        : 'Créez un nouveau site physique KORA dans le référentiel.';
    const form = isEditing
        ? agencies.update.form({ agency: agency!.uuid })
        : agencies.store.form();

    const handleClose = (nextOpen: boolean) => {
        onOpenChange(nextOpen);
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogPortal>
                <DialogOverlay className="bg-[rgba(0,29,78,0.24)] backdrop-blur-md" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                className="max-w-[38rem] rounded-[1.75rem] border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] [&>button]:hidden"
            >
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex items-start justify-between gap-5 border-b border-border/70 p-7 pb-6">
                                <div className="flex items-start gap-4">
                                    <span className="grid size-12 shrink-0 place-items-center rounded-[0.9rem] bg-gold text-[var(--kora)] shadow-[0_11px_25px_rgba(246,176,0,0.22)]">
                                        <Building2 className="size-5" />
                                    </span>
                                    <div>
                                        <span className="mb-1 block text-[0.6875rem] font-black tracking-[0.15em] text-gold uppercase">
                                            Référentiel agences
                                        </span>
                                        <DialogTitle className="text-[1.5rem] font-extrabold tracking-[-0.03em] text-foreground">
                                            {title}
                                        </DialogTitle>
                                        <DialogDescription className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                            {description}
                                        </DialogDescription>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => handleClose(false)}
                                    className="grid size-9 shrink-0 place-items-center rounded-[0.7rem] bg-muted text-foreground transition-[background,transform] duration-200 hover:scale-[1.03] hover:bg-muted/80"
                                    aria-label="Fermer"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>

                            <div className="grid gap-5 p-7">
                                <div className="grid gap-2">
                                    <Label htmlFor="agency-code">Code agence *</Label>
                                    <Input
                                        id="agency-code"
                                        name="code"
                                        defaultValue={agency?.code ?? ''}
                                        required
                                        maxLength={32}
                                        placeholder="COT"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted uppercase shadow-[inset_0_0_0_1px_var(--border)] focus-visible:bg-background"
                                    />
                                    <InputError message={errors.code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="agency-name">Nom de l’agence *</Label>
                                    <Input
                                        id="agency-name"
                                        name="name"
                                        defaultValue={agency?.name ?? ''}
                                        required
                                        placeholder="Agence Cotonou Centre"
                                        className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)] focus-visible:bg-background"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="agency-city">Ville *</Label>
                                        <Input
                                            id="agency-city"
                                            name="city"
                                            defaultValue={agency?.city ?? ''}
                                            required
                                            placeholder="Cotonou"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)] focus-visible:bg-background"
                                        />
                                        <InputError message={errors.city} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="agency-country">Pays *</Label>
                                        <Input
                                            id="agency-country"
                                            name="country"
                                            defaultValue={agency?.country ?? 'BJ'}
                                            onChange={(event) => {
                                                event.currentTarget.value = event.currentTarget.value.toUpperCase();
                                            }}
                                            required
                                            maxLength={2}
                                            placeholder="BJ"
                                            className="h-12 rounded-[0.875rem] border-0 bg-muted uppercase shadow-[inset_0_0_0_1px_var(--border)] focus-visible:bg-background"
                                        />
                                        <InputError message={errors.country} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="agency-head">Chef d’agence</Label>
                                    <select
                                        id="agency-head"
                                        name="head_user_id"
                                        defaultValue={agency?.headUserId ?? ''}
                                        className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none transition-[box-shadow,transform,background] focus:translate-y-[-1px] focus:bg-background focus:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                                    >
                                        <option value="">— Aucun —</option>
                                        {headCandidates.map((candidate) => (
                                            <option key={candidate.id} value={candidate.id}>
                                                {candidate.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.head_user_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="agency-status">Statut *</Label>
                                    <select
                                        id="agency-status"
                                        name="status"
                                        defaultValue={agency?.status ?? 'draft'}
                                        className="h-12 w-full rounded-[0.875rem] border-0 bg-muted px-3 text-sm text-foreground shadow-[inset_0_0_0_1px_var(--border)] outline-none transition-[box-shadow,transform,background] focus:translate-y-[-1px] focus:bg-background focus:shadow-[0_0_0_3px_rgba(246,176,0,0.16),inset_0_0_0_1px_var(--gold)]"
                                    >
                                        {Object.entries(statuses).map(
                                            ([value, label]) => (
                                                <option key={value} value={value}>
                                                    {statusLabels[value] ?? label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                    <InputError message={errors.status} />
                                </div>
                            </div>

                            <div className="flex flex-col-reverse gap-3 border-t border-border/70 bg-muted/30 p-7 sm:flex-row sm:justify-end">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => handleClose(false)}
                                    disabled={processing}
                                    className="h-12 rounded-[0.8rem] px-5"
                                >
                                    Annuler
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-12 rounded-[0.8rem] bg-gold px-6 font-medium text-[var(--kora)] shadow-[0_9px_22px_rgba(246,176,0,0.22)] hover:bg-[var(--kora)] hover:text-white"
                                >
                                    <Check className="size-4" />
                                    {processing
                                        ? 'Enregistrement…'
                                        : isEditing
                                            ? 'Enregistrer les changements'
                                            : 'Créer l’agence'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
