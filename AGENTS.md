# Project Coding Rules (AGENTS.md)

## Stack & Tooling
- Backend: Laravel 12, PHP 8.2+, Spatie Laravel Permission, OpenSpout.
- Frontend: Inertia.js v2, Vue 3 (`<script setup>` Composition API) + TypeScript, Tailwind CSS.
- Quality: Pest / PHPUnit (`php artisan test`), Laravel Pint (`./vendor/bin/pint --test`), `vue-tsc --noEmit`, `npm run build`.
- Timezone: `Asia/Jakarta` everywhere.
- Locale: `id` (Indonesian). All user-facing UI text in Indonesian via `lang/id/` translation files; all code, identifiers, database columns, and commit messages in English.

## Architecture Rules
1. **Thin Controllers**: Controllers only authorize, validate (via Form Requests), call an Action or Service class, and return an Inertia response or redirect.
2. **Actions & Services**: Put business logic and multi-step mutations in `app/Actions/` or `app/Services/` inside database transactions where appropriate.
3. **Form Requests**: Validate all incoming request data using dedicated Form Request classes in `app/Http/Requests/`.
4. **Eloquent Resources / DTOs**: Never pass raw Eloquent models directly to Inertia pages. Transform data with `JsonResource` classes in `app/Http/Resources/`.
5. **UID Normalization**: Always normalize NFC card UIDs through a single shared helper in PHP and TypeScript.
6. **Testing**: Every feature and business rule must be covered by automated tests before completion.

<!-- antislop:start -->
## antislop
For UI, copy, people, mobile layout, or code comments work, read `antislop.md` (core) and then the skill for the task:
- UI / visual: `skills/antislop-ui/SKILL.md`
- Copy & text: `skills/antislop-copywriting/SKILL.md`
- People: `skills/antislop-human/SKILL.md`
- Mobile / responsive: `skills/antislop-layoutmobile/SKILL.md`
- Code comments: `skills/antislop-code/SKILL.md`
Before starting, follow the core's "Two Usage Modes" section in strict order: explicit session instruction first, then global preference, then ask. A session instruction always wins. For a resolved mode, say `antislop active: <mode> (session override).` or `antislop active: <mode> (global preference).` once before presenting findings or making edits, using the actual mode and source. Acknowledging the user's request without naming the source does not replace this notice.
Only an explicit choice of antislop during or after selects a session mode. A request to review, audit, or avoid file edits does not select a mode; read the global preference in that case. Another skill's mode does not select antislop's mode.
If the mode is unresolved, ask during/after and end the response; wait for the answer before any UI review, planning, or concept. For read-only tasks, put the active-mode notice only at the start of the final answer, never in progress messages. For editing tasks, announce before the first edit and omit it from the final answer.
To update antislop later: download `antislop.md` again, or run `npx antislop-ai --update` if it was installed as skill folders.
<!-- antislop:end -->

