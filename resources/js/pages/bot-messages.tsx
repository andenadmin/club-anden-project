import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Archive, Pencil } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import botMessages from '@/routes/bot/messages';
import { OptionsEditor, type BotMessageOptionRow } from '@/components/bot-messages/options-editor';

interface BotMessage {
    id: number;
    key: string;
    category: string;
    label: string;
    content: string;
    is_archived: boolean;
    default_content: string | null;
    options_key: string | null;
    options_config: { style: 'letter' | 'number'; allowAddRemove: boolean; metaFields: string[] } | null;
    options: BotMessageOptionRow[] | null;
}

interface RestaurantSector {
    id: number;
    key: string;
    label: string;
    orden: number;
    activo: boolean;
    requiere_capacidad: boolean;
}

interface Props {
    messages: BotMessage[];
    archived: BotMessage[];
    sectores: RestaurantSector[];
}

const CATEGORIES = [
    { key: 'general',     label: 'General',     color: 'bg-slate-100 text-slate-700 border-slate-300' },
    { key: 'deportes',    label: 'Deportes',    color: 'bg-green-100 text-green-700 border-green-300' },
    { key: 'restaurante', label: 'Restaurante', color: 'bg-orange-100 text-orange-700 border-orange-300' },
    { key: 'eventos',     label: 'Eventos',     color: 'bg-purple-100 text-purple-700 border-purple-300' },
];

const VARS_HINT: Record<string, string[]> = {
    MSG_BIENVENIDA_CONOCIDO:     ['{{nombre}}'],
    MSG_REGISTRO_BIENVENIDA:     ['{{nombre}}'],
    MSG_RES_CONFIRMACION:        ['{{resumen}}'],
    MSG_RES_CONFIRMACION_FUTURA: ['{{resumen}}'],
    MSG_CONFIRMACION:            ['{{resumen}}'],
    MSG_CONFIRMAR_MAIL:          ['{{mail}}'],
    MSG_EVT_03_ENTERO:           ['{{rango_horario}}'],
    MSG_EVT_COSTO_MENU:          ['{{numero_ninos}}', '{{pack_label}}', '{{costo_menu_calculado}}'],
    MSG_EVT_ADULTOS:             ['{{precio_menu_adulto}}'],
    MSG_EVT_MENU_ADULTOS:        ['{{numero_adultos}}'],
    MSG_EVT_ADICIONAL_QTY:       ['{{item_name}}'],
};

const LS_KEY = 'bot_messages_collapsed';

function readCollapsed(): Set<number> {
    try {
        const raw = localStorage.getItem(LS_KEY);
        return raw ? new Set(JSON.parse(raw) as number[]) : new Set();
    } catch {
        return new Set();
    }
}

function writeCollapsed(set: Set<number>) {
    localStorage.setItem(LS_KEY, JSON.stringify([...set]));
}

// ─── Card ────────────────────────────────────────────────────────────────────
interface CardProps {
    msg: BotMessage;
    collapsed: boolean;
    onToggleCollapse: () => void;
}

function MessageCard({ msg, collapsed, onToggleCollapse }: CardProps) {
    const [content, setContent] = useState(msg.content);
    const [saving, setSaving]   = useState(false);
    const [saved, setSaved]     = useState(false);
    const [editing, setEditing] = useState(false);
    const dirty = content !== msg.content;

    const save = () => {
        setSaving(true);
        router.put(
            botMessages.update(msg.id).url,
            { content },
            {
                preserveScroll: true,
                onSuccess: () => { setSaved(true); setEditing(false); setTimeout(() => setSaved(false), 2000); },
                onFinish: () => setSaving(false),
            },
        );
    };

    const archive = () => {
        router.patch(`/bot/messages/${msg.id}/archive`, {}, { preserveScroll: true });
    };

    const resetDefault = () => {
        if (!window.confirm('¿Restaurar este mensaje al texto por defecto del sistema? Se perderán los cambios actuales.')) return;
        router.patch(`/bot/messages/${msg.id}/reset-default`, {}, { preserveScroll: true });
    };

    const hints = VARS_HINT[msg.key] ?? [];

    return (
        <div className="border border-sidebar-border/70 rounded-xl bg-white overflow-hidden shadow-sm dark:bg-neutral-900 dark:border-neutral-700">

            {/* Header — always visible */}
            <div className="flex items-center gap-3 px-4 py-3 bg-gray-50 border-b border-sidebar-border/70 dark:bg-neutral-800 dark:border-neutral-700">
                <button
                    onClick={onToggleCollapse}
                    className="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-neutral-200 transition-colors"
                    title={collapsed ? 'Expandir' : 'Contraer'}
                >
                    <svg className={`size-4 transition-transform duration-200 ${collapsed ? '-rotate-90' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-800 dark:text-neutral-100 truncate">{msg.label}</p>
                    {collapsed && (
                        <p className="text-[11px] text-gray-600 dark:text-neutral-400 truncate mt-0.5">
                            {content.slice(0, 80).replace(/\n/g, ' ')}…
                        </p>
                    )}
                </div>

                {!collapsed && !editing && (
                    <div className="flex items-center gap-2 shrink-0">
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        onClick={() => setEditing(true)}
                                        className="text-xs font-semibold text-white bg-[#075e54] rounded-lg px-3 py-1 hover:bg-[#0a7060] transition-colors flex items-center gap-1.5"
                                    >
                                        <Pencil className="size-3" />
                                        Editar
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>Editar mensaje</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        {msg.default_content !== null && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <button
                                            onClick={resetDefault}
                                            className="text-xs font-semibold text-black bg-amber-400 rounded-lg px-3 py-1 hover:bg-amber-500 transition-colors"
                                        >
                                            ↩ Revertir al original
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>Descartar cambios y volver al texto original del sistema</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        onClick={archive}
                                        className="text-xs font-semibold text-black bg-red-400 rounded-lg px-3 py-1 hover:bg-red-500 transition-colors flex items-center gap-1.5"
                                    >
                                        <Archive className="size-3" />
                                        Archivar
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>Archivar mensaje (se puede restaurar)</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                )}
            </div>

            {/* Body — hidden when collapsed */}
            {!collapsed && (
                <>
                    <div className="px-4 py-3">
                        {hints.length > 0 && (
                            <div className="flex flex-wrap gap-1.5 mb-2">
                                {hints.map(v => (
                                    <span key={v} className="text-[10px] font-mono bg-amber-50 text-amber-700 border border-amber-200 rounded px-1.5 py-0.5">
                                        {v}
                                    </span>
                                ))}
                            </div>
                        )}
                        {editing ? (
                            <textarea
                                value={content}
                                onChange={e => setContent(e.target.value)}
                                rows={Math.max(4, content.split('\n').length + 1)}
                                className="w-full text-sm font-mono text-gray-800 dark:text-neutral-200 border border-gray-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-[#25d366]/40 focus:border-[#25d366] resize-y leading-relaxed dark:bg-neutral-800 dark:border-neutral-600"
                            />
                        ) : (
                            <pre className="text-sm text-gray-700 dark:text-neutral-300 whitespace-pre-wrap font-sans leading-relaxed">
                                {content}
                            </pre>
                        )}
                        {msg.options && <OptionsEditor options={msg.options} config={msg.options_config} />}
                    </div>

                    {editing && (
                        <div className="flex items-center justify-end gap-2 px-4 py-2.5 bg-gray-50 border-t border-sidebar-border/70 dark:bg-neutral-800 dark:border-neutral-700">
                            <button
                                onClick={() => { setContent(msg.content); setEditing(false); }}
                                className="text-xs text-gray-500 border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-100 transition-colors dark:border-neutral-600 dark:hover:bg-neutral-700 dark:text-neutral-400"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={save}
                                disabled={saving || !dirty}
                                className="text-xs font-semibold text-white bg-[#075e54] rounded-lg px-4 py-1.5 hover:bg-[#0a7060] transition-colors disabled:opacity-50"
                            >
                                {saving ? 'Guardando…' : saved ? '✓ Guardado' : 'Guardar'}
                            </button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

// ─── Sector Row ───────────────────────────────────────────────────────────────
function SectorRow({ sector }: { sector: RestaurantSector }) {
    const [label, setLabel]   = useState(sector.label);
    const [orden, setOrden]   = useState(sector.orden);
    const [activo, setActivo] = useState(sector.activo);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved]   = useState(false);
    const dirty = label !== sector.label || orden !== sector.orden || activo !== sector.activo;

    const save = () => {
        setSaving(true);
        router.put(
            `/bot/sectores/${sector.id}`,
            { label, orden, activo },
            {
                preserveScroll: true,
                onSuccess: () => { setSaved(true); setTimeout(() => setSaved(false), 2000); },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="flex items-center gap-3 border border-sidebar-border/70 rounded-xl bg-white px-4 py-3 shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
            <div className="w-20 shrink-0">
                <label className="block text-[10px] text-gray-400 dark:text-neutral-500 mb-1">Orden</label>
                <input
                    type="number"
                    min={1}
                    value={orden}
                    onChange={e => setOrden(Number(e.target.value))}
                    className="w-full text-sm border border-gray-300 rounded-lg px-2 py-1 outline-none focus:ring-2 focus:ring-[#25d366]/40 focus:border-[#25d366] dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-200"
                />
            </div>

            <div className="flex-1 min-w-0">
                <label className="block text-[10px] text-gray-400 dark:text-neutral-500 mb-1">
                    Nombre que ve el cliente {!sector.requiere_capacidad && <span className="opacity-60">(sin control de cupo)</span>}
                </label>
                <input
                    type="text"
                    value={label}
                    onChange={e => setLabel(e.target.value)}
                    className="w-full text-sm border border-gray-300 rounded-lg px-3 py-1 outline-none focus:ring-2 focus:ring-[#25d366]/40 focus:border-[#25d366] dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-200"
                />
            </div>

            <label className="flex items-center gap-2 text-xs text-gray-600 dark:text-neutral-400 shrink-0 select-none">
                <input type="checkbox" checked={activo} onChange={e => setActivo(e.target.checked)} className="size-4" />
                Activo
            </label>

            <button
                onClick={save}
                disabled={saving || !dirty}
                className="shrink-0 text-xs font-semibold text-white bg-[#075e54] rounded-lg px-4 py-1.5 hover:bg-[#0a7060] transition-colors disabled:opacity-50"
            >
                {saving ? 'Guardando…' : saved ? '✓ Guardado' : 'Guardar'}
            </button>
        </div>
    );
}

// ─── Archived Card ────────────────────────────────────────────────────────────
function ArchivedCard({ msg }: { msg: BotMessage }) {
    const [expanded, setExpanded] = useState(false);

    const restore = () => {
        router.patch(`/bot/messages/${msg.id}/restore`, {}, { preserveScroll: true });
    };

    const catColor = CATEGORIES.find(c => c.key === msg.category)?.color ?? 'bg-gray-100 text-gray-500 border-gray-200';

    return (
        <div className="border border-dashed border-gray-300 rounded-xl bg-gray-50 overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
            <div className="flex items-center gap-3 px-4 py-3">
                <button
                    onClick={() => setExpanded(v => !v)}
                    className="shrink-0 text-gray-400 hover:text-gray-600 transition-colors"
                >
                    <svg className={`size-4 transition-transform duration-200 ${expanded ? '' : '-rotate-90'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-500 dark:text-neutral-400 truncate">{msg.label}</p>
                    <span className={`inline-block text-[10px] font-medium rounded-full px-2 py-0.5 border mt-0.5 ${catColor}`}>
                        {msg.category}
                    </span>
                </div>

                <button
                    onClick={restore}
                    className="shrink-0 text-xs font-semibold text-white bg-[#075e54] rounded-lg px-3 py-1 hover:bg-[#0a7060] transition-colors"
                >
                    Restaurar
                </button>
            </div>

            {expanded && (
                <div className="px-4 pb-3 border-t border-gray-200 dark:border-neutral-700 pt-3">
                    <pre className="text-sm text-gray-500 dark:text-neutral-500 whitespace-pre-wrap font-sans leading-relaxed">
                        {msg.content}
                    </pre>
                </div>
            )}
        </div>
    );
}

// ─── Page ────────────────────────────────────────────────────────────────────
export default function BotMessagesPage({ messages, archived, sectores }: Props) {
    const [activeTab, setActiveTab] = useState('general');
    const [collapsed, setCollapsed] = useState<Set<number>>(readCollapsed);

    const toggleCollapse = (id: number) => {
        setCollapsed(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            writeCollapsed(next);
            return next;
        });
    };

    const isArchived = activeTab === '__archived__';
    const isSectores = activeTab === '__sectores__';
    const filtered   = isArchived ? archived : messages.filter(m => m.category === activeTab);

    return (
        <>
            <Head title="Mensajes del Bot" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-y-auto p-4">

                {/* Tabs */}
                <div className="flex gap-2 flex-wrap max-w-3xl mx-auto w-full">
                    {CATEGORIES.map(cat => {
                        const count = messages.filter(m => m.category === cat.key).length;
                        return (
                            <button
                                key={cat.key}
                                onClick={() => setActiveTab(cat.key)}
                                className={`text-sm font-medium rounded-full px-4 py-1.5 border transition-colors ${
                                    activeTab === cat.key
                                        ? cat.color + ' shadow-sm'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-600'
                                }`}
                            >
                                {cat.label}
                                <span className="ml-1.5 text-[11px] opacity-60">({count})</span>
                            </button>
                        );
                    })}

                    {/* Tab Sectores */}
                    <button
                        onClick={() => setActiveTab('__sectores__')}
                        className={`text-sm font-medium rounded-full px-4 py-1.5 border transition-colors ${
                            isSectores
                                ? 'bg-orange-100 text-orange-700 border-orange-300 shadow-sm'
                                : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-600'
                        }`}
                    >
                        Sectores (Restaurante)
                        <span className="ml-1.5 text-[11px] opacity-60">({sectores.length})</span>
                    </button>

                    {/* Tab Archivados */}
                    <button
                        onClick={() => setActiveTab('__archived__')}
                        className={`text-sm font-medium rounded-full px-4 py-1.5 border transition-colors ${
                            isArchived
                                ? 'bg-gray-200 text-gray-700 border-gray-400 shadow-sm'
                                : 'bg-white text-gray-400 border-gray-200 hover:border-gray-300 dark:bg-neutral-800 dark:text-neutral-500 dark:border-neutral-600'
                        }`}
                    >
                        Archivados
                        {archived.length > 0 && (
                            <span className="ml-1.5 text-[11px] opacity-60">({archived.length})</span>
                        )}
                    </button>
                </div>

                {/* Cards */}
                <div className="space-y-3 max-w-3xl mx-auto w-full pb-6">
                    {isSectores ? (
                        <>
                            <p className="text-xs text-gray-500 dark:text-neutral-400 -mt-1 mb-1">
                                El nombre de cada sector es libre — cambialo cuando quieras, la letra (A, B, C…) se recalcula sola
                                según el orden. Desactivá un sector para que deje de ofrecerse como opción.
                            </p>
                            {sectores.map(sector => (
                                <SectorRow key={sector.id} sector={sector} />
                            ))}
                        </>
                    ) : isArchived ? (
                        filtered.length === 0 ? (
                            <p className="text-sm text-gray-400 text-center py-8">No hay mensajes archivados.</p>
                        ) : (
                            (filtered as BotMessage[]).map(msg => (
                                <ArchivedCard key={msg.id} msg={msg} />
                            ))
                        )
                    ) : (
                        (filtered as BotMessage[]).map(msg => (
                            <MessageCard
                                key={msg.id}
                                msg={msg}
                                collapsed={collapsed.has(msg.id)}
                                onToggleCollapse={() => toggleCollapse(msg.id)}
                            />
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

BotMessagesPage.layout = {
    breadcrumbs: [
        { title: 'Bot Simulator', href: '/bot' },
        { title: 'Mensajes del Bot', href: '/bot/messages' },
    ],
};
