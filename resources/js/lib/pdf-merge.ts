import { PDFDocument } from 'pdf-lib';

export type PrintPart = {
    id: number;
    label: string;
    downloadUrl: string;
    quantity: number;
};

export type MergeProgress = {
    phase: 'downloading' | 'merging';
    current: number;
    total: number;
    label: string;
};

export type MergeResult = {
    quantity: number;
    blobUrl: string;
    partLabels: string[];
};

export type MergeOptions = {
    doubleSided?: boolean;
};

export async function mergePartsForPrint(
    parts: PrintPart[],
    onProgress: (progress: MergeProgress) => void,
    signal?: AbortSignal,
    options?: MergeOptions,
): Promise<MergeResult[]> {
    const activeParts = parts.filter((p) => p.quantity > 0);
    if (activeParts.length === 0) return [];

    // Group parts by quantity
    const groups = new Map<number, PrintPart[]>();
    for (const part of activeParts) {
        if (!groups.has(part.quantity)) {
            groups.set(part.quantity, []);
        }
        groups.get(part.quantity)!.push(part);
    }

    // Fetch all unique PDFs
    const pdfCache = new Map<number, Uint8Array>();
    let downloaded = 0;
    const total = activeParts.length;

    for (const part of activeParts) {
        signal?.throwIfAborted();

        onProgress({
            phase: 'downloading',
            current: downloaded + 1,
            total,
            label: part.label,
        });

        const response = await fetch(part.downloadUrl, { signal });
        if (!response.ok) {
            throw new Error(
                `Download mislukt voor "${part.label}" (${response.status})`,
            );
        }

        pdfCache.set(part.id, new Uint8Array(await response.arrayBuffer()));
        downloaded++;
    }

    // Merge each quantity group into one PDF
    const results: MergeResult[] = [];
    const sortedQuantities = Array.from(groups.keys()).sort((a, b) => b - a);
    let merged = 0;

    for (const quantity of sortedQuantities) {
        signal?.throwIfAborted();

        const groupParts = groups.get(quantity)!;
        onProgress({
            phase: 'merging',
            current: merged + 1,
            total: sortedQuantities.length,
            label: `${quantity}x`,
        });

        const mergedPdf = await PDFDocument.create();

        for (const part of groupParts) {
            const data = pdfCache.get(part.id)!;
            let sourcePdf: PDFDocument;
            try {
                sourcePdf = await PDFDocument.load(data);
            } catch {
                throw new Error(
                    `Ongeldig PDF-bestand: "${part.label}". Het bestand is mogelijk beschadigd.`,
                );
            }
            const pages = await mergedPdf.copyPages(
                sourcePdf,
                sourcePdf.getPageIndices(),
            );
            for (const page of pages) {
                mergedPdf.addPage(page);
            }

            // For double-sided printing: insert a blank page after parts with
            // an odd number of pages so the next part starts on a front side.
            if (options?.doubleSided && pages.length % 2 !== 0) {
                const lastPage = pages[pages.length - 1];
                const { width, height } = lastPage.getSize();
                mergedPdf.addPage([width, height]);
            }
        }

        const pdfBytes = await mergedPdf.save();
        const blob = new Blob([pdfBytes.buffer as ArrayBuffer], {
            type: 'application/pdf',
        });
        const blobUrl = URL.createObjectURL(blob);

        results.push({
            quantity,
            blobUrl,
            partLabels: groupParts.map((p) => p.label),
        });

        merged++;
    }

    return results;
}
