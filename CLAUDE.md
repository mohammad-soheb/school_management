# School ERP — project rules

Multi-school ERP for small schools (Khajrana / Indore). Laravel 13, PHP 8.4, MySQL 8.4, Blade + Tailwind CSS 3 + SCSS + Alpine.js.
Specification: `SCHOOL_ERP_V1_1_MASTER_PLAN.md`. Build plan and phases: `plan.md`. **How to build a module: `docs/MODULE_GUIDE.md` (read it first).** UI components: `docs/UI_GUIDE.md`.

## Non-negotiable rules

1. **Request flow** — route → middleware → form request → controller → service → model (plan.md §2).
   Controllers are thin (one service call, return a view/redirect). Business rules, transactions, locking and audit logging live in `app/Services`. Models hold relations, casts and scopes only.
2. **Tenant isolation is a release gate.** Every tenant table has `school_id`; every tenant model uses `BelongsToSchool`.
   Never trust a `school_id` from a browser. Never call `TenantContext::withoutTenant()` in school-facing code. Validate related ids with `TenantRule::exists/unique`. Migrations use `$table->tenant()` and `$table->tenantForeign()`.
   Never remove a tenant check or a tenant test to make something pass.
3. **Authorise on the server.** Route groups declare `->middleware('can:<module>.<permission>')`; Form Requests call `authorize()`; record-level rules live in a policy extending `TenantPolicy`. Hidden buttons are not security.
4. **Money is integer paise** (`App\Support\Money\Money`), never floats. Dates that mean "a school day" use `school_today()`. Storage is UTC.
5. **Never hard-delete** students, staff, enrollments, attendance, marks, fees, payments. Change status, or correct with a reversal/adjustment row. Posted financial rows are immutable.
6. **Audit** sensitive changes with `AuditLogger::record('noun.verb', $subject, $old, $new)`. Never log passwords, tokens or document contents.
7. **Synthetic data only.** No real children's data in fixtures, screenshots, logs or prompts.
8. **Report honestly:** run the tests, quote the real counts, list failures and known limitations. Do not claim a pass you did not see.

## Commands (Windows + Git Bash)

```
source /c/Users/dell/AppData/Local/Temp/claude/c--Users-dell-Desktop-project/b3d170f2-8025-4568-a77f-c17134eebe48/scratchpad/env.sh   # PHP 8.4 + composer + mysql on PATH, cd to project
php artisan test                                  # all tests (MySQL school_erp_test)
DB_DATABASE=school_erp_test_<id> php artisan test --filter=Attendance   # parallel agents: use your own database
vendor/bin/pint --dirty                           # code style (run on files you changed)
npm run build                                     # production assets (only the lead/UI agent: writes public/build)
```

MySQL runs from `C:\Users\dell\mysql-8.4.9` on port **3307** (`erp_app` / `erp_local_dev_pw`); if it is down: start `mysqld.exe --defaults-file=C:\Users\dell\mysql-8.4.9\my.ini`.
Do **not** run `composer require/update` or edit `composer.json` / `package.json` — ask the lead.
Do **not** commit to git unless the user asks.

## Tooling quirks

- In the Bash tool, **heredocs halve backslashes** (`\\` becomes `\`). Create PHP files with the Write/Edit tools, not `cat <<EOF`.
- `composer dump-autoload` is slow with `optimize-autoloader`; it is off for development (turn on in the deploy runbook).
- Tests render Blade without a Vite build: `TestCase` calls `withoutVite()`.
