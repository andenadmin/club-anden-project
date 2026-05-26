import { useEffect, useRef, useState } from 'react';
import { X } from 'lucide-react';

interface PanelNotification {
    id: number;
    tipo: string;
    payload: {
        mensaje?: string;
        sector_key?: string;
        sector_label?: string;
        [key: string]: unknown;
    } | null;
    leida: boolean;
    created_at: string;
}

function NotificationItem({
    notification,
    onDismiss,
}: {
    notification: PanelNotification;
    onDismiss: (id: number) => void;
}) {
    const { id, tipo, payload } = notification;
    const mensaje = payload?.mensaje ?? '';

    const handleMarkRead = async () => {
        try {
            await fetch(`/panel-notifications/${id}/read`, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '' } });
        } catch {
            // best-effort
        }
        onDismiss(id);
    };

    const handleAction = async (accion: string) => {
        try {
            await fetch(`/panel-notifications/${id}/action`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '' },
                body: JSON.stringify({ accion }),
            });
        } catch {
            // best-effort
        }
        onDismiss(id);
    };

    const isError  = tipo === 'job_error';
    const isAviso  = tipo === 'aviso_confirmar';
    const isAlerta = tipo === 'sector_alerta';

    const icon = isError ? '🚨' : isAviso ? '⏳' : tipo === 'auto_confirm' ? '✅' : isAlerta ? '⚠️' : 'ℹ️';

    const borderColor = isError  ? 'border-red-400 bg-red-50'
                      : isAviso  ? 'border-yellow-400 bg-yellow-50'
                      : 'border-gray-200 bg-white';

    return (
        <div className={`flex items-start gap-3 border rounded-xl shadow-md px-4 py-3 w-full max-w-2xl ${borderColor}`}>
            <span className="text-lg shrink-0 mt-0.5">{icon}</span>
            <p className={`flex-1 text-sm leading-snug ${isError ? 'text-red-800 font-medium' : 'text-gray-800'}`}>{mensaje}</p>

            <div className="flex items-center gap-2 shrink-0 ml-2">
                {isAlerta ? (
                    <>
                        <div className="relative group">
                            <button
                                onClick={() => handleAction('informar')}
                                className="text-xs font-medium bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-lg transition-colors"
                            >
                                Sí, informar
                            </button>
                            <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 text-xs text-white bg-gray-800 rounded-lg px-3 py-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 text-center shadow-lg">
                                El sector se marcará como sin disponibilidad para nuevas reservas
                            </div>
                        </div>
                        <div className="relative group">
                            <button
                                onClick={() => handleAction('mantener')}
                                className="text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg transition-colors"
                            >
                                No, mantener
                            </button>
                            <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 text-xs text-white bg-gray-800 rounded-lg px-3 py-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 text-center shadow-lg">
                                El sector seguirá apareciendo como disponible
                            </div>
                        </div>
                    </>
                ) : isAviso ? (
                    <a
                        href="/reservas"
                        className="text-xs font-medium bg-yellow-200 hover:bg-yellow-300 text-yellow-900 px-3 py-1.5 rounded-lg transition-colors"
                        onClick={() => handleMarkRead()}
                    >
                        Ir a Reservas
                    </a>
                ) : (
                    <button
                        onClick={handleMarkRead}
                        className={`text-xs font-medium px-3 py-1.5 rounded-lg transition-colors ${
                            isError
                                ? 'bg-red-100 hover:bg-red-200 text-red-800'
                                : 'bg-green-100 hover:bg-green-200 text-green-700'
                        }`}
                    >
                        Entendido
                    </button>
                )}

                {!isError && (
                    <button
                        onClick={handleMarkRead}
                        className="text-gray-400 hover:text-gray-600 transition-colors ml-1"
                        title="Cerrar"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>
        </div>
    );
}

const TEST_MODE = import.meta.env.VITE_TEST_MODE === 'true';

export function PanelNotificationsBanner() {
    const [notifications, setNotifications] = useState<PanelNotification[]>([]);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const fetchNotifications = async () => {
        try {
            const res = await fetch('/panel-notifications', { headers: { 'Accept': 'application/json' } });
            if (res.ok) setNotifications(await res.json() as PanelNotification[]);
        } catch {
            // best-effort — no bloquear la UI
        }
    };

    useEffect(() => {
        if (!TEST_MODE) {
            fetchNotifications();
            intervalRef.current = setInterval(fetchNotifications, 30_000);
        }

        const onInject = (e: Event) => {
            const n = (e as CustomEvent).detail as PanelNotification;
            setNotifications(prev => [n, ...prev]);
        };
        const onClear = () => setNotifications([]);

        window.addEventListener('test:inject-notification', onInject);
        window.addEventListener('test:clear-notifications', onClear);

        return () => {
            if (intervalRef.current) clearInterval(intervalRef.current);
            window.removeEventListener('test:inject-notification', onInject);
            window.removeEventListener('test:clear-notifications', onClear);
        };
    }, []);

    const dismiss = (id: number) => {
        setNotifications(prev => prev.filter(n => n.id !== id));
    };

    if (notifications.length === 0) return null;

    return (
        <div
            className="fixed top-0 left-0 right-0 z-[100] flex flex-col items-center gap-2 pt-3 px-4 pointer-events-none"
        >
            {notifications.map(n => (
                <div key={n.id} className="pointer-events-auto w-full flex justify-center">
                    <NotificationItem notification={n} onDismiss={dismiss} />
                </div>
            ))}
        </div>
    );
}
