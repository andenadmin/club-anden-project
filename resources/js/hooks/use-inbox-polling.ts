import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { ConversationListItem } from '@/components/inbox/conversation-list';
import { usePageVisibility } from '@/hooks/use-page-visibility';

const LIST_INTERVAL_MS = 3000;

interface PollResponse {
    conversations: ConversationListItem[];
    inboxUnreadTotal: number;
}

/**
 * Polling de la lista de conversaciones (cada 3s mientras la pestaña esté visible).
 * Devuelve el estado más reciente y el total de unread. Detecta nuevas conversaciones
 * escaladas y dispara un toast + llama a onAlert.
 */
export function useInboxPolling(initial: ConversationListItem[], onAlert?: () => void): {
    conversations: ConversationListItem[];
    unreadTotal: number;
} {
    const [conversations, setConversations] = useState<ConversationListItem[]>(initial);
    const [unreadTotal, setUnreadTotal]     = useState<number>(
        initial.filter(c => c.estado_actual === 'PAUSADO').reduce((acc, c) => acc + c.unread_count, 0),
    );
    const visible = usePageVisibility();
    const prevRef = useRef<Map<string, ConversationListItem>>(
        new Map(initial.map(c => [c.numero, c])),
    );

    // Si las props iniciales cambian (Inertia re-render), resyncronizamos.
    useEffect(() => {
        setConversations(initial);
        prevRef.current = new Map(initial.map(c => [c.numero, c]));
    }, [initial]);

    useEffect(() => {
        if (!visible) return;

        let cancelled = false;

        const poll = async () => {
            try {
                const r = await fetch('/inbox/poll', {
                    headers: { Accept: 'application/json' },
                });
                if (!r.ok || cancelled) return;
                const data = (await r.json()) as PollResponse;
                if (cancelled) return;

                detectNewEscalations(prevRef.current, data.conversations, onAlert);

                prevRef.current = new Map(data.conversations.map(c => [c.numero, c]));
                setConversations(data.conversations);
                setUnreadTotal(data.inboxUnreadTotal);
            } catch {
                // silencioso — siguiente tick reintenta
            }
        };

        const id = window.setInterval(poll, LIST_INTERVAL_MS);
        // primer poll inmediato cuando la pestaña recupera foco
        void poll();

        return () => {
            cancelled = true;
            window.clearInterval(id);
        };
    }, [visible]);

    return { conversations, unreadTotal };
}

function detectNewEscalations(
    prev: Map<string, ConversationListItem>,
    next: ConversationListItem[],
    onAlert?: () => void,
): void {
    let alerted = false;

    for (const conv of next) {
        const before = prev.get(conv.numero);
        // ASESOR_TAKEOVER: el asesor ya está en la conversación, no alertar por cada mensaje nuevo
        const needsAlert = (c: ConversationListItem) =>
            c.estado_actual === 'PAUSADO' && c.unread_count > 0 && c.motivo_pausa !== 'ASESOR_TAKEOVER';
        const wasEscalated = before ? needsAlert(before) : false;
        const isEscalated  = needsAlert(conv);
        const newConversation = !before;
        const newlyEscalated  = !wasEscalated && isEscalated;

        if (newConversation && isEscalated) {
            toast(`Nueva conversación escalada: ${conv.nombre ?? conv.numero}`, {
                description: conv.last_message?.body ?? '',
            });
            alerted = true;
        } else if (newlyEscalated) {
            toast(`${conv.nombre ?? conv.numero} pidió hablar con un asesor`, {
                description: conv.last_message?.body ?? '',
            });
            alerted = true;
        }
    }

    if (alerted) onAlert?.();
}
