import type { InstrumentType } from '@/types/muziekstukken';

export type GuessResult = {
    instrument_type_id?: string;
    voice?: string;
    is_conductor?: boolean;
};

const CONDUCTOR_PATTERNS = [
    'full score',
    'partituur',
    'directie',
    'conductor',
    'conducteur',
    'score',
];

/**
 * Build alias tuples dynamically from InstrumentType[].
 * Each type's name (lowercased) is always an implicit alias.
 * Additional aliases come from the `aliases` field in the database.
 * Sorted longest-first so the most specific alias always wins.
 */
function buildAliases(types: InstrumentType[]): [string, string][] {
    const aliases: [string, string][] = [];
    for (const t of types) {
        const id = t.id.toString();
        aliases.push([t.name.toLowerCase(), id]);
        for (const alias of t.aliases ?? []) {
            aliases.push([alias, id]);
        }
    }
    return aliases.sort((a, b) => b[0].length - a[0].length);
}

/** Characters that count as word separators for boundary checks. */
const SEPARATOR = /[\s\-_.,/\\()]/;

function isSeparatorOrStart(str: string, index: number): boolean {
    if (index < 0) return true;
    return SEPARATOR.test(str[index]);
}

function isSeparatorOrEnd(str: string, index: number): boolean {
    if (index >= str.length) return true;
    // Allow a digit right after the alias (e.g. "trp4")
    if (/\d/.test(str[index])) return true;
    return SEPARATOR.test(str[index]);
}

/**
 * Normalise a filename for matching:
 * - Strip extension
 * - Lowercase
 * - Replace underscores with spaces
 * - Remove carets (^)
 */
function normalise(filename: string): string {
    return filename
        .replace(/\.[^.]+$/, '') // strip extension
        .toLowerCase()
        .replace(/_/g, ' ') // underscores → spaces
        .replace(/\^/g, ''); // remove carets (e.g. B^b → Bb)
}

/**
 * Extract a voice number near the matched alias position.
 * Looks for a single digit (1-9) immediately after the alias,
 * after one space following the alias, or before the alias with a space.
 */
function extractVoice(
    cleaned: string,
    matchStart: number,
    matchEnd: number,
): string {
    // Digit immediately after alias: "trp4", "altsax1"
    if (matchEnd < cleaned.length && /[1-9]/.test(cleaned[matchEnd])) {
        // Make sure it's a single digit (not part of a larger number)
        if (
            matchEnd + 1 >= cleaned.length ||
            !/\d/.test(cleaned[matchEnd + 1])
        ) {
            return cleaned[matchEnd];
        }
    }

    // Digit after a space: "Trompet 1", "Alt Sax 2"
    if (
        matchEnd < cleaned.length &&
        SEPARATOR.test(cleaned[matchEnd]) &&
        matchEnd + 1 < cleaned.length &&
        /[1-9]/.test(cleaned[matchEnd + 1])
    ) {
        if (
            matchEnd + 2 >= cleaned.length ||
            !/\d/.test(cleaned[matchEnd + 2])
        ) {
            return cleaned[matchEnd + 1];
        }
    }

    // Digit before the alias with a space: "1 Trompet"
    if (
        matchStart >= 2 &&
        SEPARATOR.test(cleaned[matchStart - 1]) &&
        /[1-9]/.test(cleaned[matchStart - 2])
    ) {
        if (matchStart - 3 < 0 || !/\d/.test(cleaned[matchStart - 3])) {
            return cleaned[matchStart - 2];
        }
    }

    return '';
}

/**
 * Guess the instrument type and voice number from a PDF filename.
 *
 * @param filename  The original filename (e.g. "Bohemian Rhapsody - Trompet 1 in Bb.pdf")
 * @param types     The InstrumentType[] available in the system
 * @returns         A partial guess with instrument_type_id, voice, and/or is_conductor
 */
export function guessFromFilename(
    filename: string,
    types: InstrumentType[],
): GuessResult {
    let cleaned = normalise(filename);

    // If there's a " - " separator, use the part after the last one
    // (isolates instrument info from the piece title)
    const dashIndex = cleaned.lastIndexOf(' - ');
    if (dashIndex !== -1) {
        cleaned = cleaned.substring(dashIndex + 3);
    }

    // Check conductor patterns first
    for (const pattern of CONDUCTOR_PATTERNS) {
        const idx = cleaned.indexOf(pattern);
        if (
            idx !== -1 &&
            isSeparatorOrStart(cleaned, idx - 1) &&
            isSeparatorOrEnd(cleaned, idx + pattern.length)
        ) {
            return { is_conductor: true };
        }
    }

    // Build alias list dynamically from instrument types (name + DB aliases)
    const aliases = buildAliases(types);

    for (const [alias, instrumentId] of aliases) {
        const idx = cleaned.indexOf(alias);
        if (idx === -1) continue;

        // Check word boundaries
        if (!isSeparatorOrStart(cleaned, idx - 1)) continue;
        if (!isSeparatorOrEnd(cleaned, idx + alias.length)) continue;

        const voice = extractVoice(cleaned, idx, idx + alias.length);

        return {
            instrument_type_id: instrumentId,
            voice: voice || undefined,
            is_conductor: false,
        };
    }

    return {};
}
