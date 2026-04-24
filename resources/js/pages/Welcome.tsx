import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Heading } from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type { OrchestraGroup } from '@/types/muziekstukken';

type Props = {
    orchestraGroups: OrchestraGroup[];
};

async function handleDownload(partId: number) {
    const response = await fetch(`/parts/${partId}/download-url`);
    const { url } = await response.json();
    window.location.href = url;
}

export default function Welcome({ orchestraGroups }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={[{ title: t('My Music'), href: '/' }]}>
            <Head title={t('My Music')} />
            <div className="space-y-10 p-6">
                <Heading title={t('My Music')} />

                {orchestraGroups.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No assignments yet.')}
                    </p>
                ) : (
                    orchestraGroups.map((group) => (
                        <section key={group.orchestra.id} className="space-y-6">
                            <div className="space-y-2">
                                <h3 className="text-lg font-semibold tracking-tight">
                                    {group.orchestra.name}
                                </h3>
                                <div className="flex flex-wrap gap-1">
                                    {group.instruments.map((instrument) => (
                                        <Badge key={instrument.id} variant="secondary">
                                            {instrument.name}
                                        </Badge>
                                    ))}
                                </div>
                            </div>

                            {group.pieces.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No music parts available.')}
                                </p>
                            ) : (
                                <div className="rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{t('Piece')}</TableHead>
                                                <TableHead>{t('Composer')}</TableHead>
                                                <TableHead>{t('Instrument')}</TableHead>
                                                <TableHead className="w-0" />
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {group.pieces.flatMap((piece) =>
                                                piece.parts.map((part) => (
                                                    <TableRow key={part.id}>
                                                        <TableCell>{piece.title}</TableCell>
                                                        <TableCell>
                                                            {piece.composer ?? (
                                                                <span className="text-muted-foreground">—</span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {part.instrument_type.name}
                                                            {part.voice != null && ` ${part.voice}`}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => handleDownload(part.id)}
                                                            >
                                                                <Download />
                                                                <span className="sr-only">
                                                                    {t('Download')}
                                                                </span>
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                )),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
