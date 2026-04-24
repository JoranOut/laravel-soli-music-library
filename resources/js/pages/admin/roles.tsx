import { Head, router } from '@inertiajs/react';
import { Heading } from '@/components/heading';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';

type Role = {
    id: number;
    name: string;
    permissions: string[];
};

type Props = {
    roles: Role[];
    permissions: string[];
};

type GroupedPermission = { name: string; label: string };

function groupPermissions(permissions: string[]): Record<string, GroupedPermission[]> {
    const groups: Record<string, GroupedPermission[]> = {};
    for (const perm of permissions) {
        const spaceIndex = perm.indexOf(' ');
        const group = spaceIndex >= 0 ? perm.slice(spaceIndex + 1) : 'admin';
        const label = spaceIndex >= 0 ? perm.slice(0, spaceIndex) : perm;
        if (!groups[group]) groups[group] = [];
        groups[group].push({ name: perm, label });
    }
    return groups;
}

export default function Roles({ roles, permissions }: Props) {
    const { t } = useTranslation();
    const grouped = groupPermissions(permissions);

    function togglePermission(role: Role, permission: string) {
        const current = role.permissions;
        const updated = current.includes(permission)
            ? current.filter((p) => p !== permission)
            : [...current, permission];

        router.put(
            `/admin/roles/${role.id}`,
            { permissions: updated },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('Admin'), href: '/admin/roles' },
                {
                    title: t('Roles & permissions'),
                    href: '/admin/roles',
                },
            ]}
        >
            <Head title={t('Roles & permissions')} />
            <div className="space-y-6 p-6">
                <Heading
                    title={t('Permission matrix')}
                    description={t('Toggle permissions per role')}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('Permission')}
                                </th>
                                {roles.map((role) => (
                                    <th
                                        key={role.id}
                                        className="px-4 py-3 text-center font-medium capitalize"
                                    >
                                        {role.name}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(grouped).map(
                                ([group, perms]) => (
                                    <>
                                        <tr
                                            key={`group-${group}`}
                                            className="border-b bg-muted/25"
                                        >
                                            <td
                                                colSpan={roles.length + 1}
                                                className="px-4 py-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                            >
                                                {group}
                                            </td>
                                        </tr>
                                        {perms.map((perm) => (
                                                <tr
                                                    key={perm.name}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="px-4 py-2.5 pl-8 text-muted-foreground">
                                                        {perm.label}
                                                    </td>
                                                    {roles.map((role) => (
                                                        <td
                                                            key={`${role.id}-${perm.name}`}
                                                            className="px-4 py-2.5 text-center"
                                                        >
                                                            <Checkbox
                                                                checked={role.permissions.includes(
                                                                    perm.name,
                                                                )}
                                                                onCheckedChange={() =>
                                                                    togglePermission(
                                                                        role,
                                                                        perm.name,
                                                                    )
                                                                }
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>
                                        ))}
                                    </>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
