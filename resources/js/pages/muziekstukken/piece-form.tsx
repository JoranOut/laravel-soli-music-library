import { useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { forwardRef, useEffect, useImperativeHandle } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
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
    bought_for_occasion: string;
    buy_date: string;
    genre: string[];
    music_type: string;
    archive_number: string;
    status: string;
    audio_youtube_url: string;
    orchestras: number[];
};

export type PieceFormHandle = {
    getData: () => PieceFormData;
    isDirty: boolean;
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
    composerSuggestions?: string[];
    arrangerSuggestions?: string[];
    publisherSuggestions?: string[];
    difficultySuggestions?: string[];
    boughtForOccasionSuggestions?: string[];
    onDirtyChange?: (dirty: boolean) => void;
    onStatusChange?: (status: string) => void;
    renderAudioMp3?: ReactNode;
};

const PieceForm = forwardRef<PieceFormHandle, Props>(function PieceForm(
    {
        piece,
        orchestras,
        action,
        method,
        canEditAllFields = true,
        showOrchestraCheckboxes = true,
        genreSuggestions = [],
        musicTypeSuggestions = [],
        composerSuggestions = [],
        arrangerSuggestions = [],
        publisherSuggestions = [],
        difficultySuggestions = [],
        boughtForOccasionSuggestions = [],
        onDirtyChange,
        onStatusChange,
        renderAudioMp3,
    },
    ref,
) {
    const { t } = useTranslation();
    const pageErrors = usePage().props.errors ?? {};

    const { data, setData, post, put, processing, isDirty } = useForm({
        title: piece?.title ?? '',
        composer: piece?.composer ?? '',
        arranger: piece?.arranger ?? '',
        publisher: piece?.publisher ?? '',
        difficulty: piece?.difficulty ?? '',
        notes: piece?.notes ?? '',
        bought_for: piece?.bought_for ?? '',
        bought_for_occasion: piece?.bought_for_occasion ?? '',
        buy_date: piece?.buy_date ?? '',
        genre: piece?.genre ?? ([] as string[]),
        music_type: piece?.music_type ?? '',
        archive_number: piece?.archive_number ?? '',
        status: piece?.status ?? 'besteld',
        audio_youtube_url: piece?.audio_youtube_url ?? '',
        orchestras: piece?.orchestras.map((o) => o.id) ?? ([] as number[]),
    });

    useImperativeHandle(ref, () => ({
        getData: () => data,
        isDirty,
    }));

    useEffect(() => {
        onDirtyChange?.(isDirty);
    }, [isDirty, onDirtyChange]);

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
    const allGenreOptions = [
        ...new Set([...genreSuggestions, ...data.genre]),
    ].sort();

    const showSubmitButton = !!action;

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {canEditAllFields ? (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="status">
                                {t('Status')}
                            </Label>
                            <Select
                                id="status"
                                value={data.status}
                                onChange={(e) => {
                                    setData('status', e.target.value);
                                    onStatusChange?.(e.target.value);
                                }}
                            >
                                <option value="besteld">{t('Besteld')}</option>
                                <option value="analoog">{t('Analoog')}</option>
                                <option value="digitaal">{t('Digitaal')}</option>
                            </Select>
                            {pageErrors.status && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.status}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="title">{t('Title')} *</Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) =>
                                    setData('title', e.target.value)
                                }
                                required
                            />
                            {pageErrors.title && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.title}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="composer">{t('Composer')}</Label>
                            <Combobox
                                options={composerSuggestions.map((s) => ({
                                    value: s,
                                    label: s,
                                }))}
                                value={data.composer}
                                onChange={(v) => setData('composer', v)}
                                placeholder={t('Composer')}
                                allowCustom
                            />
                            {pageErrors.composer && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.composer}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="arranger">{t('Arranger')}</Label>
                            <Combobox
                                options={arrangerSuggestions.map((s) => ({
                                    value: s,
                                    label: s,
                                }))}
                                value={data.arranger}
                                onChange={(v) => setData('arranger', v)}
                                placeholder={t('Arranger')}
                                allowCustom
                            />
                            {pageErrors.arranger && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.arranger}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="publisher">{t('Publisher')}</Label>
                            <Combobox
                                options={publisherSuggestions.map((s) => ({
                                    value: s,
                                    label: s,
                                }))}
                                value={data.publisher}
                                onChange={(v) => setData('publisher', v)}
                                placeholder={t('Publisher')}
                                allowCustom
                            />
                            {pageErrors.publisher && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.publisher}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="difficulty">
                                {t('Difficulty')}
                            </Label>
                            <Combobox
                                options={difficultySuggestions.map((s) => ({
                                    value: s,
                                    label: s,
                                }))}
                                value={data.difficulty}
                                onChange={(v) => setData('difficulty', v)}
                                placeholder={t('Difficulty')}
                                allowCustom
                            />
                            {pageErrors.difficulty && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.difficulty}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="music_type">
                                {t('Music type')}
                            </Label>
                            <Combobox
                                options={musicTypeSuggestions.map((s) => ({
                                    value: s,
                                    label: s,
                                }))}
                                value={data.music_type}
                                onChange={(v) => setData('music_type', v)}
                                placeholder={t('Music type')}
                                allowCustom
                            />
                            {pageErrors.music_type && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.music_type}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="bought_for">
                                {t('Bought for')}
                            </Label>
                            <Input
                                id="bought_for"
                                value={data.bought_for}
                                onChange={(e) =>
                                    setData('bought_for', e.target.value)
                                }
                            />
                            {pageErrors.bought_for && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.bought_for}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="bought_for_occasion">
                                {t('Bought for occasion')}
                            </Label>
                            <Combobox
                                options={boughtForOccasionSuggestions.map((s) => ({
                                    value: s,
                                    label: s,
                                }))}
                                value={data.bought_for_occasion}
                                onChange={(v) => setData('bought_for_occasion', v)}
                                placeholder={t('Bought for occasion')}
                                allowCustom
                            />
                            {pageErrors.bought_for_occasion && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.bought_for_occasion}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="buy_date">{t('Buy date')}</Label>
                            <Input
                                id="buy_date"
                                type="date"
                                value={data.buy_date}
                                onChange={(e) =>
                                    setData('buy_date', e.target.value)
                                }
                            />
                            {pageErrors.buy_date && (
                                <p className="text-sm text-destructive">
                                    {pageErrors.buy_date}
                                </p>
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
                            <p className="text-sm text-destructive">
                                {pageErrors.notes}
                            </p>
                        )}
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
                            onEnterCustom={(v) => {
                                if (!data.genre.includes(v)) {
                                    setData('genre', [...data.genre, v]);
                                }
                            }}
                            placeholder={t('Add genre...')}
                        />
                        {pageErrors.genre && (
                            <p className="text-sm text-destructive">
                                {pageErrors.genre}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label>YouTube</Label>
                        <Input
                            value={data.audio_youtube_url}
                            onChange={(e) =>
                                setData(
                                    'audio_youtube_url',
                                    e.target.value,
                                )
                            }
                            placeholder="https://www.youtube.com/watch?v=..."
                        />
                        {pageErrors.audio_youtube_url && (
                            <p className="text-sm text-destructive">
                                {pageErrors.audio_youtube_url}
                            </p>
                        )}
                    </div>

                    {piece && renderAudioMp3 && (
                        <div className="space-y-2">
                            <Label>MP3</Label>
                            {renderAudioMp3}
                        </div>
                    )}
                    {!piece && (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'MP3 audio and parts can be added after saving.',
                            )}
                        </p>
                    )}
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
                        <Label>{t('Bought for occasion')}</Label>
                        <p className="text-sm">{piece?.bought_for_occasion || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Buy date')}</Label>
                        <p className="text-sm">{piece?.buy_date || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Archive number')}</Label>
                        <p className="text-sm">
                            {piece?.archive_number || '-'}
                        </p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Music type')}</Label>
                        <p className="text-sm">{piece?.music_type || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Status')}</Label>
                        <p className="text-sm">{piece?.status || '-'}</p>
                    </div>
                    <div className="space-y-1">
                        <Label>{t('Genre')}</Label>
                        <div className="flex flex-wrap gap-1">
                            {piece?.genre && piece.genre.length > 0 ? (
                                piece.genre.map((g) => (
                                    <Badge key={g} variant="secondary">
                                        {g}
                                    </Badge>
                                ))
                            ) : (
                                <p className="text-sm">-</p>
                            )}
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
                                    checked={data.orchestras.includes(
                                        orchestra.id,
                                    )}
                                    onCheckedChange={() =>
                                        toggleOrchestra(orchestra.id)
                                    }
                                />
                                {orchestra.name}
                            </label>
                        ))}
                    </div>
                    {pageErrors.orchestras && (
                        <p className="text-sm text-destructive">
                            {pageErrors.orchestras}
                        </p>
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
