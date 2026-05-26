import { usePage, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { AlertCircle, AlertTriangle, CheckCircle, Clock, Info, X } from 'lucide-react';

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

    const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

    const handleMarkRead = async () => {
        try {
            await fetch(`/panel-notifications/${id}/read`, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': csrf() } });
        } catch { /* best-effort */ }
        onDismiss(id);
    };

    const handleAction = async (accion: string) => {
        try {
            await fetch(`/panel-notifications/${id}/action`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ accion }),
            });
        } catch { /* best-effort */ }
        onDismiss(id);
    };

    const handleGoToReservas = () => {
        handleMarkRead();
        const savedVista = (() => { try { return localStorage.getItem('reservas_vista') ?? 'dia'; } catch { return 'dia'; } })();
        router.visit(`/reservas?vista=${savedVista}`);
    };

    const isError  = tipo === 'job_error';
    const isAviso  = tipo === 'aviso_confirmar';
    const isAlerta = tipo === 'sector_alerta';
    const isOk     = tipo === 'auto_confirm';

    const Icon = isError ? AlertCircle : isAviso ? Clock : isAlerta ? AlertTriangle : isOk ? CheckCircle : Info;
    const iconColor = isError  ? 'text-red-600 dark:text-red-400'
                    : isAviso  ? 'text-yellow-600 dark:text-yellow-400'
                    : isAlerta ? 'text-yellow-500 dark:text-yellow-400'
                    : isOk     ? 'text-green-600 dark:text-green-400'
                    : 'text-blue-500';

    const cardStyle = isError  ? 'border-red-400 bg-red-50 dark:bg-red-950 dark:border-red-700'
                    : isAviso  ? 'border-yellow-400 bg-yellow-50 dark:bg-yellow-950 dark:border-yellow-600'
                    : isAlerta ? 'border-yellow-400 bg-yellow-50 dark:bg-yellow-950 dark:border-yellow-600'
                    : isOk     ? 'border-green-400 bg-green-50 dark:bg-green-950 dark:border-green-700'
                    : 'border-gray-200 bg-white dark:bg-neutral-900 dark:border-neutral-700';

    const textStyle = isError  ? 'text-red-800 dark:text-red-200 font-medium'
                    : isAviso  ? 'text-yellow-900 dark:text-yellow-100'
                    : isAlerta ? 'text-yellow-900 dark:text-yellow-100'
                    : isOk     ? 'text-green-800 dark:text-green-200'
                    : 'text-gray-800 dark:text-neutral-100';

    return (
        <div className={`flex items-start gap-3 border rounded-xl shadow-lg px-4 py-3 w-full max-w-xl ${cardStyle}`}>
            <Icon className={`size-5 shrink-0 mt-0.5 ${iconColor}`} />
            <p className={`flex-1 text-sm leading-snug ${textStyle}`}>{mensaje}</p>

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
                    <button
                        onClick={handleGoToReservas}
                        className="text-xs font-medium bg-yellow-200 hover:bg-yellow-300 text-yellow-900 border border-black dark:bg-yellow-800 dark:hover:bg-yellow-700 dark:text-yellow-100 dark:border-yellow-500 px-3 py-1.5 rounded-lg transition-colors"
                    >
                        Ir a Reservas
                    </button>
                ) : (
                    <button
                        onClick={handleMarkRead}
                        className={`text-xs font-medium px-3 py-1.5 rounded-lg transition-colors ${
                            isError ? 'bg-red-100 hover:bg-red-200 text-red-800 dark:bg-red-900 dark:hover:bg-red-800 dark:text-red-200'
                                    : 'bg-green-100 hover:bg-green-200 text-green-800 dark:bg-green-900 dark:hover:bg-green-800 dark:text-green-200'
                        }`}
                    >
                        Entendido
                    </button>
                )}

                {!isError && (
                    <button onClick={handleMarkRead} className="text-gray-400 hover:text-gray-600 dark:text-neutral-500 dark:hover:text-neutral-300 transition-colors ml-1" title="Cerrar">
                        <X className="size-4" />
                    </button>
                )}
            </div>
        </div>
    );
}

export function PanelNotificationsBanner() {
    const { testMode } = usePage().props as { testMode?: boolean };
    const [notifications, setNotifications] = useState<PanelNotification[]>([]);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const fetchNotifications = async () => {
        try {
            const res = await fetch('/panel-notifications', { headers: { 'Accept': 'application/json' } });
            if (res.ok) setNotifications(await res.json() as PanelNotification[]);
        } catch { /* best-effort */ }
    };

    useEffect(() => {
        if (!testMode) {
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

    const dismiss = (id: number) => setNotifications(prev => prev.filter(n => n.id !== id));

    if (notifications.length === 0) return null;

    return (
        <div className="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-3 px-4 pointer-events-none">
            {notifications.map(n => (
                <div key={n.id} className="pointer-events-auto w-full flex justify-center">
                    <NotificationItem notification={n} onDismiss={dismiss} />
                </div>
            ))}
        </div>
    );
}
