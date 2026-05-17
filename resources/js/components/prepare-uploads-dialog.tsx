import { router } from '@inertiajs/react';
import {
    Check,
    CircleAlert,
    Loader2,
    SkipForward,
    TriangleAlert,
    Upload,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import PartEditFields from '@/components/part-edit-fields';
import { Button } from '@/components/ui/button';
import type { ComboboxOption } from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import type { InstrumentType, Part } from '@/types/muziekstukken';

export type PartUpload = {
    file: File;
    instrument_type_id: string;
    is_conductor: boolean;
    voice: string;
    amount_bought: string;
    note: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    pieceId: number;
    instrumentTypes: InstrumentType[];
    uploads: PartUpload[];
    existingParts: Part[];
};

function generateCopyName(
    originalName: string,
    existingNames: string[],
): string {
    const dotIndex = originalName.lastIndexOf('.');
    const ext = dotIndex !== -1 ? originalName.slice(dotIndex) : '';
    const nameWithoutExt =
        dotIndex !== -1 ? originalName.slice(0, dotIndex) : originalName;

    // Strip any existing -kopie or -kopie-N suffix to find the base name
    const kopieMatch = nameWithoutExt.match(/^(.+)-kopie(-\d+)?$/);
    const baseName = kopieMatch ? kopieMatch[1] : nameWithoutExt;

    // Try "baseName-kopie.ext" first
    const firstCopy = `${baseName}-kopie${ext}`;
    if (!existingNames.includes(firstCopy)) {
        return firstCopy;
    }

    // Try "baseName-kopie-N.ext" starting from 2
    let n = 2;
    while (existingNames.includes(`${baseName}-kopie-${n}${ext}`)) {
        n++;
    }
    return `${baseName}-kopie-${n}${ext}`;
}

function instrumentOptions(types: InstrumentType[]): ComboboxOption[] {
    return types.map((t) => ({
        value: t.id.toString(),
        label: t.name,
        group: t.instrument_family?.name ?? 'Other',
    }));
}

export default function PrepareUploadsDialog({
    open,
    onOpenChange,
    pieceId,
    instrumentTypes,
    uploads,
    existingParts,
}: Props) {
    const { t } = useTranslation();
    const [activeIndex, setActiveIndex] = useState(0);
    const [approvedIndices, setApprovedIndices] = useState<Set<number>>(
        new Set(),
    );
    const [skippedIndices, setSkippedIndices] = useState<Set<number>>(
        new Set(),
    );
    const [formValues, setFormValues] = useState<PartUpload[]>(() =>
        uploads.map((u) => ({ ...u })),
    );
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [blobUrls, setBlobUrls] = useState<Map<number, string>>(() => {
        const urls = new Map<number, string>();
        uploads.forEach((u, i) => {
            urls.set(i, URL.createObjectURL(u.file));
        });
        return urls;
    });

    const instOptions = instrumentOptions(instrumentTypes);
    const activeBlobUrl = blobUrls.get(activeIndex);

    // Clean up blob URLs on close
    const cleanupBlobUrls = useCallback(() => {
        setBlobUrls((prev) => {
            for (const url of prev.values()) {
                URL.revokeObjectURL(url);
            }
            return new Map();
        });
    }, []);

    function handleClose(nextOpen: boolean) {
        if (nextOpen) {
            onOpenChange(true);
            return;
        }

        if (
            !window.confirm(
                t('Are you sure you want to close? Changes will be lost.'),
            )
        ) {
            return;
        }

        cleanupBlobUrls();
        onOpenChange(false);
    }

    function updateFormValue(
        index: number,
        field: keyof PartUpload,
        value: string | boolean,
    ) {
        setFormValues((prev) =>
            prev.map((v, i) => (i === index ? { ...v, [field]: value } : v)),
        );
    }

    function advanceToNext(handled: Set<number>, alsoHandled?: Set<number>) {
        const isHandled = (i: number) =>
            handled.has(i) || (alsoHandled?.has(i) ?? false);

        const nextIndex = formValues.findIndex(
            (_, i) => i > activeIndex && !isHandled(i),
        );
        if (nextIndex !== -1) {
            setActiveIndex(nextIndex);
        } else {
            const wrapIndex = formValues.findIndex((_, i) => !isHandled(i));
            if (wrapIndex !== -1) {
                setActiveIndex(wrapIndex);
            }
        }
    }

    function handleApproveAndNext() {
        const newApproved = new Set(approvedIndices);
        newApproved.add(activeIndex);
        setApprovedIndices(newApproved);
        advanceToNext(newApproved, skippedIndices);
    }

    function handleSkip() {
        const newSkipped = new Set(skippedIndices);
        newSkipped.add(activeIndex);
        setSkippedIndices(newSkipped);
        // Un-approve if it was previously approved
        setApprovedIndices((prev) => {
            const next = new Set(prev);
            next.delete(activeIndex);
            return next;
        });
        advanceToNext(newSkipped, approvedIndices);
    }

    function handleDuplicateDocument() {
        if (!activeUpload) return;

        const existingNames = formValues.map((u) => u.file.name);
        const copyName = generateCopyName(
            activeUpload.file.name,
            existingNames,
        );
        const newFile = new File([activeUpload.file], copyName, {
            type: activeUpload.file.type,
        });

        const newUpload: PartUpload = {
            file: newFile,
            instrument_type_id: '',
            is_conductor: false,
            voice: '',
            amount_bought: '1',
            note: '',
        };

        const insertAt = activeIndex + 1;

        setFormValues((prev) => [
            ...prev.slice(0, insertAt),
            newUpload,
            ...prev.slice(insertAt),
        ]);

        // Shift blob URLs at and after the insertion point up by 1
        setBlobUrls((prev) => {
            const next = new Map<number, string>();
            for (const [i, url] of prev) {
                next.set(i >= insertAt ? i + 1 : i, url);
            }
            next.set(insertAt, URL.createObjectURL(newFile));
            return next;
        });

        // Shift approved and skipped indices at and after the insertion point up by 1
        const shiftIndices = (prev: Set<number>) => {
            const next = new Set<number>();
            for (const i of prev) {
                next.add(i >= insertAt ? i + 1 : i);
            }
            return next;
        };
        setApprovedIndices(shiftIndices);
        setSkippedIndices(shiftIndices);
    }

    function handleUploadAll() {
        if (uploading || formValues.length === 0) return;

        const partsToUpload = formValues.filter(
            (_, i) => !skippedIndices.has(i),
        );
        if (partsToUpload.length === 0) return;

        const formData = new FormData();
        partsToUpload.forEach((upload, i) => {
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
        setUploadError(null);

        router.post(`/muziekstukken/${pieceId}/parts`, formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                cleanupBlobUrls();
                onOpenChange(false);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                setUploadError(
                    typeof firstError === 'string'
                        ? firstError
                        : t('Upload failed'),
                );
            },
            onFinish: () => setUploading(false),
        });
    }

    const activeUpload = formValues[activeIndex];
    const isActiveApproved = approvedIndices.has(activeIndex);
    const isActiveSkipped = skippedIndices.has(activeIndex);
    const canApprove = !!activeUpload?.instrument_type_id;
    const allHandled =
        formValues.length > 0 &&
        approvedIndices.size + skippedIndices.size === formValues.length &&
        approvedIndices.size > 0;

    // Warnings for the active file
    const activeWarnings: string[] = [];
    if (activeUpload) {
        // Duplicate filename — check against other uploads and existing parts
        const filename = activeUpload.file.name;
        const dupeInBatch = formValues.some(
            (u, i) => i !== activeIndex && u.file.name === filename,
        );
        const dupeInExisting = existingParts.some(
            (p) => p.original_filename === filename,
        );
        if (dupeInBatch || dupeInExisting) {
            activeWarnings.push(
                t('Duplicate filename: ":filename"', { filename }),
            );
        }

        // Duplicate assignment — check against other uploads and existing parts
        if (activeUpload.instrument_type_id && !activeUpload.is_conductor) {
            const key = `${activeUpload.instrument_type_id}-${activeUpload.voice}-${activeUpload.note}`;
            const typeName =
                instrumentTypes.find(
                    (it) =>
                        it.id.toString() === activeUpload.instrument_type_id,
                )?.name ?? activeUpload.instrument_type_id;
            let label = typeName;
            if (activeUpload.voice) label += ` ${activeUpload.voice}`;
            if (activeUpload.note) label += ` (${activeUpload.note})`;

            const dupeAssignBatch = formValues.some((u, i) => {
                if (i === activeIndex || u.is_conductor) return false;
                return `${u.instrument_type_id}-${u.voice}-${u.note}` === key;
            });
            const dupeAssignExisting = existingParts.some((p) => {
                if (p.is_conductor) return false;
                return (
                    `${p.instrument_type_id}-${p.voice?.toString() ?? ''}-${p.note ?? ''}` ===
                    key
                );
            });
            if (dupeAssignBatch || dupeAssignExisting) {
                activeWarnings.push(
                    t('Duplicate assignment: ":label"', { label }),
                );
            }
        }
    }

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="flex h-[85vh] max-w-7xl flex-col">
                <DialogHeader>
                    <DialogTitle>{t('Prepare uploads')}</DialogTitle>
                    <DialogDescription>
                        {t('Review and upload each file individually.')}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid min-h-0 flex-1 grid-cols-[220px_280px_1fr] gap-4">
                    {/* Left panel: file list */}
                    <div className="overflow-y-auto rounded-lg border p-2">
                        {formValues.map((upload, i) => {
                            const isApproved = approvedIndices.has(i);
                            const isSkipped = skippedIndices.has(i);
                            const isActive = i === activeIndex;
                            const typeName = instrumentTypes.find(
                                (it) =>
                                    it.id.toString() ===
                                    upload.instrument_type_id,
                            )?.name;
                            const label = isSkipped
                                ? t('Skipped')
                                : typeName
                                  ? [typeName, upload.voice, upload.note]
                                        .filter(Boolean)
                                        .join(' - ')
                                  : null;
                            return (
                                <button
                                    key={i}
                                    type="button"
                                    onClick={() => setActiveIndex(i)}
                                    className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm ${
                                        isActive
                                            ? 'bg-accent text-accent-foreground'
                                            : 'hover:bg-muted'
                                    } ${isSkipped ? 'opacity-50' : ''}`}
                                >
                                    {isApproved ? (
                                        <Check className="size-4 shrink-0 text-green-600" />
                                    ) : isSkipped ? (
                                        <SkipForward className="size-4 shrink-0 text-muted-foreground" />
                                    ) : (
                                        <span className="size-4 shrink-0" />
                                    )}
                                    <span className="min-w-0">
                                        <span
                                            className={`block truncate ${isSkipped ? 'line-through' : ''}`}
                                        >
                                            {upload.file.name}
                                        </span>
                                        {label && (
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {label}
                                            </span>
                                        )}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Middle panel: form */}
                    <div className="space-y-4 overflow-y-auto rounded-lg border bg-muted/80 p-4">
                        {activeUpload && (
                            <>
                                <p className="text-sm text-muted-foreground">
                                    {activeIndex + 1} {t('of')}{' '}
                                    {formValues.length}
                                </p>

                                {uploadError && (
                                    <div className="flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200">
                                        <CircleAlert className="mt-0.5 size-4 shrink-0" />
                                        {uploadError}
                                    </div>
                                )}

                                {activeWarnings.map((warning, i) => (
                                    <div
                                        key={i}
                                        className="flex items-start gap-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-700 dark:bg-yellow-950 dark:text-yellow-200"
                                    >
                                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                        {warning}
                                    </div>
                                ))}

                                <div className="space-y-4">
                                    <PartEditFields
                                        values={activeUpload}
                                        instrumentOptions={instOptions}
                                        onChange={(field, value) =>
                                            updateFormValue(
                                                activeIndex,
                                                field,
                                                value,
                                            )
                                        }
                                        instrumentRequired
                                    />

                                    {isActiveSkipped ? (
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                setSkippedIndices((prev) => {
                                                    const next = new Set(prev);
                                                    next.delete(activeIndex);
                                                    return next;
                                                });
                                            }}
                                            className="w-full"
                                        >
                                            {t('Unskip')}
                                        </Button>
                                    ) : (
                                        <>
                                            {!isActiveApproved && (
                                                <Button
                                                    onClick={
                                                        handleApproveAndNext
                                                    }
                                                    disabled={!canApprove}
                                                    className="w-full"
                                                >
                                                    <Check />
                                                    {t('Approve & next')}
                                                </Button>
                                            )}

                                            <Button
                                                variant="ghost"
                                                onClick={handleSkip}
                                                className="w-full text-muted-foreground"
                                            >
                                                <SkipForward />
                                                {t('Skip')}
                                            </Button>

                                            <button
                                                type="button"
                                                onClick={
                                                    handleDuplicateDocument
                                                }
                                                className="w-full cursor-pointer text-center text-sm text-muted-foreground underline hover:text-foreground"
                                            >
                                                {t(
                                                    'This document contains another instrument',
                                                )}
                                            </button>
                                        </>
                                    )}
                                </div>
                            </>
                        )}
                    </div>

                    {/* Right panel: PDF preview */}
                    <div className="min-h-0 overflow-hidden rounded-lg border">
                        {activeUpload && activeBlobUrl && (
                            <iframe
                                src={activeBlobUrl}
                                className="h-full w-full"
                                title={activeUpload.file.name}
                            />
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => handleClose(false)}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button
                        onClick={handleUploadAll}
                        disabled={!allHandled || uploading}
                    >
                        {uploading ? (
                            <Loader2 className="animate-spin" />
                        ) : (
                            <Upload />
                        )}
                        {t('Upload all parts')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
