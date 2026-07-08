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

function OptionRow({ row, onChange }: { row: RowState; onChange: (patch: Partial<RowState>) => void }) {
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
        </div>
    );
}

export function OptionsEditor({ options, config }: { options: BotMessageOptionRow[]; config: OptionsConfig | null }) {
    const [rows, setRows] = useState<Record<number, RowState>>(() =>
        Object.fromEntries(options.map(o => [o.id, makeRowState(o)])),
    );
    const [saving, setSaving] = useState(false);
    const [saved, setSaved]   = useState(false);

    // Sincronizar con los valores reales guardados cuando el server recarga los props
    // (por ejemplo después de guardar, o si se editó desde otra pestaña).
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

    // Ordenado por la posición ORIGINAL (no la que se está tipeando), así las filas
    // no saltan de lugar mientras se edita el número de orden.
    const sorted = [...options].sort((a, b) => a.orden - b.orden);

    return (
        <div className="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-neutral-700">
            <p className="text-[11px] font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wide mb-2">
                Opciones de este mensaje
            </p>
            <div className="space-y-2">
                {sorted.map(option => (
                    <OptionRow key={option.id} row={rows[option.id] ?? makeRowState(option)} onChange={p => patch(option.id, p)} />
                ))}
            </div>
            <div className="flex items-center gap-2 mt-3">
                <button
                    onClick={saveAll}
                    disabled={saving || !dirty}
                    className="text-xs font-semibold text-white bg-[#075e54] rounded-lg px-4 py-1.5 hover:bg-[#0a7060] transition-colors disabled:opacity-50"
                >
                    {saving ? 'Guardando…' : saved ? '✓ Guardado' : 'Guardar cambios'}
                </button>
            </div>
            {!config.allowAddRemove && (
                <p className="text-[11px] text-gray-400 dark:text-neutral-500 mt-2">
                    Podés renombrar, reordenar y activar/desactivar estas opciones. No se pueden agregar ni quitar
                    porque cada una dispara una parte distinta del bot.
                </p>
            )}
        </div>
    );
}
