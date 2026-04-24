import { Head, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { Fragment, useState } from 'react';
import { Heading } from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type { InstrumentFamily, InstrumentType } from '@/types/muziekstukken';

type Props = {
    instrumentTypes: (InstrumentType & { instrument_family: InstrumentFamily })[];
};

function AliasRow({ type }: { type: InstrumentType }) {
    const { t } = useTranslation();
    const [newAlias, setNewAlias] = useState('');
    const aliases = type.aliases ?? [];

    function saveAliases(updated: string[]) {
        router.put(
            `/admin/instrument-aliases/${type.id}`,
            { aliases: updated },
            { preserveScroll: true },
        );
    }

    function addAlias() {
        const trimmed = newAlias.trim().toLowerCase();
        if (!trimmed || aliases.includes(trimmed)) {
            setNewAlias('');
            return;
        }
        saveAliases([...aliases, trimmed]);
        setNewAlias('');
    }

    function removeAlias(alias: string) {
        saveAliases(aliases.filter((a) => a !== alias));
    }

    function handleKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addAlias();
        }
    }

    return (
        <tr className="border-b last:border-0">
            <td className="px-4 py-2.5 pl-8 align-top">
                <Badge variant="outline">{type.name}</Badge>
            </td>
            <td className="px-4 py-2.5">
                <div className="flex flex-wrap items-center gap-1.5">
                    {aliases.map((alias) => (
                        <Badge key={alias} variant="secondary" className="gap-1">
                            {alias}
                            <button
                                type="button"
                                onClick={() => removeAlias(alias)}
                                className="ml-0.5 rounded-full hover:bg-muted-foreground/20"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </Badge>
                    ))}
                    <div className="flex items-center gap-1">
                        <Input
                            value={newAlias}
                            onChange={(e) => setNewAlias(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder={t('Add alias...')}
                            className="h-7 w-36 text-xs"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 text-xs"
                            onClick={addAlias}
                            disabled={!newAlias.trim()}
                        >
                            {t('Add')}
                        </Button>
                    </div>
                </div>
            </td>
        </tr>
    );
}

export default function InstrumentAliases({ instrumentTypes }: Props) {
    const { t } = useTranslation();

    const grouped = instrumentTypes.reduce<
        Record<string, (InstrumentType & { instrument_family: InstrumentFamily })[]>
    >((acc, type) => {
        const family = type.instrument_family?.name ?? 'Other';
        if (!acc[family]) acc[family] = [];
        acc[family].push(type);
        return acc;
    }, {});

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('Admin'), href: '/admin/roles' },
                {
                    title: t('Instrument aliases'),
                    href: '/admin/instrument-aliases',
                },
            ]}
        >
            <Head title={t('Instrument aliases')} />
            <div className="space-y-6 p-6">
                <Heading
                    title={t('Instrument aliases')}
                    description={t(
                        'Manage filename detection aliases per instrument type',
                    )}
                />

                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
                    <p className="font-medium">{t('How matching works')}</p>
                    <ul className="mt-1.5 list-inside list-disc space-y-0.5 text-blue-800 dark:text-blue-300">
                        <li>{t('When a PDF is uploaded, the filename is matched against these aliases to auto-detect the instrument.')}</li>
                        <li>{t('The instrument name itself is always matched automatically (shown as outline badge) — no need to add it as an alias.')}</li>
                        <li>{t('Longer aliases take priority over shorter ones, so "bass trombone" matches before "trombone".')}</li>
                        <li>{t('Matching is case-insensitive and ignores underscores, dashes and other separators.')}</li>
                    </ul>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('Instrument')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('Aliases')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(grouped).map(([family, types]) => (
                                <Fragment key={family}>
                                    <tr className="border-b bg-muted/25">
                                        <td
                                            colSpan={2}
                                            className="px-4 py-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >
                                            {family}
                                        </td>
                                    </tr>
                                    {types.map((type) => (
                                        <AliasRow key={type.id} type={type} />
                                    ))}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
