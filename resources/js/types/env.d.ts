/// <reference types="vite/client" />

import type { SchoolSettingItem } from './models';

export interface AuthPermissions {
    manage_users: boolean;
    manage_master: boolean;
    manage_cards: boolean;
    manage_devices: boolean;
    operate_scan: boolean;
    view_classrooms: boolean;
    view_students: boolean;
    view_academic_years: boolean;
    view_holidays: boolean;
    manage_rules: boolean;
    view_attendances: boolean;
    excuse_attendance: boolean;
    correct_attendance: boolean;
    view_audit_logs: boolean;
    view_reports: boolean;
    manage_settings: boolean;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    nip: string | null;
    phone: string | null;
    is_active: boolean;
    roles: string[];
    role_labels: string[];
    permissions: string[];
    can: AuthPermissions;
}

export interface SharedUiStrings {
    app_name: string;
    draft_badge: string;
    nav: Record<string, string>;
    actions: Record<string, string>;
    labels: Record<string, string>;
    states: Record<string, string>;
    roles: Record<string, string>;
    genders: Record<string, string>;
    card_statuses: Record<string, string>;
    attendance_statuses: Record<string, string>;
    attendance_sources: Record<string, string>;
    scan_results: Record<string, string>;
    days: Record<string, string>;
    audit_actions: Record<string, string>;
    messages: Record<string, string>;
    flash: Record<string, string>;
}

declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: AuthUser;
        };
        flash: {
            success?: string | null;
            error?: string | null;
            plain_device_token?: string | null;
            plain_device_name?: string | null;
            last_assigned_card_id?: number | null;
        };
        ui: SharedUiStrings;
        schoolSetting?: SchoolSettingItem;
    }
}

interface ZiggyRouteFn {
    (): {
        current(name?: string, params?: Record<string, unknown>): boolean;
    };
    (
        name: string,
        params?: Record<string, unknown> | number | string | (number | string)[],
        absolute?: boolean,
    ): string;
}

declare global {
    const route: ZiggyRouteFn;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: ZiggyRouteFn;
    }
}

export {};
