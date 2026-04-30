import { useEffect, useRef, useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { dashboard } from '@/routes';

// ─── Tipos ────────────────────────────────────────────────────────────────────
interface Msg { role: 'user' | 'bot'; text: string; ts: Date }
interface Session { estado_actual: string; rama_activa: string | null; subtipo_activo: string | null; current_step: string | null }

// ─── Utils ────────────────────────────────────────────────────────────────────
const fmt = (d: Date) => d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });

function waMd(raw: string) {
    return raw
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\*(.*?)\*/g, '<strong>$1</strong>');
}

function csrf() {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

async function postJson(url: string, body: object) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json();
}

// ─── Componentes de burbuja ────────────────────────────────────────────────────
function BotBubble({ text, ts }: { text: string; ts: Date }) {
    return (
        <div className="flex items-end gap-2 max-w-[78%]">
            <div className="size-8 rounded-full bg-[#25d366] flex items-center justify-center text-white text-xs font-bold shrink-0 shadow">A</div>
            <div className="bg-white rounded-2xl rounded-bl-sm px-3.5 py-2 shadow-sm">
                <pre className="text-sm text-gray-800 whitespace-pre-wrap font-sans leading-relaxed break-words"
                    dangerouslySetInnerHTML={{ __html: waMd(text) }} />
                <p className="text-[10px] text-gray-400 text-right mt-0.5">{fmt(ts)}</p>
            </div>
        </div>
    );
}

function UserBubble({ text, ts }: { text: string; ts: Date }) {
    return (
        <div className="flex items-end gap-2 max-w-[78%] self-end flex-row-reverse">
            <div className="size-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-[10px] font-bold shrink-0 shadow">Vos</div>
            <div className="bg-[#dcf8c6] rounded-2xl rounded-br-sm px-3.5 py-2 shadow-sm">
                <p className="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed break-words">{text}</p>
                <p className="text-[10px] text-gray-400 text-right mt-0.5">{fmt(ts)}</p>
            </div>
        </div>
    );
}

function Typing() {
    return (
        <div className="flex items-end gap-2">
            <div className="size-8 rounded-full bg-[#25d366] flex items-center justify-center text-white text-xs font-bold shrink-0">A</div>
            <div className="bg-white rounded-2xl rounded-bl-sm px-4 py-3 shadow-sm">
                <div className="flex gap-1">
                    {[0,150,300].map(d => (
                        <span key={d} className="size-2 bg-gray-300 rounded-full animate-bounce" style={{ animationDelay: `${d}ms` }} />
                    ))}
                </div>
            </div>
        </div>
    );
}

// ─── Página principal ─────────────────────────────────────────────────────────
const FROM = '5491100000001';

export default function BotSimulator() {
    const [msgs, setMsgs]       = useState<Msg[]>([]);
    const [loading, setLoading] = useState(false);
    const [session, setSession] = useState<Session | null>(null);
    const inputRef              = useRef<HTMLInputElement>(null);
    const bottomRef             = useRef<HTMLDivElement>(null);

    useEffect(() => { bottomRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [msgs, loading]);
    useEffect(() => { if (!loading) inputRef.current?.focus(); }, [loading]);

    const send = async (override?: string) => {
        const msg = (override ?? inputRef.current?.value ?? '').trim();
        if (!msg || loading) return;
        if (inputRef.current) inputRef.current.value = '';

        setMsgs(p => [...p, { role: 'user', text: msg, ts: new Date() }]);
        setLoading(true);

        try {
            const data = await postJson('/bot/message', { numero_contacto: FROM, message: msg });
            const bots: Msg[] = (data.messages ?? []).map((t: string) => ({ role: 'bot' as const, text: t, ts: new Date() }));
            setMsgs(p => [...p, ...bots]);
            if (data.session_state) setSession(data.session_state);
        } catch (e) {
            setMsgs(p => [...p, { role: 'bot' as const, text: `⚠️ Error: ${e instanceof Error ? e.message : 'desconocido'}`, ts: new Date() }]);
        } finally {
            setLoading(false);
        }
    };

    const reset = async () => {
        try { await postJson('/bot/reset', { numero_contacto: FROM }); } catch { /* ignore */ }
        setMsgs([]);
        setSession(null);
        if (inputRef.current) inputRef.current.value = '';
        inputRef.current?.focus();
    };

    const quickReplies =
        session?.estado_actual === 'MENU_PRINCIPAL' ? ['A','B','C','0'] :
        session?.estado_actual === 'CONFIRMACION'   ? ['SI','CAMBIAR','0'] : [];

    return (
        <div className="h-svh overflow-hidden">
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
            <div className="flex flex-col h-full overflow-hidden">

                {/* ── Header verde estilo WhatsApp ─── */}
                <header className="flex items-center justify-between px-3 h-12 bg-[#075e54] text-white shrink-0 shadow transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                    <div className="flex items-center gap-2">
                        <div className="flex items-center gap-2.5">
                            <div className="size-7 rounded-full bg-[#25d366] flex items-center justify-center text-white text-xs font-bold shadow">A</div>
                            <div>
                                <p className="text-white text-sm font-semibold leading-tight">Andy</p>
                                <p className={`text-[10px] ${loading ? 'text-yellow-200' : 'text-green-200'}`}>
                                    {loading ? 'escribiendo…' : '● en línea'}
                                </p>
                            </div>
                        </div>
                    </div>
                    <span className="text-xs text-[#a8d8d1]">El Anden 🌿</span>
                </header>

                {/* ── Cuerpo ─────────────────────────── */}
                <div className="flex flex-1 overflow-hidden">

                    {/* Panel izquierdo: sesión */}
                    <aside className="w-52 shrink-0 flex flex-col bg-white border-r">
                        <div className="flex-1 p-4 overflow-y-auto space-y-3">
                            <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400">Sesión</p>
                            {session ? (
                                <>
                                    {[
                                        ['Estado', session.estado_actual],
                                        ['Rama', session.rama_activa],
                                        ['Subtipo', session.subtipo_activo],
                                        ['Paso', session.current_step],
                                    ].map(([label, val]) => val ? (
                                        <div key={label}>
                                            <p className="text-[9px] uppercase tracking-wider text-gray-400 mb-0.5">{label}</p>
                                            <p className="text-xs font-medium bg-emerald-50 text-emerald-700 rounded px-2 py-1">{val}</p>
                                        </div>
                                    ) : null)}
                                </>
                            ) : (
                                <p className="text-xs text-gray-400 italic">Sin sesión activa</p>
                            )}
                        </div>
                        <div className="p-3 border-t">
                            <button onClick={reset}
                                className="w-full text-xs text-red-500 border border-red-200 rounded-lg py-1.5 hover:bg-red-50 transition-colors flex items-center justify-center gap-1.5">
                                <svg className="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Reiniciar
                            </button>
                        </div>
                    </aside>

                    {/* Chat */}
                    <div className="flex-1 flex flex-col min-w-0" style={{ background: '#e5ddd5' }}>

                        {/* Mensajes */}
                        <div className="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-3">
                            {msgs.length === 0 && (
                                <div className="flex flex-1 flex-col items-center justify-center gap-3 select-none text-center">
                                    <div className="size-16 rounded-full bg-[#075e54] flex items-center justify-center text-3xl shadow-lg">🌿</div>
                                    <div>
                                        <p className="font-semibold text-gray-600">Bot El Anden</p>
                                        <p className="text-sm text-gray-500 mt-0.5">Escribí cualquier mensaje para iniciar</p>
                                    </div>
                                </div>
                            )}
                            {msgs.map((m, i) =>
                                m.role === 'bot'
                                    ? <BotBubble key={i} text={m.text} ts={m.ts} />
                                    : <UserBubble key={i} text={m.text} ts={m.ts} />
                            )}
                            {loading && <Typing />}
                            <div ref={bottomRef} />
                        </div>

                        {/* Quick replies */}
                        {quickReplies.length > 0 && (
                            <div className="flex gap-2 px-4 py-2 bg-white/70 backdrop-blur border-t border-white/50 overflow-x-auto shrink-0">
                                {quickReplies.map(r => (
                                    <button key={r} onClick={() => send(r)} disabled={loading}
                                        className="shrink-0 text-xs font-semibold text-[#075e54] border border-[#075e54] rounded-full px-4 py-1.5 hover:bg-[#075e54] hover:text-white transition-colors disabled:opacity-40">
                                        {r}
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Input */}
                        <div className="flex items-center gap-2 px-3 py-2.5 bg-[#f0f2f5] shrink-0">
                            <input
                                ref={inputRef}
                                onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } }}
                                placeholder="Escribí un mensaje…"
                                disabled={loading}
                                autoFocus
                                className="flex-1 rounded-full bg-white border border-gray-200 px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 outline-none focus:ring-2 focus:ring-[#25d366]/40 shadow-sm disabled:opacity-60"
                            />
                            <button
                                onClick={() => send()}
                                disabled={loading}
                                className="size-10 rounded-full bg-[#25d366] flex items-center justify-center text-white shadow hover:bg-[#1ebe5d] active:scale-95 transition-all disabled:opacity-50 shrink-0">
                                <svg className="size-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
            </AppContent>
        </AppShell>
        </div>
    );
}
