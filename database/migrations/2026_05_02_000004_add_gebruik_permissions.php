<?php

use App\Support\PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        PermissionMatrix::sync();

        $permissions = ['view speelperiode', 'create speelperiode', 'edit speelperiode', 'delete speelperiode'];

        foreach (['admin', 'muziekbeheer'] as $roleName) {
            Role::findByName($roleName)->givePermissionTo($permissions);
        }

        Role::findByName('dirigent')->givePermissionTo('view speelperiode');
    }

    public function down(): void
    {
        $permissions = ['view speelperiode', 'create speelperiode', 'edit speelperiode', 'delete speelperiode'];

        Role::findByName('dirigent')->revokePermissionTo('view speelperiode');

        foreach (['admin', 'muziekbeheer'] as $roleName) {
            Role::findByName($roleName)->revokePermissionTo($permissions);
        }
    }
};
