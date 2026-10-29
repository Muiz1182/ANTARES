<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionEnum::values() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $rolePermissions = [
            RoleEnum::SuperAdmin->value => PermissionEnum::values(),
            RoleEnum::AdminTu->value => [
                PermissionEnum::MasterManage->value,
                PermissionEnum::CardsManage->value,
                PermissionEnum::RulesManage->value,
                PermissionEnum::AttendanceViewAll->value,
                PermissionEnum::AttendanceExcuse->value,
                PermissionEnum::AttendanceCorrect->value,
                PermissionEnum::ReportsView->value,
                PermissionEnum::AuditView->value,
            ],
            RoleEnum::Piket->value => [
                PermissionEnum::ScanOperate->value,
                PermissionEnum::AttendanceViewAll->value,
                PermissionEnum::AttendanceExcuse->value,
            ],
            RoleEnum::WaliKelas->value => [
                PermissionEnum::AttendanceViewOwnClass->value,
                PermissionEnum::AttendanceExcuse->value,
            ],
            RoleEnum::KepalaSekolah->value => [
                PermissionEnum::AttendanceViewAll->value,
                PermissionEnum::ReportsView->value,
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
