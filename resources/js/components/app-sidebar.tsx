import { Link, usePage } from '@inertiajs/react';
import {
    Globe,
    Library,
    Music,
    Rocket,
    Shield,
    ShoppingCart,
    SlidersHorizontal,
    User,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { LocaleSwitcher } from '@/components/locale-switcher';
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
} from '@/components/ui/sidebar';
import { useTranslation } from '@/hooks/use-translation';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const roles: string[] = auth.roles ?? [];
    const isEditor = roles.includes('admin') || roles.includes('muziekbeheer');
    const canSeeAllPieces = isEditor || roles.includes('dirigent');

    const navItems: NavItem[] = [
        {
            title: t('My Music'),
            href: '/',
            icon: Music,
        },
    ];

    if (canSeeAllPieces) {
        const orchestraChildren: NavItem[] = (auth.orchestras ?? []).map(
            (o) => ({
                title: o.abbreviation || o.name,
                href: `/muziekstukken?orchestra=${o.id}`,
            }),
        );

        navItems.push({
            title: t('All Pieces'),
            href: '/muziekstukken',
            icon: Library,
            ...(roles.includes('dirigent') && orchestraChildren.length > 0
                ? { children: orchestraChildren }
                : {}),
        });
    }

    const adminItems: NavItem[] = [];

    const permissions: string[] = auth.permissions ?? [];

    if (roles.includes('admin')) {
        adminItems.push({
            title: t('Roles & permissions'),
            href: '/admin/roles',
            icon: Shield,
        });
    }

    if (permissions.includes('manage-instrument-aliases')) {
        adminItems.push({
            title: t('Instrument aliases'),
            href: '/admin/instrument-aliases',
            icon: SlidersHorizontal,
        });
    }

    const footerNavItems: NavItem[] = [
        {
            title: 'soli.nl',
            href: 'https://soli.nl',
            icon: Globe,
        },
        {
            title: t('My page'),
            href: 'https://soli.nl/mijn-pagina',
            icon: User,
        },
        {
            title: t('Shop'),
            href: 'https://winkel.soli.nl',
            icon: ShoppingCart,
        },
        {
            title: 'dev.soli.nl',
            href: 'https://dev.soli.nl',
            icon: Rocket,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} label={t('Library')} />
                {adminItems.length > 0 && (
                    <NavMain items={adminItems} label={t('Admin')} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <LocaleSwitcher />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
