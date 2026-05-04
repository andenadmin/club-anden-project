import { router } from '@inertiajs/react';
import { Pause, Play, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import {
    ESTADO_LABELS,
    labelize,
    labelizeStep,
    MOTIVO_PAUSA_LABELS,
    RAMA_LABELS,
    SUBTIPO_LABELS,
} from '@/lib/session-labels';

export interface SessionData {
    estado_actual: string;
    rama_activa: string | null;
    subtipo_activo: string | null;
    current_step: string | null;
    motivo_pausa: string | null;
    estado_previo_pausa: string | null;
    datos_parciales: Record<string, unknown>;
    timestamp_pausa: string | null;
    next_resume_check_at: string | null;
    last_message_at: string | null;
}

export interface ClienteData {
    id: number;
    nombre_cliente: string | null;
    mail: string | null;
    contador_reservas_deportes: number;
    contador_reservas_restaurante: number;
    contador_reservas_eventos: number;
}

function timeAgo(iso: string | null): string {
    if (!iso) return '—';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60_000);
    if (mins < 1) return 'recién';
    if (mins < 60) return `hace ${mins} min`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `hace ${hours} h`;
    const days = Math.floor(hours / 24);
    return `hace ${days} d`;
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="mb-2.5">
            <p className="text-[9px] uppercase tracking-wider text-gray-400 mb-0.5">{label}</p>
            <p className="text-xs font-medium text-gray-700 dark:text-neutral-200">{value}</p>
        </div>
    );
}

function Pill({ children }: { children: React.ReactNode }) {
    return (
        <span className="inline-block bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 rounded px-2 py-0.5 text-xs font-medium">
            {children}
        </span>
    );
}

export function SessionSummary({
    numero,
    cliente,
    session,
}: {
    numero: string;
    cliente: ClienteData | null;
    session: SessionData;
}) {
    const [busy, setBusy] = useState<null | 'pause' | 'resume' | 'restart'>(null);
    const isPaused = session.estado_actual === 'PAUSADO';

    const post = (action: 'pause' | 'resume' | 'restart') => {
        if (busy) return;
        setBusy(action);
        router.post(`/inbox/${numero}/${action}`, {}, {
            preserveScroll: true,
            onFinish:       () => setBusy(null),
        });
    };

    const totalReservas = cliente
        ? cliente.contador_reservas_deportes + cliente.contador_reservas_restaurante + cliente.contador_reservas_eventos
        : 0;

    return (
        <div className="flex flex-col h-full bg-white dark:bg-neutral-900 border-l border-sidebar-border/50">
            <div className="flex-1 overflow-y-auto p-4">
                <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-3">Cliente</p>
                <Row label="Nombre"   value={cliente?.nombre_cliente ?? '—'} />
                <Row label="Teléfono" value={numero} />
                {cliente?.mail && <Row label="Mail" value={cliente.mail} />}
                <Row
                    label="Reservas previas"
                    value={
                        totalReservas === 0
                            ? '—'
                            : `${cliente!.contador_reservas_deportes} dep · ${cliente!.contador_reservas_restaurante} rest · ${cliente!.contador_reservas_eventos} evt`
                    }
                />

                <div className="border-t border-sidebar-border/50 my-4" />

                <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-3">Sesión</p>
                <Row label="Estado"        value={<Pill>{labelize(session.estado_actual, ESTADO_LABELS)}</Pill>} />
                {session.rama_activa && (
                    <Row label="Rama" value={<Pill>{labelize(session.rama_activa, RAMA_LABELS)}</Pill>} />
                )}
                {session.subtipo_activo && (
                    <Row label="Subtipo" value={<Pill>{labelize(session.subtipo_activo, SUBTIPO_LABELS)}</Pill>} />
                )}
                {session.current_step && (
                    <Row label="Paso pendiente" value={labelizeStep(session.current_step)} />
                )}

                {Object.keys(session.datos_parciales).length > 0 && (
                    <>
                        <div className="border-t border-sidebar-border/50 my-4" />
                        <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-3">
                            Datos recolectados
                        </p>
                        {Object.entries(session.datos_parciales).map(([k, v]) => (
                            <Row
                                key={k}
                                label={labelizeStep(k)}
                                value={String(v ?? '—')}
                            />
                        ))}
                    </>
                )}

                {isPaused && (
                    <>
                        <div className="border-t border-sidebar-border/50 my-4" />
                        <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-3">Pausa</p>
                        <Row
                            label="Motivo"
                            value={
                                <span className="inline-block bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 rounded px-2 py-0.5 text-xs font-medium">
                                    {labelize(session.motivo_pausa, MOTIVO_PAUSA_LABELS)}
                                </span>
                            }
                        />
                        <Row label="Pausado"            value={timeAgo(session.timestamp_pausa)} />
                        {session.estado_previo_pausa && (
                            <Row
                                label="Estado previo"
                                value={labelize(session.estado_previo_pausa, ESTADO_LABELS)}
                            />
                        )}
                    </>
                )}
            </div>

            <div className="p-3 border-t border-sidebar-border/50 flex flex-col gap-2">
                {isPaused ? (
                    <button
                        onClick={() => post('resume')}
                        disabled={!!busy}
                        className="w-full text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg py-2 transition-colors disabled:opacity-50 flex items-center justify-center gap-1.5"
                    >
                        <Play className="size-3.5" />
                        {busy === 'resume' ? 'Reanudando…' : 'Solucionado / Reanudar bot'}
                    </button>
                ) : (
                    <button
                        onClick={() => post('pause')}
                        disabled={!!busy}
                        className="w-full text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg py-2 transition-colors disabled:opacity-50 flex items-center justify-center gap-1.5"
                    >
                        <Pause className="size-3.5" />
                        {busy === 'pause' ? 'Pausando…' : 'Pausar bot y tomar el chat'}
                    </button>
                )}
                <button
                    onClick={() => {
                        if (confirm('¿Reiniciar la conversación? Se descartan los datos parciales y vuelve a INICIO.')) {
                            post('restart');
                        }
                    }}
                    disabled={!!busy}
                    className="w-full text-xs text-red-500 border border-red-200 rounded-lg py-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-50 flex items-center justify-center gap-1.5"
                >
                    <RotateCcw className="size-3" />
                    Reiniciar conversación
                </button>
            </div>
        </div>
    );
}
