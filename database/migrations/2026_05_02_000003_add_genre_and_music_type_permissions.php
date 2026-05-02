<?php

use App\Support\PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        PermissionMatrix::sync();

        $role = Role::findByName('admin');
        $role->givePermissionTo(['manage-genres', 'manage-music-types']);
    }

    public function down(): void
    {
        $role = Role::findByName('admin');
        $role->revokePermissionTo(['manage-genres', 'manage-music-types']);
    }
};
