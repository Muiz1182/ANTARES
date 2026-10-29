/// <reference types="vite/client" />

export interface AuthPermissions {
    manage_users: boolean;
    manage_master: boolean;
    view_classrooms: boolean;
    view_students: boolean;
    view_academic_years: boolean;
    view_holidays: boolean;
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
    messages: Record<string, string>;
}

declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: AuthUser;
        };
        flash: {
            success?: string | null;
            error?: string | null;
        };
        ui: SharedUiStrings;
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
