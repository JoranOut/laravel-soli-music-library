<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionMatrix
{
    /** All permissions managed by this application. */
    public const PERMISSIONS = [
        'view muziekstukken',
        'create muziekstukken',
        'edit muziekstukken',
        'delete muziekstukken',
        'view partijen',
        'create partijen',
        'edit partijen',
        'delete partijen',
        'download-assigned partijen',
        'download-score partijen',
        'download-all partijen',
        'manage-roles',
        'manage-instrument-aliases',
        'manage-genres',
        'manage-music-types',
    ];

    /** Roles and their default permission sets. */
    public const ROLE_DEFAULTS = [
        'admin' => [
            'view muziekstukken', 'create muziekstukken', 'edit muziekstukken', 'delete muziekstukken',
            'view partijen', 'create partijen', 'edit partijen', 'delete partijen',
            'download-assigned partijen', 'download-score partijen', 'download-all partijen',
            'manage-roles',
            'manage-instrument-aliases',
            'manage-genres',
            'manage-music-types',
        ],
        'muziekbeheer' => [
            'view muziekstukken', 'create muziekstukken', 'edit muziekstukken', 'delete muziekstukken',
            'view partijen', 'create partijen', 'edit partijen', 'delete partijen',
            'download-assigned partijen', 'download-score partijen', 'download-all partijen',
            'manage-instrument-aliases',
        ],
        'dirigent' => [
            'view muziekstukken',
            'view partijen',
            'download-score partijen',
        ],
        'member' => [
            'view muziekstukken',
            'view partijen',
            'download-assigned partijen',
        ],
    ];

    /**
     * Ensure all permissions and roles exist in the database.
     *
     * Creates missing permissions and roles, removes deprecated permissions,
     * and assigns default permissions only to newly created roles (preserving
     * any admin UI changes for existing roles).
     */
    public static function sync(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        Permission::whereNotIn('name', self::PERMISSIONS)->delete();

        foreach (self::ROLE_DEFAULTS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                $role = Role::create(['name' => $roleName]);
                $role->syncPermissions($permissions);
            }
        }
    }

    /**
     * Sync permissions/roles and reset all role assignments to defaults.
     *
     * Used by the seeder and in tests where a fresh permission state is needed.
     */
    public static function seedDefaults(): void
    {
        self::sync();

        foreach (self::ROLE_DEFAULTS as $roleName => $permissions) {
            Role::findByName($roleName)->syncPermissions($permissions);
        }
    }
}
