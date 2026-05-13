import { Link, usePage } from '@inertiajs/react';
import { Inbox, MessageCircle, MessagesSquare, PanelLeftClose, PanelLeftOpen, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [];

function CollapseToggle() {
    const { state, toggleSidebar } = useSidebar();
    const collapsed = state === 'collapsed';
    const Icon = collapsed ? PanelLeftOpen : PanelLeftClose;
    const label = collapsed ? 'Expandir menú' : 'Achicar menú';

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    onClick={toggleSidebar}
                    tooltip={label}
                    className="text-sidebar-foreground/70 hover:text-sidebar-foreground"
                >
                    <Icon />
                    <span>{label}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

export function AppSidebar() {
    const { inboxUnreadTotal, isSuperAdmin } = usePage().props as {
        inboxUnreadTotal?: number;
        isSuperAdmin?: boolean;
    };
    const inboxUnread = inboxUnreadTotal ?? 0;

    const mainNavItems: NavItem[] = [
        { title: 'Inbox',            href: '/inbox',         icon: Inbox,           badge: inboxUnread },
        { title: 'Bot Simulator',    href: '/bot',           icon: MessageCircle },
        { title: 'Mensajes del Bot', href: '/bot/messages',  icon: MessagesSquare },
        ...(isSuperAdmin ? [{ title: 'CRM Clientes', href: '/crm', icon: Users }] : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/bot" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <div className="px-2">
                <CollapseToggle />
            </div>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
