import { usePage } from '@inertiajs/react';
import {
    BookOpen,
    CircleHelp,
    GraduationCap,
    LifeBuoy,
    MessagesSquare,
} from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { isSupportChatHidden, setSupportChatHidden } from '@/lib/support-chat';

/**
 * Menu « Aide et ressources » du topbar : liens documentation à venir
 * et toggle de visibilité du widget tawk.to (la bulle flottante peut
 * masquer des actions en bas de page).
 */
export function HelpMenu() {
    const { supportChat } = usePage().props;
    const chatAvailable = Boolean(
        supportChat?.enabled && supportChat.propertyId && supportChat.widgetId,
    );
    const [chatVisible, setChatVisible] = useState(
        () => chatAvailable && !isSupportChatHidden(),
    );

    const toggleChat = (checked: boolean) => {
        setChatVisible(checked);
        setSupportChatHidden(!checked);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="grid size-[2.625rem] place-items-center rounded-[0.75rem] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Aide et ressources"
                >
                    <CircleHelp className="size-5" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-64 rounded-[0.9375rem]"
            >
                <DropdownMenuLabel className="font-normal">
                    <div className="flex flex-col gap-0.5">
                        <span className="text-sm font-bold text-foreground">
                            Aide et ressources
                        </span>
                        <span className="text-xs text-muted-foreground">
                            Documentation et support de KORA App Suite
                        </span>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem disabled>
                    <BookOpen />
                    Documentation
                    <DropdownMenuShortcut>Bientôt</DropdownMenuShortcut>
                </DropdownMenuItem>
                <DropdownMenuItem disabled>
                    <GraduationCap />
                    Guide d’utilisateur
                    <DropdownMenuShortcut>Bientôt</DropdownMenuShortcut>
                </DropdownMenuItem>
                <DropdownMenuItem disabled>
                    <LifeBuoy />
                    Centre d’aide
                    <DropdownMenuShortcut>Bientôt</DropdownMenuShortcut>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuCheckboxItem
                    checked={chatVisible}
                    disabled={!chatAvailable}
                    onSelect={(event) => event.preventDefault()}
                    onCheckedChange={toggleChat}
                >
                    <MessagesSquare />
                    Live chat
                    {!chatAvailable && (
                        <DropdownMenuShortcut>
                            Indisponible
                        </DropdownMenuShortcut>
                    )}
                </DropdownMenuCheckboxItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
