import { usePage, Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { inboxUnreadTotal } = usePage().props as { inboxUnreadTotal: number };
    const [liveAlertCount, setLiveAlertCount] = useState<number | null>(null);

    useEffect(() => {
        const handler = (e: Event) => {
            setLiveAlertCount((e as CustomEvent<{ count: number }>).detail.count);
        };
        window.addEventListener('inbox-alert-count', handler);
        return () => window.removeEventListener('inbox-alert-count', handler);
    }, []);

    const displayCount = liveAlertCount !== null
        ? Math.max(liveAlertCount, inboxUnreadTotal)
        : inboxUnreadTotal;

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <Link
                href="/inbox"
                className="relative flex items-center justify-center rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                title="Bandeja de entrada"
            >
                <Bell className="size-4" />
                {displayCount > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-0.5 text-[10px] font-bold text-white">
                        {displayCount > 99 ? '99+' : displayCount}
                    </span>
                )}
            </Link>
        </header>
    );
}
