let nextId = 9000;

function isTestMode(): boolean {
    return (document.querySelector('meta[name="test-mode"]') as HTMLMetaElement)?.content === 'true';
}

function inject(tipo: string, payload: Record<string, unknown>) {
    window.dispatchEvent(new CustomEvent('test:inject-notification', {
        detail: { id: nextId++, tipo, payload, leida: false, created_at: new Date().toISOString() },
    }));
}

function clearAll() {
    window.dispatchEvent(new CustomEvent('test:clear-notifications'));
}

export function TestToolbar() {
    if (!isTestMode()) return null;

    return (
        <div className="fixed bottom-4 right-4 z-[200] flex flex-col gap-1.5 bg-yellow-50 border-2 border-yellow-400 rounded-xl shadow-xl px-3 py-2.5 text-xs font-mono">
            <p className="text-yellow-700 font-bold text-[10px] uppercase tracking-widest mb-0.5">Test Mode</p>

            <button
                onClick={() => inject('sector_alerta', {
                    mensaje: 'Alcanzamos el 70% de la capacidad en *Salón*. ¿Querés que informemos a quienes reservan que no hay más cupo?',
                    sector_key: 'salon',
                    sector_label: 'Salón',
                })}
                className="px-2 py-1 bg-orange-100 hover:bg-orange-200 text-orange-800 rounded-lg transition-colors text-left"
            >
                + Alerta sector (Salón)
            </button>

            <button
                onClick={() => inject('sector_alerta', {
                    mensaje: 'Alcanzamos el 70% de la capacidad en *Terraza*. ¿Querés que informemos a quienes reservan que no hay más cupo?',
                    sector_key: 'terraza',
                    sector_label: 'Terraza',
                })}
                className="px-2 py-1 bg-orange-100 hover:bg-orange-200 text-orange-800 rounded-lg transition-colors text-left"
            >
                + Alerta sector (Terraza)
            </button>

            <button
                onClick={() => inject('aviso_confirmar', {
                    mensaje: 'Tenés 2 reserva(s) de restaurante pendiente(s) de confirmación manual (#42, #43). Si no se confirman, se harán automáticamente en la próxima ejecución.',
                    cantidad: 2,
                })}
                className="px-2 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-900 rounded-lg transition-colors text-left"
            >
                + Aviso confirmar manual
            </button>

            <button
                onClick={() => inject('auto_confirm', {
                    mensaje: 'Se confirmaron automáticamente 2 reserva(s) de restaurante tras 20 hs sin confirmación manual (#42, #43).',
                    cantidad: 2,
                })}
                className="px-2 py-1 bg-green-100 hover:bg-green-200 text-green-800 rounded-lg transition-colors text-left"
            >
                + Auto-confirmación forzada
            </button>

            <button
                onClick={() => inject('job_error', {
                    mensaje: 'Error grave — Contactar programadores\n\nFalló el job de auto-confirmación de reservas.\nError: Connection refused.',
                })}
                className="px-2 py-1 bg-red-100 hover:bg-red-200 text-red-800 rounded-lg transition-colors text-left"
            >
                + Error de job
            </button>

            <button
                onClick={clearAll}
                className="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition-colors text-left mt-0.5"
            >
                Limpiar notifs
            </button>
        </div>
    );
}
