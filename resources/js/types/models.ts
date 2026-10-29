export interface PaginationLinkItem {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLinkItem[];
}

export interface AcademicYearItem {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    classrooms_count?: number;
}

export interface ClassroomItem {
    id: number;
    academic_year_id: number;
    academic_year_name?: string | null;
    name: string;
    level: number;
    homeroom_teacher_id: number | null;
    homeroom_teacher_name?: string | null;
    students_count?: number;
}

export interface StudentItem {
    id: number;
    nis: string;
    nisn: string | null;
    name: string;
    gender: 'L' | 'P';
    gender_label: string;
    classroom_id: number | null;
    classroom_name?: string | null;
    photo_url: string | null;
    parent_name: string | null;
    parent_phone: string | null;
    is_active: boolean;
}

export interface HolidayItem {
    id: number;
    date: string;
    name: string;
    description: string | null;
}

export interface UserItem {
    id: number;
    name: string;
    email: string;
    nip: string | null;
    phone: string | null;
    is_active: boolean;
    role: string | null;
    role_label: string | null;
}

export interface RoleOption {
    value: string;
    label: string;
}

export interface StudentImportValidRow {
    row: number;
    nis: string;
    nisn: string | null;
    name: string;
    gender: string;
    classroom_id: number;
    classroom_name: string;
    parent_name: string | null;
    parent_phone: string | null;
}

export interface StudentImportErrorRow {
    row: number;
    nis: string;
    name: string;
    messages: string[];
}

export interface StudentImportPreview {
    import_token: string;
    total_rows: number;
    valid_count: number;
    error_count: number;
    valid_rows: StudentImportValidRow[];
    errors: StudentImportErrorRow[];
}

