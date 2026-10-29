# AGENTS.md

Standing instructions for every agent working in this repository.
Read this file, then `docs/BRIEF.md` (full product brief) and `docs/reader-sample.md` (raw output of the NFC reader) before starting any task.

## Project

School attendance system for a junior high school (SMP, ~700 students, students only).
Students tap an NFC card (MIFARE Classic 1K / S50) on a USB reader (Hassel 13.56 MHz) at a scan station. The system records daily attendance (present, late, permission, sick, absent), shows live feedback, and produces reports.
Deployed on-premise on a school server over LAN. One scan station for now; design must allow more.

Out of scope for now (do NOT build unless asked): WhatsApp notifications, parent portal, per-subject attendance, teacher/staff attendance, biometrics, mobile app.

## Stack

- PHP 8.2+, Laravel 11/12, MySQL/MariaDB
- Inertia.js v2 + Vue 3 (Composition API, `<script setup>`, TypeScript)
- Tailwind CSS
- `spatie/laravel-permission`, `maatwebsite/excel`, `barryvdh/laravel-dompdf`
- Laravel Reverb (real-time dashboard), Laravel queue for background jobs
- Pest for tests

## Commands

Adjust if the repo differs, but keep this list accurate.

- Install: `composer install` and `npm install`
- Dev: `composer run dev` (or `php artisan serve` + `npm run dev`)
- Test: `php artisan test`
- PHP lint/format: `./vendor/bin/pint`
- Frontend type check: `npm run type-check` (or `npx vue-tsc --noEmit`)
- Frontend build: `npm run build`

Before saying a task is done: run tests, Pint, and the type check, and fix failures.

## Language

- UI text, validation messages, and reports: Indonesian (use Laravel lang files / i18n, no hard-coded strings in components where avoidable).
- Code, identifiers, comments, commit messages, docs for developers: English.
- Timezone: `Asia/Jakarta`. Store and compare times consistently; never mix UTC and local time without converting explicitly.

## Backend conventions

- Controllers are thin: validate with Form Requests, authorize with Policies/Gates, delegate to Service or Action classes (`app/Services`, `app/Actions`).
- Business logic (attendance status, lateness, debounce, absent job) lives in Services/Actions, never in controllers, models, or Vue components.
- Wrap multi-write operations in DB transactions.
- Never return raw Eloquent models to Inertia; use API Resources or DTOs and send only needed fields.
- Avoid N+1 queries: eager load, and add indexes for lookups (`nfc_cards.uid`, `attendances.date`).
- Use enums for fixed sets (attendance status, card status, scan result).
- Migrations must be reversible and never edited after merge; add a new migration instead.
- Seed realistic dummy data (classes, students, cards) for local development.

## NFC and scan rules (important)

- The reader behaves as a HID keyboard: it types the UID then presses Enter. Do not assume a driver or WebUSB. The exact UID format is documented in `docs/reader-sample.md`; follow it, do not guess.
- All UID normalization goes through ONE helper (backend) and ONE function (frontend composable) with identical behavior: uppercase hex, no separators, byte order as decided in `docs/reader-sample.md`.
- UID is an identifier, not a secret. Never rely on it alone for security-sensitive decisions.
- Every tap, including failed, duplicate, unknown, and inactive-card taps, is written to `attendance_logs`. `attendances` holds one row per student per day (unique constraint).
- Duplicate tap window (debounce) is configurable, default 30 seconds.
- Scan endpoints are authenticated with a device token (stored hashed) and rate limited.
- Scan responses must be fast (target p95 under 500 ms on LAN): keep the scan path free of heavy queries and synchronous external calls.
- Offline support: the scan station queues scans locally (IndexedDB) and syncs via a batch endpoint with idempotency keys.

## Frontend conventions

- Vue 3 `<script setup lang="ts">`, Composition API only. Shared logic in composables (e.g. `useNfcScanner`).
- Typed props and Inertia page props; no `any` unless justified in a comment.
- The Scan Station page: full screen, hidden input that is always focused and re-focuses on blur/click, tap detection by fast character bursts (inter-key gap under about 50 ms) ending with Enter, ignore slow manual typing.
- Feedback must be obvious at a glance: large photo, name, class, time, colored status, and distinct sounds for success, duplicate, and unknown card.
- Use Tailwind utilities; reuse existing components before creating new ones. Keep dashboards usable on tablet width.

## Database

- Main tables: `academic_years`, `classrooms`, `students`, `teachers`, `nfc_cards`, `attendance_rules`, `attendances`, `attendance_logs`, `devices`, `holidays`, `audit_logs`. See `docs/BRIEF.md` section 7.
- `nfc_cards` keeps a polymorphic owner relation so other card holders can be added later, but only students are used now.
- One active card per student; replacing a card deactivates the old one and keeps history.
- All manual corrections to attendance require a reason and write to `audit_logs`.

## Testing

- Every feature ships with Pest tests. Prioritize: UID normalization, scan flow (success, late, duplicate, unknown, inactive card), attendance rule calculation, absent-by-schedule job (skips holidays), authorization per role, and import validation.
- Use factories; do not depend on seeded data inside tests.
- Frontend logic with real complexity (scanner composable, offline queue) gets unit tests that simulate fast keystrokes plus Enter.

## Security and privacy

- Authorize every route by role (Super Admin, Admin/TU, Piket, Wali Kelas, Kepala Sekolah). Wali Kelas only sees their own classes.
- Student data is personal data of minors: expose only what a role needs, never log full personal data in plain logs.
- Validate all input, keep CSRF protection on web routes, never commit secrets or `.env`.

## Working agreement

1. Work on ONE phase or feature per task, matching the phases in `docs/BRIEF.md` section 11. Do not start other phases or add out-of-scope features.
2. For anything non-trivial, produce an implementation plan first and wait for approval before changing files.
3. If the brief, this file, and the code disagree, or a requirement is ambiguous, stop and ask instead of guessing.
4. Prefer small, reviewable diffs. Do not refactor unrelated code or reformat files you are not changing.
5. Do not add new dependencies without saying why; prefer what is already in the stack.
6. Never run destructive commands (dropping the database, `migrate:fresh` on non-local data, deleting files outside the task) without explicit approval.
7. At the end of each task, summarize what changed, how it was tested, and anything left undone or uncertain.

## Git

- Branch per phase/feature (e.g. `feat/phase-2-scan-station`). Conventional commit messages (`feat:`, `fix:`, `test:`, `refactor:`, `docs:`).
- Do not commit directly to `main`.
