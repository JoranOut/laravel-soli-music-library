import type { ReactNode } from 'react';
import { Fragment } from 'react';
import type { FamilyGroup } from '@/hooks/use-grouped-types';
import type { InstrumentType, Part } from '@/types/muziekstukken';

export type VoiceGroup = {
    type: InstrumentType;
    voice: number | null;
    hasMultipleVoices: boolean;
    parts: Part[];
};

type Props = {
    families: FamilyGroup[];
    showEmptyTypes?: boolean;
    renderRow: (group: VoiceGroup) => ReactNode;
    renderEmptyType?: (type: InstrumentType) => ReactNode;
};

export function VoiceLabel({ group }: { group: VoiceGroup }) {
    const note = group.parts[0]?.note;
    return (
        <span>
            {group.type.name}
            {group.hasMultipleVoices && group.voice !== null && (
                <span className="ml-1 text-muted-foreground">
                    {group.voice}
                </span>
            )}
            {note && (
                <span className="ml-1 text-muted-foreground">({note})</span>
            )}
        </span>
    );
}

export function FamilyPartsGrid({
    families,
    showEmptyTypes = false,
    renderRow,
    renderEmptyType,
}: Props) {
    return (
        <div className="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            {families
                .filter(
                    ({ types }) =>
                        showEmptyTypes ||
                        types.some(({ parts: tp }) => tp.length > 0),
                )
                .map(({ familyName, types }) => (
                    <div key={familyName} className="space-y-2">
                        <h3 className="text-sm font-semibold">{familyName}</h3>
                        <ul className="space-y-1">
                            {types.flatMap(({ type, parts: typeParts }) => {
                                if (typeParts.length === 0) {
                                    if (!showEmptyTypes || !renderEmptyType)
                                        return [];
                                    return [
                                        <Fragment key={type.id}>
                                            {renderEmptyType(type)}
                                        </Fragment>,
                                    ];
                                }

                                // Group by voice + note combination
                                const groupKey = (p: Part) =>
                                    `${p.voice ?? ''}\0${p.note ?? ''}`;
                                const groupMap = new Map<string, Part[]>();
                                for (const p of typeParts) {
                                    const key = groupKey(p);
                                    if (!groupMap.has(key))
                                        groupMap.set(key, []);
                                    groupMap.get(key)!.push(p);
                                }
                                const groups = Array.from(
                                    groupMap.values(),
                                ).sort(
                                    (a, b) =>
                                        (a[0].voice ?? 0) - (b[0].voice ?? 0),
                                );
                                const hasMultipleGroups =
                                    groups.length > 1 ||
                                    groups[0]?.[0]?.voice !== null ||
                                    groups[0]?.[0]?.note !== null;

                                return groups.map((groupParts) => {
                                    const { voice, note } = groupParts[0];
                                    return (
                                        <Fragment
                                            key={`${type.id}-${voice}-${note}`}
                                        >
                                            {renderRow({
                                                type,
                                                voice,
                                                hasMultipleVoices:
                                                    hasMultipleGroups,
                                                parts: groupParts,
                                            })}
                                        </Fragment>
                                    );
                                });
                            })}
                        </ul>
                    </div>
                ))}
        </div>
    );
}
