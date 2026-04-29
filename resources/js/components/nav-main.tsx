import type { InertiaLinkProps } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { forwardRef } from 'react';
import type { ComponentPropsWithoutRef } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

const NavLink = forwardRef<
    HTMLAnchorElement,
    Omit<ComponentPropsWithoutRef<'a'>, 'href'> & {
        href: NonNullable<InertiaLinkProps['href']>;
        external?: boolean;
    }
>(function NavLink({ href, external, children, ...props }, ref) {
    if (external) {
        const url = typeof href === 'string' ? href : href.url;
        return (
            <a
                ref={ref}
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                {...props}
            >
                {children}
            </a>
        );
    }
    return (
        <Link ref={ref} href={href} className={props.className}>
            {children}
        </Link>
    );
});

export function NavMain({
    items = [],
    label = 'Platform',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.children && item.children.length > 0 ? (
                        <Collapsible
                            key={item.title}
                            asChild
                            defaultOpen={
                                isCurrentOrParentUrl(item.href) ||
                                item.children.some((child) =>
                                    isCurrentUrl(child.href),
                                )
                            }
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton
                                        tooltip={{
                                            children: item.title,
                                        }}
                                        isActive={isCurrentUrl(item.href)}
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        <SidebarMenuSubItem>
                                            <SidebarMenuSubButton
                                                asChild
                                                isActive={isCurrentUrl(
                                                    item.href,
                                                )}
                                            >
                                                <NavLink
                                                    href={item.href}
                                                    external={item.external}
                                                >
                                                    <span>
                                                        {item.allLabel ??
                                                            item.title}
                                                    </span>
                                                </NavLink>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                        {item.children.map((child) => (
                                            <SidebarMenuSubItem
                                                key={child.title}
                                            >
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={isCurrentUrl(
                                                        child.href,
                                                    )}
                                                >
                                                    <NavLink
                                                        href={child.href}
                                                        external={
                                                            child.external
                                                        }
                                                    >
                                                        <span>
                                                            {child.title}
                                                        </span>
                                                    </NavLink>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        ))}
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </Collapsible>
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <NavLink
                                    href={item.href}
                                    external={item.external}
                                >
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </NavLink>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
