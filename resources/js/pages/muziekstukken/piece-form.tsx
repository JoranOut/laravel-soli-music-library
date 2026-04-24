import { useForm, usePage } from '@inertiajs/react';
import { forwardRef, useImperativeHandle } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { Orchestra, Piece } from '@/types/muziekstukken';

export type PieceFormData = {
    title: string;
    composer: string;
    arranger: string;
    publisher: string;
    difficulty: string;
    notes: string;
    bought_for: string;
    buy_date: string;
    genre: string[];
    music_type: string;
    archive_number: string;
    orchestras: number[];
};

export type PieceFormHandle = {
    getData: () => PieceFormData;
};

type Props = {
    piece?: Piece;
    orchestras: Orchestra[];
    action?: string;
    method?: 'post' | 'put';
    canEditAllFields?: boolean;
    showOrchestraCheckboxes?: boolean;
    genreSuggestions?: string[];
    musicTypeSuggestions?: string[];
};

const PieceForm = forwardRef<PieceFormHandle, Props>(function PieceForm(
    { piece, orchestras, action, method, canEditAllFields = true, showOrchestraCheckboxes = true, genreSuggestions = [], musicTypeSuggestions = [] },
    ref,
) {
    const { t } = useTranslation();
    const pageErrors = usePage().props.errors ?? {};
    const { data, setData, post, put, processing } = useForm({
        title: piece?.title ?? '',
        composer: piece?.composer ?? '',
        arranger: piece?.arranger ?? '',
        publisher: piece?.publisher ?? '',
        difficulty: piece?.difficulty ?? '',
        notes: piece?.notes ?? '',
        bought_for: piece?.bought_for ?? '',
        buy_date: piece?.buy_date ?? '',
        genre: piece?.genre ?? ([] as string[]),
        music_type: piece?.music_type ?? '',
        archive_number: piece?.archive_number ?? '',
        orchestras: piece?.orchestras.map((o) => o.id) ?? ([] as number[]),
    });

    useImperativeHandle(ref, () => ({
        getData: () => data,
    }));

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!action || !method) return;
        if (method === 'post') {
            post(action);
        } else {
            put(action, { preserveScroll: true });
        }
    }

    function toggleOrchestra(id: number) {
        setData(
            'orchestras',
            data.orchestras.includes(id)
                ? data.orchestras.filter((o) => o !== id)
                : [...data.orchestras, id],
        );
    }

    function toggleGenre(genre: string) {
        setData(
            'genre',
            data.genre.includes(genre)
                ? data.genre.filter((g) => g !== genre)
                : [...data.genre, genre],
        );
    }

    // Merge suggestions with current selections to show all relevant options
    const allGenreOptions = [...new Set([...genreSuggestions, ...data.genre])].sort();

    const showSubmitButton = !!action;

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {canEditAllFields ? (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="title">{t('Title')} *</Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                required
                            />
                            {pageErrors.title && (
                                <p className="text-sm text-destructive">{pageErrors.title}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="composer">{t('Composer')}</Label>
                            <Input
                                id="composer"
                                value={data.composer}
                                onChange={(e) => setData('composer', e.target.value)}
                            />
                            {pageErrors.composer && (
                                <p className="text-sm text-destructive">{pageErrors.composer}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="arranger">{t('Arranger')}</Label>
                            <Input
                                id="arranger"
                                value={data.arranger}
                                onChange={(e) => setData('arranger', e.target.value)}
                            />
                            {pageErrors.arranger && (
                                <p className="text-sm text-destructive">{pageErrors.arranger}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="publisher">{t('Publisher')}</Label>
                            <Input
                                id="publisher"
                                value={data.publisher}
                                onChange={(e) => setData('publisher', e.target.value)}
                            />
                            {pageErrors.publisher && (
                                <p className="text-sm text-destructive">{pageErrors.publisher}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="difficulty">{t('Difficulty')}</Label>
                            <Input
                                id="difficulty"
                                value={data.difficulty}
                                onChange={(e) => setData('difficulty', e.target.value)}
                            />
                            {pageErrors.difficulty && (
                                <p className="text-sm text-destructive">{pageErrors.difficulty}</p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">{t('Notes')}</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                        />
                        {pageErrors.notes && (
                            <p className="text-sm text-destructive">{pageErrors.notes}</p>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="bought_for">{t('Bought for')}</Label>
                            <Input
                                id="bought_for"
                                value={data.bought_for}
                                onChange={(e) => setData('bought_for', e.target.value)}
                            />
                            {pageErrors.bought_for && (
                                <p className="text-sm text-destructive">{pageErrors.bought_for}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="buy_date">{t('Buy date')}</Label>
                            <Input
                                id="buy_date"
                                type="date"
                                value={data.buy_date}
                                onChange={(e) => setData('buy_date', e.target.value)}
                            />
                            {pageErrors.buy_date && (
                                <p className="text-sm text-destructive">{pageErrors.buy_date}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="archive_number">{t('Archive number')}</Label>
                            <Input
                                id="archive_number"
                                value={data.archive_number}
                                onChange={(e) => setData('archive_number', e.target.value)}
                            />
                            {pageErrors.archive_number && (
                                <p className="text-sm text-destructive">{pageErrors.archive_number}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="music_type">{t('Music type')}</Label>
                            <Combobox
                                options={musicTypeSuggestions.map((s) => ({ value: s, label: s }))}
                                value={data.music_type}
                                onChange={(v) => setData('music_type', v)}
                                placeholder={t('Music type')}
                                allowCustom
                            />
                            {pageErrors.music_type && (
                                <p className="text-sm text-destructive">{pageErrors.music_type}</p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>{t('Genre')}</Label>
                        {data.genre.length > 0 && (
                            <div className="flex flex-wrap gap-1">
                                {data.genre.map((g) => (
                                    <Badge
                                        key={g}
                                        variant="secondary"
                                        className="cursor-pointer"
                                        onClick={() => toggleGenre(g)}
                                    >
                                        {g} &times;
                                    </Badge>
                                ))}
                            </div>
                        )}
                        <Combobox
                            options={allGenreOptions
                                .filter((g) => !data.genre.includes(g))
                                .map((g) => ({ value: g, label: g }))}
                            value=""
                            onChange={(v) => {
                                const trimmed = v.trim();
                                if (trimmed && !data.genre.includes(trimmed)) {
                                    setData('genre', [...data.genre, trimmed]);
                                }
                            }}
                            placeholder={t('Add genre...')}
                            allowCustom
                        />
                        {pageErrors.genre && (
                            <p className="text-sm text-destructive">{pageErrors.genre}</p>
                        )}
                    </div>
                </>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1">
                        <Label>{t('Title')}</Label>
                        <p className="text-sm">{piece?.title || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Composer')}</Label>
                        <p className="text-sm">{piece?.composer || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Arranger')}</Label>
                        <p className="text-sm">{piece?.arranger || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Publisher')}</Label>
                        <p className="text-sm">{piece?.publisher || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Difficulty')}</Label>
                        <p className="text-sm">{piece?.difficulty || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Bought for')}</Label>
                        <p className="text-sm">{piece?.bought_for || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Buy date')}</Label>
                        <p className="text-sm">{piece?.buy_date || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Archive number')}</Label>
                        <p className="text-sm">{piece?.archive_number || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Music type')}</Label>
                        <p className="text-sm">{piece?.music_type || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Genre')}</Label>
                        <div className="flex flex-wrap gap-1">
                            {piece?.genre && piece.genre.length > 0
                                ? piece.genre.map((g) => (
                                    <Badge key={g} variant="secondary">{g}</Badge>
                                ))
                                : <p className="text-sm">-</p>
                            }
                        </div>
                    </div>
                </div>
            )}

            {showOrchestraCheckboxes && (
                <div className="space-y-2">
                    <Label>{t('In use by')}</Label>
                    <div className="flex flex-wrap gap-4">
                        {orchestras.map((orchestra) => (
                            <label
                                key={orchestra.id}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={data.orchestras.includes(orchestra.id)}
                                    onCheckedChange={() => toggleOrchestra(orchestra.id)}
                                />
                                {orchestra.name}
                            </label>
                        ))}
                    </div>
                    {pageErrors.orchestras && (
                        <p className="text-sm text-destructive">{pageErrors.orchestras}</p>
                    )}
                </div>
            )}

            {showSubmitButton && (
                <Button type="submit" disabled={processing}>
                    {t('Save')}
                </Button>
            )}
        </form>
    );
});

export default PieceForm;
