<?php

namespace App\Enums;

enum PermissionEnum: string
{
    case UsersManage = 'users.manage';
    case MasterManage = 'master.manage';
    case CardsManage = 'cards.manage';
    case RulesManage = 'rules.manage';
    case DevicesManage = 'devices.manage';
    case ScanOperate = 'scan.operate';
    case AttendanceViewAll = 'attendance.view_all';
    case AttendanceViewOwnClass = 'attendance.view_own_class';
    case AttendanceExcuse = 'attendance.excuse';
    case AttendanceCorrect = 'attendance.correct';
    case ReportsView = 'reports.view';
    case AuditView = 'audit.view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
