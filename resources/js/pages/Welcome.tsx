import { Head } from '@inertiajs/react';
import { Download, Eye, Pause, Play } from 'lucide-react';
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
import { useAudioPlayer } from '@/hooks/use-audio-player';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type {
    DashboardPiece,
    OrchestraGroup,
    Part,
} from '@/types/muziekstukken';

type Props = {
    orchestraGroups: OrchestraGroup[];
};

async function handleDownload(partId: number) {
    const response = await fetch(`/parts/${partId}/download-url`);
    const { url } = await response.json();
    window.location.href = url;
}

async function handleView(partId: number) {
    const response = await fetch(`/parts/${partId}/view-url`);
    const { url } = await response.json();
    window.open(url, '_blank');
}

function PieceTitle({ piece, part }: { piece: DashboardPiece; part: Part }) {
    if (part.original_filename !== null) {
        return (
            <button
                type="button"
                className="cursor-pointer text-left hover:underline"
                onClick={() => handleView(part.id)}
            >
                {piece.title}
            </button>
        );
    }
    return <span>{piece.title}</span>;
}

function InstrumentLabel({ part }: { part: Part }) {
    return (
        <>
            {part.instrument_type.name}
            {part.voice != null && ` ${part.voice}`}
            {part.note && (
                <span className="text-muted-foreground">
                    {' - '}
                    {part.note}
                </span>
            )}
        </>
    );
}

function PartActions({
    piece,
    part,
    t,
    toggle,
    isPlaying,
    isCurrentTrack,
}: {
    piece: DashboardPiece;
    part: Part;
    t: (key: string) => string;
    toggle: (track: {
        title: string;
        composer: string | null;
        url: string;
    }) => void;
    isPlaying: boolean;
    isCurrentTrack: (url: string) => boolean;
}) {
    if (part.original_filename === null) {
        return (
            <span className="text-xs text-muted-foreground italic">
                {t('Sheet is not digitally available')}
            </span>
        );
    }

    return (
        <div className="flex items-center gap-1">
            <Button
                variant="ghost"
                size="icon"
                onClick={() => handleView(part.id)}
            >
                <Eye />
                <span className="sr-only">{t('View')}</span>
            </Button>
            <Button
                variant="ghost"
                size="icon"
                onClick={() => handleDownload(part.id)}
            >
                <Download />
                <span className="sr-only">{t('Download')}</span>
            </Button>
            {piece.audio_youtube_url && (
                <Button variant="ghost" size="icon" asChild>
                    <a
                        href={piece.audio_youtube_url}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <YouTubeIcon />
                        <span className="sr-only">{t('Open on YouTube')}</span>
                    </a>
                </Button>
            )}
            {piece.audio_url && (
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() =>
                        toggle({
                            title: piece.title,
                            composer: piece.composer,
                            url: piece.audio_url!,
                        })
                    }
                >
                    {isCurrentTrack(piece.audio_url) && isPlaying ? (
                        <Pause />
                    ) : (
                        <Play />
                    )}
                    <span className="sr-only">{t('Play')}</span>
                </Button>
            )}
        </div>
    );
}

export default function Welcome({ orchestraGroups }: Props) {
    const { t } = useTranslation();
    const { isPlaying, isCurrentTrack, toggle } = useAudioPlayer();

    return (
        <AppLayout breadcrumbs={[{ title: t('My Music'), href: '/' }]}>
            <Head title={t('My Music')} />
            <div className="space-y-10 p-4 md:p-6">
                <Heading title={t('My Music')} />

                {orchestraGroups.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No assignments yet.')}
                    </p>
                ) : (
                    orchestraGroups.map((group) => (
                        <section key={group.orchestra.id} className="space-y-6">
                            <div className="space-y-2">
                                <h3 className="text-lg font-semibold tracking-tight">
                                    {group.orchestra.name}
                                </h3>
                                <div className="flex flex-wrap gap-1">
                                    {group.instruments.map((instrument) => (
                                        <Badge
                                            key={instrument.id}
                                            variant="secondary"
                                        >
                                            {instrument.name}
                                        </Badge>
                                    ))}
                                </div>
                            </div>

                            {group.pieces.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No music parts available.')}
                                </p>
                            ) : (
                                <>
                                    {/* Desktop table */}
                                    <div className="hidden rounded-lg border md:block">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>
                                                        {t('Piece')}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t('Composer')}
                                                    </TableHead>
                                                    <TableHead>
                                                        {t('Instrument')}
                                                    </TableHead>
                                                    <TableHead className="w-0" />
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {group.pieces.flatMap((piece) =>
                                                    piece.parts.map((part) => (
                                                        <TableRow key={part.id}>
                                                            <TableCell>
                                                                <PieceTitle
                                                                    piece={
                                                                        piece
                                                                    }
                                                                    part={part}
                                                                />
                                                            </TableCell>
                                                            <TableCell>
                                                                {piece.composer ?? (
                                                                    <span className="text-muted-foreground">
                                                                        —
                                                                    </span>
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                <InstrumentLabel
                                                                    part={part}
                                                                />
                                                            </TableCell>
                                                            <TableCell>
                                                                <PartActions
                                                                    piece={
                                                                        piece
                                                                    }
                                                                    part={part}
                                                                    t={t}
                                                                    toggle={
                                                                        toggle
                                                                    }
                                                                    isPlaying={
                                                                        isPlaying
                                                                    }
                                                                    isCurrentTrack={
                                                                        isCurrentTrack
                                                                    }
                                                                />
                                                            </TableCell>
                                                        </TableRow>
                                                    )),
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>

                                    {/* Mobile card list */}
                                    <div className="space-y-3 md:hidden">
                                        {group.pieces.flatMap((piece) =>
                                            piece.parts.map((part) => (
                                                <div
                                                    key={part.id}
                                                    className="rounded-lg border p-4"
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 flex-1 space-y-1">
                                                            <div className="font-medium">
                                                                <PieceTitle
                                                                    piece={
                                                                        piece
                                                                    }
                                                                    part={part}
                                                                />
                                                            </div>
                                                            <div className="text-sm text-muted-foreground">
                                                                <InstrumentLabel
                                                                    part={part}
                                                                />
                                                                {piece.composer && (
                                                                    <>
                                                                        {' '}
                                                                        &middot;{' '}
                                                                        {
                                                                            piece.composer
                                                                        }
                                                                    </>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="shrink-0">
                                                            <PartActions
                                                                piece={piece}
                                                                part={part}
                                                                t={t}
                                                                toggle={toggle}
                                                                isPlaying={
                                                                    isPlaying
                                                                }
                                                                isCurrentTrack={
                                                                    isCurrentTrack
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            )),
                                        )}
                                    </div>
                                </>
                            )}
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
