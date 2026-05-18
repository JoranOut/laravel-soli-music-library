import { Head, Link } from '@inertiajs/react';
import { Heading } from '@/components/heading';
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
import type { PaginatedData } from '@/types/muziekstukken';

type DownloadLogEntry = {
    id: number;
    user_name: string | null;
    piece_title: string | null;
    instrument: string | null;
    voice: number | null;
    filename: string | null;
    downloaded_at: string;
    country: string | null;
};

type Props = {
    logs: PaginatedData<DownloadLogEntry>;
};

export default function DownloadLogs({ logs }: Props) {
    const { t } = useTranslation();

    function formatPart(entry: DownloadLogEntry): string {
        if (!entry.instrument) return t('Deleted part');
        if (entry.voice) return `${entry.instrument} ${entry.voice}`;
        return entry.instrument;
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('Admin'), href: '/admin/roles' },
                { title: t('Download logs'), href: '/admin/download-logs' },
            ]}
        >
            <Head title={t('Download logs')} />
            <div className="space-y-6 p-6">
                <Heading
                    title={t('Download logs')}
                    description={t('View download activity for all users.')}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('User')}</TableHead>
                                <TableHead>{t('Piece')}</TableHead>
                                <TableHead>{t('Parts')}</TableHead>
                                <TableHead>{t('Downloaded at')}</TableHead>
                                <TableHead>{t('Country')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center text-muted-foreground"
                                    >
                                        {t('No download logs found.')}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                logs.data.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell>
                                            {entry.user_name ?? (
                                                <span className="text-muted-foreground italic">
                                                    {t('Deleted user')}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {entry.piece_title ?? (
                                                <span className="text-muted-foreground italic">
                                                    {t('Deleted part')}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatPart(entry)}
                                        </TableCell>
                                        <TableCell>
                                            {new Date(
                                                entry.downloaded_at,
                                            ).toLocaleString()}
                                        </TableCell>
                                        <TableCell>
                                            {entry.country ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {logs.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2">
                        {logs.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                asChild={!!link.url}
                            >
                                {link.url ? (
                                    <Link
                                        href={link.url}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
