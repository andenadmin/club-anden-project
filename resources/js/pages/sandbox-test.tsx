import { useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import {
    AlertTriangle,
    CalendarDays,
    CheckCircle2,
    FlaskConical,
    Loader2,
    MessageSquare,
    Trash2,
    UserPlus,
    Zap,
} from 'lucide-react';

// ─── Types ──────────────────────────────────────────────────────────────────

interface Sector {
    key: string;
    label: string;
    capacidad: number;
}

interface Props {
    sectores: Sector[];
}

type Status = 'idle' | 'loading' | 'ok' | 'error';

interface ScenarioState {
    status: Status;
    lines: string[];
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function csrf() {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

async function postJson(url: string, body: object) {
    const r = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
        },
        body: JSON.stringify(body),
    });
    const json = await r.json();
    if (!r.ok) throw new Error(json.error ?? `HTTP ${r.status}`);
    return json;
}

// ─── Card ────────────────────────────────────────────────────────────────────

function ScenarioCard({
    icon: Icon,
    title,
    description,
    color,
    scenario,
    extra,
    state,
    onRun,
}: {
    icon: React.ElementType;
    title: string;
    description: string;
    color: string;
    scenario: string;
    extra?: React.ReactNode;
    state: ScenarioState;
    onRun: (scenario: string) => void;
}) {
    const isLoading = state.status === 'loading';

    return (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
            <div className={`h-1 ${color}`} />
            <div className="p-5 flex-1 flex flex-col gap-3">
                <div className="flex items-start gap-3">
                    <div className={`mt-0.5 size-9 rounded-lg flex items-center justify-center shrink-0 ${color} bg-opacity-10`}>
                        <Icon className={`size-5 ${color.replace('bg-', 'text-')}`} />
                    </div>
                    <div className="min-w-0">
                        <p className="font-semibold text-gray-800 text-sm leading-snug">{title}</p>
                        <p className="text-xs text-gray-500 mt-0.5 leading-relaxed">{description}</p>
                    </div>
                </div>

                {extra && <div className="pl-12">{extra}</div>}

                {state.status !== 'idle' && (
                    <div
                        className={`rounded-lg p-3 text-xs font-mono leading-relaxed pl-12 ${
                            state.status === 'ok'
                                ? 'bg-emerald-50 text-emerald-800'
                                : state.status === 'error'
                                  ? 'bg-red-50 text-red-800'
                                  : 'bg-gray-50 text-gray-500'
                        }`}
                    >
                        {state.lines.map((line, i) => (
                            <p key={i}>{line}</p>
                        ))}
                    </div>
                )}

                <div className="pl-12 mt-auto pt-1">
                    <button
                        onClick={() => onRun(scenario)}
                        disabled={isLoading}
                        className="inline-flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-gray-700 active:scale-95 transition-all disabled:opacity-50"
                    >
                        {isLoading ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : state.status === 'ok' ? (
                            <CheckCircle2 className="size-3.5 text-emerald-400" />
                        ) : (
                            <Zap className="size-3.5" />
                        )}
                        {state.status === 'ok' ? 'Ejecutar de nuevo' : 'Ejecutar'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Page ────────────────────────────────────────────────────────────────────

export default function SandboxTest({ sectores }: Props) {
    const [states, setStates] = useState<Record<string, ScenarioState>>({});
    const [sectorConfirmada, setSectorConfirmada] = useState(sectores[0]?.key ?? 'salon');
    const [sectorPendiente, setSectorPendiente]   = useState(sectores[1]?.key ?? 'galeria');
    const [sectorAlerta, setSectorAlerta]          = useState(sectores[0]?.key ?? 'salon');

    const setLoading = (id: string) =>
        setStates(p => ({ ...p, [id]: { status: 'loading', lines: ['Ejecutando…'] } }));

    const setResult = (id: string, status: 'ok' | 'error', lines: string[]) =>
        setStates(p => ({ ...p, [id]: { status, lines } }));

    const run = async (scenario: string, extra?: object) => {
        setLoading(scenario);
        try {
            const data = await postJson(`/sandbox/run/${scenario}`, extra ?? {});
            setResult(scenario, 'ok', data.lines ?? ['Listo']);
        } catch (e) {
            setResult(scenario, 'error', [(e as Error).message]);
        }
    };

    const state = (id: string): ScenarioState => states[id] ?? { status: 'idle', lines: [] };

    const SectorSelect = ({ value, onChange }: { value: string; onChange: (v: string) => void }) => (
        <select
            value={value}
            onChange={e => onChange(e.target.value)}
            className="text-xs border border-gray-200 rounded-md px-2 py-1 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300"
        >
            {sectores.filter(s => s.capacidad > 0).map(s => (
                <option key={s.key} value={s.key}>
                    {s.label} (cap. {s.capacidad})
                </option>
            ))}
        </select>
    );

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <div className="max-w-5xl mx-auto px-4 py-8">
                    {/* Header */}
                    <div className="mb-8 flex items-center gap-3">
                        <div className="size-10 rounded-xl bg-violet-100 flex items-center justify-center">
                            <FlaskConical className="size-5 text-violet-600" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">Panel de Pruebas</h1>
                            <p className="text-sm text-gray-500">
                                Crea datos de prueba para testear las features del sistema.
                                Solo disponible fuera de produccion.
                            </p>
                        </div>
                    </div>

                    {/* Grid de escenarios */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">

                        <ScenarioCard
                            icon={CheckCircle2}
                            title="Reserva CONFIRMADA"
                            description="Crea un cliente, una reserva CONFIRMADA en el restaurante y una conversacion en Inbox con etiqueta 'confirmada' (verde)."
                            color="bg-emerald-500"
                            scenario="restaurante-confirmada"
                            state={state('restaurante-confirmada')}
                            onRun={() => run('restaurante-confirmada', { sector: sectorConfirmada })}
                            extra={
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-gray-500">Sector:</span>
                                    <SectorSelect value={sectorConfirmada} onChange={setSectorConfirmada} />
                                </div>
                            }
                        />

                        <ScenarioCard
                            icon={CalendarDays}
                            title="Reserva PENDIENTE"
                            description="Crea un cliente, una reserva PENDIENTE y una conversacion con etiqueta 'abierta' (borde ambar en la lista)."
                            color="bg-amber-500"
                            scenario="restaurante-pendiente"
                            state={state('restaurante-pendiente')}
                            onRun={() => run('restaurante-pendiente', { sector: sectorPendiente })}
                            extra={
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-gray-500">Sector:</span>
                                    <SectorSelect value={sectorPendiente} onChange={setSectorPendiente} />
                                </div>
                            }
                        />

                        <ScenarioCard
                            icon={UserPlus}
                            title="Escalado a Asesor"
                            description="Crea una conversacion PAUSADA donde el usuario pidio hablar con un asesor. Activa la notificacion de escalado."
                            color="bg-orange-500"
                            scenario="escalada"
                            state={state('escalada')}
                            onRun={() => run('escalada')}
                        />

                        <ScenarioCard
                            icon={AlertTriangle}
                            title="Alerta de Ocupacion"
                            description="Crea reservas CONFIRMADAS hasta superar el umbral de alerta del sector. Dispara la notificacion en el banner del Inbox."
                            color="bg-red-500"
                            scenario="sector-alerta"
                            state={state('sector-alerta')}
                            onRun={() => run('sector-alerta', { sector: sectorAlerta })}
                            extra={
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-gray-500">Sector:</span>
                                    <SectorSelect value={sectorAlerta} onChange={setSectorAlerta} />
                                </div>
                            }
                        />

                        <ScenarioCard
                            icon={MessageSquare}
                            title="Conversacion Multi-Dia"
                            description="Crea una conversacion con mensajes distribuidos en 4 dias distintos. Abri la conversacion en Inbox para ver los separadores de dia."
                            color="bg-blue-500"
                            scenario="multi-dias"
                            state={state('multi-dias')}
                            onRun={() => run('multi-dias')}
                        />

                    </div>

                    {/* Separador */}
                    <div className="border-t border-gray-200 my-6" />

                    {/* Limpiar todo */}
                    <div className="bg-red-50 border border-red-200 rounded-xl p-5 flex items-start gap-4">
                        <div className="size-9 rounded-lg bg-red-100 flex items-center justify-center shrink-0 mt-0.5">
                            <Trash2 className="size-5 text-red-600" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="font-semibold text-red-800 text-sm">Limpiar datos de prueba</p>
                            <p className="text-xs text-red-600 mt-0.5">
                                Elimina todos los clientes, reservas, sesiones y mensajes creados por este panel
                                (numeros que comienzan con 549118888...).
                            </p>
                            {state('clear').status !== 'idle' && (
                                <div className="mt-3 bg-white rounded-lg p-3 text-xs font-mono text-gray-700 border border-red-100">
                                    {state('clear').lines.map((line, i) => (
                                        <p key={i}>{line}</p>
                                    ))}
                                </div>
                            )}
                        </div>
                        <button
                            onClick={() => run('clear')}
                            disabled={state('clear').status === 'loading'}
                            className="shrink-0 inline-flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 active:scale-95 transition-all disabled:opacity-50"
                        >
                            {state('clear').status === 'loading' ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <Trash2 className="size-3.5" />
                            )}
                            Limpiar todo
                        </button>
                    </div>

                    {/* Tips */}
                    <div className="mt-6 rounded-xl border border-gray-100 bg-gray-50 p-5">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Guia de testeo</p>
                        <ul className="text-xs text-gray-600 space-y-1.5">
                            <li>
                                <span className="font-medium text-gray-800">Etiquetas:</span> Crea una reserva CONFIRMADA y una PENDIENTE. Ve al Inbox y verifica que la CONFIRMADA tiene badge verde y la PENDIENTE tiene borde ambar.
                            </li>
                            <li>
                                <span className="font-medium text-gray-800">Alerta de ocupacion:</span> Ejecuta "Alerta de Ocupacion". Ve al Inbox y confirma que aparece el banner ambar arriba.
                            </li>
                            <li>
                                <span className="font-medium text-gray-800">Separadores de dia:</span> Crea una "Conversacion Multi-Dia", abri la conversacion en Inbox y verifica los separadores "Hoy", "Ayer", etc.
                            </li>
                            <li>
                                <span className="font-medium text-gray-800">Escalado:</span> Crea un "Escalado a Asesor" y espera el polling (3 seg). Deberia aparecer la notificacion del navegador si tenes permisos activos.
                            </li>
                            <li>
                                <span className="font-medium text-gray-800">Bot simulador:</span> Ve al simulador del bot (<a href="/bot" className="underline text-blue-600">Bot Andén</a> en el menu lateral) para enviar mensajes como si fueras un usuario de WhatsApp.
                            </li>
                            <li>
                                <span className="font-medium text-gray-800">Pausa global:</span> Desde el Inbox, usa el boton de pausa (icono PauseCircle) en la barra superior para testear el banner de bot pausado.
                            </li>
                        </ul>
                    </div>
                </div>
            </AppContent>
        </AppShell>
    );
}
