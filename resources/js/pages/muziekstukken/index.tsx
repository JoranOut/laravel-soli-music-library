import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    Filter,
    Pause,
    Play,
    Plus,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DateView } from '@/components/date-view';
import { Heading } from '@/components/heading';
import { YouTubeIcon } from '@/components/icons/youtube';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Combobox } from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAudioPlayer } from '@/hooks/use-audio-player';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type {
    InstrumentType,
    Orchestra,
    PaginatedData,
    Piece,
} from '@/types/muziekstukken';

const INSTRUMENTS_STORAGE_KEY = 'muziekstukken-instruments-filter';

function groupByFamily(types: InstrumentType[]) {
    const map = new Map<string, InstrumentType[]>();
    for (const t of types) {
        const name = t.instrument_family?.name ?? '';
        if (!map.has(name)) map.set(name, []);
        map.get(name)!.push(t);
    }
    return Array.from(map.entries())
        .map(([name, types]) => ({
            name,
            types: types.toSorted((a, b) => a.name.localeCompare(b.name)),
        }))
        .toSorted((a, b) => a.name.localeCompare(b.name));
}

type Props = {
    pieces: PaginatedData<Piece>;
    orchestras: Orchestra[];
    instrumentTypes: InstrumentType[];
    filterOptions: {
        composers: string[];
        arrangers: string[];
        publishers: string[];
        musicTypes: string[];
        genres: string[];
        difficulties: string[];
        boughtFors: string[];
        boughtForOccasions: string[];
    };
    filters: {
        search?: string;
        orchestra?: string;
        instruments?: string;
        composer?: string;
        arranger?: string;
        publisher?: string;
        music_type?: string;
        genre?: string;
        difficulty?: string;
        bought_for?: string;
        bought_for_occasion?: string;
        buy_date_from?: string;
        buy_date_to?: string;
    };
    canEdit: boolean;
    canEditUsages: boolean;
};

export default function Index({
    pieces,
    orchestras,
    instrumentTypes,
    filterOptions,
    filters,
    canEdit,
    canEditUsages,
}: Props) {
    const { t } = useTranslation();
    const { isPlaying, isCurrentTrack, toggle } = useAudioPlayer();
    const [usageDialogPieceId, setUsageDialogPieceId] = useState<number | null>(
        null,
    );
    const [usageForm, setUsageForm] = useState({
        orchestra_id: '',
        van: '',
        tot: '',
        details: '',
    });
    const [submitting, setSubmitting] = useState(false);

    // Instruments filter state
    const [selectedInstruments, setSelectedInstruments] = useState<number[]>(
        () => {
            try {
                const stored = localStorage.getItem(INSTRUMENTS_STORAGE_KEY);
                return stored ? JSON.parse(stored) : [];
            } catch {
                return [];
            }
        },
    );
    const [instrumentDialogOpen, setInstrumentDialogOpen] = useState(false);
    const [dialogSelection, setDialogSelection] = useState<number[]>([]);

    // More filters toggle — auto-expand when any are active
    const [showMoreFilters, setShowMoreFilters] = useState(
        () =>
            !!(
                filters.composer ||
                filters.arranger ||
                filters.publisher ||
                filters.music_type ||
                filters.genre ||
                filters.difficulty ||
                filters.bought_for ||
                filters.bought_for_occasion ||
                filters.buy_date_from ||
                filters.buy_date_to
            ),
    );

    // Navigate with filters, preserving all current filters
    function navigate(overrides: Record<string, string | undefined>) {
        const params: Record<string, string | undefined> = {};
        for (const [key, value] of Object.entries({
            ...filters,
            ...overrides,
        })) {
            params[key] = value || undefined;
        }
        router.get('/muziekstukken', params, {
            preserveState: true,
            replace: true,
        });
    }

    // On first load: sync localStorage → URL if URL has no instruments param
    const initialSyncDone = useRef(false);
    useEffect(() => {
        if (initialSyncDone.current) return;
        initialSyncDone.current = true;
        if (!filters.instruments && selectedInstruments.length > 0) {
            navigate({ instruments: selectedInstruments.join(',') });
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const orchestraOptions: ComboboxOption[] = orchestras.map((o) => ({
        value: o.id.toString(),
        label: o.name,
    }));

    const familyGroups = groupByFamily(instrumentTypes);

    const moreFilterCount = [
        filters.composer,
        filters.arranger,
        filters.publisher,
        filters.music_type,
        filters.genre,
        filters.difficulty,
        filters.bought_for,
        filters.bought_for_occasion,
        filters.buy_date_from || filters.buy_date_to,
    ].filter(Boolean).length;

    const hasAnyFilter = Object.values(filters).some(Boolean);

    function clearAllFilters() {
        setSelectedInstruments([]);
        localStorage.removeItem(INSTRUMENTS_STORAGE_KEY);
        setShowMoreFilters(false);
        router.get(
            '/muziekstukken',
            {},
            { preserveState: true, replace: true },
        );
    }

    function openUsageDialog(pieceId: number) {
        setUsageForm({ orchestra_id: '', van: '', tot: '', details: '' });
        setUsageDialogPieceId(pieceId);
    }

    function submitUsage() {
        if (!usageDialogPieceId || !usageForm.orchestra_id) return;
        setSubmitting(true);
        router.post(
            `/muziekstukken/${usageDialogPieceId}/usages`,
            {
                orchestra_id: Number(usageForm.orchestra_id),
                van: usageForm.van || null,
                tot: usageForm.tot || null,
                details: usageForm.details || null,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSubmitting(false);
                    setUsageDialogPieceId(null);
                },
            },
        );
    }

    function handleSearch(search: string) {
        navigate({ search: search || undefined });
    }

    function handleOrchestraFilter(orchestra: string) {
        navigate({ orchestra: orchestra || undefined });
    }

    function openInstrumentDialog() {
        setDialogSelection([...selectedInstruments]);
        setInstrumentDialogOpen(true);
    }

    function toggleDialogInstrument(id: number) {
        setDialogSelection((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id],
        );
    }

    function applyInstrumentFilter(ids: number[]) {
        setSelectedInstruments(ids);
        localStorage.setItem(INSTRUMENTS_STORAGE_KEY, JSON.stringify(ids));
        setInstrumentDialogOpen(false);
        navigate({ instruments: ids.length ? ids.join(',') : undefined });
    }

    return (
        <AppLayout
            breadcrumbs={[{ title: t('All Pieces'), href: '/muziekstukken' }]}
        >
            <Head title={t('All Pieces')} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading title={t('All Pieces')} />
                    {canEdit && (
                        <Button asChild>
                            <Link href="/muziekstukken/create">
                                <Plus />
                                {t('New piece')}
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="space-y-4">
                    <div className="flex items-end gap-4">
                        <Input
                            placeholder={t('Search...')}
                            defaultValue={filters.search ?? ''}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="max-w-sm"
                        />
                        <div className="space-y-1">
                            <Label>{t('In use by')}</Label>
                            <Select
                                value={filters.orchestra ?? ''}
                                onChange={(e) =>
                                    handleOrchestraFilter(e.target.value)
                                }
                                className="max-w-[200px]"
                            >
                                <option value="">{t('All orchestras')}</option>
                                {orchestras.map((o) => (
                                    <option key={o.id} value={o.id}>
                                        {o.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <Button
                            variant="outline"
                            onClick={openInstrumentDialog}
                        >
                            <Filter className="h-4 w-4" />
                            {selectedInstruments.length > 0
                                ? `${t('Parts')} (${selectedInstruments.length})`
                                : t('Filter by parts')}
                        </Button>
                        <Button
                            variant="ghost"
                            onClick={() => setShowMoreFilters((v) => !v)}
                        >
                            {showMoreFilters ? (
                                <ChevronUp className="h-4 w-4" />
                            ) : (
                                <ChevronDown className="h-4 w-4" />
                            )}
                            {moreFilterCount > 0
                                ? `${t('More filters')} (${moreFilterCount})`
                                : t('More filters')}
                        </Button>
                        {hasAnyFilter && (
                            <Button variant="ghost" onClick={clearAllFilters}>
                                <X className="h-4 w-4" />
                                {t('Clear filters')}
                            </Button>
                        )}
                    </div>

                    {showMoreFilters && (
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="space-y-1">
                                <Label>{t('Composer')}</Label>
                                <Combobox
                                    options={[
                                        { value: '', label: t('All') },
                                        ...filterOptions.composers.map((v) => ({
                                            value: v,
                                            label: v,
                                        })),
                                    ]}
                                    value={filters.composer ?? ''}
                                    onChange={(v) =>
                                        navigate({
                                            composer: v || undefined,
                                        })
                                    }
                                    placeholder={t('All')}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Arranger')}</Label>
                                <Combobox
                                    options={[
                                        { value: '', label: t('All') },
                                        ...filterOptions.arrangers.map((v) => ({
                                            value: v,
                                            label: v,
                                        })),
                                    ]}
                                    value={filters.arranger ?? ''}
                                    onChange={(v) =>
                                        navigate({
                                            arranger: v || undefined,
                                        })
                                    }
                                    placeholder={t('All')}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Publisher')}</Label>
                                <Combobox
                                    options={[
                                        { value: '', label: t('All') },
                                        ...filterOptions.publishers.map(
                                            (v) => ({
                                                value: v,
                                                label: v,
                                            }),
                                        ),
                                    ]}
                                    value={filters.publisher ?? ''}
                                    onChange={(v) =>
                                        navigate({
                                            publisher: v || undefined,
                                        })
                                    }
                                    placeholder={t('All')}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Music type')}</Label>
                                <Select
                                    value={filters.music_type ?? ''}
                                    onChange={(e) =>
                                        navigate({
                                            music_type:
                                                e.target.value || undefined,
                                        })
                                    }
                                >
                                    <option value="">{t('All')}</option>
                                    {filterOptions.musicTypes.map((v) => (
                                        <option key={v} value={v}>
                                            {v}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Genre')}</Label>
                                <Select
                                    value={filters.genre ?? ''}
                                    onChange={(e) =>
                                        navigate({
                                            genre: e.target.value || undefined,
                                        })
                                    }
                                >
                                    <option value="">{t('All')}</option>
                                    {filterOptions.genres.map((v) => (
                                        <option key={v} value={v}>
                                            {v}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Difficulty')}</Label>
                                <Select
                                    value={filters.difficulty ?? ''}
                                    onChange={(e) =>
                                        navigate({
                                            difficulty:
                                                e.target.value || undefined,
                                        })
                                    }
                                >
                                    <option value="">{t('All')}</option>
                                    {filterOptions.difficulties.map((v) => (
                                        <option key={v} value={v}>
                                            {v}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Bought for')}</Label>
                                <Combobox
                                    options={[
                                        { value: '', label: t('All') },
                                        ...filterOptions.boughtFors.map(
                                            (v) => ({
                                                value: v,
                                                label: v,
                                            }),
                                        ),
                                    ]}
                                    value={filters.bought_for ?? ''}
                                    onChange={(v) =>
                                        navigate({
                                            bought_for: v || undefined,
                                        })
                                    }
                                    placeholder={t('All')}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Bought for occasion')}</Label>
                                <Combobox
                                    options={[
                                        { value: '', label: t('All') },
                                        ...filterOptions.boughtForOccasions.map(
                                            (v) => ({
                                                value: v,
                                                label: v,
                                            }),
                                        ),
                                    ]}
                                    value={filters.bought_for_occasion ?? ''}
                                    onChange={(v) =>
                                        navigate({
                                            bought_for_occasion: v || undefined,
                                        })
                                    }
                                    placeholder={t('All')}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Buy date from')}</Label>
                                <Input
                                    type="date"
                                    value={filters.buy_date_from ?? ''}
                                    onChange={(e) =>
                                        navigate({
                                            buy_date_from:
                                                e.target.value || undefined,
                                        })
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Buy date to')}</Label>
                                <Input
                                    type="date"
                                    value={filters.buy_date_to ?? ''}
                                    onChange={(e) =>
                                        navigate({
                                            buy_date_to:
                                                e.target.value || undefined,
                                        })
                                    }
                                />
                            </div>
                        </div>
                    )}
                </div>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Archive number')}</TableHead>
                                <TableHead>{t('Title')}</TableHead>
                                <TableHead>{t('Composer')}</TableHead>
                                <TableHead>{t('Arranger')}</TableHead>
                                <TableHead>{t('Publisher')}</TableHead>
                                <TableHead>{t('Music type')}</TableHead>
                                <TableHead>{t('Genre')}</TableHead>
                                <TableHead>{t('Difficulty')}</TableHead>
                                <TableHead>{t('Buy date')}</TableHead>
                                <TableHead>{t('In use by')}</TableHead>
                                <TableHead>{t('Audio')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pieces.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={11}
                                        className="text-center text-muted-foreground"
                                    >
                                        {Object.values(filters).some(Boolean)
                                            ? t(
                                                  'No pieces found with the applied filters.',
                                              )
                                            : t('No pieces found.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {pieces.data.map((piece) => (
                                <TableRow key={piece.id}>
                                    <TableCell>
                                        {piece.archive_number ?? '-'}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={`/muziekstukken/${piece.id}`}
                                            className="hover:underline"
                                        >
                                            {piece.title}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {piece.composer ?? '-'}
                                    </TableCell>
                                    <TableCell>
                                        {piece.arranger ?? '-'}
                                    </TableCell>
                                    <TableCell>
                                        {piece.publisher ?? '-'}
                                    </TableCell>
                                    <TableCell>
                                        {piece.music_type ?? '-'}
                                    </TableCell>
                                    <TableCell>
                                        {piece.genre?.length ? (
                                            <div className="flex flex-wrap gap-1">
                                                {piece.genre.map((g) => (
                                                    <Badge
                                                        key={g}
                                                        variant="outline"
                                                    >
                                                        {g}
                                                    </Badge>
                                                ))}
                                            </div>
                                        ) : (
                                            '-'
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {piece.difficulty ?? '-'}
                                    </TableCell>
                                    <TableCell>
                                        <DateView value={piece.buy_date} />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-1">
                                            {piece.orchestras.map((o) => (
                                                <Badge
                                                    key={o.id}
                                                    variant="secondary"
                                                >
                                                    {o.abbreviation || o.name}
                                                </Badge>
                                            ))}
                                            {canEditUsages && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-6 w-6"
                                                    onClick={() =>
                                                        openUsageDialog(
                                                            piece.id,
                                                        )
                                                    }
                                                >
                                                    <Plus className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {piece.audio_youtube_url && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <a
                                                    href={
                                                        piece.audio_youtube_url
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <YouTubeIcon />
                                                    <span className="sr-only">
                                                        {t('Open on YouTube')}
                                                    </span>
                                                </a>
                                            </Button>
                                        )}
                                        {piece.audio_url &&
                                            !piece.audio_youtube_url && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        toggle({
                                                            title: piece.title,
                                                            composer:
                                                                piece.composer,
                                                            url: piece.audio_url!,
                                                        })
                                                    }
                                                >
                                                    {isCurrentTrack(
                                                        piece.audio_url,
                                                    ) && isPlaying ? (
                                                        <Pause />
                                                    ) : (
                                                        <Play />
                                                    )}
                                                    <span className="sr-only">
                                                        {t('Play')}
                                                    </span>
                                                </Button>
                                            )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {pieces.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2">
                        {pieces.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                asChild={!!link.url}
                            >
                                {link.url ? (
                                    <Link
                                        href={link.url}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>

            <Dialog
                open={instrumentDialogOpen}
                onOpenChange={(open) => {
                    if (!open) setInstrumentDialogOpen(false);
                }}
            >
                <DialogContent className="max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>{t('Filter by parts')}</DialogTitle>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Select the instruments that should be included at minimum.',
                            )}
                        </p>
                    </DialogHeader>
                    <div className="columns-5 gap-6">
                        {familyGroups.map((group) => (
                            <div
                                key={group.name}
                                className="mb-4 break-inside-avoid"
                            >
                                <h4 className="mb-2 text-sm font-semibold">
                                    {group.name}
                                </h4>
                                <div className="space-y-1">
                                    {group.types.map((type) => (
                                        <label
                                            key={type.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={dialogSelection.includes(
                                                    type.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleDialogInstrument(
                                                        type.id,
                                                    )
                                                }
                                            />
                                            {type.name}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            variant="outline"
                            onClick={() => setDialogSelection([])}
                        >
                            {t('Clear')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setInstrumentDialogOpen(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            onClick={() =>
                                applyInstrumentFilter(dialogSelection)
                            }
                        >
                            {t('Apply')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={usageDialogPieceId !== null}
                onOpenChange={(open) => {
                    if (!open) setUsageDialogPieceId(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Add usage')}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>{t('Orchestras')}</Label>
                            <Combobox
                                options={orchestraOptions}
                                value={usageForm.orchestra_id}
                                onChange={(v) =>
                                    setUsageForm((f) => ({
                                        ...f,
                                        orchestra_id: v,
                                    }))
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('From')}</Label>
                            <Input
                                type="date"
                                value={usageForm.van}
                                onChange={(e) =>
                                    setUsageForm((f) => ({
                                        ...f,
                                        van: e.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Until')}</Label>
                            <Input
                                type="date"
                                value={usageForm.tot}
                                onChange={(e) =>
                                    setUsageForm((f) => ({
                                        ...f,
                                        tot: e.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Details')}</Label>
                            <Input
                                value={usageForm.details}
                                onChange={(e) =>
                                    setUsageForm((f) => ({
                                        ...f,
                                        details: e.target.value,
                                    }))
                                }
                                placeholder={t('Details')}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            onClick={submitUsage}
                            disabled={submitting || !usageForm.orchestra_id}
                        >
                            {t('Save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
