<?php

namespace Database\Seeders;

use App\Support\PermissionMatrix;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        PermissionMatrix::seedDefaults();
    }
}
