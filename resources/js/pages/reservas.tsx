import { Head, router } from '@inertiajs/react';
import { Baby, Calendar, Search, Utensils, Wind, X } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

// ─── Tipos ───────────────────────────────────────────────────────────────────

interface Reserva {
    id: number;
    nombre: string;
    telefono: string;
    hora: string;
    numero_personas: string;
    mail: string | null;
    comentarios: string | null;
    estado: 'CONFIRMADA' | 'PENDIENTE_CONFIRMACION' | 'CANCELADA' | 'ESCALADA';
}

interface Props {
    reservas: Reserva[];
    fecha: string;
    ahora: string; // "HH:mm"
}

interface NotasAnalysis {
    hasBabyChair: boolean;
    celebration: boolean;
    allergy: boolean;
    tags: string[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Detecta keywords en el texto de comentarios y devuelve tags estructurados */
function analyzeNotes(text: string | null): NotasAnalysis {
    if (!text) return { hasBabyChair: false, celebration: false, allergy: false, tags: [] };
    const lower = text.toLowerCase();
    const hasBabyChair = /beb[eé]|silla alta|sillita/.test(lower);
    const celebration  = /cumplea[ñn]os|aniversario|festejo/.test(lower);
    const allergy      = /cel[ií]aco|alergia|sin gluten|intolerante/.test(lower);
    const tags: string[] = [
        hasBabyChair && 'bebé',
        celebration  && 'cumpleaños',
        allergy      && 'alergia',
    ].filter(Boolean) as string[];
    return { hasBabyChair, celebration, allergy, tags };
}

/**
 * Extrae el número máximo de personas de strings como "3 a 4 personas" o "9 a 14 personas".
 * Usa el máximo del rango como estimación conservadora para preparación.
 */
function extractPersonasMax(str: string): number {
    if (!str) return 0;
    const range = str.match(/(\d+)\s*a\s*(\d+)/);
    if (range) return parseInt(range[2]);
    const single = str.match(/(\d+)/);
    return single ? parseInt(single[1]) : 0;
}

/** Convierte "HH:mm" a minutos desde medianoche para comparación */
function horaToMinutes(hora: string): number {
    const [h, m] = hora.split(':').map(Number);
    return (h || 0) * 60 + (m || 0);
}

/** Agrupa reservas por turno (hora) y calcula totales */
function groupByTurno(reservas: Reserva[]) {
    const grupos: Record<string, Reserva[]> = {};
    for (const r of reservas) {
        const key = r.hora || 'Sin horario';
        if (!grupos[key]) grupos[key] = [];
        grupos[key].push(r);
    }
    // Devuelve array ordenado por hora
    return Object.entries(grupos).sort(([a], [b]) => a.localeCompare(b));
}

/** Filtra las reservas de los próximos N minutos respecto a "ahora" */
function getProximasLlegadas(reservas: Reserva[], ahora: string, ventanaMin = 30): Reserva[] {
    const ahoraMin = horaToMinutes(ahora);
    return reservas
        .filter((r) => {
            if (!r.hora) return false;
            const diff = horaToMinutes(r.hora) - ahoraMin;
            return diff >= 0 && diff <= ventanaMin;
        })
        .sort((a, b) => a.hora.localeCompare(b.hora));
}

// ─── Sub-componentes ──────────────────────────────────────────────────────────

const ESTADO_CONFIG: Record<string, { label: string; className: string }> = {
    CONFIRMADA:            { label: 'Confirmada',  className: 'bg-emerald-100 text-emerald-800' },
    PENDIENTE_CONFIRMACION:{ label: 'Pendiente',   className: 'bg-amber-100 text-amber-800' },
    ESCALADA:              { label: 'Con asesor',  className: 'bg-blue-100 text-blue-800' },
    CANCELADA:             { label: 'Cancelada',   className: 'bg-red-100 text-red-800' },
};

function EstadoBadge({ estado }: { estado: string }) {
    const cfg = ESTADO_CONFIG[estado] ?? { label: estado, className: 'bg-gray-100 text-gray-700' };
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${cfg.className}`}>
            {cfg.label}
        </span>
    );
}

function NotaBadge({ tipo }: { tipo: 'bebe' | 'cumple' | 'alergia' }) {
    const map = {
        bebe:    { icon: Baby,     label: 'Bebé',       cls: 'bg-sky-100 text-sky-800' },
        cumple:  { icon: Utensils, label: 'Cumpleaños', cls: 'bg-pink-100 text-pink-800' },
        alergia: { icon: Wind,     label: 'Alergia',    cls: 'bg-orange-100 text-orange-800' },
    };
    const { icon: Icon, label, cls } = map[tipo];
    return (
        <span className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${cls}`}>
            <Icon className="h-3 w-3" />
            {label}
        </span>
    );
}

function ReservaCard({ reserva }: { reserva: Reserva }) {
    const notas   = analyzeNotes(reserva.comentarios);
    const personas = extractPersonasMax(reserva.numero_personas);

    return (
        <div className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
            {/* Nombre + personas */}
            <div className="flex items-start justify-between gap-2">
                <p className="text-base font-semibold leading-tight">{reserva.nombre}</p>
                <span className="shrink-0 text-xl font-bold text-foreground">
                    {personas > 0 ? personas : reserva.numero_personas || '—'}
                    <span className="ml-1 text-sm font-normal text-muted-foreground">pers.</span>
                </span>
            </div>

            {/* Teléfono clickable */}
            {reserva.telefono && (
                <a
                    href={`tel:${reserva.telefono}`}
                    className="mt-1 block text-sm text-muted-foreground underline-offset-2 hover:underline"
                >
                    {reserva.telefono}
                </a>
            )}

            {/* Badges de notas */}
            {notas.tags.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                    {notas.hasBabyChair && <NotaBadge tipo="bebe" />}
                    {notas.celebration  && <NotaBadge tipo="cumple" />}
                    {notas.allergy      && <NotaBadge tipo="alergia" />}
                </div>
            )}

            {/* Comentarios en texto */}
            {reserva.comentarios && (
                <p className="mt-2 text-sm italic text-muted-foreground">"{reserva.comentarios}"</p>
            )}

            {/* Estado */}
            <div className="mt-2 flex justify-end">
                <EstadoBadge estado={reserva.estado} />
            </div>
        </div>
    );
}

function TurnoSection({ hora, reservas }: { hora: string; reservas: Reserva[] }) {
    const totalCubiertos = reservas.reduce((acc, r) => acc + extractPersonasMax(r.numero_personas), 0);
    const totalBebes     = reservas.filter((r) => analyzeNotes(r.comentarios).hasBabyChair).length;
    const totalCumples   = reservas.filter((r) => analyzeNotes(r.comentarios).celebration).length;
    const totalAlergias  = reservas.filter((r) => analyzeNotes(r.comentarios).allergy).length;

    return (
        <section>
            {/* Header del turno */}
            <div className="mb-3 rounded-lg bg-muted/60 px-4 py-2.5">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-lg font-bold">{hora}</span>
                    <span className="text-sm text-muted-foreground">
                        {reservas.length} reserva{reservas.length !== 1 ? 's' : ''} · {totalCubiertos} cubiertos
                    </span>
                </div>
                {/* Resumen de necesidades especiales */}
                {(totalBebes > 0 || totalCumples > 0 || totalAlergias > 0) && (
                    <div className="mt-1.5 flex flex-wrap gap-2 text-xs text-muted-foreground">
                        {totalBebes   > 0 && <span>👶 {totalBebes} silla{totalBebes !== 1 ? 's' : ''} alta{totalBebes !== 1 ? 's' : ''}</span>}
                        {totalCumples > 0 && <span>🎂 {totalCumples} cumpleaños</span>}
                        {totalAlergias > 0 && <span>⚠️ {totalAlergias} alergia{totalAlergias !== 1 ? 's' : ''}</span>}
                    </div>
                )}
            </div>

            {/* Cards de reservas */}
            <div className="flex flex-col gap-3">
                {reservas.map((r) => (
                    <ReservaCard key={r.id} reserva={r} />
                ))}
            </div>
        </section>
    );
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function Reservas({ reservas, fecha, ahora }: Props) {
    const [query, setQuery]   = useState('');
    const searchTimeout       = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Filtrado por búsqueda (nombre o teléfono) — se recalcula solo cuando cambia query o reservas
    const reservasFiltradas = useMemo(() => {
        if (!query.trim()) return reservas;
        const q = query.toLowerCase();
        return reservas.filter(
            (r) =>
                r.nombre.toLowerCase().includes(q) ||
                r.telefono.includes(q),
        );
    }, [reservas, query]);

    const proximas = useMemo(() => getProximasLlegadas(reservasFiltradas, ahora), [reservasFiltradas, ahora]);
    const grupos   = useMemo(() => groupByTurno(reservasFiltradas), [reservasFiltradas]);

    const totalCubiertos = useMemo(
        () => reservasFiltradas.reduce((acc, r) => acc + extractPersonasMax(r.numero_personas), 0),
        [reservasFiltradas],
    );

    function handleFechaChange(nuevaFecha: string) {
        router.get('/reservas', { fecha: nuevaFecha }, { preserveState: false, replace: true });
    }

    function handleSearch(value: string) {
        setQuery(value);
        // Cancela búsqueda anterior si el usuario sigue escribiendo
        if (searchTimeout.current) clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(() => {}, 0); // búsqueda local, sin request
    }

    return (
        <>
            <Head title="Reservas" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-y-auto p-4 md:p-6">

                {/* Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold">Reservas</h1>
                        <p className="text-sm text-muted-foreground">
                            {reservasFiltradas.length} reserva{reservasFiltradas.length !== 1 ? 's' : ''} · {totalCubiertos} cubiertos
                        </p>
                    </div>

                    {/* Selector de fecha */}
                    <div className="flex items-center gap-2">
                        <Calendar className="h-4 w-4 text-muted-foreground" />
                        <input
                            type="date"
                            value={fecha}
                            onChange={(e) => handleFechaChange(e.target.value)}
                            className="rounded-md border border-border bg-background px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                </div>

                {/* Búsqueda */}
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => handleSearch(e.target.value)}
                        placeholder="Buscar por nombre o teléfono..."
                        className="w-full rounded-xl border border-border bg-background py-2.5 pl-9 pr-9 text-base focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    {query && (
                        <button
                            onClick={() => setQuery('')}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>

                {/* Sin reservas */}
                {reservasFiltradas.length === 0 && (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 text-center text-muted-foreground">
                        <Utensils className="h-10 w-10 opacity-30" />
                        <p className="text-lg font-medium">Sin reservas para este día</p>
                        {query && <p className="text-sm">Probá borrando el filtro de búsqueda</p>}
                    </div>
                )}

                {/* Próximas llegadas — solo si hay reservas en los próximos 30 min */}
                {proximas.length > 0 && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                        <p className="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
                            Próximas llegadas (30 min)
                        </p>
                        <div className="flex flex-col gap-2">
                            {proximas.map((r) => {
                                const personas = extractPersonasMax(r.numero_personas);
                                const notas = analyzeNotes(r.comentarios);
                                return (
                                    <div key={r.id} className="flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-bold text-amber-900 dark:text-amber-200">{r.hora}</span>
                                            <span className="text-sm text-amber-800 dark:text-amber-300">{r.nombre}</span>
                                            {notas.hasBabyChair && <span className="text-xs">👶</span>}
                                            {notas.celebration  && <span className="text-xs">🎂</span>}
                                            {notas.allergy      && <span className="text-xs">⚠️</span>}
                                        </div>
                                        <span className="text-sm font-semibold text-amber-900 dark:text-amber-200">
                                            {personas > 0 ? `${personas} p.` : r.numero_personas}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Grupos por turno */}
                <div className="flex flex-col gap-6">
                    {grupos.map(([hora, rsv]) => (
                        <TurnoSection key={hora} hora={hora} reservas={rsv} />
                    ))}
                </div>
            </div>
        </>
    );
}

Reservas.layout = {
    breadcrumbs: [{ title: 'Reservas', href: '/reservas' }],
};
