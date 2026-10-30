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

export interface NfcCardOwnerSummary {
    id: number;
    nis: string;
    name: string;
    classroom_name?: string | null;
    is_active: boolean;
}

export interface NfcCardItem {
    id: number;
    uid: string;
    status: 'active' | 'inactive' | 'lost';
    status_label: string;
    issued_at: string | null;
    issued_at_formatted: string | null;
    deactivated_at: string | null;
    deactivated_at_formatted: string | null;
    replaced_by_card_id: number | null;
    replaced_by_uid?: string | null;
    notes: string | null;
    student?: NfcCardOwnerSummary | null;
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
    active_card?: NfcCardItem | null;
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

export interface DeviceItem {
    id: number;
    name: string;
    location: string | null;
    is_active: boolean;
    last_seen_at: string | null;
    last_seen_at_formatted: string | null;
}

export type ScanResultCode =
    | 'success_in'
    | 'success_out'
    | 'duplicate'
    | 'already_checked_in'
    | 'already_checked_out'
    | 'no_checkin'
    | 'blocked_excused'
    | 'unknown_card'
    | 'inactive_card'
    | 'lost_card'
    | 'student_inactive'
    | 'no_school_day';

export type ScanSoundTone = 'success' | 'duplicate' | 'warning' | 'error';

export interface ScanStudentPayload {
    id: number;
    nis?: string;
    name: string;
    classroom: string | null;
    photo_url: string | null;
}

export interface ScanAttendancePayload {
    status: string;
    status_label?: string;
    check_in_at: string | null;
    check_out_at: string | null;
}

export interface ScanResultPayload {
    ok: boolean;
    result: ScanResultCode;
    message: string;
    sound_tone: ScanSoundTone;
    scanned_at: string | null;
    uid_normalized: string | null;
    student: ScanStudentPayload | null;
    attendance: ScanAttendancePayload | null;
}

export interface ScanLogItem {
    id: number;
    uid_raw: string;
    uid_normalized: string | null;
    result: ScanResultCode;
    message: string;
    sound_tone: ScanSoundTone;
    ok: boolean;
    scanned_at: string | null;
    scanned_time_formatted: string | null;
    student: ScanStudentPayload | null;
    attendance: ScanAttendancePayload | null;
}
