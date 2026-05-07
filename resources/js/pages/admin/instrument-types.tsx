import { Head, router } from '@inertiajs/react';
import { Check, Pencil, Plus, Settings, Trash2, X } from 'lucide-react';
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
import { Combobox } from '@/components/ui/combobox';
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

type InstrumentFamily = {
    id: number;
    name: string;
};

type InstrumentType = {
    id: number;
    name: string;
    instrument_family_id: number;
    sort_order: number;
    parts_count: number;
    instrument_family?: InstrumentFamily;
};

type Props = {
    instrumentTypes: InstrumentType[];
    families: InstrumentFamily[];
};

export default function InstrumentTypes({ instrumentTypes, families }: Props) {
    const { t } = useTranslation();

    // Inline editing state
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState('');
    const [editFamilyId, setEditFamilyId] = useState('');
    const [editSortOrder, setEditSortOrder] = useState(0);

    // Add new state
    const [newName, setNewName] = useState('');
    const [newFamilyId, setNewFamilyId] = useState('');

    // Delete dialog state
    const [deleteTarget, setDeleteTarget] = useState<InstrumentType | null>(
        null,
    );
    const [replaceWithId, setReplaceWithId] = useState<string>('');

    // Manage families dialog state
    const [familiesOpen, setFamiliesOpen] = useState(false);
    const [editingFamilyId, setEditingFamilyId] = useState<number | null>(null);
    const [editFamilyName, setEditFamilyName] = useState('');
    const [newFamilyName, setNewFamilyName] = useState('');

    // Inline editing handlers
    function startEdit(type: InstrumentType) {
        setEditingId(type.id);
        setEditName(type.name);
        setEditFamilyId(String(type.instrument_family_id));
        setEditSortOrder(type.sort_order);
    }

    function cancelEdit() {
        setEditingId(null);
        setEditName('');
        setEditFamilyId('');
        setEditSortOrder(0);
    }

    function saveEdit(id: number) {
        router.put(
            `/admin/instrument-types/${id}`,
            {
                name: editName,
                instrument_family_id: editFamilyId,
                sort_order: editSortOrder,
            },
            { preserveScroll: true, onSuccess: () => cancelEdit() },
        );
    }

    function addType() {
        const trimmed = newName.trim();
        if (!trimmed || !newFamilyId) return;
        router.post(
            '/admin/instrument-types',
            { name: trimmed, instrument_family_id: newFamilyId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewName('');
                    setNewFamilyId('');
                },
            },
        );
    }

    function confirmDelete() {
        if (!deleteTarget) return;
        router.delete(`/admin/instrument-types/${deleteTarget.id}`, {
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
            addType();
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

    // Family management handlers
    function startEditFamily(family: InstrumentFamily) {
        setEditingFamilyId(family.id);
        setEditFamilyName(family.name);
    }

    function cancelEditFamily() {
        setEditingFamilyId(null);
        setEditFamilyName('');
    }

    function saveEditFamily(id: number) {
        router.put(
            `/admin/instrument-types/families/${id}`,
            { name: editFamilyName },
            { preserveScroll: true, onSuccess: () => cancelEditFamily() },
        );
    }

    function addFamily() {
        const trimmed = newFamilyName.trim();
        if (!trimmed) return;
        router.post(
            '/admin/instrument-types/families',
            { name: trimmed },
            { preserveScroll: true, onSuccess: () => setNewFamilyName('') },
        );
    }

    function deleteFamily(id: number) {
        router.delete(`/admin/instrument-types/families/${id}`, {
            preserveScroll: true,
        });
    }

    function familyHasTypes(familyId: number) {
        return instrumentTypes.some((t) => t.instrument_family_id === familyId);
    }

    function handleAddFamilyKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addFamily();
        }
    }

    function handleEditFamilyKeyDown(e: React.KeyboardEvent, id: number) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveEditFamily(id);
        }
        if (e.key === 'Escape') {
            cancelEditFamily();
        }
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('Admin'), href: '/admin/roles' },
                {
                    title: t('Instrument types'),
                    href: '/admin/instrument-types',
                },
            ]}
        >
            <Head title={t('Instrument types')} />
            <div className="space-y-6 p-6">
                <div className="flex items-start justify-between">
                    <Heading
                        title={t('Instrument types')}
                        description={t(
                            'Manage the available instrument types for music parts.',
                        )}
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setFamiliesOpen(true)}
                    >
                        <Settings className="mr-2 h-4 w-4" />
                        {t('Manage families')}
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Name')}</TableHead>
                                <TableHead className="w-48">
                                    {t('Family')}
                                </TableHead>
                                <TableHead className="w-28">
                                    {t('Sort order')}
                                </TableHead>
                                <TableHead className="w-24">
                                    {t('Parts')}
                                </TableHead>
                                <TableHead className="w-24">
                                    {t('Actions')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {instrumentTypes.map((type) => (
                                <TableRow key={type.id}>
                                    {editingId === type.id ? (
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
                                                            type.id,
                                                        )
                                                    }
                                                    className="h-8"
                                                    autoFocus
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Select
                                                    value={editFamilyId}
                                                    onChange={(e) =>
                                                        setEditFamilyId(
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-8"
                                                >
                                                    <option value="">
                                                        {t('Select...')}
                                                    </option>
                                                    {families.map((f) => (
                                                        <option
                                                            key={f.id}
                                                            value={f.id}
                                                        >
                                                            {f.name}
                                                        </option>
                                                    ))}
                                                </Select>
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
                                                            type.id,
                                                        )
                                                    }
                                                    className="h-8 w-20"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {type.parts_count}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 w-7 p-0"
                                                        onClick={() =>
                                                            saveEdit(type.id)
                                                        }
                                                        disabled={
                                                            !editName.trim() ||
                                                            !editFamilyId
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
                                            <TableCell>{type.name}</TableCell>
                                            <TableCell>
                                                {type.instrument_family?.name}
                                            </TableCell>
                                            <TableCell>
                                                {type.sort_order}
                                            </TableCell>
                                            <TableCell>
                                                {type.parts_count}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 w-7 p-0"
                                                        onClick={() =>
                                                            startEdit(type)
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
                                                                type,
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
                                        placeholder={t(
                                            'New instrument type...',
                                        )}
                                        className="h-8"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Select
                                        value={newFamilyId}
                                        onChange={(e) =>
                                            setNewFamilyId(e.target.value)
                                        }
                                        className="h-8"
                                    >
                                        <option value="">
                                            {t('Select...')}
                                        </option>
                                        {families.map((f) => (
                                            <option key={f.id} value={f.id}>
                                                {f.name}
                                            </option>
                                        ))}
                                    </Select>
                                </TableCell>
                                <TableCell />
                                <TableCell />
                                <TableCell>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        onClick={addType}
                                        disabled={
                                            !newName.trim() || !newFamilyId
                                        }
                                    >
                                        <Plus className="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* Delete instrument type dialog */}
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
                            {t('Delete instrument type')}: {deleteTarget?.name}
                        </DialogTitle>
                        <DialogDescription>
                            {deleteTarget && deleteTarget.parts_count > 0
                                ? t(
                                      ':count parts use this instrument type. Choose a replacement or remove it from all parts.',
                                      {
                                          count: String(
                                              deleteTarget.parts_count,
                                          ),
                                      },
                                  )
                                : t(
                                      'Are you sure you want to delete this instrument type?',
                                  )}
                        </DialogDescription>
                    </DialogHeader>

                    {deleteTarget && deleteTarget.parts_count > 0 && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                {t('Reassign to')}
                            </label>
                            <Combobox
                                value={replaceWithId}
                                onChange={setReplaceWithId}
                                placeholder={t('Search instrument type...')}
                                options={instrumentTypes
                                    .filter((it) => it.id !== deleteTarget.id)
                                    .map((it) => ({
                                        value: String(it.id),
                                        label: it.name,
                                        group: it.instrument_family?.name,
                                    }))}
                            />
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

            {/* Manage families dialog */}
            <Dialog open={familiesOpen} onOpenChange={setFamiliesOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Manage families')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Manage the instrument families that group instrument types.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="max-h-64 space-y-2 overflow-y-auto">
                            {families.map((family) => (
                                <div
                                    key={family.id}
                                    className="flex items-center justify-between rounded border p-2"
                                >
                                    {editingFamilyId === family.id ? (
                                        <div className="flex flex-1 items-center gap-2">
                                            <Input
                                                value={editFamilyName}
                                                onChange={(e) =>
                                                    setEditFamilyName(
                                                        e.target.value,
                                                    )
                                                }
                                                onKeyDown={(e) =>
                                                    handleEditFamilyKeyDown(
                                                        e,
                                                        family.id,
                                                    )
                                                }
                                                className="h-8"
                                                autoFocus
                                            />
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0"
                                                onClick={() =>
                                                    saveEditFamily(family.id)
                                                }
                                                disabled={
                                                    !editFamilyName.trim()
                                                }
                                            >
                                                <Check className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0"
                                                onClick={cancelEditFamily}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ) : (
                                        <>
                                            <span className="text-sm">
                                                {family.name}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 w-7 p-0"
                                                    onClick={() =>
                                                        startEditFamily(family)
                                                    }
                                                >
                                                    <Pencil className="h-3 w-3" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 w-7 p-0 text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        deleteFamily(family.id)
                                                    }
                                                    disabled={familyHasTypes(
                                                        family.id,
                                                    )}
                                                >
                                                    <Trash2 className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </div>
                            ))}
                        </div>
                        <div className="flex items-center gap-2">
                            <Input
                                value={newFamilyName}
                                onChange={(e) =>
                                    setNewFamilyName(e.target.value)
                                }
                                onKeyDown={handleAddFamilyKeyDown}
                                placeholder={t('New family...')}
                                className="h-8"
                            />
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 w-7 p-0"
                                onClick={addFamily}
                                disabled={!newFamilyName.trim()}
                            >
                                <Plus className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
