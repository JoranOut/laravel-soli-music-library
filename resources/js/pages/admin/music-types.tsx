import { Head, router } from '@inertiajs/react';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Heading } from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
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

type MusicType = {
    id: number;
    name: string;
    sort_order: number;
    piece_count: number;
};

type Props = {
    musicTypes: MusicType[];
};

export default function MusicTypes({ musicTypes }: Props) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState('');
    const [editSortOrder, setEditSortOrder] = useState(0);
    const [newName, setNewName] = useState('');
    const [deleteTarget, setDeleteTarget] = useState<MusicType | null>(null);
    const [replaceWithId, setReplaceWithId] = useState<string>('');

    function startEdit(musicType: MusicType) {
        setEditingId(musicType.id);
        setEditName(musicType.name);
        setEditSortOrder(musicType.sort_order);
    }

    function cancelEdit() {
        setEditingId(null);
        setEditName('');
        setEditSortOrder(0);
    }

    function saveEdit(id: number) {
        router.put(
            `/admin/music-types/${id}`,
            { name: editName, sort_order: editSortOrder },
            { preserveScroll: true, onSuccess: () => cancelEdit() },
        );
    }

    function addMusicType() {
        const trimmed = newName.trim();
        if (!trimmed) return;
        router.post(
            '/admin/music-types',
            { name: trimmed },
            { preserveScroll: true, onSuccess: () => setNewName('') },
        );
    }

    function confirmDelete() {
        if (!deleteTarget) return;
        router.delete(`/admin/music-types/${deleteTarget.id}`, {
            data: { replace_with_id: replaceWithId || null },
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setReplaceWithId('');
            },
        });
    }

    function handleAddKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addMusicType();
        }
    }

    function handleEditKeyDown(e: React.KeyboardEvent, id: number) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveEdit(id);
        }
        if (e.key === 'Escape') {
            cancelEdit();
        }
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('Admin'), href: '/admin/roles' },
                { title: t('Music types'), href: '/admin/music-types' },
            ]}
        >
            <Head title={t('Music types')} />
            <div className="space-y-6 p-6">
                <Heading
                    title={t('Music types')}
                    description={t(
                        'Manage the available music types for music pieces.',
                    )}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Name')}</TableHead>
                                <TableHead className="w-28">
                                    {t('Sort order')}
                                </TableHead>
                                <TableHead className="w-24">
                                    {t('Pieces')}
                                </TableHead>
                                <TableHead className="w-24">
                                    {t('Actions')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {musicTypes.map((musicType) => (
                                <TableRow key={musicType.id}>
                                    {editingId === musicType.id ? (
                                        <>
                                            <TableCell>
                                                <Input
                                                    value={editName}
                                                    onChange={(e) =>
                                                        setEditName(
                                                            e.target.value,
                                                        )
                                                    }
                                                    onKeyDown={(e) =>
                                                        handleEditKeyDown(
                                                            e,
                                                            musicType.id,
                                                        )
                                                    }
                                                    className="h-8"
                                                    autoFocus
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="number"
                                                    value={editSortOrder}
                                                    onChange={(e) =>
                                                        setEditSortOrder(
                                                            parseInt(
                                                                e.target.value,
                                                            ) || 0,
                                                        )
                                                    }
                                                    onKeyDown={(e) =>
                                                        handleEditKeyDown(
                                                            e,
                                                            musicType.id,
                                                        )
                                                    }
                                                    className="h-8 w-20"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {musicType.piece_count}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 w-7 p-0"
                                                        onClick={() =>
                                                            saveEdit(
                                                                musicType.id,
                                                            )
                                                        }
                                                        disabled={
                                                            !editName.trim()
                                                        }
                                                    >
                                                        <Check className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 w-7 p-0"
                                                        onClick={cancelEdit}
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </>
                                    ) : (
                                        <>
                                            <TableCell>
                                                {musicType.name}
                                            </TableCell>
                                            <TableCell>
                                                {musicType.sort_order}
                                            </TableCell>
                                            <TableCell>
                                                {musicType.piece_count}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 w-7 p-0"
                                                        onClick={() =>
                                                            startEdit(musicType)
                                                        }
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 w-7 p-0 text-destructive hover:text-destructive"
                                                        onClick={() => {
                                                            setDeleteTarget(
                                                                musicType,
                                                            );
                                                            setReplaceWithId(
                                                                '',
                                                            );
                                                        }}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </>
                                    )}
                                </TableRow>
                            ))}
                            <TableRow>
                                <TableCell>
                                    <Input
                                        value={newName}
                                        onChange={(e) =>
                                            setNewName(e.target.value)
                                        }
                                        onKeyDown={handleAddKeyDown}
                                        placeholder={t('New music type...')}
                                        className="h-8"
                                    />
                                </TableCell>
                                <TableCell />
                                <TableCell />
                                <TableCell>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        onClick={addMusicType}
                                        disabled={!newName.trim()}
                                    >
                                        <Plus className="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Dialog
                open={!!deleteTarget}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                        setReplaceWithId('');
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('Delete music type')}: {deleteTarget?.name}
                        </DialogTitle>
                        <DialogDescription>
                            {deleteTarget && deleteTarget.piece_count > 0
                                ? t(
                                      ':count pieces use this music type. Choose a replacement or remove it from all pieces.',
                                      {
                                          count: String(
                                              deleteTarget.piece_count,
                                          ),
                                      },
                                  )
                                : t(
                                      'Are you sure you want to delete this music type?',
                                  )}
                        </DialogDescription>
                    </DialogHeader>

                    {deleteTarget && deleteTarget.piece_count > 0 && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                {t('Reassign to')}
                            </label>
                            <Select
                                value={replaceWithId}
                                onChange={(e) =>
                                    setReplaceWithId(e.target.value)
                                }
                            >
                                <option value="">
                                    {t('Remove from all pieces')}
                                </option>
                                {musicTypes
                                    .filter((mt) => mt.id !== deleteTarget.id)
                                    .map((mt) => (
                                        <option key={mt.id} value={mt.id}>
                                            {mt.name}
                                        </option>
                                    ))}
                            </Select>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setDeleteTarget(null);
                                setReplaceWithId('');
                            }}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            {t('Delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
