# PRD: NFC School Attendance System

Status: v1 draft. Companion docs: `docs/BRIEF.md` (context, architecture, DB draft), `docs/reader-sample.md` (raw reader output), `AGENTS.md` (coding rules).

## 0. How agents should use this document

- Work on ONE phase per task. Read sections 1 to 4 (shared context), then only the phase you were asked to do (section 5).
- Each phase lists scope, user stories, acceptance criteria (AC), ordered tasks, required tests, and a Definition of Done (DoD). A phase is done only when every AC is checked and all tests pass.
- Anything marked **DEFAULT** is a seed value or decision that must be configurable and may change after talking to the school. Do not hard-code it.
- If this PRD conflicts with `docs/BRIEF.md`, this PRD wins. If requirements are ambiguous, ask before building.

## 1. Product summary

Web-based daily attendance for a junior high school (SMP, about 700 students). Students tap an NFC card (MIFARE Classic 1K) on a USB HID reader at a scan station. The system records check-in and check-out, computes lateness, marks absences automatically, and gives staff dashboards and reports. Hosted on a school server (LAN). One scan station initially.

### Goals

- A tap is recorded correctly once, with visible and audible feedback in under 1 second.
- Zero manual attendance taking for present/late students.
- Class teachers can see and correct their class attendance; school leadership can see summaries.

### Non-goals (do NOT build now)

WhatsApp or other notifications, parent/student portal, per-subject attendance, teacher/staff attendance, biometrics, mobile app, Dapodik integration.

## 2. Users and permissions

| Role | Key abilities |
|---|---|
| `super_admin` | Everything, including users, devices, system settings |
| `admin_tu` | Master data, card registration, rules, corrections, all reports |
| `piket` | Scan Station, manual excused entry for today, view today |
| `wali_kelas` | View and recap own classes, enter excused status for own classes |
| `kepala_sekolah` | Read-only dashboard and reports |

Permissions (Spatie): `users.manage`, `master.manage`, `cards.manage`, `rules.manage`, `devices.manage`, `scan.operate`, `attendance.view_all`, `attendance.view_own_class`, `attendance.excuse`, `attendance.correct`, `reports.view`, `audit.view`. Wali kelas data is always scoped to classes where they are the homeroom teacher.

## 3. Glossary

- **UID**: card serial number read by the reader, normalized per `docs/reader-sample.md` into uppercase hex without separators (the "normalized UID").
- **Tap / scan**: one reader burst of characters ending with Enter.
- **School day**: a date that is not a holiday and has an `attendance_rules` row for its weekday.
- **Station**: a registered scan device/browser with a device token.

## 4. Business rules (shared across phases)

### 4.1 Attendance statuses

`present` (Hadir), `late` (Terlambat), `permission` (Izin), `sick` (Sakit), `absent` (Alpa), `dispensation` (Dispensasi). Use a PHP backed enum. UI labels in Indonesian.

### 4.2 Time rules (per weekday, editable in UI)

DEFAULT seed values for school days Monday to Friday:

| Setting | Default |
|---|---|
| `check_in_start` | 06:00 (taps earlier are accepted as check-in) |
| `late_after` | 07:00 (check-in strictly after this time is `late`) |
| `check_out_start` | 14:00 (taps from this time on count as check-out) |
| `absent_cutoff` | 10:00 (auto-absent job runs after this time) |
| `debounce_seconds` | 30 |

### 4.3 Scan decision logic

Inputs: normalized UID, server time (or client timestamp when synced from offline queue), device.

1. Find card by UID. Not found: result `unknown_card`.
2. Card status `inactive`: `inactive_card`. Status `lost`: `lost_card` (highlight as warning for the operator).
3. Owner student not active: `student_inactive`.
4. Today is not a school day: `no_school_day`.
5. If the same card has a successful or duplicate scan within `debounce_seconds`: `duplicate` (no data change).
6. Time before `check_out_start`:
   - No attendance row today, or row status is auto `absent`: create/update as check-in. Status `present` if time <= `late_after`, else `late`. Result `success_in`.
   - Row already has check-in: `already_checked_in` (no change).
   - Row status is `permission`, `sick`, or `dispensation`: `blocked_excused` (no change; operator sees a warning and decides manually).
7. Time at or after `check_out_start`:
   - Row has check-in and no check-out: set `check_out_at`. Result `success_out`.
   - Row has check-out already: `already_checked_out`.
   - No row for today (never checked in): record check-out only with status unchanged (create row with status `absent` is NOT allowed; reject as `no_checkin`).
8. Every tap, whatever the result, writes an `attendance_logs` row (raw UID, normalized UID, device, timestamp, result).

All results are returned as machine-readable enum strings; the UI maps them to Indonesian messages and sounds.

### 4.4 Auto-absent job

Scheduled command runs every 5 minutes after `absent_cutoff` on school days. For each active student with no attendance row that date, create a row with status `absent`, `source = system`. Idempotent. A later valid check-in tap overwrites an auto-absent row (rule 4.3.6) and records an audit entry.

### 4.5 Manual entries and corrections

- Excused entries (`permission`, `sick`, `dispensation`) can be created for a student and date range, with optional note. They replace auto-`absent`.
- Any change to an existing attendance row requires a reason and writes `audit_logs` (before/after, user, time).

## 5. Phases

### Phase 0: Foundation and reader verification

**Scope**: project skeleton, tooling, reader findings documented.

**Tasks**
1. Confirm Laravel + Inertia v2 + Vue 3 + TypeScript + Tailwind + Pest are installed and a blank authenticated page renders.
2. Configure timezone `Asia/Jakarta`, locale `id`, Pint, type check, and a basic CI script (test + lint + type-check + build).
3. Add `docs/` with `BRIEF.md`, `PRD.md`, `reader-sample.md`.
4. Create a normalized-UID helper (PHP) and TypeScript twin, driven by the rules in `reader-sample.md`, with unit tests using real samples.

**AC**
- [ ] `php artisan test`, Pint, type check, and `npm run build` all pass on a clean checkout.
- [ ] UID helper tests cover every sample in `reader-sample.md`, both PHP and TS give identical output.

**DoD**: AC met, README has setup instructions.

### Phase 1: Auth, roles, master data

**User stories**
- As admin_tu, I manage academic years, classrooms, and students, and import students from Excel.
- As super_admin, I manage users and roles.
- As kepala_sekolah, I can log in and only read.

**Scope**: Auth (login, logout, password reset optional), roles/permissions seeding, CRUD for `academic_years` (one active), `classrooms` (name like `7A`, level 7/8/9, homeroom teacher), `students` (NIS, NISN, name, gender, classroom, photo, parent name/phone, active flag), `teachers` (as users), `holidays`.

**Tasks**
1. Migrations, models, factories, seeders (roles, demo classes, 30 demo students).
2. Policies and Form Requests for each resource; Inertia pages: list with search/filter/pagination, create/edit form, delete (soft delete for students).
3. Student photo upload (store on `public` disk, resize to a sensible max size).
4. Excel/CSV import: template download, row-level validation, preview with errors, commit valid rows; duplicate NIS rejected.
5. Layout shell with role-based navigation.

**AC**
- [ ] Each role sees only permitted menu items and routes (403 otherwise).
- [ ] Wali kelas sees only their own classrooms' students.
- [ ] Importing a 700-row file completes without timeout, reports per-row errors, and does not create partial duplicates.
- [ ] Only one academic year can be active.

**Tests**: policies per role, import validation, student CRUD feature tests.

### Phase 2: Cards, scanner, scan API (core)

**User stories**
- As admin_tu, I register an NFC card to a student by tapping it, and replace a lost card.
- As piket, I stand at the Scan Station and see instant feedback on each tap.

**Scope**: `nfc_cards`, `devices`, `attendances`, `attendance_logs`, scan service implementing section 4.3 (with default rules seeded), Scan Station page, card registration UI.

**Tasks**
1. Migrations and models: `nfc_cards` (uid unique, polymorphic owner, status, issued_at, deactivated_at, notes), `devices` (name, token hash, last_seen_at), `attendance_rules`, `attendances` (unique owner+date), `attendance_logs`.
2. `ScanService` / `RecordScanAction` implementing 4.3 in a transaction. Result is an enum.
3. API: `POST /api/scan` (device token auth, rate limited), see section 6.
4. Device management page (create device, show token once, revoke).
5. Composable `useNfcScanner`: global keydown listener, buffer chars, treat as tap when inter-key gaps are small and ends with Enter, ignore slow manual typing, normalize UID, emit `scan`. Configurable gap threshold, based on the max gap observed in `reader-sample.md`.
6. Scan Station page (full screen): always-focused capture, big photo/name/class/time/status, colored result states, distinct sounds for success, duplicate, unknown/inactive, last 10 taps list, connection indicator, auto-reset after 3 seconds.
7. Card registration page: pick student, tap card, validate uniqueness, save. Replace-card flow (old card `inactive`, history kept). Bulk registration mode: select a class, system walks the student list, each tap assigns to the highlighted student, with skip/undo.
8. Seeders/factories for cards in dev.

**AC**
- [ ] Tapping a registered card on the Scan Station shows student identity and status within 1 second on LAN and creates exactly one attendance row.
- [ ] All result types in 4.3 are reachable and covered by tests, each with correct UI feedback.
- [ ] Duplicate taps inside the debounce window do not alter data but are logged.
- [ ] One UID cannot be linked to two active owners; replacing a card keeps history.
- [ ] Typing on the keyboard normally (slow) on the Scan Station does not trigger scans.
- [ ] Scan API rejects missing/invalid device tokens (401) and is rate limited (429).

**Tests**: scan decision matrix (every branch of 4.3), UID normalization, card uniqueness, API auth, composable unit tests simulating fast keystroke bursts + Enter and slow typing.

### Phase 3: Rules, statuses, absent job, manual entries

**User stories**
- As admin_tu, I set time rules per weekday and manage holidays.
- As wali_kelas, I mark a student sick or on permission, and see my class today.
- As admin_tu, I correct an attendance record with a reason.

**Tasks**
1. Rules UI: per-weekday `check_in_start`, `late_after`, `check_out_start`, `absent_cutoff`, `debounce_seconds`, school-day on/off. Validation: times ordered logically.
2. Scheduled auto-absent command per 4.4 (registered in the scheduler, idempotent, skips non-school days).
3. Excused entry UI (single date or range) with optional note/attachment; permissions per role scope.
4. Correction UI with mandatory reason; writes `audit_logs`; audit log viewer (filter by user/date).
5. Today view per class (wali kelas) listing each student with status and times.

**AC**
- [ ] Changing a rule changes subsequent scan results; past records are unchanged.
- [ ] Auto-absent job on a holiday or non-school day creates nothing; running it twice creates no duplicates.
- [ ] A check-in tap after auto-absent converts the row and logs an audit entry.
- [ ] Every correction is blocked without a reason and appears in the audit log.
- [ ] Wali kelas cannot edit other classes.

**Tests**: job idempotency, holiday handling, boundary times (exactly `late_after`, exactly `check_out_start`), correction authorization.

### Phase 4: Dashboard and reports

**User stories**
- As kepala_sekolah, I see today's attendance at a glance.
- As wali_kelas, I download my class's monthly recap.

**Tasks**
1. Dashboard (real-time via Reverb, fallback to polling): counts for present, late, permission, sick, absent, not yet arrived; per-class table; list of students not yet arrived; live feed of recent taps; 30-day trend chart.
2. Reports: daily class recap; monthly class matrix (students x dates with codes H/T/I/S/A/D, totals); per-student history; top late students; top absent students. Filters: date range, class.
3. Export to Excel and PDF (printable layout with school name and logo from settings), generated via queue for large ranges.
4. Settings page: school name, logo, timezone display.

**AC**
- [ ] Dashboard numbers equal the underlying records for the same day (verified by tests).
- [ ] A scan on the Scan Station appears on the dashboard live feed within 2 seconds.
- [ ] Monthly class recap totals match the daily data and exports open correctly in Excel and as PDF.
- [ ] Wali kelas can only export their own classes; kepala_sekolah has read-only access.

### Phase 5: Offline mode, hardening, on-prem deployment

**Tasks**
1. Offline queue on the Scan Station: store taps in IndexedDB with client timestamp and idempotency key when the API is unreachable; show queued count; sync via batch endpoint when back online; the server dedupes by idempotency key and validates timestamp tolerance.
2. Security pass: policy coverage, rate limits, CSRF, input validation, log hygiene (no personal data in plain logs), file upload checks.
3. Performance pass: indexes, query review, load test simulating about 700 taps in 15 minutes.
4. Deployment on the school server: Nginx + PHP-FPM, MySQL/MariaDB, supervisor (or systemd) for queue worker and Reverb, scheduler cron, internal HTTPS, scheduled DB backup to a separate disk/location with a tested restore, `.env.example` and a deployment runbook in `docs/DEPLOY.md`.
5. User guide for piket and wali kelas (short, in Indonesian) in `docs/`.

**AC**
- [ ] Taps made while the server is unreachable are recorded after reconnection with no duplicates and correct original timestamps.
- [ ] Load test: p95 scan response under 500 ms; no errors at peak rate.
- [ ] Backup restores successfully on a clean machine following the runbook.
- [ ] Fresh deploy from the runbook works end to end.

## 6. API contracts

Auth: `Authorization: Bearer <device_token>`. Business outcomes return HTTP 200 with a `result`; transport/validation problems use 401, 422, 429.

### POST /api/scan

Request:
```json
{
  "uid": "04A1B2C3",
  "scanned_at": "2026-11-02T06:58:12+07:00",
  "idempotency_key": "6f1b3c1e-8b0a-4a2e-9f55-0d3f2c1a7e10"
}
```
`scanned_at` is optional (server time is used if absent). `uid` is the raw reader text; the server normalizes it.

Response 200:
```json
{
  "ok": true,
  "result": "success_in",
  "message": "Selamat datang",
  "student": { "id": 12, "name": "Nama Siswa", "classroom": "7A", "photo_url": "https://..." },
  "attendance": {
    "status": "present",
    "check_in_at": "2026-11-02T06:58:12+07:00",
    "check_out_at": null
  }
}
```
`ok` is true for `success_in` and `success_out`, false otherwise. For `unknown_card`, `student` and `attendance` are null.

Result values: `success_in`, `success_out`, `duplicate`, `already_checked_in`, `already_checked_out`, `no_checkin`, `blocked_excused`, `unknown_card`, `inactive_card`, `lost_card`, `student_inactive`, `no_school_day`.

### POST /api/scan/batch

Request: `{ "scans": [ { "uid", "scanned_at", "idempotency_key" }, ... ] }` (max 200). Response: `{ "results": [ { "idempotency_key", "result", ... } ] }` in the same order. Replaying an already-processed `idempotency_key` returns the original result without side effects.

## 7. Screens

| Screen | Roles | Phase |
|---|---|---|
| Login | all | 1 |
| Master data lists and forms (years, classes, students, holidays), import | admin_tu, super_admin | 1 |
| Users and roles | super_admin | 1 |
| Scan Station | piket, admin_tu, super_admin | 2 |
| Card registration, bulk registration | admin_tu | 2 |
| Devices | super_admin | 2 |
| Rules and holidays | admin_tu | 3 |
| Today per class, excused entry, corrections, audit log | wali_kelas, piket, admin_tu | 3 |
| Dashboard | kepala_sekolah, admin_tu, super_admin | 4 |
| Reports and exports | per scope | 4 |
| Settings | super_admin | 4 |

## 8. Non-functional requirements

- Scan p95 under 500 ms on LAN; dashboard usable on tablet width; Scan Station designed for a desktop full screen.
- Indonesian UI; all dates shown in `Asia/Jakarta`.
- Personal data of minors: role-scoped access, no personal data in plain logs, backups protected.
- Everything covered by Pest tests per phase; CI green before merge.

## 9. Decisions to confirm with the school

1. Actual time rules (check-in start, late threshold, check-out start, absent cutoff) and school days (Monday to Friday or Saturday too).
2. Whether early check-out (go home early) needs a separate status or permission flow.
3. Whether students arriving after the cutoff should be `late` or require manual handling.
4. Required printed report format, if any.
5. Who physically distributes cards and how lost cards are reported.

## 10. Suggested Antigravity prompt per phase

Use this template, replacing the phase number:

```
Read AGENTS.md, docs/BRIEF.md, docs/PRD.md sections 0-4, and PRD section 5 "Phase N" only.
Produce an implementation plan covering the tasks, files to create or change, and the tests,
then wait for my approval before editing anything.
Do not start other phases or any non-goal.
When done, run tests, Pint, and the type check, then give me a summary with each AC marked
as met or not met and anything uncertain.
```
