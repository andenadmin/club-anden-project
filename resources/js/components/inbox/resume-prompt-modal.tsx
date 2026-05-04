import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Caja de confirmación que se muestra cuando una conversación con `motivo_pausa = ASESOR_TAKEOVER`
 * tiene `next_resume_check_at <= now`. El asesor puede confirmar que ya resolvió (reanuda el bot)
 * o postergar 1h más. (§6.1.B)
 */
export function ResumePromptModal({
    open,
    numero,
    nombre,
    pausedAt,
    onClose,
}: {
    open: boolean;
    numero: string;
    nombre: string | null;
    pausedAt: string | null;
    onClose: () => void;
}) {
    const [busy, setBusy] = useState<null | 'resume' | 'snooze'>(null);

    const post = (action: 'resume' | 'snooze') => {
        if (busy) return;
        setBusy(action);
        router.post(`/inbox/${numero}/${action}`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setBusy(null);
                onClose();
            },
        });
    };

    const pausedRelative = pausedAt
        ? formatRelative(new Date(pausedAt))
        : null;

    return (
        <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>¿Resolviste la conversación con {nombre ?? numero}?</DialogTitle>
                    <DialogDescription>
                        {pausedRelative
                            ? `Pausaste el bot ${pausedRelative} para hablar con este cliente.`
                            : 'Pausaste el bot para hablar con este cliente.'}
                        {' '}¿Ya solucionaste su consulta?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button
                        onClick={() => post('snooze')}
                        disabled={!!busy}
                        className="text-sm font-medium px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 dark:border-neutral-600 dark:hover:bg-neutral-800 disabled:opacity-50"
                    >
                        {busy === 'snooze' ? 'Posponiendo…' : 'Todavía no'}
                    </button>
                    <button
                        onClick={() => post('resume')}
                        disabled={!!busy}
                        className="text-sm font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-2 rounded-lg disabled:opacity-50"
                    >
                        {busy === 'resume' ? 'Reanudando…' : 'Sí, reanudar bot'}
                    </button>
                </DialogFooter>

                <p className="text-[11px] text-muted-foreground mt-2">
                    Si no la cerrás, el bot se va a reanudar automáticamente cuando pasen 12 horas
                    desde la pausa, y le pedirá al usuario que empiece de nuevo.
                </p>
            </DialogContent>
        </Dialog>
    );
}

function formatRelative(date: Date): string {
    const diff = Date.now() - date.getTime();
    const mins = Math.floor(diff / 60_000);
    if (mins < 1) return 'recién';
    if (mins < 60) return `hace ${mins} min`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `hace ${hours} h`;
    const days = Math.floor(hours / 24);
    return `hace ${days} d`;
}
