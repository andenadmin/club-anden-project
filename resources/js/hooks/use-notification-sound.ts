import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'inbox_sound_muted';
const SOUND_URL   = '/notification.mp3';

/**
 * El browser bloquea Audio en pestañas en background a menos que el AudioContext
 * haya sido "desbloqueado" con un gesto del usuario (click/tecla). WhatsApp Web
 * hace lo mismo: una vez unlocked, el ding suena aunque la pestaña esté minimizada.
 */
let audioCtx: AudioContext | null = null;
let decodedBuffer: AudioBuffer | null = null;
let unlockInstalled = false;
let decodePromise: Promise<void> | null = null;

function getAudioContext(): AudioContext | null {
    if (typeof window === 'undefined') return null;
    if (audioCtx) return audioCtx;
    try {
        const Ctx = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
        if (!Ctx) return null;
        audioCtx = new Ctx();
        return audioCtx;
    } catch {
        return null;
    }
}

async function ensureDecoded(ctx: AudioContext): Promise<AudioBuffer | null> {
    if (decodedBuffer) return decodedBuffer;
    if (!decodePromise) {
        decodePromise = (async () => {
            try {
                const res = await fetch(SOUND_URL);
                const raw = await res.arrayBuffer();
                decodedBuffer = await ctx.decodeAudioData(raw.slice(0));
            } catch {
                decodedBuffer = null;
            }
        })();
    }
    await decodePromise;
    return decodedBuffer;
}

function unlockAudio(): void {
    const ctx = getAudioContext();
    if (!ctx) return;

    if (ctx.state === 'suspended') {
        void ctx.resume();
    }

    // Precargar el ding mientras el gesto todavía "autoriza" la sesión de audio.
    void ensureDecoded(ctx);

    // Keep-alive casi silencioso: algunos browsers suspenden el context si no hay
    // nada reproduciéndose cuando la pestaña pasa a background.
    try {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        gain.gain.value = 0.00001;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        // Se detiene solo; el context ya quedó marcado como "en uso".
        osc.stop(ctx.currentTime + 0.05);
    } catch {
        // ignore
    }
}

function installGestureUnlock(): void {
    if (unlockInstalled || typeof window === 'undefined') return;
    unlockInstalled = true;

    const handler = () => unlockAudio();
    window.addEventListener('pointerdown', handler, { capture: true });
    window.addEventListener('keydown', handler, { capture: true });

    // Si el usuario vuelve a la pestaña, re-resume por si el browser lo suspendió.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            const ctx = audioCtx;
            if (ctx?.state === 'suspended') void ctx.resume();
        }
    });
}

function playViaWebAudio(): boolean {
    const ctx = getAudioContext();
    if (!ctx || ctx.state !== 'running' || !decodedBuffer) return false;

    try {
        const src = ctx.createBufferSource();
        src.buffer = decodedBuffer;
        src.connect(ctx.destination);
        src.start(0);
        return true;
    } catch {
        return false;
    }
}

function playViaHtmlAudio(): void {
    try {
        const audio = new Audio(SOUND_URL);
        void audio.play().catch(() => {
            // Autoplay bloqueado — el Notification API cubre el caso background.
        });
    } catch {
        // Sin soporte
    }
}

function playDing(): void {
    installGestureUnlock();

    // Intentar resume por si llegó un alert justo al volver a foco.
    const ctx = getAudioContext();
    if (ctx?.state === 'suspended') {
        void ctx.resume().then(() => {
            if (!playViaWebAudio()) playViaHtmlAudio();
        });
        // Mientras tanto intentar HTML Audio (funciona en foreground).
        playViaHtmlAudio();
        return;
    }

    if (!playViaWebAudio()) {
        // Buffer todavía no listo: disparar decode y caer a HTML Audio.
        if (ctx) void ensureDecoded(ctx);
        playViaHtmlAudio();
    }
}

export function playNotificationSound(): void {
    try {
        const muted = localStorage.getItem(STORAGE_KEY) === 'true';
        if (!muted) playDing();
    } catch {
        playDing();
    }
}

/** Instalar listeners de unlock lo antes posible (llamar desde el boot de la app). */
export function initNotificationSoundUnlock(): void {
    installGestureUnlock();
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
        installGestureUnlock();
    }, []);

    useEffect(() => {
        try { localStorage.setItem(STORAGE_KEY, String(muted)); }
        catch {}
    }, [muted]);

    const play = useCallback(() => {
        if (!muted) playDing();
    }, [muted]);

    const toggle = useCallback(() => {
        setMuted(m => {
            // Al reactivar el sonido, el click del toggle ya es un gesto → unlock.
            if (m) unlockAudio();
            return !m;
        });
    }, []);

    return { play, muted, toggle };
}
