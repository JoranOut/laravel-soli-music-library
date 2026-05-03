import { ExternalLink, Loader2, Printer } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { FamilyPartsGrid, VoiceLabel } from '@/components/family-parts-grid';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useGroupedTypes } from '@/hooks/use-grouped-types';
import { useTranslation } from '@/hooks/use-translation';
import type { MergeProgress, MergeResult, PrintPart } from '@/lib/pdf-merge';
import { mergePartsForPrint } from '@/lib/pdf-merge';
import type { InstrumentType, Part } from '@/types/muziekstukken';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    parts: Part[];
    instrumentTypes: InstrumentType[];
};

function getPartLabel(part: Part): string {
    let label = part.instrument_type.name;
    if (part.voice !== null) label += ` ${part.voice}`;
    if (part.note) label += ` (${part.note})`;
    return label;
}

export function BatchPrintDialog({
    open,
    onOpenChange,
    parts,
    instrumentTypes,
}: Props) {
    const { t } = useTranslation();
    const { conductorParts, families } = useGroupedTypes(
        instrumentTypes,
        parts,
    );

    const printableParts = parts.filter((p) => p.download_url);

    const [quantities, setQuantities] = useState<Record<number, number>>(() => {
        const initial: Record<number, number> = {};
        for (const p of printableParts) {
            initial[p.id] = p.amount_bought ?? 0;
        }
        return initial;
    });
    const [doubleSided, setDoubleSided] = useState(true);
    const [status, setStatus] = useState<
        'idle' | 'processing' | 'done' | 'error'
    >('idle');
    const [progress, setProgress] = useState<MergeProgress | null>(null);
    const [results, setResults] = useState<MergeResult[]>([]);
    const [error, setError] = useState<string | null>(null);
    const abortRef = useRef<AbortController | null>(null);
    const blobUrlsRef = useRef<string[]>([]);

    const setQuantity = useCallback((partId: number, value: number) => {
        setQuantities((prev) => ({ ...prev, [partId]: Math.max(0, value) }));
    }, []);

    const hasAnyQuantity = Object.values(quantities).some((q) => q > 0);

    function cleanup() {
        for (const url of blobUrlsRef.current) {
            URL.revokeObjectURL(url);
        }
        blobUrlsRef.current = [];
    }

    function handleOpenChange(nextOpen: boolean) {
        if (!nextOpen) {
            abortRef.current?.abort();
            cleanup();
            setStatus('idle');
            setProgress(null);
            setResults([]);
            setError(null);
        }
        onOpenChange(nextOpen);
    }

    async function handleGenerate() {
        cleanup();
        setStatus('processing');
        setProgress(null);
        setError(null);
        setResults([]);

        const controller = new AbortController();
        abortRef.current = controller;

        const printParts: PrintPart[] = printableParts
            .filter((p) => (quantities[p.id] ?? 0) > 0)
            .map((p) => ({
                id: p.id,
                label: getPartLabel(p),
                downloadUrl: p.download_url!,
                quantity: quantities[p.id],
            }));

        try {
            const mergeResults = await mergePartsForPrint(
                printParts,
                setProgress,
                controller.signal,
                { doubleSided },
            );
            blobUrlsRef.current = mergeResults.map((r) => r.blobUrl);
            setResults(mergeResults);
            setStatus('done');
        } catch (err) {
            if (err instanceof DOMException && err.name === 'AbortError') {
                setStatus('idle');
                return;
            }
            setError(
                err instanceof Error
                    ? err.message
                    : t('An unknown error occurred'),
            );
            setStatus('error');
        }
    }

    function handleCancel() {
        abortRef.current?.abort();
        setStatus('idle');
        setProgress(null);
    }

    function openResult(blobUrl: string) {
        window.open(blobUrl, '_blank');
    }

    function openAll() {
        for (const result of results) {
            window.open(result.blobUrl, '_blank');
        }
    }

    const progressPercent =
        progress && progress.total > 0
            ? Math.round((progress.current / progress.total) * 100)
            : 0;

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{t('Drukklaar maken')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Stel per partij het aantal exemplaren in. Partijen met hetzelfde aantal worden samengevoegd tot één PDF.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                {status === 'idle' || status === 'error' ? (
                    <>
                        <div className="max-h-[60vh] space-y-6 overflow-y-auto pr-1">
                            {/* Conductor parts */}
                            {conductorParts.length > 0 && (
                                <div className="space-y-2">
                                    <h3 className="text-sm font-medium text-muted-foreground">
                                        {t('Conductor part')}
                                    </h3>
                                    <ul className="space-y-1">
                                        {conductorParts.map((part) => (
                                            <li
                                                key={part.id}
                                                className={`flex items-center justify-between text-sm ${!part.download_url ? 'text-muted-foreground/50' : ''}`}
                                            >
                                                <span>
                                                    {part.original_filename ??
                                                        t('Partituur')}
                                                </span>
                                                {part.download_url ? (
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        value={
                                                            quantities[
                                                                part.id
                                                            ] ?? 0
                                                        }
                                                        onChange={(e) =>
                                                            setQuantity(
                                                                part.id,
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                        className="w-[70px]"
                                                    />
                                                ) : (
                                                    <span>&mdash;</span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <FamilyPartsGrid
                                families={families}
                                showEmptyTypes
                                renderEmptyType={(type) => (
                                    <li className="flex items-center justify-between text-sm text-muted-foreground/50">
                                        <span>{type.name}</span>
                                        <span>&mdash;</span>
                                    </li>
                                )}
                                renderRow={(group) => {
                                    const part = group.parts[0];
                                    const hasPdf = !!part.download_url;
                                    return (
                                        <li
                                            className={`flex items-center justify-between text-sm ${!hasPdf ? 'text-muted-foreground/50' : ''}`}
                                        >
                                            <VoiceLabel group={group} />
                                            {hasPdf ? (
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    value={
                                                        quantities[part.id] ?? 0
                                                    }
                                                    onChange={(e) =>
                                                        setQuantity(
                                                            part.id,
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="w-[70px]"
                                                />
                                            ) : (
                                                <span>&mdash;</span>
                                            )}
                                        </li>
                                    );
                                }}
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="double-sided"
                                checked={doubleSided}
                                onCheckedChange={(checked) =>
                                    setDoubleSided(checked === true)
                                }
                            />
                            <Label
                                htmlFor="double-sided"
                                className="text-sm font-normal"
                            >
                                {t(
                                    'Dubbelzijdig printen (lege pagina na oneven partijen)',
                                )}
                            </Label>
                        </div>

                        {error && (
                            <div className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                {error}
                            </div>
                        )}

                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => handleOpenChange(false)}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                onClick={handleGenerate}
                                disabled={!hasAnyQuantity}
                            >
                                <Printer className="size-4" />
                                {t("Genereer PDF's")}
                            </Button>
                        </DialogFooter>
                    </>
                ) : status === 'processing' ? (
                    <>
                        <div className="space-y-3 py-4">
                            <div className="flex items-center gap-2 text-sm">
                                <Loader2 className="size-4 animate-spin" />
                                <span>
                                    {progress?.phase === 'downloading'
                                        ? `${t('Downloaden')}: ${progress.label} (${progress.current}/${progress.total})`
                                        : progress?.phase === 'merging'
                                          ? `${t('Samenvoegen')}: ${progress.label} (${progress.current}/${progress.total})`
                                          : t('Bezig...')}
                                </span>
                            </div>
                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full bg-primary transition-all duration-300"
                                    style={{ width: `${progressPercent}%` }}
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={handleCancel}>
                                {t('Cancel')}
                            </Button>
                        </DialogFooter>
                    </>
                ) : (
                    /* status === 'done' */
                    <>
                        <div className="space-y-3 py-2">
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'De volgende PDF-bestanden zijn klaar. Klik op "Openen" om ze in een nieuw tabblad te openen voor het printen.',
                                )}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Als er geen nieuw tabblad opent, controleer dan of je pop-upblocker deze site toestaat.',
                                )}
                            </p>
                            <div className="space-y-2">
                                {results.map((result) => (
                                    <div
                                        key={result.quantity}
                                        className="flex items-center justify-between rounded-md border px-3 py-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">
                                                {result.quantity}&times;
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {result.partLabels.join(', ')}
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                openResult(result.blobUrl)
                                            }
                                        >
                                            <ExternalLink className="size-4" />
                                            {t('Openen')}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => handleOpenChange(false)}
                            >
                                {t('Sluiten')}
                            </Button>
                            {results.length > 1 && (
                                <Button onClick={openAll}>
                                    <ExternalLink className="size-4" />
                                    {t('Alles openen')}
                                </Button>
                            )}
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
