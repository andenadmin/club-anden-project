import { Head, Link, router } from '@inertiajs/react';
import { Download, Search, Users } from 'lucide-react';
import { useRef, useState } from 'react';

interface CrmEntry {
    valor_lifetime: string;
    fecha_ultimo_evento: string | null;
    etiquetas: string[] | null;
    notas: string | null;
}

interface Cliente {
    id: number;
    numero_contacto: string;
    nombre_cliente: string | null;
    mail_contacto: string | null;
    contador_reservas_deportes: number;
    contador_reservas_restaurante: number;
    contador_reservas_eventos: number;
    created_at: string;
    crm: CrmEntry | null;
}

interface PaginatedClientes {
    data: Cliente[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    clientes: PaginatedClientes;
    search: string;
}

const ETIQUETA_COLORS: Record<string, string> = {
    VIP:          'bg-yellow-100 text-yellow-800',
    Frecuente:    'bg-blue-100 text-blue-800',
    Corporativo:  'bg-purple-100 text-purple-800',
    Cumpleañero:  'bg-pink-100 text-pink-800',
};

function etiquetaClass(tag: string) {
    return ETIQUETA_COLORS[tag] ?? 'bg-gray-100 text-gray-700';
}

export default function Crm({ clientes, search }: Props) {
    const [query, setQuery] = useState(search);
    const timeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    function handleSearch(value: string) {
        setQuery(value);
        if (timeout.current) clearTimeout(timeout.current);
        timeout.current = setTimeout(() => {
            router.get('/crm', { search: value }, { preserveState: true, replace: true });
        }, 350);
    }

    function exportUrl() {
        const params = query ? `?search=${encodeURIComponent(query)}` : '';
        return `/crm/export${params}`;
    }

    return (
        <>
            <Head title="CRM Clientes" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-y-auto p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Users className="h-6 w-6 text-muted-foreground" />
                        <div>
                            <h1 className="text-xl font-semibold">CRM Clientes</h1>
                            <p className="text-sm text-muted-foreground">
                                {clientes.total} cliente{clientes.total !== 1 ? 's' : ''} registrado{clientes.total !== 1 ? 's' : ''}
                            </p>
                        </div>
                    </div>
                    <a
                        href={exportUrl()}
                        className="flex items-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium hover:bg-muted transition-colors"
                    >
                        <Download className="h-4 w-4" />
                        Exportar CSV
                    </a>
                </div>

                {/* Search */}
                <div className="relative max-w-sm">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => handleSearch(e.target.value)}
                        placeholder="Buscar por nombre, teléfono o mail…"
                        className="w-full rounded-md border border-border bg-background py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                {/* Table */}
                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="min-w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Nombre</th>
                                <th className="px-4 py-3 text-left font-medium">Teléfono</th>
                                <th className="px-4 py-3 text-left font-medium">Mail</th>
                                <th className="px-4 py-3 text-center font-medium">🏅 Dep</th>
                                <th className="px-4 py-3 text-center font-medium">🍽️ Rest</th>
                                <th className="px-4 py-3 text-center font-medium">🎉 Evt</th>
                                <th className="px-4 py-3 text-right font-medium">Lifetime $</th>
                                <th className="px-4 py-3 text-left font-medium">Último evento</th>
                                <th className="px-4 py-3 text-left font-medium">Etiquetas</th>
                                <th className="px-4 py-3 text-left font-medium">Notas</th>
                                <th className="px-4 py-3 text-left font-medium">Registrado</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {clientes.data.length === 0 ? (
                                <tr>
                                    <td colSpan={11} className="px-4 py-8 text-center text-muted-foreground">
                                        No hay clientes que coincidan con la búsqueda.
                                    </td>
                                </tr>
                            ) : (
                                clientes.data.map((c) => {
                                    const crm = c.crm;
                                    const total =
                                        c.contador_reservas_deportes +
                                        c.contador_reservas_restaurante +
                                        c.contador_reservas_eventos;

                                    return (
                                        <tr key={c.id} className="hover:bg-muted/30 transition-colors">
                                            <td className="px-4 py-3 font-medium">
                                                {c.nombre_cliente ?? <span className="text-muted-foreground italic">Sin nombre</span>}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">{c.numero_contacto}</td>
                                            <td className="px-4 py-3">
                                                {c.mail_contacto
                                                    ? <a href={`mailto:${c.mail_contacto}`} className="text-blue-600 hover:underline">{c.mail_contacto}</a>
                                                    : <span className="text-muted-foreground">—</span>
                                                }
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <ReservaBadge count={c.contador_reservas_deportes} />
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <ReservaBadge count={c.contador_reservas_restaurante} />
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <ReservaBadge count={c.contador_reservas_eventos} />
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium">
                                                {crm && parseFloat(crm.valor_lifetime) > 0
                                                    ? `$${Number(crm.valor_lifetime).toLocaleString('es-AR', { minimumFractionDigits: 0 })}`
                                                    : <span className="text-muted-foreground">—</span>
                                                }
                                            </td>
                                            <td className="px-4 py-3 text-sm text-muted-foreground">
                                                {crm?.fecha_ultimo_evento
                                                    ? new Date(crm.fecha_ultimo_evento + 'T00:00:00').toLocaleDateString('es-AR')
                                                    : '—'
                                                }
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {crm?.etiquetas?.map((tag) => (
                                                        <span key={tag} className={`rounded-full px-2 py-0.5 text-xs font-medium ${etiquetaClass(tag)}`}>
                                                            {tag}
                                                        </span>
                                                    )) ?? <span className="text-muted-foreground">—</span>}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 max-w-[200px]">
                                                {crm?.notas
                                                    ? <span className="line-clamp-2 text-muted-foreground">{crm.notas}</span>
                                                    : <span className="text-muted-foreground">—</span>
                                                }
                                            </td>
                                            <td className="px-4 py-3 text-sm text-muted-foreground whitespace-nowrap">
                                                {new Date(c.created_at).toLocaleDateString('es-AR')}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {clientes.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Mostrando {clientes.from}–{clientes.to} de {clientes.total}
                        </span>
                        <div className="flex gap-1">
                            {clientes.links.map((link, i) => (
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        className={`rounded px-3 py-1 border text-sm transition-colors ${
                                            link.active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border hover:bg-muted'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        preserveScroll
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="rounded px-3 py-1 border border-border text-sm opacity-40"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                )
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Crm.layout = {
    breadcrumbs: [{ title: 'CRM Clientes', href: '/crm' }],
};

function ReservaBadge({ count }: { count: number }) {
    if (count === 0) return <span className="text-muted-foreground">—</span>;
    return (
        <span className="inline-flex items-center justify-center rounded-full bg-primary/10 text-primary text-xs font-semibold w-6 h-6">
            {count}
        </span>
    );
}
