import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Heading } from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import AppLayout from '@/layouts/app-layout';
import type { Orchestra } from '@/types/muziekstukken';
import PieceForm from './piece-form';

type Props = {
    orchestras: Orchestra[];
    nextArchiveNumber?: string;
    genreSuggestions: string[];
    musicTypeSuggestions: string[];
    composerSuggestions: string[];
    arrangerSuggestions: string[];
    publisherSuggestions: string[];
    difficultySuggestions: string[];
    boughtForOccasionSuggestions: string[];
};

export default function Create({
    orchestras,
    nextArchiveNumber,
    genreSuggestions,
    musicTypeSuggestions,
    composerSuggestions,
    arrangerSuggestions,
    publisherSuggestions,
    difficultySuggestions,
    boughtForOccasionSuggestions,
}: Props) {
    const { t } = useTranslation();
    const [formDirty, setFormDirty] = useState(false);

    useUnsavedChanges(formDirty);

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
                    defaultArchiveNumber={nextArchiveNumber}
                    onDirtyChange={setFormDirty}
                    genreSuggestions={genreSuggestions}
                    musicTypeSuggestions={musicTypeSuggestions}
                    composerSuggestions={composerSuggestions}
                    arrangerSuggestions={arrangerSuggestions}
                    publisherSuggestions={publisherSuggestions}
                    difficultySuggestions={difficultySuggestions}
                    boughtForOccasionSuggestions={boughtForOccasionSuggestions}
                />
            </div>
        </AppLayout>
    );
}
