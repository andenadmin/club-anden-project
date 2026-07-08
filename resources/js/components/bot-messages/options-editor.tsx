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

function OptionRow({ option }: { option: BotMessageOptionRow }) {
    const [label, setLabel]   = useState(option.label);
    const [orden, setOrden]   = useState(option.orden);
    const [activo, setActivo] = useState(option.activo);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved]   = useState(false);
    const dirty = label !== option.label || orden !== option.orden || activo !== option.activo;

    // Sincronizar con el valor real guardado cuando el server recarga los props.
    useEffect(() => {
        setLabel(option.label);
        setOrden(option.orden);
        setActivo(option.activo);
    }, [option.label, option.orden, option.activo]);

    const save = () => {
        setSaving(true);
        router.put(
            `/bot/message-options/${option.id}`,
            { label, orden, activo },
            {
                preserveScroll: true,
                onSuccess: () => { setSaved(true); setTimeout(() => setSaved(false), 2000); },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="flex items-center gap-2">
            <input
                type="number"
                min={1}
                value={orden}
                onChange={e => setOrden(Number(e.target.value))}
                className="w-14 shrink-0 text-sm border border-gray-300 rounded-lg px-2 py-1 outline-none focus:ring-2 focus:ring-[#25d366]/40 focus:border-[#25d366] dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-200"
            />
            <input
                type="text"
                value={label}
                onChange={e => setLabel(e.target.value)}
                className="flex-1 min-w-0 text-sm border border-gray-300 rounded-lg px-3 py-1 outline-none focus:ring-2 focus:ring-[#25d366]/40 focus:border-[#25d366] dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-200"
            />
            <label className="flex items-center gap-1.5 text-xs text-gray-600 dark:text-neutral-400 shrink-0 select-none">
                <input type="checkbox" checked={activo} onChange={e => setActivo(e.target.checked)} className="size-4" />
                Activa
            </label>
            <button
                onClick={save}
                disabled={saving || !dirty}
                className="shrink-0 text-xs font-semibold text-white bg-[#075e54] rounded-lg px-3 py-1 hover:bg-[#0a7060] transition-colors disabled:opacity-50"
            >
                {saving ? '…' : saved ? '✓' : 'Guardar'}
            </button>
        </div>
    );
}

export function OptionsEditor({ options, config }: { options: BotMessageOptionRow[]; config: OptionsConfig | null }) {
    if (!config) return null;

    const sorted = [...options].sort((a, b) => a.orden - b.orden);

    return (
        <div className="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-neutral-700">
            <p className="text-[11px] font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wide mb-2">
                Opciones de este mensaje
            </p>
            <div className="space-y-2">
                {sorted.map(option => (
                    <OptionRow key={option.id} option={option} />
                ))}
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
