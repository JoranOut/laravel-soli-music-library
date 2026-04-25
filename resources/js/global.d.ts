/// <reference types="vite/client" />

import type { Auth } from '@/types';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            adminUrl: string;
            sidebarOpen: boolean;
            locale: string;
            translations: Record<string, string>;
            [key: string]: unknown;
        };
    }
}
