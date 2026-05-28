import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'inbox_sound_muted';
const SOUND_URL   = '/notification.mp3';

export function playNotificationSound(): void {
    try {
        const muted = localStorage.getItem(STORAGE_KEY) === 'true';
        if (!muted) playDing();
    } catch {}
}

function playDing(): void {
    try {
        const audio = new Audio(SOUND_URL);
        audio.play().catch(() => {
            // El browser bloqueó la reproducción automática — silencioso
        });
    } catch {
        // Sin soporte — silencioso
    }
}

export function useNotificationSound(): {
    play:   () => void;
    muted:  boolean;
    toggle: () => void;
} {
    const [muted, setMuted] = useState<boolean>(() => {
        try { return localStorage.getItem(STORAGE_KEY) === 'true'; }
        catch { return false; }
    });

    useEffect(() => {
        try { localStorage.setItem(STORAGE_KEY, String(muted)); }
        catch {}
    }, [muted]);

    const play = useCallback(() => {
        if (!muted) playDing();
    }, [muted]);

    const toggle = useCallback(() => setMuted(m => !m), []);

    return { play, muted, toggle };
}
