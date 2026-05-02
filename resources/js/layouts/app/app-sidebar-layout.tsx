import { usePage } from '@inertiajs/react';

import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { LegalAgreementDialog } from '@/components/legal-agreement-dialog';
import type { AppLayoutProps, Auth } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <AppShell>
            <AppSidebar />
            <AppContent className="overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            {!auth.legal_agreement_accepted && <LegalAgreementDialog />}
        </AppShell>
    );
}
