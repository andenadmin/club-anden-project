import { useEffect, useRef, useState } from 'react';
import type { ChatMessage } from '@/components/inbox/conversation-chat';
import type { SessionData } from '@/components/inbox/session-summary';
import { usePageVisibility } from '@/hooks/use-page-visibility';

const CHAT_INTERVAL_MS = 2000;

interface ChatPollResponse {
    exists: boolean;
    newMessages?: ChatMessage[];
    session?: SessionData;
}

/**
 * Polling del chat seleccionado (cada 2s). Pide al server los mensajes con id > lastSeenId
 * y los appendea al estado local. También trae el estado actualizado de la sesión para
 * reflejar cambios hechos por el sweeper o el bot.
 */
export function useChatPolling(
    numero: string | null,
    initialMessages: ChatMessage[],
    initialSession: SessionData | null,
): { messages: ChatMessage[]; session: SessionData | null } {
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [session, setSession]   = useState<SessionData | null>(initialSession);
    const visible = usePageVisibility();
    const numeroRef = useRef(numero);

    // Reset cuando cambia la conversación seleccionada o cuando llegan nuevos initialMessages.
    useEffect(() => {
        numeroRef.current = numero;
        setMessages(initialMessages);
        setSession(initialSession);
    }, [numero, initialMessages, initialSession]);

    useEffect(() => {
        if (!numero || !visible) return;

        let cancelled = false;

        const poll = async () => {
            try {
                // Tomar el último id conocido AHORA (no del cierre) para evitar duplicados con sends optimistas.
                const lastId = messagesLastId();
                const r = await fetch(`/inbox/${numero}/poll?after=${lastId}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!r.ok || cancelled || numeroRef.current !== numero) return;
                const data = (await r.json()) as ChatPollResponse;
                if (cancelled || !data.exists) return;

                if (data.session) setSession(data.session);
                if (data.newMessages && data.newMessages.length > 0) {
                    setMessages(prev => {
                        const seen = new Set(prev.map(m => m.id));
                        const fresh = data.newMessages!.filter(m => !seen.has(m.id));
                        return fresh.length > 0 ? [...prev, ...fresh] : prev;
                    });
                }
            } catch {
                // silencioso
            }
        };

        const messagesLastId = (): number =>
            messages.length > 0 ? messages[messages.length - 1].id : 0;

        const id = window.setInterval(poll, CHAT_INTERVAL_MS);

        return () => {
            cancelled = true;
            window.clearInterval(id);
        };
        // Nota: `messages` deliberadamente fuera de deps para no resetear el interval en cada nuevo mensaje.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [numero, visible]);

    return { messages, session };
}
