# Module guide — how to build a School ERP module

Read `CLAUDE.md` first (non-negotiable rules), then this file, then `docs/UI_GUIDE.md` (components), then the master-plan sections your phase cites.
Several modules are built **in parallel by different developers in one working tree**. The rules below exist so that nobody edits the same file: **you add files; you do not edit shared files.**

## 1. The request flow and what goes where

```
routes/school/<module>.php        URL + name + can:<permission> middleware           (no logic, no closures)
app/Http/Middleware               already done: auth, school.context, subscription.access, two-factor.role
app/Http/Requests/<Module>/       authorize() via policy  +  rules() incl. TenantRule  (validated(), never $request->all())
app/Http/Controllers/School/<Module>/   thin: call ONE service method, return view / redirect / download
app/Services/<Module>/            business rules, DB::transaction, lockForUpdate, idempotency, AuditLogger, ValidationException
app/Models/<Module>/              BelongsToSchool, #[Fillable], casts(), relations, scopes. No workflows.
app/Policies/<Module>/            extends TenantPolicy; data-level rules (teacher sections, parent children)
app/Enums/<Module>/               backed enums with label() and tone() (badge tone)
```

Services receive validated arrays (or small readonly DTOs) and the acting `User`; they never read `Request` or the session.
A service may throw `Illuminate\Validation\ValidationException::withMessages([...])` for a business-rule violation: the page re-renders with the message.
Controllers may do trivial read queries for a view only if a service method would be a one-line wrapper; anything with filters, sorting, pagination or joins goes in a service (`paginate()`, `search()`).

Reference slices (copy their shape): `Platform/Schools/SchoolController` + `Services/Platform/SchoolRegistryService` + `Http/Requests/Platform/*` (platform, non-tenant);
`School/Identity/MemberController` + `Services/Identity/{Membership,Invitation,Role}Service` + `Http/Requests/Identity/*` (tenant); `Policies/TenantPolicy`, `Policies/Documents/StoredFilePolicy`; tests in `tests/Feature/Identity` and `tests/Feature/Tenancy`.

## 2. Your files (module anatomy)

| Kind | Location | Notes |
|---|---|---|
| Models | `app/Models/<Module>/X.php` | `use BelongsToSchool, HasFactory;` `#[Fillable([...])]` (never `school_id`) `#[UsePolicy(XPolicy::class)]` |
| Migrations | `database/migrations/2026_11_NN_…` | **your timestamp prefix only** (table in §3). Use `$table->tenant()` / `tenantForeign()` |
| Factories | `database/factories/<Module>/XFactory.php` | extend `TenantFactory`; resolve relations from `$a['school_id']` (see `StudentEnrollmentFactory`) |
| Services / Requests / Controllers / Policies / Enums | as in §1 | namespace = `<Module>` |
| Routes | `routes/school/<module>.php` | auto-loaded; names become `school.<module>.…`, URLs `/app/…` |
| Views | `resources/views/school/<module>/…` | `<x-layouts.app :title="…">` |
| Menu | `resources/menus/school/NN-<module>.php` | see §3 for group names/orders |
| Permissions | `config/permissions/<module>.php` | already exist for every module; **you own yours**: refine actions/defaults |
| School settings defaults | `config/school_defaults/<module>.php` | read with `$school->setting('<module>.key')` |
| Styles / scripts | `resources/scss/modules/_<module>.scss`, `resources/js/modules/<module>.js` | placeholders exist |
| Provider (optional) | `app/Providers/Modules/<Module>ServiceProvider.php` | auto-registered; for relations, bindings, schedule, view composers |
| Tests | `tests/Feature/<Module>/`, `tests/Unit/<Module>/` | see §8 |
| Docs | `docs/<module>/…` | design notes, assumptions needing sign-off |

### Drop-in UI extension points (add a file, edit nothing)
| Where | File you add | Receives |
|---|---|---|
| School dashboard widget | `resources/views/school/dashboard-widgets/<NN>-<module>.blade.php` (NN orders them) | `$user`, `$school` — query through a service (`app(XService::class)->summary()`), guard with `@can` |
| Platform dashboard panel | `resources/views/platform/dashboard-panels/<NN>-<module>.blade.php` | — |
| Platform school page panel | `resources/views/platform/schools/panels/<NN>-<module>.blade.php` | `$school` |
| Demo data | `database/seeders/Demo/<Module>DemoSeeder.php` (class `Database\Seeders\Demo\<Module>DemoSeeder`) | run by `DemoSeeder` after core data; find schools by code `sunrise` / `greenvalley`, insert inside `app(TenantContext::class)->run($school, fn () => …)` |

### Policies for core models
Core models are frozen, so register policies for them in **your** provider: `Gate::policy(Student::class, StudentPolicy::class);`. Your own new models use `#[UsePolicy(XPolicy::class)]`.

### Route names other modules rely on (keep them)
`school.setup.years.index`, `school.setup.classes.index`, `school.setup.subjects.index`, `school.setup.profile.edit`, `school.setup.settings.edit` · `school.students.index|create|show`, `school.students.lookup` (JSON for `<x-form.lookup>`: `[{id,label,sublabel}]`), `school.guardians.index`, `school.staff.index` · `school.attendance.index` · `school.fees.collect`, `school.fees.students.show` (student ledger) · `school.exams.index` · `school.homework.index`, `school.notices.index` · `school.promotion.index` · `parent.dashboard`, `parent.children.switch`.

### Tenant context lifetime (important for tests)
`TenantContext` is one object per process; its state is cleared at every request/job boundary. In tests every HTTP call starts AND ends with no school, exactly like production — so after `$this->signInTo(...)->post(...)`, read data with `inSchool()`, not with a bare model query.

### Extending core models without editing them
Core models (`Student`, `StudentEnrollment`, `Guardian`, `StaffProfile`, `AcademicYear`, …) are shared and frozen. Add your relations from your provider:

```php
// app/Providers/Modules/FeesServiceProvider.php
public function boot(): void
{
    Student::resolveRelationUsing('charges', fn (Student $s) => $s->hasMany(StudentCharge::class));
}
```
Need a new column or index on a core table? Add an `alter` migration with **your** timestamp prefix, and mention it in your report.

## 3. Allocation table (avoid collisions)

| Module | Migration prefix | Menu file / group (order) | Test DB | Route file |
|---|---|---|---|---|
| School setup | `2026_11_01_` | `10-setup.php` / School (500) | `school_erp_test_a1` | `setup.php` |
| People (students, guardians, staff) | `2026_11_02_` | `20-people.php` / People (100) | `school_erp_test_a2` | `students.php`, `staff.php` |
| Subscriptions (platform) | `2026_11_03_` | platform: `10-subscriptions.php` / Platform | `school_erp_test_b` | platform `subscriptions.php` |
| Attendance | `2026_11_04_` | `30-attendance.php` / Academics (200) | `school_erp_test_c` | `attendance.php` |
| Fees | `2026_11_05_` | `40-fees.php` / Finance (300) | `school_erp_test_d` | `fees.php` |
| Exams & results | `2026_11_06_` | `35-exams.php` / Academics (200) | `school_erp_test_e` | `exams.php` |
| Homework & notices | `2026_11_07_` | `38-comms.php` / Academics (200) | `school_erp_test_f` | `comms.php` |
| Promotion / rollover | `2026_11_08_` | `50-promotion.php` / School (500) | `school_erp_test_g` | `promotion.php` |
| Parent portal | `2026_11_09_` | portal: `10-portal.php` | `school_erp_test_h` | parent `portal.php` |
| Excel import | `2026_11_10_` | `70-imports.php` / School (500) | `school_erp_test_i` | `imports.php` |
| Reports, salary, certificates | `2026_11_11_` | `60-reports.php` / Reports (400), `45-salary.php` / Finance, `80-certificates.php` / People | `school_erp_test_j` | `reports.php`, … |
| QA / security / ops | `2026_11_12_` | – | `school_erp_test_k` | – |

Menu groups and orders (use the same `order` for a group everywhere): Overview 0 · People 100 · Academics 200 · Finance 300 · Reports 400 · School 500 · Administration 900.
Icons are heroicon outline names (`academic-cap`, `users`, `calendar-days`, `banknotes`, `clipboard-document-check`, …). A menu item whose route does not exist or whose `can` fails is hidden automatically.

### Shared files — do not edit (ask the lead in your report instead)
`bootstrap/*`, `config/{app,auth,database,filesystems,fortify,documents,roles}.php`, `routes/{web,school,platform,parent,dev}.php`, `app/Support/**`, `app/Providers/{App,Fortify}ServiceProvider.php`, `app/Http/Middleware/**`, `app/Contracts/**`, core models/enums/factories/migrations (anything under `database/migrations/0001*`, `2026_10_30*`, `2026_10_31*`), `app/Models/{User,Platform,Identity,Audit,Documents}`, `tests/TestCase.php`, `tests/Concerns/**`, `phpunit.xml`, `composer.*`, `package.json`, `vite.config.js`, `tailwind.config.js`, `resources/scss/{app.scss,abstracts,base,components,layouts}`, `resources/views/components/**`, `resources/js/{app.js,core,components}`, `CLAUDE.md`, `plan.md`, `docs/{MODULE_GUIDE,UI_GUIDE}.md`.

## 4. Tenancy cookbook (the release gate)

* **Model**: `use BelongsToSchool;` → global scope (fails closed without a school), `school_id` auto-filled, immutable, never mass-assignable. Route-model binding is therefore school-scoped: another school's id is a 404.
* **Migration**: `$table->id(); $table->tenant();` then `$table->tenantForeign('student_id', 'students');` (add `nullable: true` for optional references). That creates composite foreign keys `(school_id, x_id) → x(school_id, id)`. Business-unique keys always include `school_id` (`$table->unique(['school_id', 'code'])`).
* **Validation**: `TenantRule::exists('sections')`, `TenantRule::unique('students', 'admission_no')->ignore($student)`. A bare `exists:` accepts other schools' ids.
* **Queries**: just use the model. Raw `DB::table()` must add `where('school_id', app(TenantContext::class)->requireId())`.
* **Aggregates / reports**: use model queries (scoped) or add the school filter explicitly.
* **Jobs**: extend `TenantAwareJob` (re-checks membership + `requiredPermission()` when it runs). **Cache**: `TenantCache::remember()` only. **Files**: `PrivateFileService` only; serve through a controller + policy. Never `Storage::url()`.
* **Tests**: every module ships isolation tests with `AssertsTenantIsolation::assertUnreachableFromOtherSchool([...])` for each of its show/edit/update/delete routes, plus overlapping identifiers across two schools.
* Never write `withoutTenant()` in school-facing code. Platform code (Super Admin) and the few documented identity bootstraps are the only users.

## 5. Authorisation cookbook

* Permission = `"<module>.<action>"` from `config/permissions/<module>.php` (e.g. `fees.payments.post`). Every one is a Gate ability: `$user->can('fees.payments.post')`, `@can(...)`, `->middleware('can:fees.payments.post')`.
* Three layers on every write: **route** `can:` middleware → **FormRequest** `authorize()` (`$this->user()->can('create', Student::class)`) → **policy** for record rules (`TenantPolicy` already checks the record belongs to the school).
* **Teacher scoping**: plain `…view`/`…mark`/`…enter` permissions reach only the teacher's assigned sections; the `…_all` sibling or School Admin reaches everything. Use `app(AssignmentScope::class)->sectionIds($user, 'attendance.students.view_all')` (null = unrestricted) and `canAccessSection()` / `subjectIds()`.
* **Parents** have no staff permissions. Their access is decided per request from `student_guardians` (`status = active` AND `verified_at` not null — `StudentGuardian::scopeGrantingAccess()`), only in the `parent.*` area.
* School Admin holds every permission automatically, including ones you add later. Defaults for other roles come from the `'defaults'` map in your permissions file; `php artisan erp:sync-permissions` grants new defaults to existing schools.

## 6. Services cookbook

```php
public function post(array $data, User $actor): Payment
{
    return DB::transaction(function () use ($data, $actor) {
        $lock = Sequence::query()->lockForUpdate()->firstOrCreate([...]);   // counters/balances: lock the row
        ...
        $this->audit->record('payment.posted', $payment, new: [...], actor: $actor);
        return $payment;
    });
}
```
* Wrap multi-row changes in `DB::transaction`. Use `lockForUpdate()` for counters, balances, sequences, "only one X" rules.
* **Idempotency** for anything a double-click/retry could repeat: unique key `(school_id, idempotency_key)`; on duplicate return the existing record. `<x-form.idempotency />` renders the key in forms.
* **Audit**: `AuditLogger::record('noun.verb', $subject, $old, $new, $meta, $actor)`. `diff($before, $after)` computes changed keys. Audit what the master plan names: attendance, fees, marks, permissions, subscriptions, publishing, rollover.
* Domain errors → `ValidationException::withMessages(['field' => 'Human message'])`.
* Status changes and corrections, not deletes (CLAUDE.md rule 5).

## 7. Money, dates, enums, settings

* Money columns are `unsignedBigInteger`/`bigInteger` **paise**, named `*_paise`. Parse user input with `Money::parse()`, display with `Money::format()` / `@money($paise)`, form value `Money::plain()`.
* A "school day" is `school_today()` (display timezone Asia/Kolkata); timestamps are UTC. Never compare `now()` to a `date` column without converting.
* Enums: string-backed, `label()`, `tone()` (one of neutral|brand|success|warning|danger|info) and an `options()` static for selects. Cast them on the model.
* Settings: `$school->setting('attendance.leave_counts_as')` (per-school override, falls back to `config/school_defaults/attendance.php`). `$school->hasFeature('salary')` for optional modules.

## 8. Strict mode and tests

Outside production `Model::shouldBeStrict()` is on: **lazy loading throws** (eager load: `->with([...])`), accessing a missing attribute throws, mass-assigning an unlisted attribute throws. Fix the code, never switch strict mode off.

Test base: `Tests\TestCase` (MySQL, `RefreshDatabase`, no Vite). Helpers in `Tests\Concerns\InteractsWithTenancy`:
`makeSchool()` · `makeMember($school, 'teacher'|[roles])` · `makePlatformAdmin()` · `signInTo($school, $user)` · `inSchool($school, fn)` · `asMember($school, $user, fn)` (so `$user->can()` works) · `actAsTenant($school)` · `currentYear($school)`.
Tenant models **fail closed without a school**: build and read data inside `inSchool()`; assert across schools with `assertDatabaseHas()`.

Every module must test:
1. happy path through HTTP (status, redirect, DB state, flash) and through the service (unit-level rules);
2. validation failures (required, bad ids, boundaries);
3. **each role**: who is allowed (200) and who is not (403) — at least School Admin, one authorised role, one unauthorised role, and a teacher outside their assigned section where it applies;
4. **tenant isolation**: second school with the same identifiers; `assertUnreachableFromOtherSchool`; cross-school ids in request bodies are rejected;
5. audit events for sensitive actions; immutability of posted records; idempotency/concurrency-style double submits where money or counters are involved;
6. empty states and pagination render (page returns 200 and shows expected text).

Run your own tests with **your own database**: `DB_DATABASE=school_erp_test_<id> php artisan test tests/Feature/<Module>`; style: `vendor/bin/pint --dirty`. Do not run the whole suite concurrently with others on a shared database.
Validate SCSS you add without touching `public/build`: `npx vite build --outDir "$TEMP/vite-<module>" --emptyOutDir` (the lead builds the real assets).

## 9. UI essentials (details in `docs/UI_GUIDE.md`)

Pages use `<x-layouts.app :title="…">` → `<x-ui.page-header>` → filters → `<x-ui.table>` / cards → `{{ $paginator->links() }}`; forms use `<x-form.form>` + `<x-form.input>` etc.; destructive actions use `<x-ui.confirm>`. Use token classes (`bg-surface text-ink border-line`), never raw greys/whites, so dark mode works. Flash with `->with('status', '…')` (also `error`, `warning`, `info`). Any submit button posting money/counters sits in a `<x-form.form>` (double-submit lock) and carries `<x-form.idempotency />`.
Printable documents (receipts, report cards, ID cards, certificates) use `<x-layouts.print paper="a4|a5|a6|card">` and keep module print CSS in `resources/scss/modules/_<module>.scss`.

## 10. Definition of done and report

Done = implemented per the master-plan requirement IDs you were given; server-side authorisation and validation; tenant isolation proven by tests; tests for success + failure + roles; usable empty/error states; migrations run from empty (`php artisan migrate:fresh` on your test DB); `pint` clean; no shared file edited.
Your final report (concise): what you built (routes/screens), requirement IDs covered / not covered, **assumptions that need product-owner or accountant sign-off**, migrations added, permissions added, extension requests for the lead, exact test command + real result counts, known limitations. Do not claim anything you did not run.
