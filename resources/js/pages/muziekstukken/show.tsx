import { Head, Link } from '@inertiajs/react';
import { Download, History, Pause, Pencil, Play } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Heading } from '@/components/heading';
import { YouTubeIcon } from '@/components/icons/youtube';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAudioPlayer } from '@/hooks/use-audio-player';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type { InstrumentType, Part, Piece } from '@/types/muziekstukken';

type Props = {
    piece: Piece;
    parts: Part[];
    audioUrl: string | null;
    instrumentTypes: InstrumentType[];
    canEdit: boolean;
};

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="space-y-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm">
                {value || (
                    <span className="text-muted-foreground">&mdash;</span>
                )}
            </dd>
        </div>
    );
}

type FamilyGroup = {
    familyName: string;
    types: {
        type: InstrumentType;
        parts: Part[];
    }[];
};

function useGroupedTypes(instrumentTypes: InstrumentType[], parts: Part[]) {
    return useMemo(() => {
        const conductorParts = parts.filter((p) => p.is_conductor);
        const nonConductorParts = parts.filter((p) => !p.is_conductor);

        // Group parts by instrument type id
        const partsByType = new Map<number, Part[]>();
        for (const part of nonConductorParts) {
            if (!partsByType.has(part.instrument_type_id)) {
                partsByType.set(part.instrument_type_id, []);
            }
            partsByType.get(part.instrument_type_id)!.push(part);
        }

        // Group all instrument types by family
        const familyMap = new Map<
            string,
            { type: InstrumentType; parts: Part[] }[]
        >();
        for (const type of instrumentTypes) {
            const familyName = type.instrument_family?.name ?? '';
            if (!familyMap.has(familyName)) {
                familyMap.set(familyName, []);
            }
            familyMap.get(familyName)!.push({
                type,
                parts: partsByType.get(type.id) ?? [],
            });
        }

        const families: FamilyGroup[] = Array.from(familyMap.entries()).map(
            ([familyName, types]) => ({ familyName, types }),
        );

        return { conductorParts, families };
    }, [instrumentTypes, parts]);
}

function PartsOverview({
    parts,
    instrumentTypes,
    t,
}: {
    parts: Part[];
    instrumentTypes: InstrumentType[];
    t: (key: string) => string;
}) {
    const { conductorParts, families } = useGroupedTypes(
        instrumentTypes,
        parts,
    );

    return (
        <div className="space-y-6">
            {conductorParts.length > 0 && (
                <div className="space-y-2">
                    <h3 className="text-sm font-medium text-muted-foreground">
                        {t('Conductor part')}
                    </h3>
                    <div className="flex flex-wrap gap-2">
                        {conductorParts.map((part) => (
                            <div
                                key={part.id}
                                className="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                            >
                                <span>
                                    <span className="mr-[10px] text-muted-foreground">
                                        {part.amount_bought ?? 1}&times;
                                    </span>
                                    {part.original_filename}
                                </span>
                                {part.download_url && (
                                    <a href={part.download_url} download>
                                        <Download className="size-4 text-muted-foreground hover:text-foreground" />
                                        <span className="sr-only">
                                            {t('Download')}
                                        </span>
                                    </a>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                {families
                    .filter(({ types }) =>
                        types.some(({ parts: tp }) => tp.length > 0),
                    )
                    .map(({ familyName, types }) => (
                        <div key={familyName} className="space-y-2">
                            <h3 className="text-sm font-semibold">
                                {familyName}
                            </h3>
                            <ul className="space-y-1">
                                {types
                                    .filter(({ parts: tp }) => tp.length > 0)
                                    .map(({ type, parts: typeParts }) => {
                                        const totalBought = typeParts.reduce(
                                            (sum, p) =>
                                                sum + (p.amount_bought ?? 1),
                                            0,
                                        );
                                        return (
                                            <li
                                                key={type.id}
                                                className="flex items-center justify-between text-sm"
                                            >
                                                <span>
                                                    <span className="mr-[10px] text-muted-foreground">
                                                        {totalBought}&times;
                                                    </span>
                                                    {type.name}
                                                </span>
                                                {typeParts.length === 1 &&
                                                    typeParts[0]
                                                        .download_url && (
                                                        <a
                                                            href={
                                                                typeParts[0]
                                                                    .download_url
                                                            }
                                                            download
                                                        >
                                                            <Download className="size-4 text-muted-foreground hover:text-foreground" />
                                                            <span className="sr-only">
                                                                {t('Download')}
                                                            </span>
                                                        </a>
                                                    )}
                                            </li>
                                        );
                                    })}
                            </ul>
                        </div>
                    ))}
            </div>
        </div>
    );
}

export default function Show({
    piece,
    parts,
    audioUrl,
    instrumentTypes,
    canEdit,
}: Props) {
    const { t } = useTranslation();
    const { isPlaying, isCurrentTrack, toggle } = useAudioPlayer();
    const [showPreviousUsages, setShowPreviousUsages] = useState(false);

    const currentUsages = (piece.orchestra_usages ?? []).filter((u) => !u.tot);
    const previousUsages = (piece.orchestra_usages ?? []).filter((u) => u.tot);
    const visibleUsages = showPreviousUsages
        ? [...currentUsages, ...previousUsages]
        : currentUsages;

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('All Pieces'), href: '/muziekstukken' },
                { title: `${piece.archive_number ? piece.archive_number + ' — ' : ''}${piece.title}`, href: `/muziekstukken/${piece.id}` },
            ]}
        >
            <Head title={piece.title} />
            <div className="space-y-10 p-6">
                {/* Piece metadata */}
                <section className="space-y-6">
                    <div className="flex items-center justify-between">
                        <Heading title={`${piece.archive_number ? piece.archive_number + ' — ' : ''}${piece.title}`} />
                        {canEdit && (
                            <Button variant="outline" size="icon" asChild>
                                <Link href={`/muziekstukken/${piece.id}/edit`}>
                                    <Pencil />
                                </Link>
                            </Button>
                        )}
                    </div>

                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('Composer')} value={piece.composer} />
                        <Field label={t('Arranger')} value={piece.arranger} />
                        <Field label={t('Publisher')} value={piece.publisher} />
                        <Field
                            label={t('Difficulty')}
                            value={piece.difficulty}
                        />
                        <Field
                            label={t('Bought for')}
                            value={piece.bought_for}
                        />
                        <Field label={t('Buy date')} value={piece.buy_date} />
                        <Field
                            label={t('Archive number')}
                            value={piece.archive_number}
                        />
                        <Field
                            label={t('Music type')}
                            value={piece.music_type}
                        />
                    </dl>

                    <div className="space-y-1">
                        <dt className="text-sm text-muted-foreground">
                            {t('Genre')}
                        </dt>
                        <dd className="flex flex-wrap gap-1">
                            {piece.genre && piece.genre.length > 0 ? (
                                piece.genre.map((g) => (
                                    <Badge key={g} variant="secondary">
                                        {g}
                                    </Badge>
                                ))
                            ) : (
                                <span className="text-sm text-muted-foreground">
                                    &mdash;
                                </span>
                            )}
                        </dd>
                    </div>

                    <div className="space-y-1">
                        <dt className="text-sm text-muted-foreground">
                            {t('Notes')}
                        </dt>
                        <dd className="text-sm whitespace-pre-line">
                            {piece.notes || (
                                <span className="text-muted-foreground">
                                    &mdash;
                                </span>
                            )}
                        </dd>
                    </div>

                    {/* In use by */}
                    {visibleUsages.length > 0 && (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <dt className="text-sm text-muted-foreground">
                                    {t('In use by')}
                                </dt>
                                {previousUsages.length > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setShowPreviousUsages((v) => !v)
                                        }
                                    >
                                        <History />
                                        {showPreviousUsages
                                            ? t('Hide previous usage')
                                            : t('Show previous usage')}
                                    </Button>
                                )}
                            </div>
                            <dd className="flex flex-wrap gap-2">
                                {visibleUsages.map((u) => (
                                    <div
                                        key={u.id}
                                        className="flex items-center gap-1.5"
                                    >
                                        <Badge
                                            variant={
                                                u.tot ? 'outline' : 'secondary'
                                            }
                                        >
                                            {u.orchestra.name}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {u.van ?? '?'} &ndash;{' '}
                                            {u.tot ?? t('present')}
                                        </span>
                                        {u.details && (
                                            <span className="text-xs text-muted-foreground italic">
                                                {u.details}
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </dd>
                        </div>
                    )}

                    {/* Audio player */}
                    {piece.audio_youtube_url && (
                        <div className="space-y-1">
                            <dt className="text-sm text-muted-foreground">
                                {t('Audio')}
                            </dt>
                            <dd>
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={piece.audio_youtube_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <YouTubeIcon />
                                        {t('Open on YouTube')}
                                    </a>
                                </Button>
                            </dd>
                        </div>
                    )}

                    {audioUrl && !piece.audio_youtube_url && (
                        <div className="space-y-1">
                            <dt className="text-sm text-muted-foreground">
                                {t('Audio')}
                            </dt>
                            <dd>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        toggle({
                                            title: piece.title,
                                            composer: piece.composer,
                                            url: audioUrl,
                                        })
                                    }
                                >
                                    {isCurrentTrack(audioUrl) && isPlaying ? (
                                        <Pause />
                                    ) : (
                                        <Play />
                                    )}
                                    {isCurrentTrack(audioUrl) && isPlaying
                                        ? t('Pause')
                                        : t('Play')}
                                </Button>
                            </dd>
                        </div>
                    )}
                </section>

                {/* Parts */}
                <section className="space-y-6">
                    <Heading title={t('Parts')} />
                    <Tabs defaultValue="parts">
                        <TabsList>
                            <TabsTrigger value="parts">
                                {t('Parts')}
                            </TabsTrigger>
                            <TabsTrigger value="parts-overview">
                                {t('Parts overview')}
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="parts">
                            {parts.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No parts uploaded yet.')}
                                </p>
                            ) : (
                                <div className="rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    {t('Instrument')}
                                                </TableHead>
                                                <TableHead>
                                                    {t('Voice')}
                                                </TableHead>
                                                <TableHead>
                                                    {t('Amount bought')}
                                                </TableHead>
                                                <TableHead>
                                                    {t('Partituur')}
                                                </TableHead>
                                                <TableHead className="w-0" />
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {parts.map((part) => (
                                                <TableRow key={part.id}>
                                                    <TableCell>
                                                        {
                                                            part.instrument_type
                                                                .name
                                                        }
                                                        {part.note && (
                                                            <span className="text-muted-foreground">
                                                                {' - '}
                                                                {part.note}
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {part.voice ?? (
                                                            <span className="text-muted-foreground">
                                                                &mdash;
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {part.amount_bought ?? (
                                                            <span className="text-muted-foreground">
                                                                &mdash;
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {part.is_conductor ? (
                                                            <Badge>
                                                                {t('Yes')}
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                {t('No')}
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {part.download_url && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                asChild
                                                            >
                                                                <a
                                                                    href={
                                                                        part.download_url
                                                                    }
                                                                    download
                                                                >
                                                                    <Download />
                                                                    <span className="sr-only">
                                                                        {t(
                                                                            'Download',
                                                                        )}
                                                                    </span>
                                                                </a>
                                                            </Button>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </TabsContent>

                        <TabsContent value="parts-overview">
                            <PartsOverview
                                parts={parts}
                                instrumentTypes={instrumentTypes}
                                t={t}
                            />
                        </TabsContent>
                    </Tabs>
                </section>
            </div>
        </AppLayout>
    );
}
