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
};

export default function Create({ orchestras, genreSuggestions, musicTypeSuggestions }: Props) {
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
                />
            </div>
        </AppLayout>
    );
}
