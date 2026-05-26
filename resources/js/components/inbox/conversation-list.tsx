import { Link } from '@inertiajs/react';
import { Archive, ArchiveRestore, MoreVertical, Pin, PinOff, Star, StarOff, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import { labelize, MOTIVO_PAUSA_LABELS } from '@/lib/session-labels';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export interface ConversationListItem {
    numero: string;
    nombre: string | null;
    estado_actual: string;
    motivo_pausa: string | null;
    last_message_at: string | null;
    unread_count: number;
    last_message: {
        sender: 'user' | 'bot' | 'advisor';
        body: string;
        created_at: string | null;
    } | null;
    is_pinned: boolean;
    is_archived: boolean;
    is_important: boolean;
}

function formatRelative(iso: string | null): string {
    if (!iso) return '';
    const date = new Date(iso);
    const diffMs = Date.now() - date.getTime();
    const mins = Math.floor(diffMs / 60_000);
    if (mins < 1) return 'ahora';
    if (mins < 60) return `${mins}m`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d`;
    return date.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit' });
}

function senderPrefix(sender: 'user' | 'bot' | 'advisor'): string {
    switch (sender) {
        case 'bot':     return 'Bot: ';
        case 'advisor': return 'Vos: ';
        default:        return '';
    }
}

function initials(name: string | null, numero: string): string {
    if (name) {
        return name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(w => w[0]?.toUpperCase() ?? '')
            .join('') || '?';
    }
    return numero.slice(-2);
}

function SectionHeader({ label }: { label: string }) {
    return (
        <div className="px-3 py-1 text-[10px] uppercase tracking-wider font-semibold text-muted-foreground bg-muted/40 border-b border-sidebar-border/30 select-none">
            {label}
        </div>
    );
}

interface ConversationListProps {
    conversations: ConversationListItem[];
    selectedNumero: string | null;
    emptySearch?: boolean;
    isArchived?: boolean;
    onPin?: (numero: string) => void;
    onArchive?: (numero: string) => void;
    onUnarchive?: (numero: string) => void;
    onDelete?: (numero: string) => void;
    onImportant?: (numero: string) => void;
}

function ConversationItem({
    c,
    isSelected,
    isArchived,
    onPin,
    onArchive,
    onUnarchive,
    onDelete,
    onImportant,
}: {
    c: ConversationListItem;
    isSelected: boolean;
    isArchived: boolean;
    onPin?: (n: string) => void;
    onArchive?: (n: string) => void;
    onUnarchive?: (n: string) => void;
    onDelete?: (n: string) => void;
    onImportant?: (n: string) => void;
}) {
    const isPaused = c.estado_actual === 'PAUSADO';
    const needsAttention = isPaused && c.motivo_pausa !== 'ASESOR_TAKEOVER';

    return (
        <li className={cn('relative group border-b border-sidebar-border/50', needsAttention && 'attention-pulse')}>
            <Link
                href={`/inbox/${c.numero}`}
                preserveScroll
                className={cn(
                    'flex items-start gap-3 px-3 py-3 pr-8 transition-colors',
                    isSelected
                        ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                        : 'hover:bg-sidebar-accent/40',
                )}
            >
                <div className="size-10 shrink-0 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-semibold dark:bg-emerald-900/40 dark:text-emerald-300">
                    {initials(c.nombre, c.numero)}
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-baseline justify-between gap-2">
                        <p className="font-medium text-sm truncate flex items-center gap-1">
                            {c.is_important && <span className="text-yellow-500 text-xs">⭐</span>}
                            {c.is_pinned && !c.is_important && <span className="text-blue-500 text-xs">📌</span>}
                            {c.nombre ?? c.numero}
                        </p>
                        <span className="text-[10px] text-muted-foreground shrink-0">
                            {formatRelative(c.last_message_at)}
                        </span>
                    </div>
                    <div className="flex items-baseline justify-between gap-2 mt-0.5">
                        <p className="text-xs text-muted-foreground truncate">
                            {c.last_message
                                ? senderPrefix(c.last_message.sender) + c.last_message.body
                                : 'Sin mensajes aún'}
                        </p>
                        {c.unread_count > 0 && (
                            <span className="shrink-0 text-[10px] font-bold bg-red-500 text-white rounded-full px-1.5 min-w-[18px] text-center">
                                {c.unread_count > 99 ? '99+' : c.unread_count}
                            </span>
                        )}
                    </div>
                    {isPaused && (
                        <span className="inline-block mt-1 text-[9px] uppercase tracking-wider font-semibold bg-amber-100 text-amber-700 rounded px-1.5 py-0.5 dark:bg-amber-900/40 dark:text-amber-300">
                            {labelize(c.motivo_pausa, MOTIVO_PAUSA_LABELS)}
                        </span>
                    )}
                </div>
            </Link>

            {/* 3-dot menu */}
            <div className="absolute right-1 top-2 z-10">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            className="p-1.5 rounded text-muted-foreground hover:text-foreground hover:bg-accent opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity"
                            aria-label="Opciones"
                        >
                            <MoreVertical className="size-3.5" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-44">
                        {!isArchived && (
                            <>
                                <DropdownMenuItem onClick={() => onImportant?.(c.numero)} className="gap-2 cursor-pointer">
                                    {c.is_important
                                        ? <><StarOff className="size-3.5" /> Quitar estrella</>
                                        : <><Star className="size-3.5 text-yellow-500" /> Marcar importante</>
                                    }
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => onPin?.(c.numero)} className="gap-2 cursor-pointer">
                                    {c.is_pinned
                                        ? <><PinOff className="size-3.5" /> Desfijar</>
                                        : <><Pin className="size-3.5" /> Fijar arriba</>
                                    }
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => onArchive?.(c.numero)} className="gap-2 cursor-pointer">
                                    <Archive className="size-3.5" /> Archivar
                                </DropdownMenuItem>
                            </>
                        )}
                        {isArchived && (
                            <DropdownMenuItem onClick={() => onUnarchive?.(c.numero)} className="gap-2 cursor-pointer">
                                <ArchiveRestore className="size-3.5" /> Desarchivar
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => onDelete?.(c.numero)}
                            className="gap-2 cursor-pointer text-destructive focus:text-destructive"
                        >
                            <Trash2 className="size-3.5" /> Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </li>
    );
}

export function ConversationList({
    conversations,
    selectedNumero,
    emptySearch = false,
    isArchived = false,
    onPin,
    onArchive,
    onUnarchive,
    onDelete,
    onImportant,
}: ConversationListProps) {
    if (conversations.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center text-center p-8 text-sm text-muted-foreground">
                {emptySearch ? (
                    <>
                        <p className="font-medium">Sin resultados</p>
                        <p className="text-xs mt-1">Ninguna conversación coincide con tu búsqueda.</p>
                    </>
                ) : isArchived ? (
                    <>
                        <p className="font-medium">Sin conversaciones archivadas</p>
                        <p className="text-xs mt-1">Acá van a aparecer los chats que archives.</p>
                    </>
                ) : (
                    <>
                        <p className="font-medium">No hay conversaciones todavía</p>
                        <p className="text-xs mt-1">Cuando un usuario escriba al bot, aparecerá acá.</p>
                    </>
                )}
            </div>
        );
    }

    const itemProps = { isArchived, onPin, onArchive, onUnarchive, onDelete, onImportant };

    // Archived tab: flat list, no sections
    if (isArchived) {
        return (
            <ul className="flex flex-col">
                {conversations.map(c => (
                    <ConversationItem
                        key={c.numero}
                        c={c}
                        isSelected={c.numero === selectedNumero}
                        {...itemProps}
                    />
                ))}
            </ul>
        );
    }

    // Active tab: group into sections
    const importantes = conversations.filter(c => c.is_important);
    const fijados     = conversations.filter(c => c.is_pinned && !c.is_important);
    const generales   = conversations.filter(c => !c.is_pinned && !c.is_important);
    const hasSections = importantes.length > 0 || fijados.length > 0;

    return (
        <ul className="flex flex-col">
            {importantes.length > 0 && (
                <>
                    <SectionHeader label="⭐ Importantes" />
                    {importantes.map(c => (
                        <ConversationItem key={c.numero} c={c} isSelected={c.numero === selectedNumero} {...itemProps} />
                    ))}
                </>
            )}
            {fijados.length > 0 && (
                <>
                    <SectionHeader label="📌 Fijados" />
                    {fijados.map(c => (
                        <ConversationItem key={c.numero} c={c} isSelected={c.numero === selectedNumero} {...itemProps} />
                    ))}
                </>
            )}
            {generales.length > 0 && (
                <>
                    {hasSections && <SectionHeader label="General" />}
                    {generales.map(c => (
                        <ConversationItem key={c.numero} c={c} isSelected={c.numero === selectedNumero} {...itemProps} />
                    ))}
                </>
            )}
        </ul>
    );
}
