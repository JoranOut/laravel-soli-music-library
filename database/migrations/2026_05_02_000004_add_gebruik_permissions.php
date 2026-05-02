<?php

use App\Support\PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        PermissionMatrix::sync();

        $permissions = ['view gebruik', 'create gebruik', 'edit gebruik', 'delete gebruik'];

        foreach (['admin', 'muziekbeheer'] as $roleName) {
            Role::findByName($roleName)->givePermissionTo($permissions);
        }

        Role::findByName('dirigent')->givePermissionTo('view gebruik');
    }

    public function down(): void
    {
        $permissions = ['view gebruik', 'create gebruik', 'edit gebruik', 'delete gebruik'];

        Role::findByName('dirigent')->revokePermissionTo('view gebruik');

        foreach (['admin', 'muziekbeheer'] as $roleName) {
            Role::findByName($roleName)->revokePermissionTo($permissions);
        }
    }
};
