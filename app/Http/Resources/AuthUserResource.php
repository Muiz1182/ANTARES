<?php

namespace App\Http\Resources;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceRule;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\NfcCard;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $roleNames = $user->getRoleNames()->values()->all();

        $roleLabels = array_map(static function (string $roleName): string {
            $enum = RoleEnum::tryFrom($roleName);

            return $enum ? $enum->label() : $roleName;
        }, $roleNames);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nip' => $user->nip,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'roles' => $roleNames,
            'role_labels' => $roleLabels,
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'can' => [
                'manage_users' => $user->can('viewAny', User::class),
                'manage_master' => $user->can('create', Student::class),
                'manage_cards' => $user->can('viewAny', NfcCard::class),
                'manage_devices' => $user->can('viewAny', Device::class),
                'operate_scan' => $user->can(PermissionEnum::ScanOperate->value),
                'view_classrooms' => $user->can('viewAny', Classroom::class),
                'view_students' => $user->can('viewAny', Student::class),
                'view_academic_years' => $user->can('viewAny', AcademicYear::class),
                'view_holidays' => $user->can('viewAny', Holiday::class),
                'manage_rules' => $user->can('viewAny', AttendanceRule::class),
                'view_attendances' => $user->can('viewAny', Attendance::class),
                'excuse_attendance' => $user->can('excuse', Attendance::class),
                'correct_attendance' => $user->can('correct', Attendance::class),
                'view_audit_logs' => $user->can('viewAny', AuditLog::class),
                'view_reports' => $user->can(PermissionEnum::ReportsView->value)
                    || $user->can(PermissionEnum::AttendanceViewOwnClass->value),
                'manage_settings' => $user->can('viewAny', SchoolSetting::class),
            ],
        ];
    }
}
