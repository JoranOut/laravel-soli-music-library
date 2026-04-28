import { Head } from '@inertiajs/react';
import { Heading } from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type { Orchestra } from '@/types/muziekstukken';
import PieceForm from './piece-form';

type Props = {
    orchestras: Orchestra[];
    genreSuggestions: string[];
    musicTypeSuggestions: string[];
    composerSuggestions: string[];
    arrangerSuggestions: string[];
    publisherSuggestions: string[];
    difficultySuggestions: string[];
};

export default function Create({
    orchestras,
    genreSuggestions,
    musicTypeSuggestions,
    composerSuggestions,
    arrangerSuggestions,
    publisherSuggestions,
    difficultySuggestions,
}: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('All Pieces'), href: '/muziekstukken' },
                { title: t('New piece'), href: '/muziekstukken/create' },
            ]}
        >
            <Head title={t('New piece')} />
            <div className="space-y-6 p-6">
                <Heading title={t('New piece')} />
                <PieceForm
                    orchestras={orchestras}
                    action="/muziekstukken"
                    method="post"
                    genreSuggestions={genreSuggestions}
                    musicTypeSuggestions={musicTypeSuggestions}
                    composerSuggestions={composerSuggestions}
                    arrangerSuggestions={arrangerSuggestions}
                    publisherSuggestions={publisherSuggestions}
                    difficultySuggestions={difficultySuggestions}
                />
            </div>
        </AppLayout>
    );
}
