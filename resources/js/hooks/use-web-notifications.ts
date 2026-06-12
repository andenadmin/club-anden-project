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

export function fireWebNotification(
    title: string,
    options?: NotificationOptions & { url?: string },
): void {
    if (!SUPPORTED || Notification.permission !== 'granted') return;
    try {
        const { url, ...rest } = options ?? {};
        const n = new Notification(title, { icon: '/favicon.ico', ...rest });
        if (url) {
            n.onclick = () => {
                window.focus();
                window.location.href = url;
                n.close();
            };
        }
    } catch {}
}
