import { Checkbox } from '@/components/ui/checkbox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type PartValues = {
    instrument_type_id: string;
    is_conductor: boolean;
    voice: string;
    amount_bought: string;
    note: string;
};

type Props = {
    values: PartValues;
    instrumentOptions: ComboboxOption[];
    onChange: (field: keyof PartValues, value: string | boolean) => void;
    instrumentRequired?: boolean;
    checkboxId?: string;
};

export default function PartEditFields({
    values,
    instrumentOptions,
    onChange,
    instrumentRequired = false,
    checkboxId = 'is_conductor',
}: Props) {
    const { t } = useTranslation();

    return (
        <div className="space-y-4">
            <div className="space-y-1">
                <Label>
                    {t('Instrument')}
                    {instrumentRequired && (
                        <>
                            {' '}
                            <span className="text-destructive">*</span>
                        </>
                    )}
                </Label>
                <Combobox
                    className="bg-background"
                    options={instrumentOptions}
                    value={values.instrument_type_id}
                    onChange={(v) => onChange('instrument_type_id', v)}
                />
            </div>

            <div className="space-y-1">
                <Label>{t('Voice')}</Label>
                <Input
                    type="number"
                    min="1"
                    className="bg-background"
                    value={values.voice}
                    onChange={(e) => onChange('voice', e.target.value)}
                    placeholder="-"
                />
            </div>

            <div className="space-y-1">
                <Label>{t('Note')}</Label>
                <Input
                    className="bg-background"
                    maxLength={20}
                    value={values.note}
                    onChange={(e) => onChange('note', e.target.value)}
                    placeholder="-"
                />
            </div>

            <div className="space-y-1">
                <Label>{t('Amount bought')}</Label>
                <Input
                    type="number"
                    min="0"
                    className="bg-background"
                    value={values.amount_bought}
                    onChange={(e) => onChange('amount_bought', e.target.value)}
                    placeholder="-"
                />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id={checkboxId}
                    checked={values.is_conductor}
                    onCheckedChange={(checked) =>
                        onChange('is_conductor', !!checked)
                    }
                />
                <Label htmlFor={checkboxId}>{t('Partituur')}</Label>
            </div>
        </div>
    );
}
