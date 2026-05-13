import { useEffect, useRef, useState } from 'react';
import { CheckCheck, Info, Send } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface ChatMessage {
    id: number;
    direction: 'inbound' | 'outbound';
    sender: 'user' | 'bot' | 'advisor';
    body: string;
    wa_status: string | null;
    created_at: string | null;
}

const fmtTime = (iso: string | null): string => {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
};

function waMd(raw: string): string {
    return raw
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\*(.*?)\*/g, '<strong>$1</strong>');
}

function csrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

function MessageBubble({ msg }: { msg: ChatMessage }) {
    const isImage = msg.body.startsWith('[IMG]');
    const imageUrl = isImage ? msg.body.slice(5) : null;

    if (msg.sender === 'user') {
        return (
            <div className="flex items-end gap-2 max-w-[78%]">
                <div className="size-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-[10px] font-bold shrink-0 shadow">U</div>
                <div className="bg-white rounded-2xl rounded-bl-sm px-3.5 py-2 shadow-sm">
                    <p className="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed break-words">{msg.body}</p>
                    <p className="text-[10px] text-gray-400 text-right mt-0.5">{fmtTime(msg.created_at)}</p>
                </div>
            </div>
        );
    }

    const isAdvisor = msg.sender === 'advisor';
    const bg     = isAdvisor ? 'bg-[#dcf8c6]' : 'bg-white';
    const avatar = isAdvisor
        ? <div className="size-8 rounded-full bg-emerald-700 flex items-center justify-center text-white text-[10px] font-bold shrink-0 shadow">AA</div>
        : <div className="size-8 rounded-full bg-[#25d366]   flex items-center justify-center text-white text-xs font-bold shrink-0 shadow">A</div>;

    return (
        <div className="flex items-end gap-2 max-w-[78%] self-end flex-row-reverse">
            {avatar}
            <div className={cn('rounded-2xl rounded-br-sm shadow-sm overflow-hidden', isImage ? 'p-0' : 'px-3.5 py-2', bg)}>
                {isImage ? (
                    <img
                        src={imageUrl!}
                        alt="Imagen del bot"
                        className="max-w-[260px] rounded-2xl rounded-br-sm block"
                    />
                ) : (
                    <pre className="text-sm text-gray-800 whitespace-pre-wrap font-sans leading-relaxed break-words"
                        dangerouslySetInnerHTML={{ __html: waMd(msg.body) }} />
                )}
                <div className={cn('flex items-center justify-end gap-1 mt-0.5', isImage && 'px-2 pb-1')}>
                    <p className="text-[10px] text-gray-400">{fmtTime(msg.created_at)}</p>
                    {msg.wa_status === 'failed' && (
                        <span className="text-[10px] text-red-500 font-semibold">⚠ no enviado</span>
                    )}
                    {(msg.wa_status === 'delivered' || msg.wa_status === 'read') && (
                        <CheckCheck className={cn('size-3', msg.wa_status === 'read' ? 'text-blue-500' : 'text-gray-400')} />
                    )}
                </div>
            </div>
        </div>
    );
}

export function ConversationChat({
    numero,
    nombre,
    messages,
    canReply,
    estadoActual,
    onMessageSent,
    onInfoClick,
}: {
    numero: string;
    nombre: string | null;
    messages: ChatMessage[];
    canReply: boolean;
    estadoActual: string;
    onMessageSent: (msg: ChatMessage) => void;
    onInfoClick?: () => void;
}) {
    const [draft, setDraft]     = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError]     = useState<string | null>(null);
    const bottomRef             = useRef<HTMLDivElement>(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length]);

    const send = async () => {
        const text = draft.trim();
        if (!text || sending) return;

        setSending(true);
        setError(null);

        try {
            const r = await fetch(`/inbox/${numero}/reply`, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({ message: text }),
            });
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            const data = await r.json();
            onMessageSent({
                id:        data.message.id,
                direction: 'outbound',
                sender:    data.message.sender,
                body:      data.message.body,
                wa_status: data.message.wa_status,
                created_at: data.message.created_at,
            });
            setDraft('');
        } catch (e) {
            setError(e instanceof Error ? e.message : 'No se pudo enviar el mensaje');
        } finally {
            setSending(false);
        }
    };

    return (
        <div className="flex-1 flex flex-col overflow-hidden bg-[#e5ddd5] min-w-0">
            {/* Header */}
            <header className="bg-[#075e54] text-white px-3 h-12 flex items-center justify-between shrink-0 shadow">
                <div className="flex items-center gap-2.5 min-w-0">
                    <div className="size-7 rounded-full bg-[#25d366] flex items-center justify-center text-white text-xs font-bold shadow shrink-0">
                        {(nombre ?? numero).slice(0, 1).toUpperCase()}
                    </div>
                    <div className="min-w-0">
                        <p className="text-white text-sm font-semibold leading-tight truncate">{nombre ?? numero}</p>
                        <p className="text-[10px] text-green-200 truncate">{numero}</p>
                    </div>
                </div>
                {onInfoClick && (
                    <button
                        onClick={onInfoClick}
                        className="hidden lg:flex p-1.5 -mr-1 rounded text-white hover:bg-white/10"
                        aria-label="Ver info del cliente"
                    >
                        <Info className="size-5" />
                    </button>
                )}
            </header>

            {/* Mensajes */}
            <div className="flex-1 overflow-y-auto">
                <div className="px-3 sm:px-4 py-4 flex flex-col gap-3">
                    {messages.length === 0 && (
                        <p className="text-center text-sm text-gray-500 py-8">
                            Esta conversación todavía no tiene mensajes.
                        </p>
                    )}
                    {messages.map(m => <MessageBubble key={m.id} msg={m} />)}
                    <div ref={bottomRef} />
                </div>
            </div>

            {/* Composer */}
            <div className="bg-[#f0f2f5] shrink-0 px-2 sm:px-3 py-2.5 border-t border-gray-200">
                {canReply ? (
                    <>
                        {error && (
                            <p className="text-xs text-red-600 mb-2 px-1">{error}</p>
                        )}
                        <div className="flex items-end gap-2">
                            <textarea
                                value={draft}
                                onChange={e => setDraft(e.target.value)}
                                onKeyDown={e => {
                                    if (e.key === 'Enter' && !e.shiftKey) {
                                        e.preventDefault();
                                        void send();
                                    }
                                }}
                                placeholder="Escribir como Administración Anden…"
                                disabled={sending}
                                rows={1}
                                className="flex-1 min-w-0 resize-none rounded-2xl bg-white border border-gray-200 px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 outline-none focus:ring-2 focus:ring-emerald-700/40 shadow-sm disabled:opacity-60 max-h-32"
                            />
                            <button
                                onClick={() => void send()}
                                disabled={sending || !draft.trim()}
                                className="size-10 rounded-full bg-emerald-700 flex items-center justify-center text-white shadow hover:bg-emerald-800 active:scale-95 transition-all disabled:opacity-50 shrink-0">
                                <Send className="size-4" />
                            </button>
                        </div>
                    </>
                ) : (
                    <p className="text-xs text-center text-gray-600 px-2 py-1.5">
                        El bot está respondiendo a este usuario ({estadoActual.toLowerCase().replaceAll('_', ' ')}).
                        Pausá el bot para escribirle vos.
                    </p>
                )}
            </div>
        </div>
    );
}
