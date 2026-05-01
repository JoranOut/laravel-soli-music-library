import { useMemo } from 'react';
import type { InstrumentType, Part } from '@/types/muziekstukken';

export type FamilyGroup = {
    familyName: string;
    types: {
        type: InstrumentType;
        parts: Part[];
    }[];
};

export function useGroupedTypes(instrumentTypes: InstrumentType[], parts: Part[]) {
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

        const families: FamilyGroup[] = Array.from(familyMap.entries())
            .map(([familyName, types]) => ({ familyName, types }))
            .sort((a, b) => a.familyName.localeCompare(b.familyName));

        return { conductorParts, families };
    }, [instrumentTypes, parts]);
}
