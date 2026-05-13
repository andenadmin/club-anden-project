import { useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';

interface CostoEvento {
    id: number;
    concepto: string;
    descripcion: string;
    precio: string;
    updated_at: string;
}

interface Props {
    precios: CostoEvento[];
    flash?: { success?: string };
}

const GROUPS = [
    {
        label: 'Menús por Pack (precio por niño)',
        color: 'bg-purple-100 text-purple-700 border-purple-300',
        conceptos: ['pack_1_menu', 'pack_2_menu', 'pack_3_menu', 'pack_4_menu'],
    },
    {
        label: 'Infraestructura',
        color: 'bg-blue-100 text-blue-700 border-blue-300',
        conceptos: ['cancha', 'coordinador'],
    },
    {
        label: 'Menú Adultos',
        color: 'bg-orange-100 text-orange-700 border-orange-300',
        conceptos: ['menu_adulto'],
    },
    {
        label: 'Adicionales',
        color: 'bg-green-100 text-green-700 border-green-300',
        conceptos: ['adicional_papas', 'adicional_sandwiches', 'adicional_frutas', 'adicional_helados'],
    },
];

function formatDate(iso: string): string {
    try {
        return new Date(iso).toLocaleDateString('es-AR', {
            day: '2-digit', month: '2-digit', year: '2-digit',
            hour: '2-digit', minute: '2-digit',
        });
    } catch {
        return iso;
    }
}

function formatPrecio(valor: string): string {
    return '$' + Number(valor).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// ─── Fila editable ───────────────────────────────────────────────────────────
function PrecioRow({ item }: { item: CostoEvento }) {
    const [editing, setEditing] = useState(false);
    const [valor, setValor]     = useState(String(Math.round(Number(item.precio))));
    const [saving, setSaving]   = useState(false);
    const [saved, setSaved]     = useState(false);
    const inputRef              = useRef<HTMLInputElement>(null);

    const startEdit = () => {
        setEditing(true);
        setTimeout(() => inputRef.current?.select(), 30);
    };

    const cancel = () => {
        setValor(String(Math.round(Number(item.precio))));
        setEditing(false);
    };

    const save = () => {
        if (isNaN(Number(valor)) || Number(valor) < 0) return;
        setSaving(true);
        router.patch(
            `/bot/precios/${item.id}`,
            { precio: Number(valor) },
            {
                preserveScroll: true,
                onSuccess: () => { setSaved(true); setEditing(false); setTimeout(() => setSaved(false), 2000); },
                onFinish:  () => setSaving(false),
            },
        );
    };

    const handleKey = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter')  save();
        if (e.key === 'Escape') cancel();
    };

    return (
        <tr className="border-b border-gray-100 dark:border-neutral-800 hover:bg-gray-50 dark:hover:bg-neutral-800/50 transition-colors">
            <td className="px-4 py-3 text-sm text-gray-700 dark:text-neutral-300">{item.descripcion}</td>
            <td className="px-4 py-3 text-xs text-gray-400 dark:text-neutral-500 font-mono">{item.concepto}</td>
            <td className="px-4 py-3 text-right">
                {editing ? (
                    <div className="flex items-center justify-end gap-2">
                        <span className="text-sm text-gray-500">$</span>
                        <input
                            ref={inputRef}
                            type="number"
                            min={0}
                            value={valor}
                            onChange={e => setValor(e.target.value)}
                            onKeyDown={handleKey}
                            className="w-28 text-sm text-right border border-[#25d366] rounded-lg px-2 py-1 outline-none focus:ring-2 focus:ring-[#25d366]/30 dark:bg-neutral-800 dark:border-[#25d366] dark:text-neutral-200"
                        />
                        <button
                            onClick={save}
                            disabled={saving}
                            className="text-xs font-semibold text-white bg-[#075e54] rounded-lg px-3 py-1 hover:bg-[#0a7060] transition-colors disabled:opacity-50"
                        >
                            {saving ? '…' : '✓'}
                        </button>
                        <button
                            onClick={cancel}
                            className="text-xs text-gray-400 hover:text-gray-600 px-1"
                        >
                            ✕
                        </button>
                    </div>
                ) : (
                    <button
                        onClick={startEdit}
                        className="group flex items-center gap-2 ml-auto"
                    >
                        <span className={`text-sm font-semibold tabular-nums ${saved ? 'text-[#075e54]' : 'text-gray-800 dark:text-neutral-200'}`}>
                            {formatPrecio(valor)}
                        </span>
                        <span className="text-[10px] text-gray-300 group-hover:text-[#075e54] transition-colors">editar</span>
                    </button>
                )}
            </td>
            <td className="px-4 py-3 text-xs text-gray-400 dark:text-neutral-500 text-right whitespace-nowrap">
                {formatDate(item.updated_at)}
            </td>
        </tr>
    );
}

// ─── Grupo de conceptos ───────────────────────────────────────────────────────
function GrupoPrecios({ group, precios }: { group: typeof GROUPS[0]; precios: CostoEvento[] }) {
    const items = precios.filter(p => group.conceptos.includes(p.concepto));
    if (items.length === 0) return null;

    return (
        <div className="border border-sidebar-border/70 rounded-xl overflow-hidden shadow-sm dark:border-neutral-700">
            <div className={`px-4 py-2.5 border-b border-sidebar-border/70 dark:border-neutral-700 flex items-center gap-2`}>
                <span className={`text-xs font-semibold rounded-full px-3 py-0.5 border ${group.color}`}>
                    {group.label}
                </span>
            </div>
            <table className="w-full bg-white dark:bg-neutral-900">
                <thead>
                    <tr className="text-[11px] text-gray-400 uppercase tracking-wide border-b border-gray-100 dark:border-neutral-800">
                        <th className="px-4 py-2 text-left font-medium">Descripción</th>
                        <th className="px-4 py-2 text-left font-medium">Concepto</th>
                        <th className="px-4 py-2 text-right font-medium">Precio</th>
                        <th className="px-4 py-2 text-right font-medium">Actualizado</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map(item => (
                        <PrecioRow key={item.id} item={item} />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────
export default function BotPreciosPage({ precios, flash }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [importing, setImporting] = useState(false);

    const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setImporting(true);
        const form = new FormData();
        form.append('archivo', file);
        router.post('/bot/precios/import', form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => { setImporting(false); if (fileInputRef.current) fileInputRef.current.value = ''; },
        });
    };

    return (
        <>
            <Head title="Tabla de Precios" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-y-auto p-4">

                {/* Header */}
                <div className="max-w-3xl mx-auto w-full flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-lg font-semibold text-gray-800 dark:text-neutral-100">Tabla de Precios</h1>
                        <p className="text-xs text-gray-400 mt-0.5">Los cambios se aplican en el siguiente cálculo del bot.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <a
                            href="/bot/precios/template"
                            className="text-xs text-gray-500 border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50 transition-colors dark:border-neutral-600 dark:text-neutral-400 dark:hover:bg-neutral-800"
                        >
                            ↓ Template CSV
                        </a>
                        <button
                            onClick={() => fileInputRef.current?.click()}
                            disabled={importing}
                            className="text-xs font-medium text-white bg-[#075e54] rounded-lg px-3 py-1.5 hover:bg-[#0a7060] transition-colors disabled:opacity-50"
                        >
                            {importing ? 'Importando…' : '↑ Importar CSV'}
                        </button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".csv,text/csv"
                            className="hidden"
                            onChange={handleImport}
                        />
                    </div>
                </div>

                {/* Flash */}
                {flash?.success && (
                    <div className="max-w-3xl mx-auto w-full text-sm text-[#075e54] bg-green-50 border border-green-200 rounded-lg px-4 py-2.5 dark:bg-green-900/20 dark:border-green-800 dark:text-green-400">
                        {flash.success}
                    </div>
                )}

                {/* Grupos */}
                <div className="space-y-4 max-w-3xl mx-auto w-full pb-8">
                    {GROUPS.map(group => (
                        <GrupoPrecios key={group.label} group={group} precios={precios} />
                    ))}
                </div>
            </div>
        </>
    );
}

BotPreciosPage.layout = {
    breadcrumbs: [
        { title: 'Bot Simulator', href: '/bot' },
        { title: 'Tabla de Precios', href: '/bot/precios' },
    ],
};
