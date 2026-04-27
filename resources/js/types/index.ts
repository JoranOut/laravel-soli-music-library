import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    external?: boolean;
    children?: NavItem[];
    allLabel?: string;
};

export type User = {
    id: number;
    name: string;
    email: string;
};

export type Auth = {
    user: User | null;
    roles: string[];
    assignments: { onderdeel_id: number; instrument_soort: string }[];
    permissions: string[];
    orchestras: { id: number; name: string; abbreviation: string }[];
};

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};
