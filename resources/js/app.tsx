import { createInertiaApp, router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { PanelNotificationsBanner } from '@/components/panel-notifications-banner';
import { TestToolbar } from '@/components/test-toolbar';
import { playNotificationSound } from '@/hooks/use-notification-sound';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

function ErrorToast({ message, details }: { message: string; details?: string }) {
    const [expanded, setExpanded] = useState(false);
    return (
        <div className="bg-red-50 border border-red-200 rounded-xl px-4 py-3 shadow-lg w-80">
            <div className="flex items-start gap-2">
                <p className="flex-1 text-sm text-red-800 leading-snug">
                    <span className="font-semibold">ups, tuvimos un error:</span>{' '}
                    <span>{message}</span>
                </p>
                {details && (
                    <button
                        onClick={() => setExpanded(v => !v)}
                        className="shrink-0 text-red-400 hover:text-red-600 transition-colors mt-0.5"
                        title={expanded ? 'Ocultar detalles' : 'Ver detalles'}
                    >
                        {expanded ? <ChevronUp className="size-4" /> : <ChevronDown className="size-4" />}
                    </button>
                )}
                <button
                    onClick={() => toast.dismiss('app-error')}
                    className="shrink-0 text-red-400 hover:text-red-600 transition-colors mt-0.5"
                    title="Cerrar"
                >
                    <X className="size-4" />
                </button>
            </div>
            {expanded && details && (
                <pre className="mt-2 text-[11px] text-red-700 bg-red-100 rounded-lg p-2 overflow-auto max-h-40 whitespace-pre-wrap font-mono select-all leading-relaxed">
                    {details}
                </pre>
            )}
        </div>
    );
}

router.on('httpException', (event) => {
    event.preventDefault();
    const response = (event as CustomEvent).detail?.response;
    const status   = response?.status ?? '?';
    const url      = response?.url ?? '';
    toast.custom(
        () => (
            <ErrorToast
                message={`${status}`}
                details={`HTTP ${status}\nURL: ${url}`}
            />
        ),
        { id: 'app-error', duration: Infinity },
    );
});

router.on('networkError', (event) => {
    event.preventDefault();
    const error   = (event as CustomEvent).detail?.error;
    const details = error instanceof Error ? error.message : String(error ?? '');
    toast.custom(
        () => <ErrorToast message="error de red" details={details || undefined} />,
        { id: 'app-error', duration: Infinity },
    );
});

function GlobalAlertPoller() {
    const prevCountRef = useRef<number | null>(null);

    useEffect(() => {
        const check = async () => {
            try {
                const res = await fetch('/inbox/alert-count', { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const { count } = await res.json() as { count: number };
                if (prevCountRef.current !== null && count > prevCountRef.current) {
                    playNotificationSound();
                }
                prevCountRef.current = count;
                window.dispatchEvent(new CustomEvent('inbox-alert-count', { detail: { count } }));
            } catch {}
        };
        check();
        const id = setInterval(check, 10_000);
        return () => clearInterval(id);
    }, []);

    return null;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'bot-simulator':
            case name === 'inbox':
            case name === 'bot-messages-unlock':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
                <PanelNotificationsBanner />
                <GlobalAlertPoller />
                <TestToolbar />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
