import { useSyncExternalStore } from 'react';

/**
 * Hook que devuelve `true` si la pestaña está visible (foco) y `false` cuando está oculta.
 * Usa Page Visibility API + useSyncExternalStore para integrar con React.
 */
export function usePageVisibility(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}

function subscribe(callback: () => void): () => void {
    if (typeof document === 'undefined') return () => {};
    document.addEventListener('visibilitychange', callback);
    window.addEventListener('focus', callback);
    window.addEventListener('blur', callback);
    return () => {
        document.removeEventListener('visibilitychange', callback);
        window.removeEventListener('focus', callback);
        window.removeEventListener('blur', callback);
    };
}

function getSnapshot(): boolean {
    if (typeof document === 'undefined') return true;
    return document.visibilityState === 'visible';
}

function getServerSnapshot(): boolean {
    return true;
}
