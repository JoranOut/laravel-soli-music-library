import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { useTranslation } from '@/hooks/use-translation';

export function useUnsavedChanges(isDirty: boolean) {
    const { t } = useTranslation();

    useEffect(() => {
        if (!isDirty) return;

        // Browser close / refresh
        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            e.preventDefault();
        };
        window.addEventListener('beforeunload', handleBeforeUnload);

        // Inertia navigation (links, router.get, etc.)
        // Only guard against GET navigations, not form submissions (POST/PUT/DELETE)
        const removeListener = router.on('before', (event) => {
            const method = event.detail.visit.method;
            if (method !== 'get') return;

            if (
                !window.confirm(
                    t(
                        'You have unsaved changes. Are you sure you want to leave this page?',
                    ),
                )
            ) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            removeListener();
        };
    }, [isDirty, t]);
}
