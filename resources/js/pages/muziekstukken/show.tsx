import { Head, Link, router } from '@inertiajs/react';
import {
    Download,
    History,
    Pause,
    Pencil,
    Play,
    Plus,
    Printer,
} from 'lucide-react';
import { useState } from 'react';
import { BatchPrintDialog } from '@/components/batch-print-dialog';
import { DateView } from '@/components/date-view';
import { FamilyPartsGrid, VoiceLabel } from '@/components/family-parts-grid';
import { Heading } from '@/components/heading';
import { YouTubeIcon } from '@/components/icons/youtube';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { useGroupedTypes } from '@/hooks/use-grouped-types';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type {
    InstrumentType,
    Orchestra,
    Part,
    Piece,
} from '@/types/muziekstukken';

type Props = {
    piece: Piece;
    parts: Part[];
    audioUrl: string | null;
    instrumentTypes: InstrumentType[];
    orchestras: Orchestra[];
    canEdit: boolean;
    canEditSpeelperiodes: boolean;
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
                                    {part.original_filename ?? t('Partituur')}
                                </span>
                                <span className="text-muted-foreground">
                                    {part.amount_bought ?? 1}&times;
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

            <FamilyPartsGrid
                families={families}
                showEmptyTypes
                renderEmptyType={(type) => (
                    <li className="flex items-center justify-between text-sm text-muted-foreground/80">
                        <span>{type.name}</span>
                        <span>0&times;</span>
                    </li>
                )}
                renderRow={(group) => {
                    const totalBought = group.parts.reduce(
                        (sum, p) => sum + (p.amount_bought ?? 1),
                        0,
                    );
                    const downloadUrl =
                        group.parts.length === 1
                            ? group.parts[0].download_url
                            : null;
                    return (
                        <li className="flex items-center justify-between gap-2 text-sm">
                            <VoiceLabel group={group} />
                            <span className="flex items-center gap-2">
                                <span className="text-muted-foreground">
                                    {totalBought}&times;
                                </span>
                                {downloadUrl ? (
                                    <a href={downloadUrl} download>
                                        <Download className="size-4 text-muted-foreground hover:text-foreground" />
                                        <span className="sr-only">
                                            {t('Download')}
                                        </span>
                                    </a>
                                ) : (
                                    <span className="size-4" />
                                )}
                            </span>
                        </li>
                    );
                }}
            />
        </div>
    );
}

export default function Show({
    piece,
    parts,
    audioUrl,
    instrumentTypes,
    orchestras,
    canEdit,
    canEditSpeelperiodes,
}: Props) {
    const { t } = useTranslation();
    const { isPlaying, isCurrentTrack, toggle } = useAudioPlayer();
    const [showPreviousSpeelperiodes, setShowPreviousSpeelperiodes] = useState(false);
    const [batchPrintOpen, setBatchPrintOpen] = useState(false);
    const [speelperiodeDialogOpen, setSpeelperiodeDialogOpen] = useState(false);
    const [speelperiodeForm, setSpeelperiodeForm] = useState({
        orchestra_id: '',
        van: '',
        tot: '',
        details: '',
    });
    const [submitting, setSubmitting] = useState(false);

    const orchestraOptions: ComboboxOption[] = orchestras.map((o) => ({
        value: o.id.toString(),
        label: o.name,
    }));

    function submitSpeelperiode() {
        if (!speelperiodeForm.orchestra_id) return;
        setSubmitting(true);
        router.post(
            `/muziekstukken/${piece.id}/speelperiodes`,
            {
                orchestra_id: Number(speelperiodeForm.orchestra_id),
                van: speelperiodeForm.van || null,
                tot: speelperiodeForm.tot || null,
                details: speelperiodeForm.details || null,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSubmitting(false);
                    setSpeelperiodeDialogOpen(false);
                },
            },
        );
    }

    const today = new Date().toISOString().split('T')[0];
    const isPast = (u: { tot?: string | null }) =>
        !!u.tot && u.tot < today;
    const currentSpeelperiodes = (piece.speelperiodes ?? []).filter(
        (u) => !isPast(u),
    );
    const previousSpeelperiodes = (piece.speelperiodes ?? []).filter((u) =>
        isPast(u),
    );
    const visibleSpeelperiodes = showPreviousSpeelperiodes
        ? [...currentSpeelperiodes, ...previousSpeelperiodes]
        : currentSpeelperiodes;

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('All Pieces'), href: '/muziekstukken' },
                {
                    title: `${piece.archive_number ? piece.archive_number + ' — ' : ''}${piece.title}`,
                    href: `/muziekstukken/${piece.id}`,
                },
            ]}
        >
            <Head title={piece.title} />
            <div className="space-y-10 p-6">
                {/* Piece metadata */}
                <section className="space-y-6">
                    <div className="flex items-center justify-between">
                        <Heading
                            title={`${piece.archive_number ? piece.archive_number + ' — ' : ''}${piece.title}`}
                        />
                        <div className="flex items-center gap-2">
                            {canEditSpeelperiodes && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setSpeelperiodeForm({
                                            orchestra_id: '',
                                            van: '',
                                            tot: '',
                                            details: '',
                                        });
                                        setSpeelperiodeDialogOpen(true);
                                    }}
                                >
                                    <Plus />
                                    {t('Add play period')}
                                </Button>
                            )}
                            {canEdit && parts.some((p) => p.download_url) && (
                                <Button
                                    variant="outline"
                                    onClick={() => setBatchPrintOpen(true)}
                                >
                                    <Printer />
                                    {t('Drukklaar maken')}
                                </Button>
                            )}
                            {canEdit && (
                                <Button variant="outline" size="icon" asChild>
                                    <Link
                                        href={`/muziekstukken/${piece.id}/edit`}
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>

                    <dl className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                        <Field label={t('Status')} value={piece.status} />
                        <Field label={t('Composer')} value={piece.composer} />
                        <Field label={t('Arranger')} value={piece.arranger} />
                        <Field label={t('Publisher')} value={piece.publisher} />
                        <Field
                            label={t('Difficulty')}
                            value={piece.difficulty}
                        />
                        <Field
                            label={t('Music type')}
                            value={piece.music_type}
                        />
                        <Field
                            label={t('Bought for')}
                            value={piece.bought_for}
                        />
                        <Field
                            label={t('Bought for occasion')}
                            value={piece.bought_for_occasion}
                        />
                        <Field label={t('Buy date')} value={piece.buy_date} />
                        <Field
                            label={t('Archive number')}
                            value={piece.archive_number}
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
                    {visibleSpeelperiodes.length > 0 && (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <dt className="text-sm text-muted-foreground">
                                    {t('In use by')}
                                </dt>
                                {previousSpeelperiodes.length > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setShowPreviousSpeelperiodes((v) => !v)
                                        }
                                    >
                                        <History />
                                        {showPreviousSpeelperiodes
                                            ? t('Hide previous play periods')
                                            : t('Show previous play periods')}
                                    </Button>
                                )}
                            </div>
                            <dd className="flex flex-wrap gap-2">
                                {visibleSpeelperiodes.map((u) => (
                                    <div
                                        key={u.id}
                                        className="flex items-center gap-1.5"
                                    >
                                        <Badge
                                            variant={
                                                isPast(u)
                                                    ? 'outline'
                                                    : 'secondary'
                                            }
                                        >
                                            {u.orchestra.name}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            <DateView value={u.van} /> &ndash;{' '}
                                            {u.tot ? (
                                                <DateView value={u.tot} />
                                            ) : (
                                                t('present')
                                            )}
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
                </section>

                {/* Audio */}
                {(piece.audio_youtube_url || audioUrl) && (
                    <section className="space-y-6">
                        <Heading title={t('Audio')} />
                        <div className="flex flex-wrap gap-2">
                            {piece.audio_youtube_url && (
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
                            )}
                            {audioUrl && (
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
                            )}
                        </div>
                    </section>
                )}

                {/* Parts */}
                <section className="space-y-6">
                    <Heading title={t('Parts')} />
                    <Tabs defaultValue="overview">
                        <TabsList>
                            <TabsTrigger value="overview">
                                {t('Overview')}
                            </TabsTrigger>
                            <TabsTrigger value="list">{t('List')}</TabsTrigger>
                        </TabsList>

                        <TabsContent value="overview">
                            <PartsOverview
                                parts={parts}
                                instrumentTypes={instrumentTypes}
                                t={t}
                            />
                        </TabsContent>

                        <TabsContent value="list">
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
                                                    {t('Original filename')}
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
                                                    <TableCell className="text-muted-foreground">
                                                        {part.original_filename ?? (
                                                            <span>&mdash;</span>
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
                    </Tabs>
                </section>
            </div>
            <BatchPrintDialog
                open={batchPrintOpen}
                onOpenChange={setBatchPrintOpen}
                parts={parts}
                instrumentTypes={instrumentTypes}
            />
            <Dialog
                open={speelperiodeDialogOpen}
                onOpenChange={(open) => {
                    if (!open) setSpeelperiodeDialogOpen(false);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Add play period')}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>{t('Orchestras')}</Label>
                            <Combobox
                                options={orchestraOptions}
                                value={speelperiodeForm.orchestra_id}
                                onChange={(v) =>
                                    setSpeelperiodeForm((f) => ({
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
                                value={speelperiodeForm.van}
                                onChange={(e) =>
                                    setSpeelperiodeForm((f) => ({
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
                                value={speelperiodeForm.tot}
                                onChange={(e) =>
                                    setSpeelperiodeForm((f) => ({
                                        ...f,
                                        tot: e.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Details')}</Label>
                            <Input
                                value={speelperiodeForm.details}
                                onChange={(e) =>
                                    setSpeelperiodeForm((f) => ({
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
                            onClick={submitSpeelperiode}
                            disabled={submitting || !speelperiodeForm.orchestra_id}
                        >
                            {t('Save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
