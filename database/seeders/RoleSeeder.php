<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // Gunakan firstOrCreate agar tidak error jika role sudah ada
        $role = Role::firstOrCreate(['name' => 'Super Admin']);

        $permissions = Permission::whereIn('name', [
            'index-order', 'create-order', 'index-transaction', 'create-transaction'
            // Catatan: 'index-rent' & 'create-rent' tidak ada di PermissionSeeder, jadi saya hapus agar aman
        ])->get();

        // Gunakan syncPermissions atau givePermissionTo
        $role->syncPermissions($permissions);
    }
}