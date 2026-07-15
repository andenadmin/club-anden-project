import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

export interface BotMessageOptionRow {
    id: number;
    options_key: string;
    value: string;
    label: string;
    orden: number;
    activo: boolean;
}

interface OptionsConfig {
    style: 'letter' | 'number';
    allowAddRemove: boolean;
    hint?: string;
    metaFields: string[];
}

interface RowState {
    label: string;
    orden: number;
    activo: boolean;
}

function makeRowState(o: BotMessageOptionRow): RowState {
    return { label: o.label, orden: o.orden, activo: o.activo };
}

function OptionRow({
    row,
    onChange,
    onDelete,
    allowDelete,
}: {
    row: RowState;
    onChange: (patch: Partial<RowState>) => void;
    onDelete?: () => void;
    allowDelete?: boolean;
}) {
    return (
        <div className="flex items-center gap-2">
            <input
                type="number"
                min={1}
                value={row.orden}
                onChange={e => onChange({ orden: Number(e.target.value) })}
                className="w-14 shrink-0 text-sm border border-gray-300 rounded-lg px-2 py-1 outline-none focus:ring-2 focus:ring-[#25d366]/40 focus:border-[#25d366] dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-200"
            />
            <input
                type="text"
                value={row.label}
                onChange={e => onChange({ label: e.target.value })}
                className="flex-1 min-w-0 text-sm border border-gray-300 rounded-lg px-3 py-1 outline-none focus:ring-2 focus:ring-[#25d366]/40 focus:border-[#25d366] dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-200"
            />
            <label className="flex items-center gap-1.5 text-xs text-gray-600 dark:text-neutral-400 shrink-0 select-none">
                <input type="checkbox" checked={row.activo} onChange={e => onChange({ activo: e.target.checked })} className="size-4" />
                Activa
            </label>
            {allowDelete && (
                <button
                    type="button"
                    onClick={onDelete}
                    title="Eliminar opción"
                    className="shrink-0 text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                >
                    <svg className="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            )}
        </div>
    );
}

export function OptionsEditor({ options, config }: { options: BotMessageOptionRow[]; config: OptionsConfig | null }) {
    const [rows, setRows] = useState<Record<number, RowState>>(() =>
        Object.fromEntries(options.map(o => [o.id, makeRowState(o)])),
    );
    const [saving, setSaving]       = useState(false);
    const [saved, setSaved]         = useState(false);
    const [newLabel, setNewLabel]   = useState('');
    const [adding, setAdding]       = useState(false);
    const [showAdd, setShowAdd]     = useState(false);

    useEffect(() => {
        setRows(Object.fromEntries(options.map(o => [o.id, makeRowState(o)])));
    }, [options]);

    if (!config) return null;

    const dirty = options.some(o => {
        const r = rows[o.id];
        return r && (r.label !== o.label || r.orden !== o.orden || r.activo !== o.activo);
    });

    const patch = (id: number, p: Partial<RowState>) => {
        setRows(prev => ({ ...prev, [id]: { ...prev[id], ...p } }));
    };

    const saveAll = () => {
        setSaving(true);
        router.put(
            '/bot/message-options',
            { options: options.map(o => ({ id: o.id, ...rows[o.id] })) },
            {
                preserveScroll: true,
                onSuccess: () => { setSaved(true); setTimeout(() => setSaved(false), 2000); },
                onFinish: () => setSaving(false),
            },
        );
    };

    const addOption = () => {
        if (!newLabel.trim() || !options[0]) return;
        setAdding(true);
        router.post(
            '/bot/message-options',
            { options_key: options[0].options_key, label: newLabel.trim() },
            {
                preserveScroll: true,
                onSuccess: () => { setNewLabel(''); setShowAdd(false); },
                onFinish: () => setAdding(false),
            },
        );
    };

    const deleteOption = (id: number) => {
        if (!confirm('¿Eliminás esta opción?')) return;
        router.delete(`/bot/message-options/${id}`, { preserveScroll: true });
    };

    const sorted = [...options].sort((a, b) => a.orden - b.orden);

    return (
        <div className="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-neutral-700">
            <p className="text-[11px] font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wide mb-2">
                Opciones de este mensaje
            </p>
            <div className="space-y-2">
                {sorted.map(option => (
                    <OptionRow
                        key={option.id}
                        row={rows[option.id] ?? makeRowState(option)}
                        onChange={p => patch(option.id, p)}
                        allowDelete={config.allowAddRemove}
                        onDelete={() => deleteOption(option.id)}
                    />
                ))}
            </div>

            {config.allowAddRemove && (
                <div className="mt-3">
                    {showAdd ? (
                        <div className="flex items-center gap-2">
                            <input
                                type="text"
                                autoFocus
                                value={newLabel}
                                onChange={e => setNewLabel(e.target.value)}
                                onKeyDown={e => { if (e.key === 'Enter') addOption(); if (e.key === 'Escape') { setShowAdd(false); setNewLabel(''); } }}
                                placeholder="Texto de la nueva opción…"
                                className="flex-1 min-w-0 text-sm border border-gray-300 rounded-lg px-3 py-1 outline-none focus:ring-2 focus:ring-[#25d366]/40 focus:border-[#25d366] dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-200"
                            />
                            <button
                                type="button"
                                onClick={addOption}
                                disabled={adding || !newLabel.trim()}
                                className="shrink-0 text-xs font-semibold text-white bg-[#075e54] rounded-lg px-3 py-1.5 hover:bg-[#0a7060] transition-colors disabled:opacity-50"
                            >
                                {adding ? 'Agregando…' : 'Agregar'}
                            </button>
                            <button
                                type="button"
                                onClick={() => { setShowAdd(false); setNewLabel(''); }}
                                className="shrink-0 text-xs text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                            >
                                Cancelar
                            </button>
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={() => setShowAdd(true)}
                            className="flex items-center gap-1 text-xs text-[#075e54] hover:text-[#0a7060] dark:text-[#25d366] dark:hover:text-[#1ebd5a] font-medium transition-colors"
                        >
                            <svg className="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                            </svg>
                            Agregar opción
                        </button>
                    )}
                </div>
            )}

            <div className="flex items-center gap-2 mt-3">
                <button
                    onClick={saveAll}
                    disabled={saving || !dirty}
                    className="text-xs font-semibold text-white bg-[#075e54] rounded-lg px-4 py-1.5 hover:bg-[#0a7060] transition-colors disabled:opacity-50"
                >
                    {saving ? 'Guardando…' : saved ? '✓ Guardado' : 'Guardar cambios'}
                </button>
            </div>

            {config.hint && (
                <p className="text-[11px] text-amber-600 dark:text-amber-400 mt-2 leading-relaxed">
                    ⚠️ {config.hint}
                </p>
            )}
            {!config.allowAddRemove && (
                <p className="text-[11px] text-gray-400 dark:text-neutral-500 mt-2">
                    Podés renombrar, reordenar y activar/desactivar estas opciones. No se pueden agregar ni quitar
                    porque cada una dispara una parte distinta del bot.
                </p>
            )}
        </div>
    );
}
