import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { AlertCircle, AlertTriangle, Bell, CheckCircle, Clock, Info, X } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useWebNotifications } from '@/hooks/use-web-notifications';

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

// ─── Dialog para sector_alerta ────────────────────────────────────────────────

function SectorAlertaDialog({
    notification,
    onDismiss,
}: {
    notification: PanelNotification;
    onDismiss: (id: number) => void;
}) {
    const { id, payload } = notification;
    const mensaje = payload?.mensaje ?? '';
    const sectorLabel = payload?.sector_label ?? '';

    const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

    const handleAction = async (accion: string) => {
        if (id < 9000) {
            try {
                await fetch(`/panel-notifications/${id}/action`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ accion }),
                });
            } catch { /* best-effort */ }
        }
        onDismiss(id);
    };

    return (
        <Dialog open onOpenChange={() => handleAction('mantener')}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                        <AlertTriangle className="size-5 shrink-0" />
                        Alerta de capacidad — {sectorLabel}
                    </DialogTitle>
                    <DialogDescription className="text-sm text-foreground pt-1">
                        {mensaje}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
                    <button
                        onClick={() => handleAction('mantener')}
                        className="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium bg-secondary hover:bg-secondary/80 text-secondary-foreground transition-colors"
                    >
                        No, mantener disponible
                    </button>
                    <div className="relative group flex-1">
                        <button
                            onClick={() => handleAction('informar')}
                            className="w-full px-4 py-2.5 rounded-lg text-sm font-semibold bg-red-600 hover:bg-red-700 text-white transition-colors"
                        >
                            Sí, informar sin cupo
                        </button>
                        <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-72 text-xs text-white bg-gray-900 rounded-lg px-3 py-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 text-center shadow-lg">
                            El sector se marcará como sin disponibilidad en WhatsApp para nuevas reservas
                        </div>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ─── Banner genérico (aviso_confirmar, auto_confirm, job_error) ───────────────

function BannerItem({
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
        if (id < 9000) {
            try {
                await fetch(`/panel-notifications/${id}/read`, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': csrf() } });
            } catch { /* best-effort */ }
        }
        onDismiss(id);
    };

    const handleGoToReservas = () => {
        handleMarkRead();
        const savedVista = (() => { try { return localStorage.getItem('reservas_vista') ?? 'dia'; } catch { return 'dia'; } })();
        router.visit(`/reservas?vista=${savedVista}`);
    };

    const isError = tipo === 'job_error';
    const isAviso = tipo === 'aviso_confirmar';
    const isOk    = tipo === 'auto_confirm';

    const Icon = isError ? AlertCircle : isAviso ? Clock : isOk ? CheckCircle : Info;

    const iconColor = isError ? 'text-red-400'
                    : isAviso ? 'text-yellow-500 dark:text-yellow-400'
                    : isOk    ? 'text-green-500 dark:text-green-400'
                    : 'text-blue-400';

    const cardStyle = isError ? 'border-red-400 bg-red-50 dark:bg-red-950 dark:border-red-700'
                    : isAviso ? 'border-yellow-400 bg-yellow-50 dark:bg-yellow-950 dark:border-yellow-600'
                    : isOk    ? 'border-green-400 bg-green-50 dark:bg-green-950 dark:border-green-700'
                    : 'border-gray-200 bg-white dark:bg-neutral-900 dark:border-neutral-700';

    const textStyle = isError ? 'text-red-800 dark:text-red-200 font-medium'
                    : isAviso ? 'text-yellow-900 dark:text-yellow-100'
                    : isOk    ? 'text-green-800 dark:text-green-200'
                    : 'text-gray-800 dark:text-neutral-100';

    return (
        <div className={`flex items-start gap-3 border rounded-xl shadow-lg px-5 py-4 w-full max-w-xl ${cardStyle}`}>
            <Icon className={`size-5 shrink-0 mt-0.5 ${iconColor}`} />
            <p className={`flex-1 text-sm leading-snug ${textStyle}`}>{mensaje}</p>

            <div className="flex items-center gap-2 shrink-0 ml-2">
                {isAviso ? (
                    <button
                        onClick={handleGoToReservas}
                        className="text-xs font-medium bg-yellow-200 hover:bg-yellow-300 text-yellow-900 border border-black dark:bg-yellow-800 dark:hover:bg-yellow-700 dark:text-yellow-100 dark:border-yellow-500 px-3 py-1.5 rounded-lg transition-colors"
                    >
                        Ir a Reservas
                    </button>
                ) : (
                    <button
                        onClick={handleMarkRead}
                        className={`text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors ${
                            isError ? 'bg-red-100 hover:bg-red-200 text-red-800 border-red-400 dark:bg-red-900 dark:hover:bg-red-800 dark:text-red-200 dark:border-red-700'
                                    : 'bg-green-100 hover:bg-green-200 text-green-800 border-green-400 dark:bg-green-900 dark:hover:bg-green-800 dark:text-green-200 dark:border-green-700'
                        }`}
                    >
                        Entendido
                    </button>
                )}

                {!isError && (
                    <button
                        onClick={handleMarkRead}
                        className="size-6 flex items-center justify-center rounded-full bg-gray-500 hover:bg-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-400 transition-colors ml-1"
                        title="Cerrar"
                    >
                        <X className="size-3.5 text-white" />
                    </button>
                )}
            </div>
        </div>
    );
}

// ─── Dialog de permisos de notificaciones ────────────────────────────────────

const SUPPORTED = typeof window !== 'undefined' && 'Notification' in window;
const DISMISS_KEY = 'notif_perm_dismissed';
const DISMISS_TTL = 24 * 60 * 60 * 1000;

function wasDismissedRecently(): boolean {
    try {
        const ts = localStorage.getItem(DISMISS_KEY);
        return !!ts && Date.now() - parseInt(ts, 10) < DISMISS_TTL;
    } catch { return false; }
}

export function NotificationsPermissionDialog() {
    const [open, setOpen] = useState(false);
    const { permission, requestPermission } = useWebNotifications();

    useEffect(() => {
        if (!SUPPORTED || permission === 'granted') return;
        if (wasDismissedRecently()) return;
        const t = setTimeout(() => setOpen(true), 800);
        return () => clearTimeout(t);
    }, [permission]);

    if (!open || !SUPPORTED || permission === 'granted') return null;

    const isBlocked = permission === 'denied';

    const handleActivar = async () => {
        await requestPermission();
        setOpen(false);
    };

    const handleDismiss = () => {
        try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch {}
        setOpen(false);
    };

    return (
        <Dialog open={open} onOpenChange={handleDismiss}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Bell className="size-5 shrink-0" />
                        {isBlocked ? 'Notificaciones bloqueadas' : 'Activá las notificaciones'}
                    </DialogTitle>
                    <DialogDescription className="pt-2 text-sm leading-relaxed">
                        {isBlocked
                            ? 'Bloqueaste las notificaciones en este navegador. Para recibir alertas de nuevas reservas y mensajes sin respuesta, habilitarlas manualmente desde la configuración del sitio en tu navegador.'
                            : 'Sin notificaciones activadas no vas a recibir alertas de nuevas reservas ni avisos de mensajes sin respuesta, incluso si tenés otra pestaña abierta.'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="flex-col sm:flex-row gap-2 pt-2">
                    <button
                        onClick={handleDismiss}
                        className="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium bg-secondary hover:bg-secondary/80 text-secondary-foreground transition-colors"
                    >
                        Ahora no
                    </button>
                    {!isBlocked && (
                        <button
                            onClick={handleActivar}
                            className="flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold bg-primary hover:bg-primary/90 text-primary-foreground transition-colors"
                        >
                            Activar notificaciones
                        </button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ─── Root ─────────────────────────────────────────────────────────────────────

function isTestMode(): boolean {
    return (document.querySelector('meta[name="test-mode"]') as HTMLMetaElement)?.content === 'true';
}

export function PanelNotificationsBanner() {
    const [notifications, setNotifications] = useState<PanelNotification[]>([]);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const fetchNotifications = async () => {
        try {
            const res = await fetch('/panel-notifications', { headers: { 'Accept': 'application/json' } });
            if (res.ok) setNotifications(await res.json() as PanelNotification[]);
        } catch { /* best-effort */ }
    };

    useEffect(() => {
        if (!isTestMode()) {
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

    const alertas  = notifications.filter(n => n.tipo === 'sector_alerta');
    const banners  = notifications.filter(n => n.tipo !== 'sector_alerta');

    if (notifications.length === 0) return null;

    return (
        <>
            {/* Dialogs modales para sector_alerta (uno a la vez, el primero) */}
            {alertas[0] && (
                <SectorAlertaDialog notification={alertas[0]} onDismiss={dismiss} />
            )}

            {/* Banners arriba para el resto */}
            {banners.length > 0 && (
                <div className="fixed top-3 left-0 right-0 z-[100] flex flex-col items-center gap-2 px-4 pointer-events-none">
                    {banners.map(n => (
                        <div key={n.id} className="pointer-events-auto w-full flex justify-center">
                            <BannerItem notification={n} onDismiss={dismiss} />
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
