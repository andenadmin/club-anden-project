/**
 * Barra de herramientas de prueba — solo visible cuando VITE_TEST_MODE=true.
 * Permite inyectar notificaciones y forzar estados de UI sin tocar la DB.
 */

const TEST_MODE = import.meta.env.VITE_TEST_MODE === 'true';

let nextId = 9000;

function inject(tipo: string, payload: Record<string, unknown>) {
    const event = new CustomEvent('test:inject-notification', {
        detail: { id: nextId++, tipo, payload, leida: false, created_at: new Date().toISOString() },
    });
    window.dispatchEvent(event);
}

function clearAll() {
    window.dispatchEvent(new CustomEvent('test:clear-notifications'));
}

export function TestToolbar() {
    if (!TEST_MODE) return null;

    return (
        <div className="fixed bottom-4 right-4 z-[200] flex flex-col gap-1.5 bg-yellow-50 border-2 border-yellow-400 rounded-xl shadow-xl px-3 py-2.5 text-xs font-mono">
            <p className="text-yellow-700 font-bold text-[10px] uppercase tracking-widest mb-0.5">🧪 Test Mode</p>

            <button
                onClick={() => inject('sector_alerta', {
                    mensaje: '⚠️ Alcanzamos el 100% de la capacidad en *Salón*. ¿Querés que informemos a quienes reservan que no hay más cupo?',
                    sector_key: 'salon',
                    sector_label: 'Salón',
                })}
                className="px-2 py-1 bg-orange-100 hover:bg-orange-200 text-orange-800 rounded-lg transition-colors text-left"
            >
                + Alerta sector (Salón)
            </button>

            <button
                onClick={() => inject('sector_alerta', {
                    mensaje: '⚠️ Alcanzamos el 100% de la capacidad en *Terraza*. ¿Querés que informemos a quienes reservan que no hay más cupo?',
                    sector_key: 'terraza',
                    sector_label: 'Terraza',
                })}
                className="px-2 py-1 bg-orange-100 hover:bg-orange-200 text-orange-800 rounded-lg transition-colors text-left"
            >
                + Alerta sector (Terraza)
            </button>

            <button
                onClick={() => inject('auto_confirm', {
                    mensaje: '✅ Se confirmaron automáticamente 3 reservas de restaurante para las próximas 24 hs.',
                })}
                className="px-2 py-1 bg-green-100 hover:bg-green-200 text-green-800 rounded-lg transition-colors text-left"
            >
                + Auto-confirmación
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
