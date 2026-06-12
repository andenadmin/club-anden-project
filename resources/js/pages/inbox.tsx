import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Bell, BellOff, ChevronDown, Info, Menu, Search, Volume2, VolumeX, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import { useNotificationSound } from '@/hooks/use-notification-sound';
import { useWebNotifications } from '@/hooks/use-web-notifications';
import { useTitleFlash } from '@/hooks/use-title-flash';
import { cn } from '@/lib/utils';

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
    const [summaryDesktopVisible, setSummaryDesktopVisible] = useState(true);
    const [optimisticMessages, setOptimisticMessages] = useState<ChatMessage[]>([]);
    const [resumePromptDismissed, setResumePromptDismissed] = useState(false);
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState<'activos' | 'archivados'>('activos');
    const [tabsCollapsed, setTabsCollapsed] = useState(false);
    const [archivedConversations, setArchivedConversations] = useState<ConversationListItem[]>([]);
    const [loadingArchived, setLoadingArchived] = useState(false);
    const { toggleSidebar } = useSidebar();

    const { play: playAlert, muted, toggle: toggleMuted } = useNotificationSound();
    const { permission, requestPermission } = useWebNotifications();
    const [alertCount, setAlertCount] = useState(0);
    useEffect(() => {
        const handler = (e: Event) => setAlertCount((e as CustomEvent<{ count: number }>).detail.count);
        window.addEventListener('inbox-alert-count', handler);
        return () => window.removeEventListener('inbox-alert-count', handler);
    }, []);

    // Polling de la lista (3s) — actualiza conversaciones y trigger toasts en escaladas nuevas.
    const { conversations: polledConversations, unreadTotal } = useInboxPolling(initialConversations, playAlert);

    // Estado local para actualizaciones optimistas (pin, archive, delete, important).
    const [activeConversations, setActiveConversations] = useState<ConversationListItem[]>(initialConversations);
    useEffect(() => { setActiveConversations(polledConversations); }, [polledConversations]);

    // Polling del chat seleccionado (2s) — agrega mensajes nuevos y refresca estado de la sesión.
    const selectedNumero  = selected?.numero ?? null;
    // Estabilizar referencias: solo cambian cuando cambia la conversación seleccionada,
    // no en cada re-render del inbox polling.
    const initialMessages = useMemo(() => selected?.messages ?? [], [selectedNumero]);
    const initialSession  = useMemo(() => selected?.session ?? null, [selectedNumero]);
    const { messages: liveMessages, session: liveSession } = useChatPolling(
        selectedNumero, initialMessages, initialSession,
    );

    // Título de pestaña parpadeante con el contador de unread cuando la pestaña está en background.
    useTitleFlash(unreadTotal);

    const csrf = () =>
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

    const handleTabChange = async (newTab: 'activos' | 'archivados') => {
        setTab(newTab);
        setSearch('');
        if (newTab === 'archivados' && archivedConversations.length === 0) {
            setLoadingArchived(true);
            try {
                const r = await fetch('/inbox/archived-list', { headers: { Accept: 'application/json' } });
                if (r.ok) setArchivedConversations(await r.json());
            } finally {
                setLoadingArchived(false);
            }
        }
    };

    const handlePin = async (numero: string) => {
        setActiveConversations(prev =>
            prev.map(c => c.numero === numero ? { ...c, is_pinned: !c.is_pinned } : c),
        );
        await fetch(`/inbox/${numero}/pin`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf() } });
    };

    const handleImportant = async (numero: string) => {
        setActiveConversations(prev =>
            prev.map(c => c.numero === numero ? { ...c, is_important: !c.is_important } : c),
        );
        await fetch(`/inbox/${numero}/important`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf() } });
    };

    const handleArchive = async (numero: string) => {
        setActiveConversations(prev => prev.filter(c => c.numero !== numero));
        await fetch(`/inbox/${numero}/archive`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf() } });
        if (selected?.numero === numero) router.visit('/inbox', { preserveScroll: false });
        // Forzar reload de archivados si el tab está abierto
        setArchivedConversations([]);
    };

    const handleUnarchive = async (numero: string) => {
        setArchivedConversations(prev => prev.filter(c => c.numero !== numero));
        await fetch(`/inbox/${numero}/unarchive`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf() } });
    };

    const handleDelete = async (numero: string) => {
        if (!window.confirm('¿Eliminar esta conversación? Esta acción no se puede deshacer.')) return;
        setActiveConversations(prev => prev.filter(c => c.numero !== numero));
        setArchivedConversations(prev => prev.filter(c => c.numero !== numero));
        await fetch(`/inbox/${numero}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf() } });
        if (selected?.numero === numero) router.visit('/inbox', { preserveScroll: false });
    };

    const displayedConversations = tab === 'archivados' ? archivedConversations : activeConversations;

    const filteredConversations = useMemo(() => {
        if (!search.trim()) return displayedConversations;
        const q = search.toLowerCase().trim();
        return displayedConversations.filter(c =>
            c.nombre?.toLowerCase().includes(q) ||
            c.numero.includes(q) ||
            c.last_message?.body.toLowerCase().includes(q),
        );
    }, [displayedConversations, search]);

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

    const messages = useMemo(() =>
        selected
            ? [...liveMessages, ...optimisticMessages.filter(o => !liveMessages.some(m => m.id === o.id))]
            : [],
    [selected, liveMessages, optimisticMessages]);

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
                <header className="flex flex-col shrink-0 border-b border-sidebar-border/50">
                    <div className="h-12 px-2 flex items-center gap-2">
                        <button
                            onClick={toggleSidebar}
                            className="md:hidden p-2 -ml-1 rounded-md hover:bg-accent active:bg-accent/80"
                            aria-label="Abrir menú">
                            <Menu className="size-5" />
                        </button>
                        <h1 className="font-semibold text-sm flex-1">Bandeja de entrada</h1>
                        <button
                            onClick={() => setTabsCollapsed(c => !c)}
                            className="p-2 rounded-md hover:bg-accent active:bg-accent/80 text-muted-foreground hover:text-foreground"
                            title={tabsCollapsed ? 'Expandir' : 'Contraer'}
                        >
                            <ChevronDown className={`size-4 transition-transform duration-200 ${tabsCollapsed ? '-rotate-90' : ''}`} />
                        </button>
                        <button
                            onClick={requestPermission}
                            title={
                                permission === 'granted'
                                    ? 'Notificaciones del sistema activas'
                                    : permission === 'denied'
                                    ? 'Notificaciones bloqueadas — habilitá el permiso en la configuración del navegador'
                                    : 'Activar notificaciones del sistema'
                            }
                            className={`p-2 rounded-md hover:bg-accent active:bg-accent/80 ${
                                permission === 'granted'
                                    ? 'text-green-500'
                                    : permission === 'denied'
                                    ? 'text-red-400'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {permission === 'denied'
                                ? <VolumeX className="size-4" />
                                : <Volume2 className="size-4" />
                            }
                        </button>
                        <button
                            onClick={toggleMuted}
                            title={muted ? 'Activar sonido de alertas' : 'Silenciar alertas'}
                            className="relative p-2 rounded-md hover:bg-accent active:bg-accent/80 text-muted-foreground hover:text-foreground"
                        >
                            {muted ? <BellOff className="size-4" /> : <Bell className="size-4" />}
                            {!muted && alertCount > 0 && (
                                <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-0.5 text-[10px] font-bold text-white">
                                    {alertCount > 99 ? '99+' : alertCount}
                                </span>
                            )}
                        </button>
                    </div>
                    {!tabsCollapsed && <div className="flex">
                        {(['activos', 'archivados'] as const).map(t => (
                            <button
                                key={t}
                                onClick={() => handleTabChange(t)}
                                className={cn(
                                    'flex-1 py-2 text-xs font-medium transition-colors border-b-2',
                                    tab === t
                                        ? 'border-primary text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {t === 'activos' ? 'Activos' : 'Archivados'}
                            </button>
                        ))}
                    </div>}
                </header>
                <div className="px-3 py-2 border-b border-sidebar-border/50 shrink-0">
                    <div className="relative">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Buscar conversación..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="w-full pl-8 pr-7 py-1.5 text-sm bg-muted/50 border border-input rounded-md placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                        {search && (
                            <button
                                onClick={() => setSearch('')}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                aria-label="Limpiar búsqueda"
                            >
                                <X className="size-3.5" />
                            </button>
                        )}
                    </div>
                </div>
                <div className="flex-1 overflow-y-auto">
                    {loadingArchived ? (
                        <div className="flex items-center justify-center p-8 text-sm text-muted-foreground">
                            Cargando...
                        </div>
                    ) : (
                        <ConversationList
                            conversations={filteredConversations}
                            selectedNumero={selected?.numero ?? null}
                            emptySearch={!!search.trim()}
                            isArchived={tab === 'archivados'}
                            onPin={handlePin}
                            onArchive={handleArchive}
                            onUnarchive={handleUnarchive}
                            onDelete={handleDelete}
                            onImportant={handleImportant}
                        />
                    )}
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
                            onInfoClick={!summaryDesktopVisible ? () => setSummaryDesktopVisible(true) : undefined}
                        />
                    </div>

                    {/* Summary panel — desktop fijo a la derecha */}
                    {summaryDesktopVisible && (
                        <aside className="hidden lg:flex w-72 shrink-0 flex-col">
                            <SessionSummary
                                numero={selected.numero}
                                cliente={selected.cliente}
                                session={liveSession ?? selected.session}
                                onClose={() => setSummaryDesktopVisible(false)}
                            />
                        </aside>
                    )}

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
