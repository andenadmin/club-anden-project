import { useEffect } from 'react';
import { usePageVisibility } from '@/hooks/use-page-visibility';

/**
 * Cuando la pestaña está en background y el contador es > 0, actualiza document.title con
 * `(N) ${baseTitle}` para que el asesor vea que hay novedades. Cuando vuelve la visibilidad,
 * restaura el título original.
 */
export function useTitleFlash(count: number): void {
    const visible = usePageVisibility();

    useEffect(() => {
        if (typeof document === 'undefined') return;
        const original = document.title.replace(/^\(\d+\)\s*/, '');

        if (!visible && count > 0) {
            document.title = `(${count}) ${original}`;
        } else {
            document.title = original;
        }

        return () => {
            document.title = document.title.replace(/^\(\d+\)\s*/, '');
        };
    }, [visible, count]);
}
