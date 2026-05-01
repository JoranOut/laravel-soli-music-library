import { Head } from '@inertiajs/react';
import { Download, Pause, Play } from 'lucide-react';
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
import type { OrchestraGroup } from '@/types/muziekstukken';

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

export default function Welcome({ orchestraGroups }: Props) {
    const { t } = useTranslation();
    const { isPlaying, isCurrentTrack, toggle } = useAudioPlayer();

    return (
        <AppLayout breadcrumbs={[{ title: t('My Music'), href: '/' }]}>
            <Head title={t('My Music')} />
            <div className="space-y-10 p-6">
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
                                <div className="rounded-lg border">
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
                                                            {part.original_filename !==
                                                            null ? (
                                                                <button
                                                                    type="button"
                                                                    className="cursor-pointer text-left hover:underline"
                                                                    onClick={() =>
                                                                        handleView(
                                                                            part.id,
                                                                        )
                                                                    }
                                                                >
                                                                    {
                                                                        piece.title
                                                                    }
                                                                </button>
                                                            ) : (
                                                                <span>
                                                                    {
                                                                        piece.title
                                                                    }
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {piece.composer ?? (
                                                                <span className="text-muted-foreground">
                                                                    —
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {
                                                                part
                                                                    .instrument_type
                                                                    .name
                                                            }
                                                            {part.voice !=
                                                                null &&
                                                                ` ${part.voice}`}
                                                            {part.note && (
                                                                <span className="text-muted-foreground">
                                                                    {' - '}
                                                                    {part.note}
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {part.original_filename !==
                                                            null ? (
                                                                <div className="flex items-center gap-1">
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        onClick={() =>
                                                                            handleDownload(
                                                                                part.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Download />
                                                                        <span className="sr-only">
                                                                            {t(
                                                                                'Download',
                                                                            )}
                                                                        </span>
                                                                    </Button>
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
                                                                                    {t(
                                                                                        'Open on YouTube',
                                                                                    )}
                                                                                </span>
                                                                            </a>
                                                                        </Button>
                                                                    )}
                                                                    {piece.audio_url && (
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            onClick={() =>
                                                                                toggle(
                                                                                    {
                                                                                        title: piece.title,
                                                                                        composer:
                                                                                            piece.composer,
                                                                                        url: piece.audio_url!,
                                                                                    },
                                                                                )
                                                                            }
                                                                        >
                                                                            {isCurrentTrack(
                                                                                piece.audio_url,
                                                                            ) &&
                                                                            isPlaying ? (
                                                                                <Pause />
                                                                            ) : (
                                                                                <Play />
                                                                            )}
                                                                            <span className="sr-only">
                                                                                {t(
                                                                                    'Play',
                                                                                )}
                                                                            </span>
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                <span className="text-xs text-muted-foreground italic">
                                                                    {t(
                                                                        'Sheet is not digitally available',
                                                                    )}
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                )),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
