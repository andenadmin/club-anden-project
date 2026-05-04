import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { labelize, MOTIVO_PAUSA_LABELS } from '@/lib/session-labels';

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

export function ConversationList({
    conversations,
    selectedNumero,
}: {
    conversations: ConversationListItem[];
    selectedNumero: string | null;
}) {
    if (conversations.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center text-center p-8 text-sm text-muted-foreground">
                <p className="font-medium">No hay conversaciones todavía</p>
                <p className="text-xs mt-1">Cuando un usuario escriba al bot, aparecerá acá.</p>
            </div>
        );
    }

    return (
        <ul className="flex flex-col">
            {conversations.map(c => {
                const isSelected = c.numero === selectedNumero;
                const isPaused   = c.estado_actual === 'PAUSADO';
                return (
                    <li key={c.numero}>
                        <Link
                            href={`/inbox/${c.numero}`}
                            preserveScroll
                            className={cn(
                                'flex items-start gap-3 px-3 py-3 border-b border-sidebar-border/50 transition-colors',
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
                                    <p className="font-medium text-sm truncate">
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
                    </li>
                );
            })}
        </ul>
    );
}
