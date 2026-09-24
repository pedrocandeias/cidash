# CLAUDE.md

## Project: CIDASH

Communication & Image Dashboard. An internal operational workspace for the Serviço de Comunicação e Imagem da Universidade do Porto.

- `ARCHITECTURE.md` is the source of truth for the data model, navigation and scope (MVP / Phase 2 / Future). Read it before any structural change and keep it updated.
- `TODO.md` is the backlog. Tick items off when they are done. Anything outside the current phase needs explicit approval.
- `CHANGELOG.md` records every change, in Portuguese: each release gets a block `## <version> (<YYYY-MM-DD>)` with Conventional Commits lines (`feat:`, `fix:`, `docs:`, `build:`, `ci:`, `chore:`, `refactor:`), no Added/Changed categories. While in 0.x, `feat` bumps the minor version and `fix` the patch. Update it in the same commit as the change, and tag the release commit with an annotated tag `v<version>` (message `CIDASH <version>`) when pushing.
- Core rule: every domain record is an `objects` row plus relations. A domain model uses the `IsRecord` trait (its table's uuid `id` references `objects.id`, cascading on delete) and is registered in `App\Core\RecordTypes` (morph alias, detail route, label). New modules must not change the core (`app/Core`: `WorkspaceScope`, `IsRecord`, `BelongsToWorkspace`, `Links`, `Tags`, `Terms`).
- Tests that need a domain model without a module use `Tests\Fixtures\TestNote` (`TestNote::setUpTable()`).
- Never use `WithoutModelEvents` in seeders or disable model events around record models: `IsRecord` creates the `objects` row, the activity log and the search index in model events.
- There is no institutional Directory (deferred to Future). "Pessoas de interesse" (`people`) is a manually curated per-workspace list of expert profiles for the media.
- Multi-workspace from day one. The pilot is the Reitoria only, but the app will expand to the faculties' communication teams. Workspaces are strictly isolated: nothing is visible across teams except to the super admin. Every object has a `workspace_id`, all reads go through the core workspace scope, and relations only link objects in the same workspace. The only global data is user accounts, the source catalogue and collected `news_items`, which are public content. Any code that bypasses the scope must do so explicitly and be covered by the isolation tests.
- Roles: a global super admin (`users.is_super_admin`), plus `manager`, `editor` or `member` per workspace (member < editor < manager). Editors approve content. Managers manage users only within their own workspace. The matrix is in `ARCHITECTURE.md` §2.6.
- UI language is Portuguese (pt-PT). Code, schema and field names are in English.
- Visual identity: the Claude Design system "CIDASH" (https://claude.ai/artifact/PkiFqHQqyrCQeVVWdpwrcG): Lato, brand bronze, near-square corners (max 6px), signal colours. Its tokens live in `resources/css/app.css`. Use the theme and signal utilities (`text-critical`, `bg-warning-surface`, `text-success`, `bg-info-surface`, `bg-primary`…), never raw Tailwind palette colours (`text-red-600`, `bg-amber-50`), so both themes stay legible. `bronze` marks only the brand and the active page; it is not a text colour.
- Database: SQLite, chosen for portability. All persistent state (data, queues, cache, sessions, uploads) lives in the data folder, so do not introduce Redis or a DB server. Avoid raw engine-specific SQL outside `Core/Search`.
- Stack: Laravel 13 (official React starter kit: Inertia, TypeScript, Tailwind, shadcn/ui, Fortify) + SQLite, deployed on a plain LAMP server that is exposed to the web. Only cron is available (no Redis, no supervisor, no Node on the server). SQLite, `storage/` and `.env` must stay outside `public/`.
- i18n from day one: no hardcoded user-facing text. The key is the English text: `t('Log out')` in React (`useTranslation` from `@/lib/i18n`) and `__('Log out')` in PHP, translated in `lang/pt_PT.json`. Laravel's own messages live in `lang/pt_PT/*.php`. A key is shared by the whole app: before reusing an English key, check that its Portuguese translation fits the new place (gender and number: tarefa/pedido/campanha); if not, use a distinct English key (e.g. event status "Held", campaign status "Ended").
- Auth: local accounts by invitation only (no public registration), with optional TOTP 2FA that any user can turn on or off. SSO U.Porto comes later, keyed by institutional email.
- Email is configured in the app (Admin → Email, stored encrypted in `settings`), not in `.env`. The app must still work when email is not configured.
- In-app notifications extend `App\Notifications\CidashNotification`: they carry plain data (never models, since the queue worker has no workspace context to restore scoped models), the bell is sent synchronously and only the email is queued, following the user's `EmailPreferences`.
- Date columns cast as `date` are stored as `Y-m-d 00:00:00`: compare them with Carbon values (`startOfDay`/`endOfDay`), not with `toDateString()` strings.
- User-created vocabularies (expertise areas, tags) must be protected against human-error duplicates: normalize, add a unique index on `normalized_name`, reuse instead of duplicating, and suggest near-matches.
- News scrapers store metadata only (headline, lead, date, URL), respect robots.txt and rate limits, and never bypass paywalls.
- Commands (run inside DDEV):
  - `ddev start`; the app is at https://cidash.ddev.site
  - `ddev npm run build`: rebuild frontend assets (Vite HMR inside DDEV is not configured yet)
  - `ddev composer ci:check`: everything CI runs (vp check, tsc, Pint, PHPStan, tests). Must pass before finishing a task
  - `ddev artisan test --filter=Name`: single test
  - `ddev composer lint` / `ddev npm run check:fix`: auto-fix PHP / frontend formatting
  - `ddev exec scripts/build-dist.sh`: build `dist/` from the current commit to upload to the LAMP server (CI also publishes it as an artifact); install and update steps are in `ARCHITECTURE.md` §7
  - `ddev artisan cidash:create-workspace "CI Reitoria" --slug=reitoria` and `ddev artisan cidash:create-user EMAIL NAME [--super-admin] [--workspace=reitoria --role=manager]`: bootstrap without public registration (prints a set-password link)
  - Local seed (`ddev artisan migrate:fresh --seed`): `test@example.com` / `password`, super admin and manager of CI Reitoria
- After backend route changes, run `ddev npm run build` before `ci:check`: Wayfinder regenerates `resources/js/actions` and `resources/js/routes`, and stale generated files can hide TypeScript errors.
- The host PHP has no `pdo_sqlite`, so always run PHP, Composer and npm through DDEV.

---

Behavioral guidelines to reduce common LLM coding mistakes. Merge with project-specific instructions as needed.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.
