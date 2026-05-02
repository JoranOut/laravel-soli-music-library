import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

export function LegalAgreementDialog() {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    function handleAgree() {
        setProcessing(true);
        router.post('/legal-agreement/accept', {}, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    function handleDisagree() {
        setProcessing(true);
        router.post('/auth/logout');
    }

    return (
        <Dialog open modal>
            <DialogContent
                onPointerDownOutside={(e) => e.preventDefault()}
                onEscapeKeyDown={(e) => e.preventDefault()}
                onInteractOutside={(e) => e.preventDefault()}
                className="[&>button:last-child]:hidden sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle>
                        {t('Music Library Terms of Use')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('Please read and accept the terms of use to continue.')}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 text-sm text-muted-foreground">
                    <ul className="list-disc space-y-2 pl-5">
                        <li>{t('The music in this library is legally purchased by and property of Muziekvereniging Soli. You may only use the documents for activities related to Muziekvereniging Soli.')}</li>
                        <li>{t('Distribution of documents to third parties is prohibited.')}</li>
                        <li>{t('You must delete all downloaded copies once you are no longer assigned to them.')}</li>
                        <li>{t('Violation of these terms may result in termination of your membership and/or legal action.')}</li>
                    </ul>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        variant="outline"
                        onClick={handleDisagree}
                        disabled={processing}
                    >
                        {t('I do not agree')}
                    </Button>
                    <Button
                        onClick={handleAgree}
                        disabled={processing}
                    >
                        {t('I agree')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
