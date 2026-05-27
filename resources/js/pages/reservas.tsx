import { Head, router } from '@inertiajs/react';
import { Baby, ChevronLeft, ChevronRight, MessageCircle, Pencil, Search, Utensils, Wind, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';

// ─── Tipos ───────────────────────────────────────────────────────────────────

type TipoReserva = 'RESTAURANTE' | 'FUTBOL' | 'NINOS' | 'PADEL' | 'HOCKEY' | 'GENERAL_EVT';
type Vista = 'dia' | 'semana' | 'quincena';

interface Reserva {
    id: number;
    tipo: TipoReserva;
    nombre: string;
    telefono: string;
    fecha: string | null;   // "Y-m-d"
    hora: string | null;    // "HH:mm"
    numero_personas: string;
    sector: string | null;
    mail: string | null;
    comentarios: string | null;
    estado: 'CONFIRMADA' | 'PENDIENTE_CONFIRMACION' | 'CANCELADA' | 'ESCALADA' | 'COMPLETADA';
    nombre_hijo: string | null;
    necesidades_especiales: string | null;
}

interface Props {
    reservas: Reserva[];
    fecha: string;          // start date "Y-m-d"
    ahora: string;          // "HH:mm"
    es_hoy: boolean;
    vista: Vista;
    fechas_rango: string[]; // ["Y-m-d", ...]
}

// ─── Configuración de tipos y estados ────────────────────────────────────────

const TIPO_CONFIG: Record<TipoReserva, { label: string; badge: string; col: string; text: string }> = {
    RESTAURANTE: {
        label: 'Restaurante',
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        col:   'border-l-emerald-400',
        text:  'text-emerald-700 dark:text-emerald-400',
    },
    FUTBOL: {
        label: 'Cumple Fútbol',
        badge: 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300',
        col:   'border-l-pink-400',
        text:  'text-pink-700 dark:text-pink-400',
    },
    NINOS: {
        label: 'Cumpleaños',
        badge: 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300',
        col:   'border-l-pink-400',
        text:  'text-pink-700 dark:text-pink-400',
    },
    PADEL: {
        label: 'Cumple Pádel',
        badge: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
        col:   'border-l-sky-400',
        text:  'text-sky-700 dark:text-sky-400',
    },
    HOCKEY: {
        label: 'Cumple Hockey',
        badge: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
        col:   'border-l-orange-400',
        text:  'text-orange-700 dark:text-orange-400',
    },
    GENERAL_EVT: {
        label: 'Evento',
        badge: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
        col:   'border-l-violet-400',
        text:  'text-violet-700 dark:text-violet-400',
    },
};

const ESTADO_CONFIG: Record<string, { label: string; className: string }> = {
    CONFIRMADA:            { label: 'Confirmada', className: 'bg-emerald-100 text-emerald-800' },
    PENDIENTE_CONFIRMACION:{ label: 'Pendiente',  className: 'bg-amber-100 text-amber-800' },
    ESCALADA:              { label: 'Con asesor', className: 'bg-blue-100 text-blue-800' },
    CANCELADA:             { label: 'Cancelada',  className: 'bg-red-100 text-red-800' },
    COMPLETADA:            { label: 'Completada', className: 'bg-slate-100 text-slate-600' },
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

interface NotasAnalysis { hasBabyChair: boolean; celebration: boolean; allergy: boolean }

function sinAcentos(str: string): string {
    return str.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

function analyzeNotes(text: string | null): NotasAnalysis {
    if (!text) return { hasBabyChair: false, celebration: false, allergy: false };
    const lower = text.toLowerCase();
    return {
        hasBabyChair: /beb[eé]|silla alta|sillita/.test(lower),
        celebration:  /cumplea[ñn]os|aniversario|festejo/.test(lower),
        allergy:      /cel[ií]aco|alergia|sin gluten|intolerante/.test(lower),
    };
}

function extractPersonasMax(str: string): number {
    if (!str) return 0;
    const range = str.match(/(\d+)\s*a\s*(\d+)/);
    if (range) return parseInt(range[2]);
    const single = str.match(/(\d+)/);
    return single ? parseInt(single[1]) : 0;
}

function horaToMinutes(hora: string): number {
    const [h, m] = hora.split(':').map(Number);
    return (h || 0) * 60 + (m || 0);
}

function isReservaVencida(hora: string | null, ahora: string, ventanaMin = 150): boolean {
    if (!hora) return false;
    return horaToMinutes(ahora) - horaToMinutes(hora) >= ventanaMin;
}

function groupByTurno(reservas: Reserva[]) {
    const grupos: Record<string, Reserva[]> = {};
    for (const r of reservas) {
        const key = r.hora ?? 'Sin horario';
        grupos[key] ??= [];
        grupos[key].push(r);
    }
    return Object.entries(grupos).sort(([a], [b]) => a.localeCompare(b));
}

function getProximasLlegadas(reservas: Reserva[], ahora: string, ventanaMin = 30): Reserva[] {
    const ahoraMin = horaToMinutes(ahora);
    return reservas
        .filter((r) => {
            if (!r.hora) return false;
            const diff = horaToMinutes(r.hora) - ahoraMin;
            return diff >= 0 && diff <= ventanaMin;
        })
        .sort((a, b) => (a.hora ?? '').localeCompare(b.hora ?? ''));
}

/** Normaliza hora ingresada por el usuario: "15.30" → "15:30", "1530" → "15:30" */
function normalizeHora(raw: string): string {
    const s = raw.trim().replace('.', ':').replace(',', ':');
    // "1530" sin separador → "15:30"
    if (/^\d{3,4}$/.test(s)) {
        const h = s.slice(0, -2).padStart(2, '0');
        const m = s.slice(-2);
        return `${h}:${m}`;
    }
    // "15:3" → "15:03"
    const match = s.match(/^(\d{1,2}):(\d{1,2})$/);
    if (match) return `${match[1].padStart(2, '0')}:${match[2].padStart(2, '0')}`;
    return s;
}

/** Parsea "Y-m-d" sin problemas de timezone */
function parseDateLocal(iso: string): Date {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function formatFechaColumna(iso: string): { diaSemana: string; dia: string; mes: string } {
    const d = parseDateLocal(iso);
    return {
        diaSemana: d.toLocaleDateString('es-AR', { weekday: 'short' }),
        dia:       String(d.getDate()),
        mes:       d.toLocaleDateString('es-AR', { month: 'short' }),
    };
}

function todayIso(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function isToday(iso: string): boolean { return iso === todayIso(); }
function isPast(iso: string): boolean  { return iso < todayIso(); }

// ─── Sub-componentes compartidos ─────────────────────────────────────────────

function EstadoBadge({ estado }: { estado: string }) {
    const cfg = ESTADO_CONFIG[estado] ?? { label: estado, className: 'bg-gray-100 text-gray-700' };
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${cfg.className}`}>
            {cfg.label}
        </span>
    );
}

function TipoBadge({ tipo }: { tipo: TipoReserva }) {
    const cfg = TIPO_CONFIG[tipo];
    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${cfg.badge}`}>
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

// ─── Dialog de edición ────────────────────────────────────────────────────────

const ESTADO_OPTIONS = [
    { value: 'CONFIRMADA',             label: 'Confirmada' },
    { value: 'PENDIENTE_CONFIRMACION', label: 'Pendiente de confirmación' },
    { value: 'COMPLETADA',             label: 'Completada' },
    { value: 'CANCELADA',              label: 'Cancelada' },
    { value: 'ESCALADA',               label: 'Con asesor' },
];

const SECTOR_OPTIONS = ['Salón', 'Galería', 'Terraza', 'Parrilla', 'Sin preferencia'] as const;

function makeFormData(r: Reserva) {
    return {
        nombre:          r.nombre,
        fecha:           r.fecha ?? '',
        hora:            r.hora ?? '',
        numero_personas: r.numero_personas,
        sector:          r.sector ?? '',
        mail:            r.mail ?? '',
        comentarios:     r.comentarios ?? '',
        estado:          r.estado,
    };
}

function EditReservaDialog({ reserva, open, onClose }: { reserva: Reserva; open: boolean; onClose: () => void }) {
    const [form, setForm]         = useState(() => makeFormData(reserva));
    const [saving, setSaving]     = useState(false);
    const [errors, setErrors]     = useState<Record<string, string>>({});

    // Reinicializar el form cada vez que se abre el dialog
    useEffect(() => {
        if (open) {
            setForm(makeFormData(reserva));
            setErrors({});
        }
    }, [open, reserva.id]);

    function set<K extends keyof typeof form>(key: K, value: typeof form[K]) {
        setForm(f => ({ ...f, [key]: value }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        const payload = { ...form, hora: normalizeHora(form.hora) };
        setSaving(true);
        router.patch(`/reservas/${reserva.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => { setSaving(false); onClose(); },
            onError:   (errs) => { setSaving(false); setErrors(errs as Record<string, string>); },
        });
    }

    const inputCls = 'w-full rounded-md border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
    const labelCls = 'block text-sm font-medium mb-1';
    const errCls   = 'mt-1 text-xs text-red-500';

    return (
        <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar reserva</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4 pt-2">
                    {/* Nombre */}
                    <div>
                        <label className={labelCls}>Nombre responsable</label>
                        <input type="text" value={form.nombre} onChange={e => set('nombre', e.target.value)} className={inputCls} />
                        {errors.nombre && <p className={errCls}>{errors.nombre}</p>}
                    </div>

                    {/* Fecha + Hora */}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={labelCls}>Fecha</label>
                            <input type="date" value={form.fecha} onChange={e => set('fecha', e.target.value)} className={inputCls} />
                            {errors.fecha && <p className={errCls}>{errors.fecha}</p>}
                        </div>
                        <div>
                            <label className={labelCls}>Hora</label>
                            <input
                                type="text"
                                value={form.hora}
                                onChange={e => set('hora', e.target.value)}
                                onBlur={e => set('hora', normalizeHora(e.target.value))}
                                placeholder="ej: 15:30"
                                className={inputCls}
                            />
                            {errors.hora && <p className={errCls}>{errors.hora}</p>}
                        </div>
                    </div>

                    {/* Personas */}
                    {!(['NINOS', 'FUTBOL', 'PADEL', 'HOCKEY'] as TipoReserva[]).includes(reserva.tipo) && (
                        <div>
                            <label className={labelCls}>Número de personas</label>
                            <input type="text" value={form.numero_personas} onChange={e => set('numero_personas', e.target.value)} className={inputCls} />
                        </div>
                    )}

                    {/* Sector (solo restaurante) */}
                    {reserva.tipo === 'RESTAURANTE' && (
                        <div>
                            <label className={labelCls}>Sector</label>
                            <select value={form.sector} onChange={e => set('sector', e.target.value)} className={inputCls}>
                                <option value="">— Sin especificar —</option>
                                {SECTOR_OPTIONS.map(s => <option key={s} value={s}>{s}</option>)}
                            </select>
                        </div>
                    )}

                    {/* Mail */}
                    <div>
                        <label className={labelCls}>Email de contacto</label>
                        <input type="email" value={form.mail} onChange={e => set('mail', e.target.value)} placeholder="Sin email" className={inputCls} />
                        {errors.mail && <p className={errCls}>{errors.mail}</p>}
                    </div>

                    {/* Estado */}
                    <div>
                        <label className={labelCls}>Estado</label>
                        <select value={form.estado} onChange={e => set('estado', e.target.value as Reserva['estado'])} className={inputCls}>
                            {ESTADO_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </select>
                    </div>

                    {/* Comentarios */}
                    <div>
                        <label className={labelCls}>Comentarios / extras</label>
                        <textarea value={form.comentarios} onChange={e => set('comentarios', e.target.value)} rows={3} placeholder="Sin comentarios" className={`${inputCls} resize-none`} />
                    </div>

                    <DialogFooter className="pt-2">
                        <button type="button" onClick={onClose} className="rounded-md border border-border px-4 py-2 text-sm hover:bg-accent transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" disabled={saving} className="rounded-md px-4 py-2 text-sm font-medium bg-neutral-900 text-white hover:bg-neutral-700 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition-colors disabled:opacity-50">
                            {saving ? 'Guardando…' : 'Guardar'}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ─── Card vista DÍA (completa) ────────────────────────────────────────────────

function ReservaCard({ reserva }: { reserva: Reserva }) {
    const [editOpen,   setEditOpen]   = useState(false);
    const [completing, setCompleting] = useState(false);
    const notas    = analyzeNotes(reserva.comentarios);
    const personas = extractPersonasMax(reserva.numero_personas);
    const tipoCfg  = TIPO_CONFIG[reserva.tipo];

    const yaCompletada = reserva.estado === 'COMPLETADA';

    function marcarLlegada() {
        setCompleting(true);
        router.patch(`/reservas/${reserva.id}`, {
            nombre:          reserva.nombre,
            fecha:           reserva.fecha ?? '',
            hora:            reserva.hora ?? '',
            numero_personas: reserva.numero_personas,
            sector:          reserva.sector ?? '',
            mail:            reserva.mail ?? '',
            comentarios:     reserva.comentarios ?? '',
            estado:          'COMPLETADA',
        }, { preserveScroll: true, onFinish: () => setCompleting(false) });
    }

    return (
        <div className={`rounded-xl border border-border border-l-4 ${tipoCfg.col} bg-card px-4 py-3 shadow-sm`}>
            <div className="flex items-start justify-between gap-2">
                <div className="flex items-baseline gap-2 min-w-0">
                    <span className="text-sm font-mono font-semibold text-muted-foreground shrink-0">#{reserva.id}</span>
                    <p className="text-base font-semibold leading-tight truncate">{reserva.nombre}</p>
                </div>
                <EstadoBadge estado={reserva.estado} />
            </div>

            <div className="mt-3 flex items-center justify-between gap-2 text-sm text-foreground/60">
                {reserva.telefono ? (
                    <div className="flex items-center gap-1">
                        <MessageCircle className="h-3.5 w-3.5" />
                        <span>{reserva.telefono}</span>
                    </div>
                ) : <span />}
                <div className="flex items-center gap-2">
                    {reserva.sector && reserva.sector !== 'Sin preferencia' && (
                        <span className="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                            {reserva.sector}
                        </span>
                    )}
                    <span className="font-semibold text-foreground">
                        {personas > 0
                            ? `${personas} ${personas === 1 ? 'persona' : 'personas'}`
                            : reserva.numero_personas || '—'}
                    </span>
                </div>
            </div>

            {(notas.hasBabyChair || notas.celebration || notas.allergy) && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                    {notas.hasBabyChair && <NotaBadge tipo="bebe" />}
                    {notas.celebration  && <NotaBadge tipo="cumple" />}
                    {notas.allergy      && <NotaBadge tipo="alergia" />}
                </div>
            )}

            {reserva.nombre_hijo && (
                <p className="mt-2 text-sm font-medium text-pink-700 dark:text-pink-400">🎂 {reserva.nombre_hijo}</p>
            )}

            {reserva.necesidades_especiales && (
                <div className="mt-2 rounded-md bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 px-2.5 py-1.5">
                    <p className="text-xs font-semibold text-red-700 dark:text-red-400">⚠️ Necesidades: {reserva.necesidades_especiales}</p>
                </div>
            )}

            {reserva.comentarios && (
                <p className="mt-2 text-sm italic text-muted-foreground">"{reserva.comentarios}"</p>
            )}

            {/* ¿Llegó la reserva? */}
            {!yaCompletada ? (
                <div className="mt-3 flex items-center justify-between gap-2 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 px-3 py-2">
                    <span className="text-xs font-medium text-amber-800 dark:text-amber-300">¿Llegó la reserva?</span>
                    <button
                        onClick={marcarLlegada}
                        disabled={completing}
                        className="text-xs font-semibold rounded-md px-3 py-1 bg-amber-500 text-white hover:bg-amber-600 transition-colors disabled:opacity-50"
                    >
                        {completing ? '…' : 'Sí, llegó'}
                    </button>
                </div>
            ) : (
                <div className="mt-3 rounded-lg bg-slate-100 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 px-3 py-2">
                    <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Reserva completada</span>
                </div>
            )}

            <div className="mt-3 flex gap-2">
                {reserva.telefono && (
                    <button
                        onClick={() => router.visit(`/inbox/${reserva.telefono}`)}
                        className="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium bg-emerald-500 text-white hover:bg-emerald-600 transition-colors"
                    >
                        <MessageCircle className="h-4 w-4" />
                        Chatear
                    </button>
                )}
                <button
                    onClick={() => setEditOpen(true)}
                    className="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold bg-blue-600 text-white hover:bg-blue-500 dark:bg-blue-500 dark:hover:bg-blue-400 transition-colors"
                >
                    <Pencil className="h-4 w-4" />
                    Editar
                </button>
            </div>
            <EditReservaDialog reserva={reserva} open={editOpen} onClose={() => setEditOpen(false)} />
        </div>
    );
}

function TurnoSection({ hora, reservas }: { hora: string; reservas: Reserva[] }) {
    const totalCubiertos = reservas.reduce((acc, r) => acc + extractPersonasMax(r.numero_personas), 0);
    const notas = reservas.map((r) => analyzeNotes(r.comentarios));
    const totalBebes   = notas.filter((n) => n.hasBabyChair).length;
    const totalCumples = notas.filter((n) => n.celebration).length;
    const totalAlergias = notas.filter((n) => n.allergy).length;

    return (
        <section>
            <div className="mb-3 rounded-lg bg-muted/60 px-4 py-2.5">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-lg font-bold">{hora}</span>
                    <span className="text-sm text-muted-foreground">
                        {reservas.length} reserva{reservas.length !== 1 ? 's' : ''} · {totalCubiertos} cubiertos
                    </span>
                </div>
                {(totalBebes > 0 || totalCumples > 0 || totalAlergias > 0) && (
                    <div className="mt-1.5 flex flex-wrap gap-2 text-xs text-muted-foreground">
                        {totalBebes    > 0 && <span>{totalBebes} silla{totalBebes !== 1 ? 's' : ''} alta{totalBebes !== 1 ? 's' : ''}</span>}
                        {totalCumples  > 0 && <span>{totalCumples} cumpleaños</span>}
                        {totalAlergias > 0 && <span>{totalAlergias} alergia{totalAlergias !== 1 ? 's' : ''}</span>}
                    </div>
                )}
            </div>
            <div className="flex flex-col gap-3">
                {reservas.map((r) => <ReservaCard key={r.id} reserva={r} />)}
            </div>
        </section>
    );
}

// ─── Card compacta para vista SEMANA / QUINCENA ───────────────────────────────

function ReservaCardCompacta({ reserva }: { reserva: Reserva }) {
    const [editOpen, setEditOpen] = useState(false);
    const tipoCfg  = TIPO_CONFIG[reserva.tipo];
    const personas = extractPersonasMax(reserva.numero_personas);

    return (
        <div className={`rounded-lg border border-border border-l-4 ${tipoCfg.col} bg-card px-2.5 py-2 text-xs`}>
            <div className="flex items-start justify-between gap-1">
                <p className="truncate font-medium leading-tight">{reserva.nombre}</p>
                <EstadoBadge estado={reserva.estado} />
            </div>
            <div className="mt-1 text-muted-foreground">
                {personas > 0
                    ? `${personas} ${personas === 1 ? 'persona' : 'personas'}`
                    : reserva.numero_personas || '—'}
            </div>
            <div className="mt-1.5 flex gap-1">
                {reserva.telefono && (
                    <button
                        onClick={() => router.visit(`/inbox/${reserva.telefono}`)}
                        className="flex flex-1 items-center justify-center gap-1 rounded px-2 py-1 text-xs font-medium bg-emerald-500 text-white hover:bg-emerald-600 transition-colors"
                    >
                        <MessageCircle className="h-3 w-3" />
                        Chat
                    </button>
                )}
                <button
                    onClick={() => setEditOpen(true)}
                    className="flex flex-1 items-center justify-center gap-1 rounded px-2 py-1 text-xs font-medium bg-white text-neutral-900 border border-neutral-200 hover:bg-neutral-50 dark:bg-white dark:text-black dark:hover:bg-neutral-100 transition-colors"
                >
                    <Pencil className="h-3 w-3" />
                    Edit
                </button>
            </div>
            <EditReservaDialog reserva={reserva} open={editOpen} onClose={() => setEditOpen(false)} />
        </div>
    );
}

// ─── Vista SEMANA / QUINCENA — grilla horaria ────────────────────────────────

function VistaMultiDia({
    fechas_rango,
    reservas,
    ahora,
    fullWidth,
    highlightToday = false,
    filtroTipo,
    onFiltroToggle,
}: {
    fechas_rango: string[];
    reservas: Reserva[];
    ahora: string;
    fullWidth: boolean;
    highlightToday?: boolean;
    filtroTipo: TipoReserva | null;
    onFiltroToggle: (tipo: TipoReserva) => void;
}) {
    const reservasFiltradas = useMemo(
        () => filtroTipo ? reservas.filter((r) => r.tipo === filtroTipo) : reservas,
        [reservas, filtroTipo],
    );

    // Agrupa por fecha → hora (usa reservas filtradas por tipo)
    const byFechaHora = useMemo(() => {
        const map: Record<string, Record<string, Reserva[]>> = {};
        for (const f of fechas_rango) map[f] = {};
        for (const r of reservasFiltradas) {
            if (!r.fecha || !map[r.fecha]) continue;
            const h = r.hora ?? 'Sin hora';
            map[r.fecha][h] ??= [];
            map[r.fecha][h].push(r);
        }
        return map;
    }, [fechas_rango, reservasFiltradas]);

    // Franjas horarias únicas presentes en los datos, ordenadas
    const timeSlots = useMemo(() => {
        const times = new Set<string>();
        for (const r of reservasFiltradas) if (r.hora) times.add(r.hora);
        return [...times].sort();
    }, [reservasFiltradas]);

    const totalGeneral  = reservas.length;
    const totalPersonas = reservas.reduce((acc, r) => acc + extractPersonasMax(r.numero_personas), 0);

    // Renderiza la grilla — isFull: columnas de día con flex-1 (desktop) vs ancho fijo (mobile/scroll)
    const renderGrid = (isFull: boolean) => {
        const dayCol = isFull ? 'flex-1 min-w-0' : 'w-[187px] shrink-0';

        return (
            <div style={!isFull ? { minWidth: `${56 + fechas_rango.length * 152}px` } : undefined}>

                {/* ── Headers de día ── */}
                <div className="flex gap-1.5 pb-2 border-b border-border/40 mb-1">
                    <div className="w-12 shrink-0" />
                    {fechas_rango.map((f) => {
                        const { diaSemana, dia, mes } = formatFechaColumna(f);
                        const hoy    = isToday(f);
                        const pasado = isPast(f);
                        const count  = Object.values(byFechaHora[f] ?? {}).flat().length;
                        return (
                            <div
                                key={f}
                                className={`${dayCol} rounded-lg px-1 py-2 text-center transition-colors duration-300 ${
                                    hoy && highlightToday
                                        ? 'bg-emerald-200 dark:bg-emerald-600 ring-2 ring-emerald-500 dark:ring-emerald-400'
                                        : hoy ? 'bg-primary/10' : pasado ? 'bg-muted/20' : 'bg-muted/40'
                                }`}
                            >
                                <p className={`text-xs font-medium capitalize leading-none ${hoy ? 'text-primary' : 'text-muted-foreground'}`}>
                                    {diaSemana}
                                </p>
                                <p className={`text-sm font-bold leading-snug ${hoy ? 'text-primary' : 'text-foreground'}`}>
                                    {dia} {mes}
                                </p>
                                {count > 0 && (
                                    <p className="text-xs text-muted-foreground">{count} res.</p>
                                )}
                            </div>
                        );
                    })}
                </div>

                {/* ── Filas por franja horaria ── */}
                {timeSlots.length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">Sin reservas en este período</p>
                ) : (
                    <div className="flex flex-col divide-y divide-border">
                        {timeSlots.map((time) => (
                            <div key={time} className="flex gap-1.5 py-1.5">
                                {/* Hora */}
                                <div className="w-12 shrink-0 flex items-start justify-end pt-1 pr-1">
                                    <span className="text-sm font-bold tabular-nums text-foreground">{time}</span>
                                </div>
                                {/* Celda por día */}
                                {fechas_rango.map((f) => {
                                    const cards = byFechaHora[f]?.[time] ?? [];
                                    return (
                                        <div key={f} className={`${dayCol} flex flex-col gap-1`}>
                                            {cards.length === 0 ? (
                                                <div className="h-5 rounded border border-dashed border-border/25" />
                                            ) : (
                                                cards.map((r) => <ReservaCardCompacta key={r.id} reserva={r} />)
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="flex flex-col gap-3">

            {/* Resumen: conteos a la izquierda, personas a la derecha */}
            {totalGeneral > 0 && (
                <div className="flex items-start justify-between gap-2 text-sm">
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="text-muted-foreground">{totalGeneral} reservas</span>
                        <div className="flex items-center gap-2">
                            {(['RESTAURANTE', 'NINOS', 'GENERAL_EVT'] as TipoReserva[]).map((tipo) => {
                                const count = reservas.filter((r) => r.tipo === tipo).length;
                                if (!count) return null;
                                const activo = filtroTipo === tipo;
                                const opaco  = filtroTipo !== null && !activo;
                                return (
                                    <button
                                        key={tipo}
                                        type="button"
                                        onClick={() => onFiltroToggle(tipo)}
                                        className={`flex items-center gap-1 rounded-full transition-opacity ${opaco ? 'opacity-30' : 'opacity-100'}`}
                                    >
                                        <TipoBadge tipo={tipo} />
                                        <span className={`text-xs font-semibold ${TIPO_CONFIG[tipo].text}`}>{count}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                    <span className="shrink-0 text-muted-foreground">{totalPersonas} personas</span>
                </div>
            )}

            {/* Grilla: full-width en desktop para semana, siempre scroll para quincena y mobile */}
            {fullWidth ? (
                <>
                    <div className="hidden lg:block">{renderGrid(true)}</div>
                    <div className="lg:hidden">
                        <ScrollArea className="w-full">
                            {renderGrid(false)}
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                    </div>
                </>
            ) : (
                <ScrollArea className="w-full">
                    {renderGrid(false)}
                    <ScrollBar orientation="horizontal" />
                </ScrollArea>
            )}
        </div>
    );
}

// ─── Vista DÍA (con tabs activas/historial) ───────────────────────────────────

function VistaDia({
    reservas,
    ahora,
    es_hoy,
    filtroTipo,
    onFiltroToggle,
}: {
    reservas: Reserva[];
    ahora: string;
    es_hoy: boolean;
    filtroTipo: TipoReserva | null;
    onFiltroToggle: (tipo: TipoReserva) => void;
}) {
    const [pestaña, setPestaña] = useState<'activas' | 'historial'>(() => {
        try { return (localStorage.getItem('reservas_tab') as 'activas' | 'historial') ?? 'activas'; }
        catch { return 'activas'; }
    });

    function handleTabChange(tab: 'activas' | 'historial') {
        try { localStorage.setItem('reservas_tab', tab); } catch {}
        setPestaña(tab);
    }

    const reservasTipo      = useMemo(() => filtroTipo ? reservas.filter((r) => r.tipo === filtroTipo) : reservas, [reservas, filtroTipo]);
    const reservasActivas   = useMemo(() => reservasTipo.filter((r) => !isReservaVencida(r.hora, ahora)), [reservasTipo, ahora]);
    const reservasHistorial = useMemo(() => reservasTipo.filter((r) => isReservaVencida(r.hora, ahora)),  [reservasTipo, ahora]);
    const reservasTab       = pestaña === 'activas' ? reservasActivas : reservasHistorial;

    const proximas = useMemo(() => getProximasLlegadas(reservasActivas, ahora), [reservasActivas, ahora]);
    const grupos   = useMemo(() => groupByTurno(reservasTab), [reservasTab]);

    const totalCubiertos = useMemo(
        () => reservasTab.reduce((acc, r) => acc + extractPersonasMax(r.numero_personas), 0),
        [reservasTab],
    );

    const totalPersonasDia = useMemo(
        () => reservas.reduce((acc, r) => acc + extractPersonasMax(r.numero_personas), 0),
        [reservas],
    );

    return (
        <div className="flex flex-col gap-4">
            {/* Resumen total del día */}
            {reservas.length > 0 && (
                <div className="flex items-start justify-between gap-2 text-sm">
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="text-muted-foreground">{reservas.length} reservas</span>
                        <div className="flex items-center gap-2">
                            {(['RESTAURANTE', 'NINOS', 'GENERAL_EVT'] as TipoReserva[]).map((tipo) => {
                                const count = reservas.filter((r) => r.tipo === tipo).length;
                                if (!count) return null;
                                const activo = filtroTipo === tipo;
                                const opaco  = filtroTipo !== null && !activo;
                                return (
                                    <button
                                        key={tipo}
                                        type="button"
                                        onClick={() => onFiltroToggle(tipo)}
                                        className={`flex items-center gap-1 rounded-full transition-opacity ${opaco ? 'opacity-30' : 'opacity-100'}`}
                                    >
                                        <TipoBadge tipo={tipo} />
                                        <span className={`text-xs font-semibold ${TIPO_CONFIG[tipo].text}`}>{count}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                    <span className="shrink-0 text-muted-foreground">{totalPersonasDia} personas</span>
                </div>
            )}

            {/* Tabs activas / historial */}
            <div className="flex gap-1 rounded-lg border border-border bg-muted/40 p-1">
                {(['activas', 'historial'] as const).map((tab) => {
                    const count = tab === 'activas' ? reservasActivas.length : reservasHistorial.length;
                    const active = pestaña === tab;
                    return (
                        <button
                            key={tab}
                            onClick={() => handleTabChange(tab)}
                            className={`flex-1 rounded-md px-3 py-1.5 text-sm font-medium capitalize transition-colors ${
                                active ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {tab === 'activas' ? 'Activas' : 'Historial'}
                            {count > 0 && (
                                <span className={`ml-1.5 rounded-full px-1.5 py-0.5 text-xs ${
                                    active ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                }`}>
                                    {count}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {/* Sin reservas */}
            {reservasTab.length === 0 && (
                <div className="flex flex-1 flex-col items-center justify-center gap-2 py-12 text-center text-muted-foreground">
                    <Utensils className="h-10 w-10 opacity-30" />
                    {pestaña === 'activas' ? (
                        <>
                            <p className="text-lg font-medium">No hay reservas activas</p>
                            {reservasHistorial.length > 0 && (
                                <button onClick={() => handleTabChange('historial')} className="text-sm underline-offset-2 hover:underline">
                                    Ver historial del día ({reservasHistorial.length})
                                </button>
                            )}
                        </>
                    ) : (
                        <p className="text-lg font-medium">Sin historial para este día</p>
                    )}
                </div>
            )}

            {/* Próximas llegadas */}
            {pestaña === 'activas' && proximas.length > 0 && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                    <p className="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
                        Próximas llegadas (30 min)
                    </p>
                    <div className="flex flex-col gap-2">
                        {proximas.map((r) => {
                            const personas = extractPersonasMax(r.numero_personas);
                            const notas    = analyzeNotes(r.comentarios);
                            return (
                                <div key={r.id} className="flex items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-bold text-amber-900 dark:text-amber-200">{r.hora}</span>
                                        <span className="text-sm text-amber-800 dark:text-amber-300">{r.nombre}</span>
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
    );
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function Reservas({ reservas, fecha, ahora, es_hoy, vista, fechas_rango }: Props) {
    const [query, setQuery]           = useState('');
    const [dateHighlight, setDateHighlight] = useState(false);
    const [filtroTipo, setFiltroTipo] = useState<TipoReserva | null>(null);

    function toggleFiltroTipo(tipo: TipoReserva) {
        setFiltroTipo((prev) => (prev === tipo ? null : tipo));
    }
    const searchTimeout     = useRef<ReturnType<typeof setTimeout> | null>(null);
    const dateInputRef      = useRef<HTMLInputElement>(null);
    const highlightTimeout  = useRef<ReturnType<typeof setTimeout> | null>(null);

    const reservasFiltradas = useMemo(() => {
        if (!query.trim()) return reservas;
        const q = sinAcentos(query);
        return reservas.filter(
            (r) => sinAcentos(r.nombre).includes(q) || r.telefono.includes(q),
        );
    }, [reservas, query]);

    const pendingCount = useMemo(
        () => reservas.filter((r) => r.estado === 'PENDIENTE_CONFIRMACION').length,
        [reservas],
    );

    function handleConfirmAllToday() {
        if (!confirm(`¿Confirmar las ${pendingCount} reservas pendientes del día?`)) return;
        router.post('/reservas/confirm-all-today', { fecha }, { preserveScroll: true });
    }

    // Al montar: activa el highlight si venimos de "Ir a hoy"
    useEffect(() => {
        if (sessionStorage.getItem('reservas_highlight_today') !== '1') return;
        sessionStorage.removeItem('reservas_highlight_today');
        setDateHighlight(true);
    }, []);

    // Apaga el highlight después de 1.5s (en effect separado para sobrevivir el StrictMode double-invoke)
    useEffect(() => {
        if (!dateHighlight) return;
        const t = setTimeout(() => setDateHighlight(false), 1500);
        return () => clearTimeout(t);
    }, [dateHighlight]);

    // Al montar: si no hay ?vista en la URL, aplicar la preferencia guardada
    useEffect(() => {
        const saved = localStorage.getItem('reservas_vista') as Vista | null;
        const hasVistaParam = new URLSearchParams(window.location.search).has('vista');
        if (saved && !hasVistaParam && saved !== vista) {
            router.get('/reservas', { fecha, vista: saved }, { preserveState: false, replace: true });
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    function navigate(params: Record<string, string>) {
        router.get('/reservas', params, { preserveState: false, replace: true });
    }

    function handleFechaChange(nuevaFecha: string) {
        navigate({ fecha: nuevaFecha, vista });
    }

    function handleVistaChange(nuevaVista: Vista) {
        localStorage.setItem('reservas_vista', nuevaVista);
        navigate({ fecha, vista: nuevaVista });
    }

    function shiftFecha(pasos: number) {
        const dias = vista === 'quincena' ? 15 : vista === 'semana' ? 7 : 1;
        const d    = parseDateLocal(fecha);
        d.setDate(d.getDate() + pasos * dias);
        const shifted = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        navigate({ fecha: shifted, vista });
    }

    function goToToday() {
        sessionStorage.setItem('reservas_highlight_today', '1');
        navigate({ fecha: todayIso(), vista });
    }

    function handleSearch(value: string) {
        setQuery(value);
        if (value) setFiltroTipo(null);
        if (searchTimeout.current) clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(() => {}, 0);
    }

    const VISTAS: { key: Vista; label: string }[] = [
        { key: 'dia',      label: 'Día' },
        { key: 'semana',   label: 'Semana' },
        { key: 'quincena', label: '15 días' },
    ];

    return (
        <>
            <Head title="Reservas" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-y-auto p-4 md:p-6">

                {/* Header */}
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-2xl font-bold shrink-0">Reservas</h1>

                    <div className="flex items-center gap-2">
                        {/* Confirmar todas (solo vista día con pendientes) */}
                        {vista === 'dia' && pendingCount > 0 && (
                            <button
                                onClick={handleConfirmAllToday}
                                className="rounded-md px-2.5 py-1.5 text-sm font-medium bg-emerald-600 hover:bg-emerald-700 text-white transition-colors whitespace-nowrap"
                            >
                                Confirmar todas ({pendingCount})
                            </button>
                        )}

                        {/* Ir a hoy */}
                        <button
                            onClick={goToToday}
                            className="rounded-md px-2.5 py-1.5 text-sm font-medium border border-border bg-background hover:bg-accent/40 transition-colors whitespace-nowrap"
                        >
                            <span className="sm:hidden">Hoy</span>
                            <span className="hidden sm:inline">Ir a hoy</span>
                        </button>

                        {/* Navegación de fecha */}
                        <div className="flex items-center gap-1.5">
                            <button
                                onClick={() => shiftFecha(-1)}
                                className="rounded-md p-1.5 bg-black text-white hover:bg-neutral-700 dark:bg-neutral-400 dark:text-black dark:hover:bg-neutral-300"
                                title="Anterior"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => dateInputRef.current?.showPicker?.()}
                                className="rounded-md border border-border bg-background px-3 py-1.5 text-sm cursor-pointer hover:bg-accent/40 transition-colors select-none"
                            >
                                {parseDateLocal(fecha).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' })}
                            </button>
                            <input
                                ref={dateInputRef}
                                type="date"
                                value={fecha}
                                onChange={(e) => handleFechaChange(e.target.value)}
                                className="sr-only"
                            />
                            <button
                                onClick={() => shiftFecha(1)}
                                className="rounded-md p-1.5 bg-black text-white hover:bg-neutral-700 dark:bg-neutral-400 dark:text-black dark:hover:bg-neutral-300"
                                title="Siguiente"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                {/* Tabs de vista */}
                <div className="flex gap-1 rounded-lg border border-border bg-muted/40 p-1">
                    {VISTAS.map(({ key, label }) => (
                        <button
                            key={key}
                            onClick={() => handleVistaChange(key)}
                            className={`flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                vista === key
                                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-black shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
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

                {/* Contenido según vista */}
                {vista === 'dia' ? (
                    <div className="max-w-2xl mx-auto w-full">
                        <VistaDia
                            reservas={reservasFiltradas}
                            ahora={ahora}
                            es_hoy={es_hoy}
                            filtroTipo={filtroTipo}
                            onFiltroToggle={toggleFiltroTipo}
                        />
                    </div>
                ) : (
                    <VistaMultiDia
                        fechas_rango={fechas_rango}
                        reservas={reservasFiltradas}
                        ahora={ahora}
                        fullWidth={vista === 'semana'}
                        highlightToday={dateHighlight}
                        filtroTipo={filtroTipo}
                        onFiltroToggle={toggleFiltroTipo}
                    />
                )}
            </div>
        </>
    );
}

Reservas.layout = {
    breadcrumbs: [{ title: 'Reservas', href: '/reservas' }],
};
