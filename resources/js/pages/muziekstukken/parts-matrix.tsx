import { Input } from '@/components/ui/input';
import { useGroupedTypes } from '@/hooks/use-grouped-types';
import { useTranslation } from '@/hooks/use-translation';
import type { InstrumentType, Part } from '@/types/muziekstukken';

type Props = {
    instrumentTypes: InstrumentType[];
    parts: Part[];
    matrixEdits: Record<string, number>;
    onMatrixChange: (key: string, value: number) => void;
};

export default function PartsMatrix({
    instrumentTypes,
    parts,
    matrixEdits,
    onMatrixChange,
}: Props) {
    const { t } = useTranslation();
    const { families } = useGroupedTypes(instrumentTypes, parts);

    return (
        <div className="space-y-6">
            {/* Conductor part */}
            <div className="space-y-2">
                <h3 className="text-sm font-medium text-muted-foreground">
                    {t('Conductor part')}
                </h3>
                <div className="flex items-center gap-2">
                    <span className="text-sm">{t('Partituur')}</span>
                    <Input
                        type="number"
                        min="0"
                        className="w-[80px]"
                        value={matrixEdits['conductor'] ?? 0}
                        onChange={(e) =>
                            onMatrixChange(
                                'conductor',
                                Math.max(0, parseInt(e.target.value) || 0),
                            )
                        }
                    />
                </div>
            </div>

            {/* Instrument families grid */}
            <div className="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                {families.map(({ familyName, types }) => (
                    <div key={familyName} className="space-y-2">
                        <h3 className="text-sm font-semibold">{familyName}</h3>
                        <ul className="space-y-1">
                            {types.flatMap(({ type }) => {
                                // Calculate visible voices: always show voice 1,
                                // show voice N+1 when voice N has amount > 0
                                const voices: number[] = [1];
                                let v = 1;
                                while ((matrixEdits[`type_${type.id}_v${v}`] ?? 0) > 0) {
                                    v++;
                                    voices.push(v);
                                }

                                return voices.map((voice) => {
                                    const key = `type_${type.id}_v${voice}`;
                                    return (
                                        <li
                                            key={key}
                                            className="flex items-center justify-between text-sm"
                                        >
                                            <span>
                                                {voices.length > 1
                                                    ? `${type.name} ${voice}`
                                                    : type.name}
                                            </span>
                                            <Input
                                                type="number"
                                                min="0"
                                                className="w-[70px]"
                                                value={matrixEdits[key] ?? 0}
                                                onChange={(e) =>
                                                    onMatrixChange(
                                                        key,
                                                        Math.max(
                                                            0,
                                                            parseInt(e.target.value) || 0,
                                                        ),
                                                    )
                                                }
                                            />
                                        </li>
                                    );
                                });
                            })}
                        </ul>
                    </div>
                ))}
            </div>
        </div>
    );
}
