import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Info, Menu } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { ConversationChat, type ChatMessage } from '@/components/inbox/conversation-chat';
import { ConversationList, type ConversationListItem } from '@/components/inbox/conversation-list';
import { ResumePromptModal } from '@/components/inbox/resume-prompt-modal';
import { SessionSummary, type ClienteData, type SessionData } from '@/components/inbox/session-summary';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useSidebar } from '@/components/ui/sidebar';
import { useChatPolling } from '@/hooks/use-chat-polling';
import { useInboxPolling } from '@/hooks/use-inbox-polling';
import { useTitleFlash } from '@/hooks/use-title-flash';

interface SelectedConversation {
    numero:   string;
    nombre:   string | null;
    cliente:  ClienteData | null;
    session:  SessionData;
    messages: ChatMessage[];
}

interface Props {
    conversations: ConversationListItem[];
    selected:      SelectedConversation | null;
}

function InboxBody({ conversations: initialConversations, selected }: Props) {
    const [summaryOpen, setSummaryOpen] = useState(false);
    const [optimisticMessages, setOptimisticMessages] = useState<ChatMessage[]>([]);
    const [resumePromptDismissed, setResumePromptDismissed] = useState(false);
    const { toggleSidebar } = useSidebar();

    // Polling de la lista (3s) — actualiza conversaciones y trigger toasts en escaladas nuevas.
    const { conversations, unreadTotal } = useInboxPolling(initialConversations);

    // Polling del chat seleccionado (2s) — agrega mensajes nuevos y refresca estado de la sesión.
    const selectedNumero  = selected?.numero ?? null;
    const initialMessages = selected?.messages ?? [];
    const initialSession  = selected?.session ?? null;
    const { messages: liveMessages, session: liveSession } = useChatPolling(
        selectedNumero, initialMessages, initialSession,
    );

    // Título de pestaña parpadeante con el contador de unread cuando la pestaña está en background.
    useTitleFlash(unreadTotal);

    // Resetear el dismissed cuando cambia la conversación o cuando next_resume_check_at se posterga.
    const dismissKey = selectedNumero
        ? `${selectedNumero}|${liveSession?.next_resume_check_at ?? ''}`
        : '';
    useEffect(() => {
        setResumePromptDismissed(false);
    }, [dismissKey]);

    // Limpiar mensajes optimistas al cambiar de conversación.
    useEffect(() => {
        setOptimisticMessages([]);
    }, [selectedNumero]);

    const messages = selected
        ? [...liveMessages, ...optimisticMessages.filter(o => !liveMessages.some(m => m.id === o.id))]
        : [];

    // Composer disponible cuando el bot está pausado (sea por cliente o por asesor).
    const canReply = liveSession?.estado_actual === 'PAUSADO';

    // Caja de confirmación §6.1.B — solo si está en ASESOR_TAKEOVER y next_resume_check_at vencido.
    const showResumePrompt =
        !resumePromptDismissed
        && selected
        && liveSession?.motivo_pausa === 'ASESOR_TAKEOVER'
        && liveSession?.next_resume_check_at !== null
        && liveSession?.next_resume_check_at !== undefined
        && new Date(liveSession.next_resume_check_at).getTime() <= Date.now();

    return (
        <div className="flex h-full overflow-hidden">
            {/* Lista de conversaciones (siempre visible en md+, oculta en mobile cuando hay seleccionada) */}
            <aside className={`${selected ? 'hidden md:flex' : 'flex'} flex-col w-full md:w-80 shrink-0 bg-background border-r border-sidebar-border/50 overflow-hidden`}>
                {/* Top bar mobile-only: en md+ el AppSidebar siempre está visible, pero en mobile
                    es offcanvas → necesitamos un botón visible para abrirlo. */}
                <header className="md:hidden h-12 px-2 flex items-center gap-2 border-b border-sidebar-border/50 shrink-0">
                    <button
                        onClick={toggleSidebar}
                        className="p-2 -ml-1 rounded-md hover:bg-accent active:bg-accent/80"
                        aria-label="Abrir menú">
                        <Menu className="size-5" />
                    </button>
                    <h1 className="font-semibold text-sm">Bandeja de entrada</h1>
                </header>
                <div className="flex-1 overflow-y-auto">
                    <ConversationList
                        conversations={conversations}
                        selectedNumero={selected?.numero ?? null}
                    />
                </div>
            </aside>

            {/* Chat + summary del seleccionado */}
            {selected ? (
                <>
                    <div className="flex-1 flex flex-col overflow-hidden min-w-0 relative">
                        <div className="md:hidden absolute top-0 left-0 right-0 z-10 h-12 bg-[#075e54] flex items-center justify-between px-2 shadow">
                            <button
                                onClick={() => router.visit('/inbox', { preserveScroll: true })}
                                className="p-1.5 -ml-1 rounded text-white hover:bg-white/10"
                                aria-label="Volver">
                                <ArrowLeft className="size-5" />
                            </button>
                            <button
                                onClick={() => setSummaryOpen(true)}
                                className="p-1.5 -mr-1 rounded text-white hover:bg-white/10"
                                aria-label="Ver resumen">
                                <Info className="size-5" />
                            </button>
                        </div>
                        <ConversationChat
                            numero={selected.numero}
                            nombre={selected.nombre}
                            messages={messages}
                            canReply={canReply}
                            estadoActual={liveSession?.estado_actual ?? selected.session.estado_actual}
                            onMessageSent={msg => setOptimisticMessages(p => [...p, msg])}
                        />
                    </div>

                    {/* Summary panel — desktop fijo a la derecha */}
                    <aside className="hidden lg:flex w-72 shrink-0 flex-col">
                        <SessionSummary
                            numero={selected.numero}
                            cliente={selected.cliente}
                            session={liveSession ?? selected.session}
                        />
                    </aside>

                    {/* Summary mobile/tablet — sheet */}
                    <Sheet open={summaryOpen} onOpenChange={setSummaryOpen}>
                        <SheetContent side="right" className="w-80 p-0 flex flex-col">
                            <SheetHeader className="sr-only">
                                <SheetTitle>Resumen</SheetTitle>
                            </SheetHeader>
                            <SessionSummary
                                numero={selected.numero}
                                cliente={selected.cliente}
                                session={liveSession ?? selected.session}
                            />
                        </SheetContent>
                    </Sheet>

                    {/* Caja de confirmación §6.1.B */}
                    <ResumePromptModal
                        open={!!showResumePrompt}
                        numero={selected.numero}
                        nombre={selected.nombre}
                        pausedAt={liveSession?.timestamp_pausa ?? selected.session.timestamp_pausa}
                        onClose={() => setResumePromptDismissed(true)}
                    />
                </>
            ) : (
                <div className="hidden md:flex flex-1 flex-col items-center justify-center text-center p-8 text-muted-foreground bg-[#f5f5f3] dark:bg-neutral-900">
                    <p className="text-sm font-medium">Seleccioná una conversación</p>
                    <p className="text-xs mt-1">El historial y el resumen aparecen acá.</p>
                </div>
            )}
        </div>
    );
}

export default function Inbox(props: Props) {
    return (
        <>
            <Head title="Bandeja de entrada" />
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent variant="sidebar" style={{ height: '100svh', overflow: 'hidden' }}>
                    <InboxBody {...props} />
                </AppContent>
            </AppShell>
        </>
    );
}
