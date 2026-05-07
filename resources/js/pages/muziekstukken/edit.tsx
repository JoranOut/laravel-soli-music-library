import { Head, router } from '@inertiajs/react';
import {
    CircleAlert,
    Music,
    Plus,
    Save,
    Trash2,
    TriangleAlert,
    Upload,
} from 'lucide-react';
import { useRef, useState, useMemo } from 'react';
import { Heading } from '@/components/heading';
import type { PartUpload } from '@/components/prepare-uploads-dialog';
import PrepareUploadsDialog from '@/components/prepare-uploads-dialog';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import AppLayout from '@/layouts/app-layout';
import { guessFromFilename } from '@/lib/instrument-guess';
import type {
    InstrumentType,
    Orchestra,
    Part,
    Piece,
} from '@/types/muziekstukken';
import PartsMatrix from './parts-matrix';
import type { PieceFormHandle } from './piece-form';
import PieceForm from './piece-form';

type Props = {
    piece: Piece;
    audioUrl: string | null;
    orchestras: Orchestra[];
    instrumentTypes?: InstrumentType[];
    canEditAllFields?: boolean;
    canEditSpeelperiodes?: boolean;
    genreSuggestions?: string[];
    musicTypeSuggestions?: string[];
    composerSuggestions?: string[];
    arrangerSuggestions?: string[];
    publisherSuggestions?: string[];
    difficultySuggestions?: string[];
    boughtForOccasionSuggestions?: string[];
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
    canEditSpeelperiodes = true,
    genreSuggestions = [],
    musicTypeSuggestions = [],
    composerSuggestions = [],
    arrangerSuggestions = [],
    publisherSuggestions = [],
    difficultySuggestions = [],
    boughtForOccasionSuggestions = [],
}: Props) {
    const { t } = useTranslation();
    const pieceFormRef = useRef<PieceFormHandle>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const audioInputRef = useRef<HTMLInputElement>(null);
    const [audioUploading, setAudioUploading] = useState(false);
    const [uploads, setUploads] = useState<PartUpload[]>([]);
    const [prepareDialogOpen, setPrepareDialogOpen] = useState(false);
    const [deletePart, setDeletePart] = useState<Part | null>(null);
    const [endSpeelperiodeIndex, setEndSpeelperiodeIndex] = useState<
        number | null
    >(null);
    const [partEdits, setPartEdits] = useState<Record<number, PartEdit>>({});
    const [saving, setSaving] = useState(false);
    const [showPastSpeelperiodes, setShowPastSpeelperiodes] = useState(false);
    const [speelperiodes, setSpeelperiodes] = useState<UsageEdit[]>(() =>
        (piece.speelperiodes ?? []).map((u) => ({
            id: u.id,
            orchestra_id: u.orchestra_id.toString(),
            van: u.van?.split('T')[0] ?? '',
            tot: u.tot?.split('T')[0] ?? '',
            details: u.details ?? '',
        })),
    );

    const [pieceFormDirty, setPieceFormDirty] = useState(false);
    const [currentStatus, setCurrentStatus] = useState(piece.status);

    // Matrix edits for fileless parts (non-digital pieces)
    const [matrixEdits, setMatrixEdits] = useState<Record<string, number>>(
        () => {
            const edits: Record<string, number> = {};
            // Initialize from existing fileless parts
            for (const part of piece.parts.filter(
                (p) => p.original_filename === null,
            )) {
                if (part.is_conductor) {
                    edits['conductor'] =
                        (edits['conductor'] ?? 0) + (part.amount_bought ?? 1);
                } else {
                    const voice = part.voice ?? 1;
                    const key = `type_${part.instrument_type_id}_v${voice}`;
                    edits[key] = (edits[key] ?? 0) + (part.amount_bought ?? 1);
                }
            }
            return edits;
        },
    );
    const [initialMatrixEdits] = useState(() => ({ ...matrixEdits }));

    const initialSpeelperiodes = useMemo(
        () =>
            (piece.speelperiodes ?? []).map((u) => ({
                id: u.id,
                orchestra_id: u.orchestra_id.toString(),
                van: u.van?.split('T')[0] ?? '',
                tot: u.tot?.split('T')[0] ?? '',
                details: u.details ?? '',
            })),
        [piece.speelperiodes],
    );

    const hasPartChanges =
        Object.keys(partEdits).length > 0 &&
        piece.parts.some((p) => hasPartChanged(p));

    const hasSpeelperiodeChanges =
        speelperiodes.length !== initialSpeelperiodes.length ||
        speelperiodes.some(
            (u, i) =>
                !initialSpeelperiodes[i] ||
                u.orchestra_id !== initialSpeelperiodes[i].orchestra_id ||
                u.van !== initialSpeelperiodes[i].van ||
                u.tot !== initialSpeelperiodes[i].tot ||
                u.details !== initialSpeelperiodes[i].details,
        );

    const hasMatrixChanges =
        Object.keys(matrixEdits).some(
            (key) => (matrixEdits[key] ?? 0) !== (initialMatrixEdits[key] ?? 0),
        ) ||
        Object.keys(initialMatrixEdits).some(
            (key) => (matrixEdits[key] ?? 0) !== (initialMatrixEdits[key] ?? 0),
        );

    useUnsavedChanges(
        pieceFormDirty ||
            hasPartChanges ||
            (canEditSpeelperiodes && hasSpeelperiodeChanges) ||
            hasMatrixChanges,
    );

    const today = new Date().toISOString().split('T')[0];
    const isPast = (u: { tot: string }) => !!u.tot && u.tot < today;

    // --- Validation warnings & errors ---
    type Alert = {
        type: 'error' | 'warning';
        message: string;
        partIds: number[];
    };

    const alerts = (() => {
        const result: Alert[] = [];
        const fileParts = piece.parts.filter(
            (p) => p.original_filename !== null,
        );

        // Error: analog status with uploaded files
        if (currentStatus !== 'digitaal' && fileParts.length > 0) {
            result.push({
                type: 'error',
                message: t(
                    'This piece has uploaded files but the status is not set to "digitaal". Uploaded parts will not be visible.',
                ),
                partIds: [],
            });
        }

        // Warning: no partituur (conductor part)
        const hasPartituur = piece.parts.some(
            (p) => getPartEdit(p).is_conductor,
        );
        if (piece.parts.length > 0 && !hasPartituur) {
            result.push({
                type: 'warning',
                message: t('There is no conductor part uploaded. Please check with the muziekbeheer volunteers.'),
                partIds: [],
            });
        }

        // Warning: duplicate original filenames
        if (fileParts.length > 0) {
            const filenameCounts = new Map<string, number[]>();
            for (const p of fileParts) {
                const name = p.original_filename!;
                if (!filenameCounts.has(name)) filenameCounts.set(name, []);
                filenameCounts.get(name)!.push(p.id);
            }
            for (const [filename, ids] of filenameCounts) {
                if (ids.length > 1) {
                    result.push({
                        type: 'warning',
                        message: t(
                            'Duplicate filename: ":filename" (:count times)',
                            {
                                filename,
                                count: ids.length.toString(),
                            },
                        ),
                        partIds: ids,
                    });
                }
            }
        }

        // Warning: non-unique instrument+voice+note assignment
        if (fileParts.length > 0) {
            const assignmentCounts = new Map<
                string,
                { ids: number[]; label: string }
            >();
            for (const p of fileParts) {
                const edit = getPartEdit(p);
                if (edit.is_conductor) continue;
                const typeName =
                    instrumentTypes.find(
                        (it) => it.id.toString() === edit.instrument_type_id,
                    )?.name ?? edit.instrument_type_id;
                const key = `${edit.instrument_type_id}-${edit.voice}-${edit.note}`;
                let label = typeName;
                if (edit.voice) label += ` ${edit.voice}`;
                if (edit.note) label += ` (${edit.note})`;
                if (!assignmentCounts.has(key))
                    assignmentCounts.set(key, { ids: [], label });
                assignmentCounts.get(key)!.ids.push(p.id);
            }
            for (const [, { ids, label }] of assignmentCounts) {
                if (ids.length > 1) {
                    result.push({
                        type: 'warning',
                        message: t(
                            'Duplicate assignment: ":label" (:count times)',
                            {
                                label,
                                count: ids.length.toString(),
                            },
                        ),
                        partIds: ids,
                    });
                }
            }
        }

        return result;
    })();

    const highlightedPartIds = new Set(alerts.flatMap((a) => a.partIds));

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

    function addSpeelperiode() {
        setSpeelperiodes((prev) => [
            ...prev,
            { id: null, orchestra_id: '', van: '', tot: '', details: '' },
        ]);
    }

    function updateSpeelperiode(
        index: number,
        field: keyof UsageEdit,
        value: string,
    ) {
        setSpeelperiodes((prev) =>
            prev.map((u, i) => (i === index ? { ...u, [field]: value } : u)),
        );
    }

    function handleMatrixChange(key: string, value: number) {
        setMatrixEdits((prev) => {
            const next = { ...prev, [key]: value };

            // When setting a voice to 0, remove higher voices
            const match = key.match(/^type_(\d+)_v(\d+)$/);
            if (match && value === 0) {
                const typeId = match[1];
                let v = parseInt(match[2]) + 1;
                while (`type_${typeId}_v${v}` in next) {
                    delete next[`type_${typeId}_v${v}`];
                    v++;
                }
            }

            return next;
        });
    }

    function buildMatrixPayload() {
        const payload: {
            instrument_type_id: number;
            is_conductor: boolean;
            voice: number | null;
            amount_bought: number;
        }[] = [];

        // Conductor entry
        if (matrixEdits['conductor'] !== undefined) {
            // Use the first instrument type as a placeholder for conductor parts
            const conductorTypeId = instrumentTypes[0]?.id;
            if (conductorTypeId) {
                payload.push({
                    instrument_type_id: conductorTypeId,
                    is_conductor: true,
                    voice: null,
                    amount_bought: matrixEdits['conductor'],
                });
            }
        }

        // Regular instrument types — include all voices present in matrixEdits
        for (const type of instrumentTypes) {
            let v = 1;
            while (`type_${type.id}_v${v}` in matrixEdits) {
                payload.push({
                    instrument_type_id: type.id,
                    is_conductor: false,
                    voice: v,
                    amount_bought: matrixEdits[`type_${type.id}_v${v}`],
                });
                v++;
            }
        }

        return payload;
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

        const speelperiodePayload = speelperiodes
            .filter((u) => u.orchestra_id !== '')
            .map((u) => ({
                id: u.id,
                orchestra_id: Number(u.orchestra_id),
                van: u.van || null,
                tot: u.tot || null,
                details: u.details || null,
            }));

        // Build matrix_parts payload for non-digital pieces
        const matrixPartsPayload =
            currentStatus !== 'digitaal' ? buildMatrixPayload() : undefined;

        setSaving(true);
        router.put(
            `/muziekstukken/${piece.id}`,
            {
                ...pieceData,
                speelperiodes: speelperiodePayload,
                parts: changedParts,
                ...(matrixPartsPayload
                    ? { matrix_parts: matrixPartsPayload }
                    : {}),
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
                instrument_type_id: guess.instrument_type_id ?? '',
                is_conductor: guess.is_conductor ?? false,
                voice: guess.voice ?? '',
                amount_bought: '1',
                note: '',
            };
        });
        setUploads(newUploads);
        setPrepareDialogOpen(true);
        if (fileInputRef.current) fileInputRef.current.value = '';
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
                        boughtForOccasionSuggestions={
                            boughtForOccasionSuggestions
                        }
                        onDirtyChange={setPieceFormDirty}
                        onStatusChange={setCurrentStatus}
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

                {/* In use by — speelperiodes */}
                {canEditSpeelperiodes && (
                    <section className="space-y-6">
                        <Heading title={t('In use by')} />

                        {speelperiodes.some((u) => isPast(u)) && (
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={showPastSpeelperiodes}
                                    onCheckedChange={(checked) =>
                                        setShowPastSpeelperiodes(!!checked)
                                    }
                                />
                                {t('Show past play periods')}
                            </label>
                        )}

                        {speelperiodes.length > 0 && (
                            <div className="rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>
                                                {t('Orchestras')}
                                            </TableHead>
                                            <TableHead className="w-[150px]">
                                                {t('From')}
                                            </TableHead>
                                            <TableHead className="w-[150px]">
                                                {t('Until')}
                                            </TableHead>
                                            <TableHead>
                                                {t('Details')}
                                            </TableHead>
                                            <TableHead className="w-[80px]" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {speelperiodes.map(
                                            (speelperiode, i) => {
                                                if (
                                                    !showPastSpeelperiodes &&
                                                    isPast(speelperiode)
                                                )
                                                    return null;
                                                return (
                                                    <TableRow
                                                        key={
                                                            speelperiode.id ??
                                                            `new-${i}`
                                                        }
                                                    >
                                                        <TableCell>
                                                            <Combobox
                                                                options={
                                                                    orchestraOptions
                                                                }
                                                                value={
                                                                    speelperiode.orchestra_id
                                                                }
                                                                onChange={(v) =>
                                                                    updateSpeelperiode(
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
                                                                value={
                                                                    speelperiode.van
                                                                }
                                                                onChange={(e) =>
                                                                    updateSpeelperiode(
                                                                        i,
                                                                        'van',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </TableCell>
                                                        <TableCell>
                                                            <Input
                                                                type="date"
                                                                value={
                                                                    speelperiode.tot
                                                                }
                                                                onChange={(e) =>
                                                                    updateSpeelperiode(
                                                                        i,
                                                                        'tot',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </TableCell>
                                                        <TableCell>
                                                            <Input
                                                                value={
                                                                    speelperiode.details
                                                                }
                                                                onChange={(e) =>
                                                                    updateSpeelperiode(
                                                                        i,
                                                                        'details',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder={t(
                                                                    'Details',
                                                                )}
                                                            />
                                                        </TableCell>
                                                        <TableCell>
                                                            {!speelperiode.tot && (
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setEndSpeelperiodeIndex(
                                                                            i,
                                                                        )
                                                                    }
                                                                >
                                                                    {t('End')}
                                                                </Button>
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            },
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        <Button variant="outline" onClick={addSpeelperiode}>
                            <Plus />
                            {t('Add play period')}
                        </Button>
                    </section>
                )}

                {/* Parts management */}
                {canEditAllFields && (
                    <section className="space-y-6">
                        <Heading
                            title={t('Parts')}
                            description={t(
                                'Manage sheet music parts for this piece',
                            )}
                        />

                        {alerts.length > 0 && (
                            <div className="space-y-2">
                                {alerts.map((alert, i) => (
                                    <div
                                        key={i}
                                        className={`flex items-start gap-2 rounded-lg border p-3 text-sm ${
                                            alert.type === 'error'
                                                ? 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200'
                                                : 'border-yellow-300 bg-yellow-50 text-yellow-800 dark:border-yellow-700 dark:bg-yellow-950 dark:text-yellow-200'
                                        }`}
                                    >
                                        {alert.type === 'error' ? (
                                            <CircleAlert className="mt-0.5 size-4 shrink-0" />
                                        ) : (
                                            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                        )}
                                        {alert.message}
                                    </div>
                                ))}
                            </div>
                        )}

                        {currentStatus === 'digitaal' ? (
                            <>
                                {/* Existing file-based parts */}
                                {piece.parts.filter(
                                    (p) => p.original_filename !== null,
                                ).length > 0 && (
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
                                                {piece.parts
                                                    .filter(
                                                        (p) =>
                                                            p.original_filename !==
                                                            null,
                                                    )
                                                    .map((part) => {
                                                        const edit =
                                                            getPartEdit(part);
                                                        return (
                                                            <TableRow
                                                                key={part.id}
                                                                className={
                                                                    highlightedPartIds.has(
                                                                        part.id,
                                                                    )
                                                                        ? 'bg-yellow-50 dark:bg-yellow-950/50'
                                                                        : undefined
                                                                }
                                                            >
                                                                <TableCell className="text-xs text-muted-foreground">
                                                                    {
                                                                        part.original_filename
                                                                    }
                                                                </TableCell>
                                                                <TableCell>
                                                                    <Combobox
                                                                        options={
                                                                            instOptions
                                                                        }
                                                                        value={
                                                                            edit.instrument_type_id
                                                                        }
                                                                        onChange={(
                                                                            v,
                                                                        ) =>
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
                                                                        maxLength={
                                                                            20
                                                                        }
                                                                        value={
                                                                            edit.note
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updatePartEdit(
                                                                                part.id,
                                                                                'note',
                                                                                e
                                                                                    .target
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
                                                                        value={
                                                                            edit.voice
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updatePartEdit(
                                                                                part.id,
                                                                                'voice',
                                                                                e
                                                                                    .target
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
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updatePartEdit(
                                                                                part.id,
                                                                                'amount_bought',
                                                                                e
                                                                                    .target
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
                                <div>
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
                            </>
                        ) : (
                            <PartsMatrix
                                instrumentTypes={instrumentTypes}
                                parts={piece.parts}
                                matrixEdits={matrixEdits}
                                onMatrixChange={handleMatrixChange}
                            />
                        )}
                    </section>
                )}

                {/* Bottom save button */}
                <div className="flex justify-end">
                    <Button onClick={saveAll} disabled={saving}>
                        <Save />
                        {t('Save')}
                    </Button>
                </div>
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
                                        deletePart?.original_filename ??
                                        deletePart?.instrument_type?.name ??
                                        '',
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
                open={endSpeelperiodeIndex !== null}
                onOpenChange={() => setEndSpeelperiodeIndex(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('End play period')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Are you sure you want to end this play period? Members of ":orchestra" will no longer see this piece.',
                                {
                                    orchestra:
                                        endSpeelperiodeIndex !== null
                                            ? (orchestras.find(
                                                  (o) =>
                                                      o.id.toString() ===
                                                      speelperiodes[
                                                          endSpeelperiodeIndex
                                                      ]?.orchestra_id,
                                              )?.name ?? '')
                                            : '',
                                },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEndSpeelperiodeIndex(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (endSpeelperiodeIndex !== null) {
                                    updateSpeelperiode(
                                        endSpeelperiodeIndex,
                                        'tot',
                                        new Date().toISOString().split('T')[0],
                                    );
                                    setEndSpeelperiodeIndex(null);
                                }
                            }}
                        >
                            {t('End')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <PrepareUploadsDialog
                open={prepareDialogOpen}
                onOpenChange={(open) => {
                    setPrepareDialogOpen(open);
                    if (!open) setUploads([]);
                }}
                pieceId={piece.id}
                instrumentTypes={instrumentTypes}
                uploads={uploads}
                existingParts={piece.parts.filter(
                    (p) => p.original_filename !== null,
                )}
            />
        </AppLayout>
    );
}
