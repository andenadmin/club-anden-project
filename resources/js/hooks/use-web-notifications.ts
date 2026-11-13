import { useCallback, useEffect, useState } from 'react';

const SUPPORTED = typeof window !== 'undefined' && 'Notification' in window;

export function useWebNotifications(): {
    permission: NotificationPermission;
    requestPermission: () => Promise<void>;
} {
    const [permission, setPermission] = useState<NotificationPermission>(
        SUPPORTED ? Notification.permission : 'denied',
    );

    useEffect(() => {
        if (!SUPPORTED) return;
        setPermission(Notification.permission);
    }, []);

    const requestPermission = useCallback(async () => {
        if (!SUPPORTED) return;
        const result = await Notification.requestPermission();
        setPermission(result);
    }, []);

    return { permission, requestPermission };
}

/**
 * Notificación del sistema operativo. Suena aunque la pestaña esté minimizada
 * (el sonido lo pone el OS, no el tab). Es el mismo mecanismo que usa WhatsApp Web
 * cuando no estás mirando la pestaña.
 */
export function fireWebNotification(
    title: string,
    options?: NotificationOptions & { url?: string },
): void {
    if (!SUPPORTED || Notification.permission !== 'granted') return;
    try {
        const { url, tag, ...rest } = options ?? {};
        const n = new Notification(title, {
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            // silent:false es el default, pero lo dejamos explícito.
            silent: false,
            // renotify+tag: si llega otra alerta con el mismo tag, vuelve a sonar.
            renotify: true,
            tag: tag ?? `anden-${Date.now()}`,
            ...rest,
        });
        n.onclick = () => {
            try { window.focus(); } catch {}
            if (url) window.location.href = url;
            n.close();
        };
    } catch {}
}
