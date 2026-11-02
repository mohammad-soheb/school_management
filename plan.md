# School ERP — Implementation Plan

**Source specification:** [SCHOOL_ERP_V1_1_MASTER_PLAN.md](SCHOOL_ERP_V1_1_MASTER_PLAN.md) (V1.1, 28 Oct 2026)
**Plan date:** 29 Oct 2026
**Status:** Draft for review. No application code is written until this plan is approved (master plan §22.1).

The master plan says **what** to build. This plan says **how** it is built (architecture and code conventions) and **in what order** (phases). Where the two disagree, the master plan wins and this file is corrected.

---

## 1. Current project state

| Item | State |
|---|---|
| Framework | Laravel 13.39.0 (fresh skeleton, no app code yet) |
| PHP | 8.4.27 (winget), extensions for Laravel enabled in its `php.ini` |
| Composer | 2.10.3 at `%LOCALAPPDATA%\composer-bin` |
| Database | `.env` uses SQLite (skeleton default). XAMPP has **MariaDB 10.4** — not the target (master plan requires MySQL 8.x/InnoDB, and MariaDB 10.4 is end-of-life) |
| Version control | **Not a git repository yet** |
| Tests | PHPUnit 12 (skeleton default) |

---

## 2. Architecture

### 2.1 Request flow

Every request passes through the same six layers, in this order:

```
HTTP request
   │
   ▼
1. Route          routes/{platform,school,parent}.php
   │              URL, name, middleware stack, controller action
   ▼
2. Middleware     app/Http/Middleware
   │              authentication, MFA, active school context, subscription access, rate limits
   ▼
3. Form Request   app/Http/Requests/{Module}
   │              authorize() → policy check;  rules() → validation incl. tenant-scoped exists/unique
   ▼
4. Controller     app/Http/Controllers/{Area}/{Module}
   │              thin: call ONE service method, return view / redirect / download
   ▼
5. Service        app/Services/{Module}
   │              business rules, DB transactions, row locks, idempotency, audit events
   ▼
6. Model          app/Models/{Module}
                  Eloquent: tenant global scope, relationships, casts, no business workflows
   │
   ▼
MySQL 8 (InnoDB): foreign keys, composite tenant keys, unique constraints
```

### 2.2 Layer responsibilities

| Layer | Is responsible for | Must NOT |
|---|---|---|
| **Route** | URL shape, route names, grouping by area, attaching middleware, scoped route-model binding | contain closures with logic |
| **Middleware** | Cross-cutting checks that apply to whole groups of routes: auth, MFA, resolving the active school, subscription state, security headers | load module data or apply business rules |
| **Form Request** | Authorisation via policies (`authorize()`), input validation, normalising input, tenant-scoped `exists`/`unique` rules | write to the database or call services |
| **Controller** | Receive the validated request, call one service method, choose the response (view, redirect, PDF, JSON) | run queries other than simple reads for views, use `DB::transaction`, contain `if` business rules |
| **Service** | All business rules and workflows: transactions, locking, calculations, state transitions, audit logging, dispatching jobs | read from `Request`/session directly (receives validated arrays/DTOs + the acting `User`), render views |
| **Model** | Table mapping, relationships, casts, tenant global scope, simple query scopes | contain multi-step workflows or call other services |

Supporting pieces that sit alongside the layers:

- **Policies** (`app/Policies`) — one per model; called from Form Requests (writes) and controllers (`$this->authorize()` for reads/downloads).
- **Enums** (`app/Enums`) — backed PHP enums for statuses (student status, attendance status, payment mode, subscription state).
- **Jobs** (`app/Jobs`) — async work (imports, exports, bulk report cards); they call services, never contain business logic themselves.
- **Livewire components** — only where interactivity is clearly needed (e.g. the attendance marking grid). They follow the same rules as controllers: validate, authorise, then call a service.

### 2.3 Folder layout

```
app/
├── Enums/{Module}/                     StudentStatus, AttendanceStatus, PaymentMode …
├── Http/
│   ├── Controllers/
│   │   ├── Platform/{Module}/          Super Admin backoffice
│   │   ├── School/{Module}/            School Admin, Teacher, Accountant, Staff
│   │   └── Parent/                     Parent portal
│   ├── Middleware/                     ResolveSchoolContext, RequireTwoFactor …
│   └── Requests/{Module}/              StoreStudentRequest, PostPaymentRequest …
├── Jobs/{Module}/
├── Models/{Module}/                    Students\Student, Fees\FeePayment …
├── Policies/{Module}/
├── Services/{Module}/                  Students\StudentService, Fees\PaymentPostingService …
└── Support/
    ├── Tenancy/                        TenantContext, BelongsToSchool, SchoolScope, TenantRule, TenantAwareJob
    ├── Audit/                          AuditLogger
    └── Money/                          Money value object + cast
routes/
├── web.php                             public pages + includes below
├── platform.php
├── school.php
└── parent.php
tests/
├── Unit/{Module}/                      calculations and rules in services
├── Feature/{Module}/                   HTTP flows through all six layers
└── Feature/Tenancy/                    cross-school attack tests (release gate)
```

**Modules** (used as the `{Module}` sub-namespace in every layer, from master plan §17.1):
`Platform`, `Identity`, `SchoolSetup`, `Students`, `Staff`, `Academics`, `Attendance`, `Fees`, `Communication`, `Reports`, `Imports`, `Documents`, `Audit`. Subscriptions live under `Platform`.

### 2.4 Route areas

| Area | File | URL prefix | Middleware stack | Who |
|---|---|---|---|---|
| Guest / auth | Fortify routes | `/` | `guest`, `throttle` | everyone |
| Platform backoffice | `routes/platform.php` | `/platform` | `auth`, `two-factor`, `platform.admin` | Super Admin |
| School app | `routes/school.php` | `/app` | `auth`, `school.context`, `two-factor.role`, `subscription.access` | School Admin, Teacher, Accountant, Staff |
| Parent portal | `routes/parent.php` | `/parent` | `auth`, `school.context`, `guardian`, `subscription.access` | Parent/Guardian |

Area files are registered in `bootstrap/app.php` (`withRouting(then: …)`). The active school is **not** in the URL; it is kept in the session and re-validated against the user's memberships on every request (master plan §17.2.1).

### 2.5 Middleware

| Alias | Class | Purpose |
|---|---|---|
| `school.context` | `ResolveSchoolContext` | Reads the selected school from the session, confirms an active membership for the logged-in user, and sets `TenantContext`. No valid membership → redirect to school picker / 403. |
| `platform.admin` | `EnsurePlatformAdmin` | Only Super Admins reach `/platform`. |
| `two-factor` | `RequireTwoFactor` | Blocks access until TOTP 2FA is set up and confirmed (mandatory for Super Admin). |
| `two-factor.role` | `RequireTwoFactorForRole` | Enforces 2FA for school roles where the school/policy requires it (e.g. users who reverse payments). |
| `subscription.access` | `EnsureSubscriptionAccess` | Applies the active/grace/expired/suspended policy (read-only/export mode, renewal path). Pass-through until Phase 11. |
| `guardian` | `EnsureGuardianAccount` | Parent area only for users with a verified guardian profile in the active school. |
| (global) | `SecurityHeaders` | CSP, `X-Frame-Options`, `Referrer-Policy`, HSTS in production. |

Rate limiters (in `AppServiceProvider`): login, password reset, 2FA challenge, invitations, file downloads, exports. Keys include the school where relevant.

### 2.6 Tenant isolation — defence in depth (release gate)

Every tenant-owned table has `school_id`. Isolation is enforced at six independent levels so that one mistake does not leak data:

1. **Middleware** — `ResolveSchoolContext` is the only place that sets the active school, and only from a server-validated membership.
2. **Model** — the `BelongsToSchool` trait adds a global scope (`where school_id = current`) and fills `school_id` on create. `school_id` is never mass-assignable. If no tenant context is set outside platform/console code, the scope **fails closed** (throws), it does not return all rows.
3. **Route model binding** — because of the global scope, `/app/students/{student}` for another school's ID returns 404, not the record.
4. **Form Request** — related IDs are validated with `TenantRule::exists('sections')` / `TenantRule::unique(...)`, which add the `school_id` condition.
5. **Policy** — every policy checks `$model->school_id === TenantContext::id()` plus the role/assignment rule (teacher assigned to class, guardian linked to child).
6. **Database** — tenant tables have a unique key on `(school_id, id)`; child tables reference parents with composite foreign keys `(school_id, parent_id) → parent(school_id, id)`, so a row physically cannot point at another school's row.

Beyond HTTP requests:

- **Queued jobs** extend `TenantAwareJob`: the payload stores `school_id` + acting user id; `handle()` restores `TenantContext` and re-checks authorisation before touching data.
- **Cache keys** go through a helper that prefixes `school:{id}:`; permission/membership changes clear that school's permission cache.
- **Files** are stored on a private disk under `schools/{school_id}/…` with random names; downloads go through a controller that checks the policy, never via public URLs.
- **Exports/search** reuse the same scoped models; no raw cross-tenant queries.

```php
// app/Support/Tenancy/BelongsToSchool.php (sketch)
trait BelongsToSchool
{
    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);           // fails closed without context

        static::creating(function (Model $model) {
            $model->school_id = app(TenantContext::class)->requireId();
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
```

### 2.7 Authorisation model

- **Package:** `spatie/laravel-permission` with the **teams** feature, using `school_id` as the team key — roles and permissions are assigned per school membership.
- **Permissions** are named `module.action`, e.g. `students.view`, `students.create`, `attendance.correct`, `fees.payment.post`, `fees.payment.reverse`, `marks.publish`, `imports.run`, `exports.run`, `documents.download`.
- **Default roles** per school (seeded on school creation, editable later): School Admin, Teacher, Accountant, Staff, Parent. Super Admin is a platform role (no school team) and does not get school data access by default (§19.1).
- **Policies** combine the permission with data-level rules: teachers only for assigned class/section/subject; parents only for currently linked, verified children.
- Hidden buttons are never the control — every action is checked on the server.

### 2.8 Coding conventions

| Topic | Convention |
|---|---|
| Money | Integer **paise** in `BIGINT UNSIGNED` columns, `Money` value object + cast. No floats anywhere (FR-412). |
| Time | Stored in UTC; displayed in `Asia/Kolkata`. Attendance/fee "business dates" are `DATE` columns in school-local date. |
| Statuses | Backed PHP enums; transitions done only by service methods. |
| Identifiers | Internal `BIGINT` ids; human ids (admission no., employee id, receipt no.) unique per school via `(school_id, …)` unique keys. |
| Deleting | No hard delete of students, staff, or financial/academic records. Status changes or soft deletes; financial corrections by reversal entries. |
| Transactions | Any write touching more than one row in a business sense happens inside a service-level `DB::transaction`, with `lockForUpdate()` where counters/balances are involved. |
| Idempotency | Payment posting, imports and year rollover take an idempotency key with a unique DB constraint. |
| Audit | Services call `AuditLogger::record(event, subject, actor, changes)` for sensitive changes (attendance, fees, marks, permissions, subscriptions). |
| Validation input | Services receive `$request->validated()` arrays, or small readonly DTOs for finance; never the `Request` object. |
| Strictness | `Model::shouldBeStrict()` outside production (no lazy loading, no silent attribute discard). |
| Style | Laravel Pint; Larastan for static analysis. |
| Test data | Factories with synthetic data only — never real student data (master plan §5.10). |

### 2.9 Example: one vertical slice through all layers

```php
// 1. Route — routes/school.php
Route::post('students', [StudentController::class, 'store'])->name('students.store');

// 3. Form Request — app/Http/Requests/Students/StoreStudentRequest.php
class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Student::class);
    }

    public function rules(): array
    {
        return [
            'admission_no' => ['required', 'string', 'max:30', TenantRule::unique('students', 'admission_no')],
            'name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'section_id' => ['required', 'integer', TenantRule::exists('sections')],
        ];
    }
}

// 4. Controller — app/Http/Controllers/School/Students/StudentController.php
class StudentController extends Controller
{
    public function __construct(private StudentService $students) {}

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = $this->students->admit($request->validated(), $request->user());

        return to_route('school.students.show', $student)->with('status', 'Student admitted.');
    }
}

// 5. Service — app/Services/Students/StudentService.php
class StudentService
{
    public function __construct(private AuditLogger $audit) {}

    public function admit(array $data, User $actor): Student
    {
        return DB::transaction(function () use ($data, $actor) {
            $student = Student::create(Arr::only($data, ['admission_no', 'name', 'date_of_birth']));
            $student->enrollments()->create([
                'academic_year_id' => AcademicYear::current()->id,
                'section_id' => $data['section_id'],
                'status' => EnrollmentStatus::Active,
            ]);
            $this->audit->record('student.admitted', $student, $actor);

            return $student;
        });
    }
}

// 6. Model — app/Models/Students/Student.php
class Student extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = ['admission_no', 'name', 'date_of_birth'];   // school_id is never fillable
}
```

(Middleware, layer 2, is attached to the whole `/app` group, not per route.)

### 2.10 Technology stack (proposed — confirm Laravel 13 compatibility at install time)

| Concern | Choice | Phase |
|---|---|---|
| Framework / runtime | Laravel 13, PHP 8.4 | installed |
| Database | MySQL 8.4 LTS (local + separate test database) | 1 |
| Auth backend (login, reset, TOTP 2FA, recovery codes) | `laravel/fortify` with our own Blade views | 2 |
| Roles / permissions | `spatie/laravel-permission` (teams = `school_id`) | 2 |
| UI | Blade + Tailwind CSS + Alpine.js via Vite; Livewire only for interactive screens | 1 |
| PDF (receipts, report cards) | `barryvdh/laravel-dompdf` (or similar, decided in Phase 7) | 7 |
| Excel/CSV import/export | `openspout/openspout` (streaming, low memory) | 12 |
| Static analysis / style | Larastan, Pint | 1 |
| Queue / cache / session (dev) | database drivers; Redis evaluated for production in Phase 14 | 1 / 14 |

---

## 3. Phases at a glance

Each phase is delivered as small **vertical slices** (route → middleware → request → controller → service → model + migration + tests). A phase starts only when its blocking decisions are signed off.

| # | Phase | Master plan ref | Depends on | Blocking decisions | Exit gate |
|---|---|---|---|---|---|
| 0 | Discovery & decisions (no code) | Phase 0, §21.1 | — | — | G0 |
| 1 | Project setup & skeleton | §17.1 | plan approved | D01 | tooling green |
| 2 | Secure foundation (identity, tenancy, roles, MFA, audit, files) | Phase 1, §17.2, §19.1 | 1 | D01, MFA recovery (§14.15) | G1 + G2 |
| 3 | School setup | Phase 2 (FR-201–202) | 2 | — | setup CRUD + isolation tests |
| 4 | Students, guardians, staff, documents | Phase 2 (FR-203–210) | 3 | D07, field list (§14.8) | master-data acceptance |
| 5 | Academic lifecycle & year rollover | Phase 2B | 4 | D05 | G3 |
| 6 | Attendance | Phase 3 | 4 | attendance rule (§14.4) | attendance acceptance |
| 7 | Fees (7A design review → 7B build) | Phase 4, §17.4 | 5 | D03, D04, D05, D14 | G4 |
| 8 | Exams, marks & report cards | Phase 5 (FR-501–504, 509–511) | 5 | D06 | marks acceptance |
| 9 | Homework & notices | Phase 5 (FR-505–508) | 4 | — | communication acceptance |
| 10 | Parent portal & PWA | Phase 6 | 6, 7, 8, 9 | D07 | G5 |
| 11 | Backoffice & subscriptions | Phase 8 | 2 | D10, D12 | renewal/expiry acceptance |
| 12 | Excel import | Phase 9 | 4, 7 | import templates (§14.9) | import acceptance |
| 13 | Dashboard, reports, exports + conditional salary / TC / ID cards | Phase 7, §18.1 | 6, 7, 8 | D08 | reports acceptance |
| 14 | QA, security, performance, backup & deployment | Phase 10, §19 | all built phases | D02, D13 | G6 |
| 15 | Pilot & launch | Phases 11–12 | 14 | D09, D10, D11 | G7 |

Phase 0 runs **in parallel** with Phases 1–2: the foundation (identity, tenancy, audit) does not depend on school interviews, but module schemas from Phase 3 onward do.

---

## 4. Phase details

### Phase 0 — Discovery & decisions (product owner; no code)

- Interview 3–5 target schools; collect **anonymised** samples: student spreadsheet, fee register and receipts, attendance sheet, report card, promotion procedure (§21.1).
- Fill in the decision register (section 5 below) with signed-off answers.
- Rank P1/P2 features (homework/notices, TC, ID cards, salary) with pilot input (§18.4).
- **Exit (G0):** interviews recorded, templates collected, decisions D03–D14 answered or explicitly deferred.

### Phase 1 — Project setup & skeleton

**Goal:** a clean, tested, version-controlled skeleton that enforces the layer structure.

- `git init`, review `.gitignore`, first commit of the skeleton.
- Install MySQL 8.4 LTS locally; create `school_erp` and `school_erp_test`; point `.env` and `phpunit.xml` at MySQL (SQLite does not behave like MySQL for composite FKs and row locking, so tests must run on MySQL).
- Config: `APP_NAME`, timezone `UTC` + display timezone `Asia/Kolkata`, secure session/cookie settings, `Password::defaults()`, `Model::shouldBeStrict()`.
- Install front-end tooling (Tailwind, Alpine via Vite) and base Blade layouts: guest layout, school app layout (with school switcher), platform layout, parent mobile layout; flash messages; 403/404/419/500 pages.
- Create route files (`platform.php`, `school.php`, `parent.php`) and register them in `bootstrap/app.php`.
- Create the folder skeleton and `app/Support` base classes (empty `TenantContext`, `AuditLogger` interfaces filled in Phase 2).
- Dev tooling: Pint config, Larastan, `composer test` script.
- Replace the skeleton `CLAUDE.md` with project rules (layer rules from §2.2, tenant rules from §2.6, test-on-MySQL, no real child data).

**Exit:** migrations run on an empty MySQL DB; `php artisan test`, Pint and Larastan pass; layouts render.

### Phase 2 — Secure foundation (FR-101 – FR-110, §17.2, §19.1)

**Goal:** everything later modules reuse — identity, school context, permissions, MFA, audit, private files — plus the cross-tenant attack test suite.

| Layer | Items |
|---|---|
| Migrations / Models | `schools`, `users` (extend), `school_memberships` (user ↔ school, type, status), spatie permission tables with `school_id` team key, `invitations`, `audit_events`, `stored_files` (document metadata), `two_factor` columns (Fortify) |
| Middleware | `ResolveSchoolContext`, `EnsurePlatformAdmin`, `RequireTwoFactor`, `RequireTwoFactorForRole`, `SecurityHeaders`, rate limiters |
| Form Requests | `StoreSchoolRequest`, `InviteMemberRequest`, `AcceptInvitationRequest`, `SwitchSchoolRequest`, `UpdateMemberRolesRequest` |
| Controllers | `Platform\Schools\SchoolController` (index/create/store/show), `School\Identity\SwitchSchoolController`, `School\Identity\MemberController`, `School\Identity\InvitationController`, `School\DashboardController` (placeholder), Fortify views (login, reset, 2FA setup/challenge, recovery codes) |
| Services | `Platform\SchoolRegistryService` (create school + seed default roles + first admin invite), `Identity\MembershipService`, `Identity\InvitationService`, `Identity\SchoolContextService`, `Identity\RoleService`, `Support\Audit\AuditLogger`, `Documents\PrivateFileService` |
| Support | `TenantContext`, `BelongsToSchool`, `SchoolScope` (fail-closed), `TenantRule`, `TenantAwareJob`, tenant cache-key helper |

**Tests (release gate — must stay green for the rest of the project):**
- Two schools with **overlapping** human identifiers; a School A user tries School B records via direct ID, route binding, nested relation, mass assignment of `school_id`, `exists` rule, export filter, file download, queued job, cache key → all denied with no data disclosed.
- Super Admin cannot use `/platform` without confirmed 2FA; recovery codes single-use.
- Unauthenticated access to every protected route group is denied.
- Login/reset rate limits; session invalidated on password/role change.
- Audit event written for membership/role changes.

**Exit (G1 + G2):** architecture record signed off, tenant test suite green, 2FA working, private storage and audit working.

### Phase 3 — School setup (FR-201 – FR-202)

- **Models:** `academic_years` (one current per school), `school_classes` (`classes` is avoided as a table name), `sections`, `subjects`, `class_subjects`, `school_holidays` / working-day settings, school profile fields + logo (private file).
- **Services:** `SchoolProfileService`, `AcademicYearService` (set current year, lock state used later), `ClassStructureService`.
- **Controllers / Requests:** CRUD per entity under `School\SchoolSetup\*`.
- **Tests:** CRUD, uniqueness per school, "one current year" rule, isolation tests for every entity.

### Phase 4 — Students, guardians, staff, documents (FR-203 – FR-210, FR-2B01)

- **Models:** `students` (permanent identity), `student_enrollments` (year, class, section, roll no., status), `guardians`, `student_guardians` (relationship, verified_at, active), `staff_profiles`, `teacher_assignments` (staff ↔ year/class/section/subject), student/staff documents via `stored_files`.
- **Services:** `StudentService` (admit, update, change status — never delete), `GuardianService` (link/verify/unlink, invite parent login), `StaffService`, `TeacherAssignmentService`, `DocumentService` (type/size allow-list, MIME check, private storage).
- **Controllers:** students, guardians, staff, assignments, document upload/download (download always via policy).
- **Tests:** duplicate admission/employee numbers blocked per school but allowed across schools; history kept when inactive; teacher assignment feeds later policies; documents not reachable without authorisation; search/pagination.
- **Blocked by:** D07 (how guardians are verified), §14.8 (exact permitted fields).

### Phase 5 — Academic lifecycle & year rollover (Phase 2B)

- **Models:** `promotion_runs` (idempotency key, status, source/target year), `promotion_run_items` (student, outcome: promote/retain/transfer/withdraw/graduate, target class/section), `enrollment_changes`.
- **Services:** `PromotionService` — `preview()` (no writes), `commit()` (one transaction, locks the run row, unique key prevents duplicate enrollments, re-run returns the existing result); `AcademicYearLockService` (lock/reopen by authorised role, audited); `WithdrawalService` (transfer/withdrawal with history).
- **Tests:** mixed cohort produces correct enrollments; previous-year data unchanged; retry and two concurrent commits do not duplicate anything.
- Dues carry-forward (FR-2B06) is added in Phase 7 as an extension of `PromotionService`, because it needs the fee ledger.
- **Blocked by:** D05.

### Phase 6 — Attendance (FR-301 – FR-307)

- **Models:** `attendance_sheets` (school, date, section, session, submitted_by/at; unique per section+date+session), `student_attendance` (unique per sheet+student), `staff_attendance`, `attendance_corrections`.
- **Services:** `AttendanceService` (open sheet with roster from enrollments, "mark all present", submit; already-submitted sheet warns instead of duplicating), `AttendanceCorrectionService` (authorised, reason, audit), `AttendanceReportService` (documented percentage formula).
- **UI:** Livewire marking grid for teachers (large touch targets, mobile-first); safe retry on double submit.
- **Tests:** percentage calculation against hand-checked cases incl. leave/half-day/holidays; teacher restricted to assigned sections; corrections audited; p95 load target noted for Phase 14.
- **Blocked by:** attendance rule for holidays/leave/half-days (§14.4).

### Phase 7 — Fees (FR-401 – FR-416, §17.4)

**7A — Fee ledger design review (no code, master plan §22.5).** Produce the fee state machine, schema, allocation order, receipt numbering, daily close rules, 20 numerical reconciliation cases and 10 hostile/concurrent cases. **The school accountant approves before 7B starts.**

**7B — Build:**
- **Models:** `fee_heads`, `fee_plans` / `fee_plan_lines`, `student_charges` + `charge_lines`, `fee_payments` (idempotency key unique), `payment_allocations`, `fee_adjustments` (discounts, fines, reversals, with reason/approver), `receipt_sequences`, `opening_balances`, `cashier_closes`.
- **Services:**
  - `FeePlanService` — set up heads and plans per year/class.
  - `ChargePostingService` — post charges to enrolled students; posted lines are immutable.
  - `PaymentPostingService` — one transaction: lock the receipt sequence row, create the payment, allocations, receipt number and audit event; the same idempotency key returns the existing receipt.
  - `AllocationService` — the approved allocation order; advance payments held as credit.
  - `ReversalService` — permissioned reversal/adjustment, never edit or delete a posted record.
  - `CashierCloseService` — daily close by user and payment mode, counted vs ledger, reviewer sign-off.
  - `ReceiptService` — printable/PDF receipt.
  - Extend `PromotionService` with dues carry-forward as linked opening entries.
- **Tests:** the 20 reconciliation cases; double-click, retry and parallel posting → exactly one payment; receipt numbers unique under concurrency; daily close equals ledger after reversals; parent cannot change anything.
- **Blocked by:** D03, D04, D05, D14.

### Phase 8 — Exams, marks & report cards (FR-501 – FR-504, FR-509 – FR-511)

- **Models:** `exams`, `exam_subjects`, `exam_components`, `grading_schemes` / `grade_bands`, `marks` (absent / not-applicable flags), `result_publications` (publish/unpublish/lock history), `report_card_templates` (small fixed catalogue with configurable fields).
- **Services:** `ExamService`, `MarksEntryService` (only assigned teachers, locked after publish), `ResultCalculationService` (pure, unit-tested: totals, percentage, grade, rounding — never guess a denominator), `ResultPublicationService`, `ReportCardService` (PDF, queued for bulk).
- **Tests:** a hand-checked report for every pilot grading variant; unassigned teacher blocked; edits after lock blocked.
- **Blocked by:** D06.

### Phase 9 — Homework & notices (FR-505 – FR-508)

- **Models:** `homework` (+ private attachment), `notices`, `notice_audiences` (school-wide, classes, roles).
- **Services:** `HomeworkService`, `NoticeService` (audience resolution reused by parent portal).
- **Tests:** audience targeting; attachments private; creator/editor recorded.

### Phase 10 — Parent portal & PWA (FR-601 – FR-608)

- **Middleware:** `EnsureGuardianAccount`; per-request child check in `GuardianStudentPolicy` (current, verified link only).
- **Controllers:** `Parent\DashboardController`, `Parent\ChildSwitchController`, child attendance, fees/receipts, homework, notices, published results.
- **Services:** `ParentPortalService` (read-only aggregates for one child, with "last updated" timestamps).
- **PWA:** web manifest, icons, a minimal service worker for the app shell only (no offline data promise).
- **Tests:** Parent A cannot open Parent B's child by changing the ID; multi-child switching; no write routes in the parent area; mobile viewport checks.
- **Blocked by:** D07.

### Phase 11 — Backoffice & subscriptions (FR-801 – FR-809)

- **Models:** `subscription_plans`, `school_subscriptions`, `subscription_payments`, `renewal_events`.
- **Services:** `SubscriptionService` (status calculation: Active / Expiring Soon / Grace / Expired / Suspended), `RenewalService` (record payment + extend in one transaction, audited), `SchoolSuspensionService`.
- **Middleware:** `EnsureSubscriptionAccess` now applies the approved expiry policy (read/export and renewal paths stay open; no data deleted).
- **Controllers:** `Platform\Schools\*`, `Platform\Subscriptions\*`, expiry dashboard.
- **Tests:** date/status rules as unit tests; renewal extends the right school; school admins cannot reach any platform route.
- **Blocked by:** D10, D12.

### Phase 12 — Excel import (FR-901 – FR-908)

- **Models:** `import_jobs` (file hash, type, status, counts, actor), `import_rows` (raw data, errors, result).
- **Services / Jobs:** `ImportTemplateService` (downloadable templates), `ImportParseJob` (stream, validate each row with the same rules as the Form Requests), `ImportPreviewService`, `ImportCommitJob` (idempotent on file hash + row key; uses the existing domain services — e.g. `StudentService::admit()` — so imported data obeys the same rules).
- **Safety:** file type/size limits, CSV/Excel formula-injection protection on both import and export.
- **Tests:** valid file; missing columns; bad dates; duplicates; invalid class/section; partial failure; retry creates no duplicates; import lands only in the active school.
- **Blocked by:** templates and duplicate rules (§14.9).

### Phase 13 — Dashboard, reports, exports + conditional modules (FR-704 – FR-706, §18.1)

- **Services:** `DashboardService` (tenant-keyed cached aggregates), `ReportService` per area (collections, defaulters, attendance, results), `ExportJob` (queued, permission-checked, private download link).
- **Conditional (decided by D08):** basic salary (FR-701 – FR-703, kept in its own module so it can be deferred), transfer/leaving certificate, student ID card templates.
- **Tests:** report totals reconcile with ledger/attendance tests; exports contain only the active school's data and respect permissions.

### Phase 14 — QA, security, performance, backup & deployment (FR-1001 – FR-1013, §19)

- Security: dependency audit (`composer audit`, `npm audit`), static checks, upload/XSS/CSRF/injection tests, review of every policy against the permission matrix, external security review.
- Performance: seed realistic data (multiple schools, 30–60 students per section); load tests for morning attendance, month-end collections, results publication, imports/exports; record p50/p95, query counts, error rates against §19.5 targets.
- Operations: staging and production environments with separate credentials/storage; HTTPS; encrypted daily backups + binlog/PITR, off-server copy; **actual restore drill** into staging with checks of fee totals, student counts, files and permissions; monitoring, error tracking, cost alerts; deployment and rollback runbook; incident playbook.
- **Blocked by:** D02 (hosting/budget), D13 (recovery targets).
- **Exit (G6):** load tests recorded, restore demonstrated, no open high/critical vulnerabilities.

### Phase 15 — Pilot & launch (master plan Phases 11–12)

- Onboard 2–3 pilot schools with the import tools; train admins and teachers; track defects, support minutes and weekly pilot metrics (§21.3).
- Fix critical defects; move non-critical requests to the backlog.
- Legal/privacy documents reviewed by counsel (D11); price sheet finalised (D10, D09).
- **Exit (G7):** pilots signed off; zero open critical auth/tenant/finance/data-loss defects; launch decision recorded.

---

## 5. Decision register status

| ID | Decision | Blocks phase | Status |
|---|---|---|---|
| D01 | Laravel/PHP/Livewire versions | 1, 2 | **Proposed:** Laravel 13 + PHP 8.4 (installed), Blade + Livewire selectively — needs sign-off |
| D02 | Hosting / database / backup budget | 14 | Open |
| D03 | Fee allocation and reversal | 7 | Open |
| D04 | Receipt numbering and voids | 7 | Open |
| D05 | Annual rollover and dues carry-forward | 5, 7 | Open |
| D06 | Report card grading / templates | 8 | Open |
| D07 | Parent verification / MFA | 4, 10 | Open |
| D08 | Salary vs certificates / ID cards | 13 | Open |
| D09 | Support and migration inclusions | 15 | Open |
| D10 | Student limits and price | 11, 15 | Open |
| D11 | Retention / export / deletion / child privacy | 15 (design input from 2) | Open — legal review |
| D12 | Grace / expired access policy | 11 | Open |
| D13 | Recovery targets (RPO ≤1h, RTO ≤4h) | 14 | Open |
| D14 | Cash close and payment modes | 7 | Open |
| — | Local database: install MySQL 8.4 (instead of XAMPP MariaDB 10.4) | 1 | **Proposed** |

---

## 6. Working protocol for every slice

1. Read the relevant master plan section and existing code.
2. List the files per layer (route, middleware, request, controller, service, model/migration, policy, tests) before writing code.
3. Build the slice through all layers, including cross-school and wrong-role test cases.
4. Run `php artisan test`, Pint and Larastan; report the real results, including failures.
5. Report migrations, new config/env values, manual steps, assumptions and known limitations.
6. Never remove a tenant check or test to make a test pass; never trust a browser-supplied `school_id`.
7. Where a financial, privacy or year-end rule is not decided, stop that part and ask — don't invent a default.

**Definition of done** (master plan §12): requirement implemented; server-side validation and permission checks; tenant isolation verified; automated tests for success and failure cases; usable empty/error states; migrations documented; known limitations written down; no open critical security, data-integrity or data-loss defect.

---

## 7. Next steps

1. Review and approve this plan (especially §2 architecture and the D01 / MySQL proposals).
2. Start Phase 1 (project setup & skeleton).
3. In parallel, the product owner starts Phase 0 school interviews so Phases 3+ are not blocked.
