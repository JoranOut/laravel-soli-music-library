import { Head, router } from '@inertiajs/react';
import {
    Archive,
    Music,
    Plus,
    RotateCcw,
    Save,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Heading } from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Combobox } from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { guessFromFilename } from '@/lib/instrument-guess';
import type {
    InstrumentType,
    Orchestra,
    Part,
    Piece,
} from '@/types/muziekstukken';
import type { AudioType, PieceFormHandle } from './piece-form';
import PieceForm from './piece-form';

type PartUpload = {
    file: File;
    instrument_type_id: string;
    is_conductor: boolean;
    voice: string;
    amount_bought: string;
    note: string;
};

type Props = {
    piece: Piece;
    audioUrl: string | null;
    orchestras: Orchestra[];
    instrumentTypes?: InstrumentType[];
    canEditAllFields?: boolean;
    canArchive?: boolean;
    genreSuggestions?: string[];
    musicTypeSuggestions?: string[];
    composerSuggestions?: string[];
    arrangerSuggestions?: string[];
    publisherSuggestions?: string[];
    difficultySuggestions?: string[];
};

function instrumentOptions(types: InstrumentType[]): ComboboxOption[] {
    return types.map((t) => ({
        value: t.id.toString(),
        label: t.name,
        group: t.instrument_family?.name ?? 'Other',
    }));
}

type UsageEdit = {
    id: number | null;
    orchestra_id: string;
    van: string;
    tot: string;
    details: string;
};

type PartEdit = {
    instrument_type_id: string;
    is_conductor: boolean;
    voice: string;
    amount_bought: string;
    note: string;
};

export default function Edit({
    piece,
    audioUrl,
    orchestras,
    instrumentTypes = [],
    canEditAllFields = true,
    canArchive = false,
    genreSuggestions = [],
    musicTypeSuggestions = [],
    composerSuggestions = [],
    arrangerSuggestions = [],
    publisherSuggestions = [],
    difficultySuggestions = [],
}: Props) {
    const { t } = useTranslation();
    const pieceFormRef = useRef<PieceFormHandle>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const audioInputRef = useRef<HTMLInputElement>(null);
    const [audioType, setAudioType] = useState<AudioType>(
        piece.audio_youtube_url
            ? 'youtube'
            : piece.audio_file_path
              ? 'mp3'
              : 'none',
    );
    const [audioUploading, setAudioUploading] = useState(false);
    const [uploads, setUploads] = useState<PartUpload[]>([]);
    const [uploading, setUploading] = useState(false);
    const [deletePart, setDeletePart] = useState<Part | null>(null);
    const [endUsageIndex, setEndUsageIndex] = useState<number | null>(null);
    const [partEdits, setPartEdits] = useState<Record<number, PartEdit>>({});
    const [saving, setSaving] = useState(false);
    const [showArchiveDialog, setShowArchiveDialog] = useState(false);
    const [archiveConfirmTitle, setArchiveConfirmTitle] = useState('');
    const [usages, setUsages] = useState<UsageEdit[]>(() =>
        (piece.orchestra_usages ?? []).map((u) => ({
            id: u.id,
            orchestra_id: u.orchestra_id.toString(),
            van: u.van?.split('T')[0] ?? '',
            tot: u.tot?.split('T')[0] ?? '',
            details: u.details ?? '',
        })),
    );

    const instOptions = instrumentOptions(instrumentTypes);
    const orchestraOptions: ComboboxOption[] = orchestras.map((o) => ({
        value: o.id.toString(),
        label: o.name,
    }));

    function getPartEdit(part: Part): PartEdit {
        return (
            partEdits[part.id] ?? {
                instrument_type_id: part.instrument_type_id.toString(),
                is_conductor: part.is_conductor,
                voice: part.voice?.toString() ?? '',
                amount_bought: part.amount_bought?.toString() ?? '',
                note: part.note ?? '',
            }
        );
    }

    function updatePartEdit(
        partId: number,
        field: keyof PartEdit,
        value: string | boolean,
    ) {
        setPartEdits((prev) => ({
            ...prev,
            [partId]: {
                ...getPartEditById(partId),
                [field]: value,
            },
        }));
    }

    function getPartEditById(partId: number): PartEdit {
        const part = piece.parts.find((p) => p.id === partId)!;
        return getPartEdit(part);
    }

    function hasPartChanged(part: Part): boolean {
        const edit = partEdits[part.id];
        if (!edit) return false;
        return (
            edit.instrument_type_id !== part.instrument_type_id.toString() ||
            edit.is_conductor !== part.is_conductor ||
            edit.voice !== (part.voice?.toString() ?? '') ||
            edit.amount_bought !== (part.amount_bought?.toString() ?? '') ||
            edit.note !== (part.note ?? '')
        );
    }

    function addUsage() {
        setUsages((prev) => [
            ...prev,
            { id: null, orchestra_id: '', van: '', tot: '', details: '' },
        ]);
    }

    function updateUsage(index: number, field: keyof UsageEdit, value: string) {
        setUsages((prev) =>
            prev.map((u, i) => (i === index ? { ...u, [field]: value } : u)),
        );
    }

    function saveAll() {
        const pieceData = pieceFormRef.current?.getData();
        if (!pieceData) return;

        const changedParts = piece.parts
            .filter((p) => hasPartChanged(p))
            .map((p) => {
                const edit = getPartEdit(p);
                return {
                    id: p.id,
                    instrument_type_id: edit.instrument_type_id,
                    is_conductor: edit.is_conductor,
                    voice: edit.voice === '' ? null : Number(edit.voice),
                    amount_bought:
                        edit.amount_bought === ''
                            ? null
                            : Number(edit.amount_bought),
                    note: edit.note === '' ? null : edit.note,
                };
            });

        const usagePayload = usages
            .filter((u) => u.orchestra_id !== '')
            .map((u) => ({
                id: u.id,
                orchestra_id: Number(u.orchestra_id),
                van: u.van || null,
                tot: u.tot || null,
                details: u.details || null,
            }));

        setSaving(true);
        router.put(
            `/muziekstukken/${piece.id}`,
            {
                ...pieceData,
                usages: usagePayload,
                parts: changedParts,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    setPartEdits({});
                },
            },
        );
    }

    function handleFilesSelected(e: React.ChangeEvent<HTMLInputElement>) {
        const files = Array.from(e.target.files ?? []);
        const newUploads: PartUpload[] = files.map((file) => {
            const guess = guessFromFilename(file.name, instrumentTypes);
            return {
                file,
                instrument_type_id:
                    guess.instrument_type_id ??
                    instrumentTypes[0]?.id.toString() ??
                    '',
                is_conductor: guess.is_conductor ?? false,
                voice: guess.voice ?? '',
                amount_bought: '1',
                note: '',
            };
        });
        setUploads((prev) => [...prev, ...newUploads]);
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    function updateUpload(
        index: number,
        field: keyof PartUpload,
        value: string | boolean,
    ) {
        setUploads((prev) =>
            prev.map((u, i) => (i === index ? { ...u, [field]: value } : u)),
        );
    }

    function removeUpload(index: number) {
        setUploads((prev) => prev.filter((_, i) => i !== index));
    }

    function submitUploads() {
        if (uploads.length === 0) return;

        const formData = new FormData();
        uploads.forEach((upload, i) => {
            formData.append(`parts[${i}][file]`, upload.file);
            formData.append(
                `parts[${i}][instrument_type_id]`,
                upload.instrument_type_id,
            );
            formData.append(
                `parts[${i}][is_conductor]`,
                upload.is_conductor ? '1' : '0',
            );
            if (upload.voice !== '') {
                formData.append(`parts[${i}][voice]`, upload.voice);
            }
            if (upload.amount_bought !== '') {
                formData.append(
                    `parts[${i}][amount_bought]`,
                    upload.amount_bought,
                );
            }
            if (upload.note !== '') {
                formData.append(`parts[${i}][note]`, upload.note);
            }
        });

        setUploading(true);
        router.post(`/muziekstukken/${piece.id}/parts`, formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                setUploads([]);
            },
        });
    }

    function confirmDeletePart() {
        if (!deletePart) return;
        router.delete(`/muziekstukken/${piece.id}/parts/${deletePart.id}`, {
            preserveScroll: true,
            onFinish: () => setDeletePart(null),
        });
    }

    function handleAudioUpload(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('audio', file);

        setAudioUploading(true);
        router.post(`/muziekstukken/${piece.id}/audio`, formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setAudioUploading(false);
                if (audioInputRef.current) audioInputRef.current.value = '';
            },
        });
    }

    function handleAudioDelete() {
        router.delete(`/muziekstukken/${piece.id}/audio`, {
            preserveScroll: true,
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('All Pieces'), href: '/muziekstukken' },
                {
                    title: piece.title,
                    href: `/muziekstukken/${piece.id}/edit`,
                },
            ]}
        >
            <Head title={piece.title} />
            <div className="space-y-10 p-6">
                {/* Page heading with save button */}
                <div className="flex items-center justify-between">
                    <Heading title={t('Edit piece')} />
                    <Button onClick={saveAll} disabled={saving}>
                        <Save />
                        {t('Save')}
                    </Button>
                </div>

                {/* Piece metadata */}
                <section className="space-y-6">
                    <PieceForm
                        ref={pieceFormRef}
                        piece={piece}
                        orchestras={orchestras}
                        canEditAllFields={canEditAllFields}
                        showOrchestraCheckboxes={false}
                        genreSuggestions={genreSuggestions}
                        musicTypeSuggestions={musicTypeSuggestions}
                        composerSuggestions={composerSuggestions}
                        arrangerSuggestions={arrangerSuggestions}
                        publisherSuggestions={publisherSuggestions}
                        difficultySuggestions={difficultySuggestions}
                        onAudioTypeChange={setAudioType}
                        renderAudioMp3={
                            <>
                                {audioUrl && (
                                    <audio
                                        controls
                                        src={audioUrl}
                                        className="w-full max-w-md"
                                    />
                                )}
                                <div className="flex items-center gap-4">
                                    <input
                                        ref={audioInputRef}
                                        type="file"
                                        accept=".mp3,audio/mpeg"
                                        onChange={handleAudioUpload}
                                        className="hidden"
                                    />
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            audioInputRef.current?.click()
                                        }
                                        disabled={audioUploading}
                                    >
                                        <Music />
                                        {piece.audio_file_path
                                            ? t('Replace MP3')
                                            : t('Upload MP3')}
                                    </Button>
                                    {piece.audio_file_path && (
                                        <Button
                                            variant="outline"
                                            onClick={handleAudioDelete}
                                        >
                                            <Trash2 className="text-destructive" />
                                            {t('Remove audio')}
                                        </Button>
                                    )}
                                </div>
                            </>
                        }
                    />
                </section>

                {/* Parts management */}
                {canEditAllFields && (
                    <section className="space-y-6">
                        <Heading
                            title={t('Parts')}
                            description={t(
                                'Manage sheet music parts for this piece',
                            )}
                        />

                        {/* Existing parts */}
                        {piece.parts.length > 0 && (
                            <div className="rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>
                                                {t('Original filename')}
                                            </TableHead>
                                            <TableHead>
                                                {t('Instrument')}
                                            </TableHead>
                                            <TableHead className="w-[150px]">
                                                {t('Note')}
                                            </TableHead>
                                            <TableHead className="w-[100px]">
                                                {t('Voice')}
                                            </TableHead>
                                            <TableHead className="w-[120px]">
                                                {t('Amount bought')}
                                            </TableHead>
                                            <TableHead>
                                                {t('Partituur')}
                                            </TableHead>
                                            <TableHead className="w-[80px]" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {piece.parts.map((part) => {
                                            const edit = getPartEdit(part);
                                            return (
                                                <TableRow key={part.id}>
                                                    <TableCell className="text-xs text-muted-foreground">
                                                        {part.original_filename}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Combobox
                                                            options={
                                                                instOptions
                                                            }
                                                            value={
                                                                edit.instrument_type_id
                                                            }
                                                            onChange={(v) =>
                                                                updatePartEdit(
                                                                    part.id,
                                                                    'instrument_type_id',
                                                                    v,
                                                                )
                                                            }
                                                            className="max-w-[250px]"
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            maxLength={20}
                                                            value={edit.note}
                                                            onChange={(e) =>
                                                                updatePartEdit(
                                                                    part.id,
                                                                    'note',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-[130px]"
                                                            placeholder="-"
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            min="1"
                                                            value={edit.voice}
                                                            onChange={(e) =>
                                                                updatePartEdit(
                                                                    part.id,
                                                                    'voice',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-[80px]"
                                                            placeholder="-"
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={
                                                                edit.amount_bought
                                                            }
                                                            onChange={(e) =>
                                                                updatePartEdit(
                                                                    part.id,
                                                                    'amount_bought',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-[100px]"
                                                            placeholder="-"
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Checkbox
                                                            checked={
                                                                edit.is_conductor
                                                            }
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                updatePartEdit(
                                                                    part.id,
                                                                    'is_conductor',
                                                                    !!checked,
                                                                )
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setDeletePart(
                                                                    part,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="text-destructive" />
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {/* Upload new parts */}
                        <div className="space-y-4">
                            <div className="flex items-center gap-4">
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".pdf"
                                    multiple
                                    onChange={handleFilesSelected}
                                    className="hidden"
                                />
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                >
                                    <Upload />
                                    {t('Select PDF files')}
                                </Button>
                            </div>

                            {uploads.length > 0 && (
                                <div className="rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    {t('File')}
                                                </TableHead>
                                                <TableHead>
                                                    {t('Instrument')}
                                                </TableHead>
                                                <TableHead className="w-[150px]">
                                                    {t('Note')}
                                                </TableHead>
                                                <TableHead className="w-[100px]">
                                                    {t('Voice')}
                                                </TableHead>
                                                <TableHead className="w-[120px]">
                                                    {t('Amount bought')}
                                                </TableHead>
                                                <TableHead>
                                                    {t('Partituur')}
                                                </TableHead>
                                                <TableHead className="w-[80px]" />
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {uploads.map((upload, i) => (
                                                <TableRow key={i}>
                                                    <TableCell>
                                                        {upload.file.name}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Combobox
                                                            options={
                                                                instOptions
                                                            }
                                                            value={
                                                                upload.instrument_type_id
                                                            }
                                                            onChange={(v) =>
                                                                updateUpload(
                                                                    i,
                                                                    'instrument_type_id',
                                                                    v,
                                                                )
                                                            }
                                                            className="max-w-[250px]"
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            maxLength={20}
                                                            value={upload.note}
                                                            onChange={(e) =>
                                                                updateUpload(
                                                                    i,
                                                                    'note',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-[130px]"
                                                            placeholder="-"
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            min="1"
                                                            value={upload.voice}
                                                            onChange={(e) =>
                                                                updateUpload(
                                                                    i,
                                                                    'voice',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-[80px]"
                                                            placeholder="-"
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            value={
                                                                upload.amount_bought
                                                            }
                                                            onChange={(e) =>
                                                                updateUpload(
                                                                    i,
                                                                    'amount_bought',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-[100px]"
                                                            placeholder="-"
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Checkbox
                                                            checked={
                                                                upload.is_conductor
                                                            }
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                updateUpload(
                                                                    i,
                                                                    'is_conductor',
                                                                    !!checked,
                                                                )
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                removeUpload(i)
                                                            }
                                                        >
                                                            <Trash2 className="text-destructive" />
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>

                                    <div className="border-t p-4">
                                        <Button
                                            onClick={submitUploads}
                                            disabled={uploading}
                                        >
                                            <Upload />
                                            {t('Upload parts')}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {/* In use by — usage records */}
                <section className="space-y-6">
                    <Heading title={t('In use by')} />

                    {usages.length > 0 && (
                        <div className="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Orchestras')}</TableHead>
                                        <TableHead className="w-[150px]">
                                            {t('From')}
                                        </TableHead>
                                        <TableHead className="w-[150px]">
                                            {t('Until')}
                                        </TableHead>
                                        <TableHead>{t('Details')}</TableHead>
                                        <TableHead className="w-[80px]" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {usages.map((usage, i) => (
                                        <TableRow key={usage.id ?? `new-${i}`}>
                                            <TableCell>
                                                <Combobox
                                                    options={orchestraOptions}
                                                    value={usage.orchestra_id}
                                                    onChange={(v) =>
                                                        updateUsage(
                                                            i,
                                                            'orchestra_id',
                                                            v,
                                                        )
                                                    }
                                                    className="max-w-[250px]"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="date"
                                                    value={usage.van}
                                                    onChange={(e) =>
                                                        updateUsage(
                                                            i,
                                                            'van',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="date"
                                                    value={usage.tot}
                                                    onChange={(e) =>
                                                        updateUsage(
                                                            i,
                                                            'tot',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    value={usage.details}
                                                    onChange={(e) =>
                                                        updateUsage(
                                                            i,
                                                            'details',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder={t('Details')}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {!usage.tot && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setEndUsageIndex(i)
                                                        }
                                                    >
                                                        {t('End')}
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    <Button variant="outline" onClick={addUsage}>
                        <Plus />
                        {t('Add usage')}
                    </Button>
                </section>

                {/* Danger zone — archive/restore */}
                {canArchive && (
                    <>
                        <Separator />
                        <section className="space-y-4">
                            <Heading title={t('Danger zone')} />
                            {piece.deleted_at ? (
                                <div className="rounded-lg border border-amber-500/50 bg-amber-50 p-4 dark:bg-amber-950/20">
                                    <p className="mb-4 text-sm text-amber-800 dark:text-amber-200">
                                        {t('This piece is archived.')}
                                    </p>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/muziekstukken/${piece.id}/restore`,
                                            )
                                        }
                                    >
                                        <RotateCcw />
                                        {t('Restore piece')}
                                    </Button>
                                </div>
                            ) : (
                                <div className="rounded-lg border border-destructive/50 p-4">
                                    <Button
                                        variant="destructive"
                                        onClick={() =>
                                            setShowArchiveDialog(true)
                                        }
                                    >
                                        <Archive />
                                        {t('Archive piece')}
                                    </Button>
                                </div>
                            )}
                        </section>
                    </>
                )}
            </div>

            <Dialog
                open={!!deletePart}
                onOpenChange={() => setDeletePart(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Delete part')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Are you sure you want to delete ":filename"? This action cannot be undone.',
                                {
                                    filename:
                                        deletePart?.original_filename ?? '',
                                },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeletePart(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmDeletePart}
                        >
                            {t('Delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={endUsageIndex !== null}
                onOpenChange={() => setEndUsageIndex(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('End usage')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Are you sure you want to end this usage? Members of ":orchestra" will no longer see this piece.',
                                {
                                    orchestra:
                                        endUsageIndex !== null
                                            ? (orchestras.find(
                                                  (o) =>
                                                      o.id.toString() ===
                                                      usages[endUsageIndex]
                                                          ?.orchestra_id,
                                              )?.name ?? '')
                                            : '',
                                },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEndUsageIndex(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (endUsageIndex !== null) {
                                    updateUsage(
                                        endUsageIndex,
                                        'tot',
                                        new Date().toISOString().split('T')[0],
                                    );
                                    setEndUsageIndex(null);
                                }
                            }}
                        >
                            {t('End')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={showArchiveDialog}
                onOpenChange={(open) => {
                    setShowArchiveDialog(open);
                    if (!open) setArchiveConfirmTitle('');
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Archive piece')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Are you sure you want to archive ":title"? The piece will be hidden from all users.',
                                { title: piece.title },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label>
                            {t('Type ":title" to confirm', {
                                title: piece.title,
                            })}
                        </Label>
                        <Input
                            value={archiveConfirmTitle}
                            onChange={(e) =>
                                setArchiveConfirmTitle(e.target.value)
                            }
                            placeholder={piece.title}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowArchiveDialog(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={archiveConfirmTitle !== piece.title}
                            onClick={() => {
                                setShowArchiveDialog(false);
                                setArchiveConfirmTitle('');
                                router.post(
                                    `/muziekstukken/${piece.id}/archive`,
                                );
                            }}
                        >
                            {t('Archive piece')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
