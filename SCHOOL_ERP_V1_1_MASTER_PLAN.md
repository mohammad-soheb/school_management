---
title: "Multi-School ERP V1.1 — Development-Ready Master Plan"
subtitle: "Consolidated Requirements, Architecture, Security, Commercial Model & Implementation Gates"
date: "28 October 2026"
---

**Product:** Multi-school ERP for small schools in Khajrana and greater Indore, Madhya Pradesh  
**Version:** 1.1  
**Status:** Proposed development baseline — conditional approval required; not permission to code unresolved rules  
**Audience:** Product owner, developer, QA tester, pilot schools and implementation/support team

> **Purpose of this document:** This is the consolidated V1.1 master plan, based on the original 28 October 2026 V1.0 specification and a technical/commercial risk review. V1.1 supersedes V1.0 where requirements differ. This is the working specification for building V1. It records the product decisions agreed so far, defines phase-wise requirements and acceptance criteria, and gives Claude Code/developers a controlled implementation sequence. Anything explicitly marked “confirm” is a decision to validate before the affected code is finalised.

## Document map

1. Executive summary
2. Product goals and success measures
3. Scope
4. Users and access roles
5. Business rules and assumptions
6. Phase plan at a glance
7. Detailed phase requirements
8. Proposed data model
9. Non-functional requirements
10. Key end-to-end workflows
11. Testing strategy
12. Definition of done
13. Risks and mitigations
14. Decisions to confirm before coding
15. Claude Code implementation protocol
16. Approval checklist
17. V1.1 additions: architecture and isolation contract
18. V1.1 additions: domain rules and missing workflows
19. V1.1 additions: security, privacy, resilience and performance
20. V1.1 additions: pricing, unit economics and support
21. V1.1 additions: release priorities, metrics and decision register
22. V1.1 development kickoff prompts

## 1. Executive summary

Build a simple, secure, mobile-friendly, multi-tenant School ERP for small schools around Khajrana/Indore. The initial commercial target is approximately **approximately ₹999/month as an entry-price hypothesis, with tiered paid plans**, with final pricing and student limits to be validated with real schools.

The product should reduce manual work in student records, staff records, attendance, fees, salary, exams/results, homework and parent communication. The owner will operate a separate platform backoffice to manage customer schools and manually renew subscriptions. The initial product will offer standard setup assistance, teacher training and migration of agreed existing spreadsheet data as a **bounded onboarding package**. Complex data cleaning, extra visits and custom work are charged separately or explicitly approved as promotions.

### Baseline architecture

- **Application:** A supported Laravel release on a supported PHP runtime; proposed implementation baseline is Laravel + PHP 8.4, verified against official support matrix when development begins.
- **Database:** MySQL 8.x/InnoDB (exact provider/version confirmed before coding); use transactions, foreign keys, composite indexes and tenant-aware referential integrity.
- **Tenancy model:** One modular-monolith application and one shared database; tenant-owned rows are scoped by `school_id`. Tenant-aware access control extends to queues, caches, documents, exports, search and background jobs.
- **User experience:** Responsive server-rendered application (Blade/Livewire/Alpine candidate) installable as a PWA where practical. No separate SPA or native Android/iOS app in V1.
- **Hosting:** Cloud hosting; AWS is a candidate, but its free credits are not a permanent production-cost assumption.
- **Payments:** No online payment gateway in V1. School fee payments and ERP subscription payments are recorded manually.

The architecture and version choices above are a proposed baseline; final framework/UI-library/hosting selections must be recorded before Phase 1 implementation.

## 2. Product goals and success measures

### 2.1 Product goals

1. Let a small school manage essential daily administration from one system.
2. Give teachers a low-friction way to record attendance, homework and marks.
3. Let parents view only the information for their linked child or children.
4. Let the product owner onboard and manage multiple schools from one backoffice.
5. Make school onboarding practical through spreadsheet import, configuration and hands-on training.
6. Keep initial running costs and operational complexity low without compromising student-data isolation, backups or access controls.

### 2.2 Initial success measures

Use these as pilot targets, not guaranteed outcomes:

- 2–3 pilot schools complete onboarding and use the system for real workflows.
- Each pilot can manage its agreed student, staff, attendance and fee workflows without maintaining a parallel system for every task.
- A school admin can complete common tasks without developer intervention after training.
- Parent accounts can access linked-child data and cannot access unrelated children.
- Cross-school isolation tests pass for all tenant-owned modules.
- The team can import agreed Excel templates and produce a clear error report for invalid rows.
- Backup restoration is tested before production launch.
- Pilot feedback, critical defects and support requests are recorded and reviewed before broader sales.

## 3. Scope

### 3.1 Included in V1

1. School profile, academic year, classes, sections, subjects and school calendar basics.
2. Student admissions, profiles, guardian details, documents and student status/history.
3. Staff profiles and assignments.
4. Student and staff attendance.
5. Fee setup, student dues, manual payments, receipts and collection/defaulter reports.
6. Basic salary management and salary payment records.
7. Exams, marks, grade/percentage calculation and report cards.
8. Homework/assignments and notices/circulars.
9. Parent portal with child profile, attendance, fee information, homework, notices and results.
10. Dashboard and operational reports.
11. Super Admin backoffice and school subscription tracking/renewal.
12. Controlled Excel import for agreed student, staff and initial fee data templates.
13. Responsive web UX/PWA readiness, permissions, audit trail, tested recovery and production operations.
14. **Academic year close, promotion/retention, transfer/withdrawal and rollover with historical preservation.**
15. **Daily collection close/reconciliation and finance control reports.**
16. Basic transfer/leaving certificate and student ID card templates, **only if validated as priority by pilot schools** (otherwise V1.1).

### 3.2 Explicitly out of scope for V1

- Live GPS bus tracking, driver app, geofencing and ETA.
- Transport/fleet management beyond any basic school profile field that may be needed later.
- Online fee or subscription payment gateway, UPI AutoPay and automatic bank reconciliation.
- Paid SMS, WhatsApp automation, IVR and bulk messaging integrations.
- Biometric integration.
- Native Android/iOS applications.
- Library, inventory, hostel, canteen or asset management.
- Automatic timetable generation or teacher substitution engine.
- Live classes/video conferencing and online examination proctoring.
- Advanced statutory payroll calculations such as PF/ESI/TDS unless separately approved.
- AI-based question generation, grading or predictive analytics.
- School-specific custom code forks. Differences should be handled by configuration where possible.

Anything added to V1 after this baseline should be captured as a change request with its cost, risk and schedule impact.

## 4. Users and access roles

Roles are starting defaults. Permissions must be configurable at module/action level where appropriate; avoid relying on hidden buttons as a security control.

### 4.1 Super Admin (platform owner)

Can view/manage customer schools, platform-level users, subscription plans/status, renewal records, school activation/suspension, support records and platform audit information. Super Admin access **must require MFA/2FA**, with secure recovery and break-glass procedure. School Admin and Accountant MFA is strongly recommended and should be supported if feasible.

### 4.2 School Admin

Can manage only their school’s configuration, students, guardians, staff, classes/sections, attendance, fees, salary (if permitted), exams/results, homework, notices and reports. Cannot access another school’s data or platform-level administration.

### 4.3 Teacher

Can access assigned classes/subjects and permitted workflows: student roster, attendance, homework, marks entry and notices. Cannot access unrestricted financial data or other unassigned classes unless granted permission.

### 4.4 Accountant

Can access fee structures, collections, receipts, dues and authorised financial reports. Salary access is optional and must be separately permissioned.

### 4.5 Staff

Can access only assigned/authorised features. Default access should be minimal.

### 4.6 Parent/Guardian

Can access only child/children linked and verified by the school. Can view permitted academic and financial information. Parent cannot edit marks, attendance, fee ledger or school-owned records.

### 4.7 User identity and school membership

A user account and its school membership/role should be modelled explicitly. A parent may have multiple linked children within a school. The design may allow one login to have more than one school membership, but every request must select and validate an authorised school context; this must not weaken isolation.

## 5. Business rules and assumptions

1. One shared MySQL database serves multiple schools. School-owned records carry `school_id`.
2. Tenant context is derived from authenticated membership/server-side authorisation, not trusted from arbitrary browser input.
3. A student may have multiple guardians; a guardian may have multiple children.
4. Attendance, fee and marks changes must record who changed them and when. Financial records should be reversed/adjusted rather than silently erased.
5. School fee payments are entered manually in V1 with a payment mode such as Cash, UPI, Bank Transfer or Other. Record reference/date/received-by where applicable.
6. ERP subscription payment is separate from the school’s student-fee ledger. The platform owner manually marks the subscription payment received and renews access.
7. Subscription status should support Active, Expiring Soon, Grace Period, Expired and Suspended. Exact grace-period length is configurable/confirmed before launch.
8. The initial entry-price hypothesis is approximately ₹999/month; final tiers, taxes, annual discount, student caps, onboarding charges and included support must be validated before sales launch.
9. WhatsApp/SMS, maps and third-party integrations may incur usage charges; do not promise that every future service will remain free.
10. Use synthetic or properly anonymised student data during development and testing. Do not put real children’s personal data into AI prompts or development fixtures.
11. **A subscription tier never bypasses security and privacy controls.** School access and exports must follow the signed expiry/retention policy.
12. **Pricing is a hypothesis:** proposed Starter / Standard / Growth student bands and one-time onboarding are not customer-approved. School interviews must validate willingness to pay.
13. The school/customer agreement must clarify data ownership, permitted processing, authorised users, support boundaries, retention/export and responsibilities for child/guardian information. Obtain appropriate legal/privacy review before production use.

## 6. Phase plan at a glance

- **Phase 0 — Requirements validation:** Confirm real school workflows. Exit gate: requirements and sample templates approved.
- **Phase 1 — Secure foundation:** Authentication, tenancy, roles, audit and core schema. Exit gate: cross-school access tests pass.
- **Phase 2 — School, student and staff:** Master data management. Exit gate: school can create and maintain records.
- **Phase 2B — Academic lifecycle:** Enrollment history, promotion/retention, withdrawal and rollover. Exit gate: old and new year data remain correct after rollover.
- **Phase 3 — Attendance:** Student/staff attendance and reports. Exit gate: attendance workflow passes real scenarios.
- **Phase 4 — Fees:** Charge/fee ledger, allocation, payments, receipts, reversals, daily collection close and dues. Exit gate: concurrency and reconciliation cases pass.
- **Phase 5 — Academics and communication:** Exams/results, homework and notices. Exit gate: academic workflows function end-to-end.
- **Phase 6 — Parent portal:** Safe, mobile-friendly family view. Exit gate: parent/child access tests pass.
- **Phase 7 — Reports and optional basic salary:** Essential reports first; salary may move to V1.1 if pilot schools prefer certificates, ID cards or other operational basics.
- **Phase 8 — Backoffice/subscriptions:** Platform owner can operate SaaS accounts. Exit gate: renewal/expiry flow verified.
- **Phase 9 — Excel migration:** Repeatable onboarding import. Exit gate: import preview/errors/duplicates tested.
- **Phase 10 — QA, security and deployment:** Production candidate. Exit gate: release gates and backup restore pass.
- **Phase 11 — Pilot:** Test with 2–3 schools. Exit gate: critical defects resolved and feedback reviewed.
- **Phase 12 — Launch and operations:** Repeatable paid onboarding. Exit gate: support, renewal and deployment runbooks ready.

The phases are ordered by dependency. Some work can overlap only after the core schema and acceptance criteria are stable.

---

## 7. Detailed phase requirements

## Phase 0 — Requirements validation

**Objective:** Prevent building a generic ERP that does not match local schools’ actual processes.

### Tasks

- Interview 3–5 target schools, ideally including a small primary school and a school with multiple sections/classes.
- Document how admissions, student records, attendance, fees, salary and report cards are handled today.
- Collect anonymised examples of student/staff spreadsheets, fee receipts, fee structure, report cards and attendance formats.
- Confirm school calendar, academic-year rollover, grading approach, fee frequency, partial payments, discounts, previous balances and receipt requirements.
- Decide which modules are needed by the first paying schools and which can wait.
- Validate willingness to pay, student-count ranges, onboarding expectations and support expectations.

### Deliverables

- Approved workflow notes.
- MVP screen inventory and user-role matrix.
- Sample import templates and report-card/receipt examples.
- Initial pricing/plan hypothesis and explicit list of assumptions.
- Prioritised backlog with must-have, should-have and later items.

### Exit criteria

- At least 3 schools have been interviewed or the reason for fewer interviews is recorded.
- No critical ambiguity remains around student setup, attendance, fees and reports.
- Product owner signs off the V1 baseline before schema implementation.

## Phase 1 — Architecture and secure foundation

**Objective:** Create the application foundation that every later module can safely reuse.

### Requirements

- **FR-101:** Create a school registry for tenant records and a platform-level Super Admin account.
- **FR-102:** Implement login/logout, password reset and secure password hashing using framework-standard mechanisms.
- **FR-103:** Implement roles/permissions for Super Admin, School Admin, Teacher, Accountant, Staff and Parent.
- **FR-104:** Implement server-side school-context resolution and membership checks.
- **FR-105:** Tenant-owned queries and mutations must be scoped to the authorised `school_id`.
- **FR-106:** Validate school ownership of related IDs (for example, a fee payment cannot be attached to another school’s student).
- **FR-107:** Create audit events for sensitive changes to attendance, fees, marks, user permissions and subscription status.
- **FR-108:** Establish consistent validation, error handling, logging, environment-based configuration and database migration workflow.
- **FR-109:** Establish secure file-upload rules and private storage for documents.
- **FR-110:** Configure HTTPS in deployed environments, secure session/cookie settings, CSRF protections, request validation and rate limits for sensitive endpoints.

### Acceptance criteria

- Unauthenticated users cannot access protected pages/API routes.
- School A users cannot read or change School B records through UI, API, guessed IDs, exports or nested relationships.
- Parents cannot access non-linked students.
- Authorisation is enforced on the server, not only by hiding UI controls.
- Automated tests cover cross-school access, role restrictions and unauthorised record changes.
- Migrations run from an empty database and can be applied to a test environment repeatably.

## Phase 2 — School, student and staff management

**Objective:** Enable schools to set up their organisation and core records.

### Requirements

- **FR-201:** Maintain school profile, contact information, logo and basic settings.
- **FR-202:** Configure academic years, classes, sections, subjects, class teachers and working-day/holiday basics.
- **FR-203:** Create/edit/view/search student records with admission number, name, DOB, class/section, status and other school-approved fields.
- **FR-204:** Record parent/guardian contacts and link one or more guardians to a student.
- **FR-205:** Support more than one child linked to a guardian account.
- **FR-206:** Store permitted student documents privately with access checks and file-type/size validation.
- **FR-207:** Maintain student status/history (active, inactive/withdrawn or transferred) without unintentionally deleting financial/academic history.
- **FR-208:** Create/edit/search staff profiles, designation, employee ID, joining date, qualification and authorised class/subject assignments.
- **FR-209:** Prevent duplicate admission/employee identifiers within the relevant school, subject to the agreed rules.
- **FR-210:** Provide pagination, filters and helpful empty/error states for large lists.

### Acceptance criteria

- School Admin can add, edit, search and deactivate a student and staff member.
- Class/section/subject choices are limited to the current school.
- Validation prevents missing required values and invalid relationships.
- Student/staff history is preserved when a person becomes inactive.
- A school cannot see another school’s master records.

## Phase 2B — Academic-year closing and student promotion (V1.1 ADDITION)

**Objective:** Prevent annual rollover from corrupting permanent student identity and historical records.

### Requirements

- **FR-2B01:** Keep permanent student identity separate from per-academic-year `student_enrollments` (class, section, roll number, year, status).
- **FR-2B02:** Provide an authorised preview of year-end promotion: promote, retain, transfer/withdraw or graduate, one student at a time or in bulk.
- **FR-2B03:** Validate the target academic year, class, section and capacity/policies, and block duplicate active enrollments where disallowed.
- **FR-2B04:** Preserve old attendance, fees, marks, reports, receipts and class histories against their original year/enrollment.
- **FR-2B05:** Require a confirmation summary, audit actor/time, and idempotent processing/re-run protection. Do not globally overwrite students' class IDs.
- **FR-2B06:** Define whether unpaid dues carry forward and if so as distinct opening balance entries, not silently rewritten historical charges.
- **FR-2B07:** Define reopening/correction rules after a year is locked; only authorised roles may reopen.

### Acceptance criteria

- Promotion of a mixed cohort (promoted, retained, transferred, withdrawn) produces correct new enrollments without duplicating identities.
- Previous-year marks, attendance and invoices still render identically after rollover.
- Retrying a completed rollover cannot create duplicate enrollments or duplicated carried dues.
- Two concurrent rollover requests cannot produce inconsistent state.

## Phase 3 — Attendance management

**Objective:** Replace manual attendance calculations with consistent daily records and reports.

### Requirements

- **FR-301:** Record student attendance by date and class/section with Present, Absent, Late, Leave and Half-day statuses.
- **FR-302:** Record staff attendance with applicable statuses.
- **FR-303:** Restrict teachers to assigned classes where configured; School Admin may manage all school attendance.
- **FR-304:** Prevent accidental duplicate attendance records for the same entity/date and define an authorised correction process.
- **FR-305:** Provide daily, monthly, student-wise, class-wise and staff-wise attendance reports.
- **FR-306:** Calculate attendance percentage using a documented denominator and treatment of holidays/leave/half-days.
- **FR-307:** Record editor and edit timestamp for corrections.

### Acceptance criteria

- A teacher can mark attendance for an assigned class and see saved records.
- The system warns about an already-submitted attendance sheet rather than duplicating records.
- Monthly totals match a set of manually verified test cases, including leave and half-day rules.
- Unauthorised users cannot change submitted attendance.

## Phase 4 — Fee management

**Objective:** Manage fee obligations and manual collections accurately, with an auditable history.

### Requirements

- **FR-401:** Configure fee heads (for example tuition, admission, annual, exam and other charges).
- **FR-402:** Configure fee amounts and schedules by academic year/class or approved school-specific rule.
- **FR-403:** Assign fee obligations to a student and show due, paid, discount and balance amounts.
- **FR-404:** Support discounts/scholarships, late fines, previous balances, partial payments and advance payments where approved.
- **FR-405:** Record a manual payment with amount, date, mode (Cash/UPI/Bank Transfer/Other), receipt number and recording user.
- **FR-406:** Generate a printable/downloadable receipt and preserve receipt history.
- **FR-407:** Show student-wise dues, fee-defaulter lists, daily/monthly collection totals, **daily cashier/collection close** and payment history.
- **FR-408:** Prevent payments from being linked to students or fee obligations outside the current school.
- **FR-409:** Use a correction/reversal workflow for mistaken financial entries; do not silently erase posted payments.
- **FR-410:** Do not integrate a payment gateway in V1.
- **FR-411:** Represent assessments as posted fee charges/invoices with individual fee heads and dated due obligations; preserve allocations linking payments to charges.
- **FR-412:** Represent monetary values in integer minor units or fixed-precision `DECIMAL`, never binary floating-point.
- **FR-413:** Guarantee atomic payment posting, allocation, per-school receipt sequencing and idempotent re-submissions; use transactional locks/constraints for concurrency.
- **FR-414:** Add a daily cashier report by date, user and payment mode (cash, UPI, bank, other), with voids/reversals and closing reconciliation.
- **FR-415:** Mark posted receipts immutable; corrections occur through permissioned reversal/adjustment events with reason and audit.
- **FR-416:** Record the source of opening balances imported from an earlier system and prevent silent recalculation of locked periods.

### Acceptance criteria

- Verified test cases reconcile assessed fees, discounts, payments and balance due.
- Partial/advance payments are allocated consistently and do not create negative or duplicate balances unexpectedly.
- Receipt numbers are unique within the agreed school/year-or-global scope; concurrently submitted receipts cannot collide.
- Double-click, retry and concurrent collections cannot double-post the same verified request.
- Daily close agrees with the ledger by payment mode after authorised reversals.
- An authorised accountant can record a payment and print a receipt; a parent cannot alter the ledger.
- Financial changes are traceable to the acting user and timestamp.

## Phase 5 — Exams, results, homework and notices

**Objective:** Provide the most common academic communication workflows without advanced timetable/learning-management complexity.

### Requirements

- **FR-501:** Create an exam with name, academic year, applicable class/section, dates and subjects.
- **FR-502:** Configure maximum marks and applicable passing/grade rules.
- **FR-503:** Enter/edit marks only for authorised classes/subjects and calculate totals, percentages and grades according to the selected school rules.
- **FR-504:** Generate a basic printable student report card and preserve result history using configurable school-approved templates/rules rather than school-specific code forks.
- **FR-505:** Let authorised teachers create homework/assignments with class/section, subject, description, due date and optional attachment.
- **FR-506:** Let authorised users create notices/circulars targeted to the whole school or selected classes/roles.
- **FR-507:** Display active/current homework and notices in the relevant dashboard/parent portal.
- **FR-508:** Record who created/edited marks, homework and notices.
- **FR-509:** Support exam components, weightages, absent/not-applicable marks and publish/unpublish/lock state only where pilot-approved; never guess percentage denominator.
- **FR-510:** Publish only authorised final results; preserve earlier publication history or corrections.
- **FR-511:** Use school-configurable layout fields for logo, signatures, grading and remarks, within a small controlled template catalogue.

### Acceptance criteria

- Marks and percentage calculations match independently checked examples.
- Users cannot enter marks for an unassigned class/subject without permission.
- A parent sees only homework/notices that apply to their linked child or school-wide audience.
- Attachments are private and authorised; no public file URLs for student documents.

## Phase 6 — Parent portal and PWA-ready experience

**Objective:** Give families an easy mobile view without building a native app in V1.

### Requirements

- **FR-601:** Provide a mobile-responsive parent login and dashboard.
- **FR-602:** Link each parent account to school-verified guardian/student relationships.
- **FR-603:** Display the parent’s linked child/children and allow switching between them.
- **FR-604:** Show child profile, attendance, fee status/payment history and receipts as permitted by school policy.
- **FR-605:** Show homework, notices, exam schedule and published results.
- **FR-606:** Provide logout, password reset and basic account-help flow.
- **FR-607:** Prepare the responsive web application for PWA installation where supported, while explaining that PWA installability does not equal full native background capability.
- **FR-608:** Do not display a “live” or current status for stale information without its last-updated timestamp.

### Acceptance criteria

- Parent A cannot access Parent B’s child by changing a URL or request ID.
- One guardian linked to multiple children can switch between those children.
- Parent pages work on common mobile screen sizes and do not require horizontal scrolling for normal tasks.
- Parent accounts cannot edit marks, attendance or fee records.

## Phase 7 — Basic salary and reports

**Objective:** Add simple salary payment management and consolidated reporting.

### Requirements

- **FR-701:** Configure a staff member’s basic salary and applicable allowances/deductions as agreed.
- **FR-702:** Record salary period, working days/attendance inputs where applicable, advance, deduction, net amount and payment status.
- **FR-703:** Record salary payments and generate a basic salary slip/statement.
- **FR-704:** Provide dashboard summaries for students, staff, daily attendance, fee dues/collections, exams and authorised salary figures.
- **FR-705:** Provide school-scoped reports and CSV/Excel export where required; PDF export can be added for receipts/report cards first.
- **FR-706:** Restrict salary and financial reports to authorised roles.
- **FR-707:** Exclude statutory payroll automation (PF/ESI/TDS) from V1 unless separately approved.
- **FR-708:** Payroll/salary is **conditional for first paid release**. Keep the domain isolated so the module can be deferred without affecting other features.

### Acceptance criteria

- Salary totals reconcile against independently checked examples.
- Only authorised users can view/edit salary and salary reports.
- Exported data is scoped to the current school and permission set.

## Phase 8 — Super Admin backoffice and subscription management

**Objective:** Operate the commercial SaaS without a payment gateway.

### Requirements

- **FR-801:** List/search schools and show account status, main contact and onboarding state.
- **FR-802:** Create a school and initialise its School Admin membership.
- **FR-803:** Maintain subscription plan, start date, expiry date, amount, payment status, payment mode/reference and renewal history.
- **FR-804:** Allow an authorised Super Admin to record a subscription payment and manually extend/renew the subscription.
- **FR-805:** Show Active, Expiring Soon, Grace Period, Expired and Suspended states.
- **FR-806:** Support an agreed grace-period rule and configurable expiry warnings/dashboard lists.
- **FR-807:** Enforce access behaviour after expiry according to an approved policy; do not silently delete data. Recommended default: allow restricted read/export or a defined grace period, then block normal operational access while retaining admin/export and renewal paths.
- **FR-808:** Record platform-level actions (activation, suspension, renewal and plan changes) in an audit log.
- **FR-809:** Keep school subscriptions separate from the school’s fee ledger for its students.

### Acceptance criteria

- Super Admin can create a school, assign a plan and activate its initial access.
- Recording a verified subscription payment and renewing extends the correct school’s expiry date and creates a renewal-history record.
- An expired school follows the configured policy and can regain access after authorised renewal.
- School Admins cannot access platform-wide school lists or alter subscriptions.

## Phase 9 — Excel data migration and onboarding

**Objective:** Make migration and local implementation repeatable instead of manually typing every record.

### Requirements

- **FR-901:** Provide downloadable templates and column guidance for agreed student, staff and initial fee data.
- **FR-902:** Allow authorised upload and parse supported Excel/CSV formats with file-size/type limits.
- **FR-903:** Preview parsed rows before committing the import.
- **FR-904:** Validate required columns, identifiers, class/section references, dates, phone formats where appropriate and duplicate candidates.
- **FR-905:** Show row-level errors and downloadable/reviewable import results.
- **FR-906:** Use an idempotency/duplicate strategy so retrying an import does not silently create duplicates.
- **FR-907:** Record who imported data, when, into which school, and counts of successful/failed rows.
- **FR-908:** Provide a documented manual cleanup procedure for register-only or inconsistent source data; no promise of perfect OCR/register extraction in V1.

### Acceptance criteria

- A valid sample spreadsheet imports to the correct school.
- Invalid rows are reported clearly and do not corrupt valid existing records.
- Duplicate handling follows a documented rule and import can be safely reviewed before commit.
- Imported records still obey all tenant and role permissions.

## Phase 10 — QA, security hardening and production deployment

**Objective:** Produce a release candidate fit for real school data.

### Requirements

- **FR-1001:** Maintain automated tests for core business rules, roles and tenant isolation.
- **FR-1002:** Test input validation, access control, file upload security, CSRF/XSS/SQL-injection defences using framework-standard protections and appropriate tests.
- **FR-1003:** Configure HTTPS, secure secrets/environment variables, least-privilege database credentials and a firewall/limited administrative access.
- **FR-1004:** Set up error logging/monitoring and a documented process for investigating incidents.
- **FR-1005:** Configure automatic database backups and a mandatory separate/off-server encrypted copy for production; fee-taking production use requires a documented recovery solution materially better than daily-only backups.
- **FR-1006:** Perform and document a backup restore test before production launch.
- **FR-1007:** Configure hosting cost alerts/budgets and review actual charges before opening access to all pilot users.
- **FR-1008:** Test deployment and migrations in staging before production; maintain rollback/recovery instructions.
- **FR-1009:** Test common workflows on mobile and desktop browsers.
- **FR-1010:** Record p50/p95 latency under representative real-world workloads. **Release ceiling**: common screens p95 ≤3 seconds under at least 50 simulated concurrent users on the agreed setup; **engineering targets**: typical list/search and teacher attendance initial load p95 ≤1.5 seconds, saves p95 ≤2 seconds; feasibility must be measured on target infrastructure.
- **FR-1011:** Load-test morning attendance, month-end collections, results publication and imports/exports independently.
- **FR-1012:** Include tenant-safety tests for cache, queue jobs, exports, file downloads, nested relations and search.
- **FR-1013:** Run a deployment-time dependency audit, static security checks and pre-production penetration/security review appropriate to the risk profile.

### Acceptance criteria

- Critical security and data-integrity defects are resolved before launch.
- Backup restoration has been demonstrated, not merely assumed.
- Production secrets are not committed to source control.
- Staging-to-production deployment steps are documented and repeatable.
- The application passes the agreed release test suite.

## Phase 11 — Pilot with 2–3 schools

**Objective:** Validate the software with real workflows before selling broadly.

### Tasks

- Onboard 2–3 schools using an agreed pilot agreement and scope.
- Import approved data, verify counts and sample records with the school.
- Train school admins and teachers; provide short operating guides.
- Track bugs, feature requests, training gaps and support time separately.
- Review actual adoption of attendance, fee records, parent login and reports.
- Resolve critical defects and classify non-critical requests into V1.1/V2 backlog.

### Exit criteria

- No open critical security, cross-school data-leak or financial-calculation defect.
- Pilot schools can perform agreed core workflows.
- Backups and restore process remain operational.
- Feedback and unresolved limitations are documented and communicated.
- Product owner approves wider onboarding.

## Phase 12 — Launch and ongoing operations

**Objective:** Move from pilot to a repeatable small-business SaaS operation.

### Requirements

- Publish a simple plan/pricing sheet with student limits, included modules, support boundaries and renewal terms.
- Define onboarding checklist, data-migration checklist and teacher-training guide.
- Define support hours/channel, incident severity and response expectations; avoid promising 24/7 support unless staffed.
- Create a renewal reminder and school-expiry review process.
- Maintain release notes, database migration discipline and a staging environment.
- Review monthly infrastructure cost, support time per school, active usage, failed workflows and renewal intent.
- Maintain privacy notice, customer terms/data responsibilities and a practical data export/exit process.

### Exit criteria

- A new school can be onboarded using the repeatable checklist.
- Subscription renewal, support and backup processes have named owners.
- Pricing and operating cost assumptions have been checked against pilot results.
- V1 launch decision is recorded by the product owner.

---

## 8. Proposed data model (logical, not final migrations)

One shared MySQL database will contain many tables; it does **not** mean one table for all data. It means a common set of tables stores records for all schools, with each tenant-owned record scoped by `school_id`.

### 8.1 Platform/global entities

- `schools`
- `users`
- `roles`, `permissions` and role-permission mappings
- `school_user` (membership, school context and role assignment)
- `subscription_plans`
- `school_subscriptions`
- `subscription_payments` / renewal history
- platform audit/support records

### 8.2 Tenant-owned entities (initial catalogue)

- School setup: `academic_years`, `classes`, `sections`, `subjects`, `school_calendar`
- People: `students`, `guardians` or users with guardian profiles, `student_guardians`, `staff_profiles`, `student_enrollments`
- Attendance: `student_attendance`, `staff_attendance`
- Fees: `fee_heads`, `fee_structures`, `student_fee_assignments` or invoices, `fee_payments`, payment allocations/adjustments, receipt sequence
- Payroll: `salary_structures`, `salary_periods`, `salary_payments`, salary slips/records
- Academics: `exams`, `exam_subjects`, `exam_marks`, `homework`, `notices`
- Platform support per school: `import_jobs`, `import_errors`, `audit_logs`, document metadata

Exact normalisation, foreign keys, indexes and unique constraints are design tasks in Phase 1. Apply tenant-aware constraints wherever practical, and validate school ownership for every related record. Do not create a separate copy of these tables for each school in the initial model.

### 8.3 Data integrity rules

- Use foreign keys and appropriate unique constraints.
- Index `school_id` and common school-scoped filters/joins.
- Validate related records belong to the same authorised school.
- Do not trust user-supplied primary keys or school IDs without authorisation.
- Avoid hard-deleting students/staff when their financial/attendance history must remain; use inactive/soft-delete policies where appropriate.
- Use database transactions for fee posting, payment allocation, marks publication and subscription renewal.
- Keep sensitive documents in private storage and deliver them only after authorization checks.

## 9. Non-functional requirements

### Security and privacy

- Use current supported framework/runtime versions and built-in security controls.
- Hash passwords with framework-standard password hashing.
- Enforce server-side role and tenant authorisation on every protected action.
- Use HTTPS in production and store secrets outside the repository.
- Rate-limit login/password-reset and other abuse-prone endpoints.
- Protect against cross-site request forgery, injection, cross-site scripting and unsafe uploads using framework controls and validation.
- Keep audit records for sensitive financial, academic and access-control changes.
- Collect only the personal data needed for school workflows; restrict exports and downloads.
- Define retention, correction, export, account closure and incident response with schools before launch. Obtain suitable legal/privacy review because the system handles children’s information.

### Reliability and backup

- Daily full backup remains a baseline, **not the sole production recovery mechanism**: for live fee operations, require encrypted off-server storage and point-in-time restore/binlog or equivalent recoverability.
- Test restore before launch and periodically thereafter.
- **Revised internal production targets:** RPO ≤1 hour for posted financial/attendance data and RTO ≤4 hours for core operations, subject to measured validation on paid infrastructure. Pilot/demo environments may use less strict targets only if clearly disclosed and do not hold production-critical data.
- Never display stale GPS/live-status claims; live bus tracking is out of scope for V1.

### Usability and accessibility

- Responsive layouts for common desktop, tablet and mobile screen sizes.
- Clear validation messages and confirmation for destructive/financial actions.
- Pagination/search/filtering for large lists.
- Support keyboard navigation and readable contrast for core workflows.
- Keep teacher flows short: attendance and marks entry should not require navigating unrelated modules.

### Maintainability

- Use version control, code review and database migrations.
- Separate business logic from controllers/views where practical.
- Use shared components for forms, tables, receipts and permission checks.
- Maintain a staging environment and repeatable deployment notes.
- Do not let Claude Code make broad, unrelated refactors without review.

## 10. Key end-to-end workflows

### 10.1 School onboarding

1. Super Admin creates the school record.
2. Super Admin assigns a plan and validity dates.
3. School Admin receives credentials or a secure invitation.
4. School configures academic year, classes and sections.
5. Approved Excel data is previewed, validated and imported.
6. School verifies sample student/staff/fee records.
7. Staff and teachers receive training; school moves to active use.

### 10.2 Attendance

1. Teacher logs in and opens an assigned class/date.
2. System loads only the authorised student roster.
3. Teacher marks statuses and submits attendance.
4. System saves attendance with editor/timestamp and prevents accidental duplicates.
5. Admin views reports; parent sees published attendance for linked children.

### 10.3 Manual student-fee payment

1. Accountant/Admin opens the correct student and outstanding fee record.
2. System shows assessed amount, previous payments and current balance.
3. User enters amount, date and payment mode; system validates against applicable rules.
4. System posts the payment transactionally and creates a receipt number.
5. Receipt is printed/downloaded; ledger and reports update.
6. Corrections use a traceable reversal/adjustment process.

### 10.4 Parent access

1. School verifies guardian identity/contact and links guardian to student.
2. Parent obtains secure login access.
3. Parent opens the dashboard and chooses a linked child.
4. Each request validates the current parent-child relationship and school context.
5. Parent views permitted attendance, fees, homework, notices and published results.

### 10.5 Subscription renewal

1. Platform owner receives and verifies payment outside the ERP.
2. Super Admin records amount, date, mode/reference and school.
3. System creates a payment/renewal history record and extends the subscription validity.
4. Status becomes Active; audit log records who made the change.
5. Expired schools follow the agreed grace/restriction policy without deletion of school data.

### 10.6 Excel import

1. User downloads the correct template.
2. User uploads a spreadsheet for the current school.
3. System parses and previews rows, validates fields and identifies duplicates/errors.
4. User reviews the preview and confirms the import.
5. Valid rows commit; failures are reported; summary is recorded in the import log.
6. School checks counts and sample records before the import is treated as complete.

## 11. Testing strategy

- **Unit tests:** calculations, validation and domain rules (attendance percentages, fee balances, grade/percentage rules, subscription date/status rules).
- **Feature tests:** login, CRUD flows, attendance submission, fee collection/receipt, results publication, parent dashboard and renewal.
- **Tenant-isolation tests:** user from School A cannot read/update/delete/export records of School B using altered IDs, filters, nested relationships or endpoints.
- **Role tests:** verify least-privilege access for teachers, accountants, staff and parents.
- **Financial integrity tests:** partial payments, discounts, old dues, advance payments, reversals and receipt uniqueness.
- **Import tests:** valid templates, missing columns, bad dates, duplicate identifiers, invalid class/section, partial failures and retries.
- **Security tests:** file upload, session handling, rate limits, validation, CSRF and dependency/configuration review.
- **Browser/device tests:** mainstream desktop browsers and mobile viewport sizes.
- **Operational tests:** migration from clean database, staging deployment, backup and restore, logs/alerts and expired-subscription behaviour.
- **User acceptance testing:** real workflows with pilot school representatives and sign-off checklist.

## 12. Definition of done

A feature/phase is not “done” merely because its screen exists. It is done when:

1. The agreed requirement is implemented and reviewed.
2. Validation and permission checks exist on the server.
3. School/tenant isolation is verified for tenant-owned data.
4. Automated tests cover expected behaviour and important failure cases.
5. Relevant error/empty/loading states are usable.
6. Database migrations and any configuration changes are documented.
7. Manual testing is completed in staging.
8. Known limitations are documented and accepted.
9. No critical security, data-integrity or data-loss defect remains open.

## 13. Risks and mitigations

- **Feature creep:** Freeze V1 scope; use a prioritised backlog and formal change requests.
- **Cross-school data leak:** Use central tenant middleware/policies, school-scoped relationships, automated isolation tests and code review.
- **Fee/salary calculation error:** Write calculation rules, reconcile sample cases, use transactions and preserve audit history.
- **Poor spreadsheet quality:** Use templates, preview, row-level errors and school verification after import.
- **Excessive customisation:** Prefer school configuration; charge separately for approved custom work.
- **Low teacher adoption:** Keep workflows short, provide on-site training and quick guides, and collect pilot feedback.
- **Hosting costs rise:** Set budgets/alerts and review usage; do not rely on permanent free credits.
- **Data loss:** Automate backups, keep an off-server copy where possible and test restoration.
- **Children’s personal data mishandled:** Minimise data, use private files and strict permissions, clarify customer responsibilities and obtain privacy/legal review.
- **Founder becomes the only support path:** Document troubleshooting, define support hours and make onboarding repeatable.

## 14. Decisions to confirm before coding

1. Exact technology versions and UI approach (recommended: supported Laravel + MySQL + responsive server-rendered UI/PWA; confirm whether Blade/Livewire or a separate frontend is preferred).
2. Hosting provider and backup/storage arrangement; AWS is a candidate, not assumed free forever.
3. School subscription plans, student limits, grace period and expiry behaviour.
4. Attendance rule for holidays, leave and half-days.
5. Fee due schedule, discount/fine logic, receipt format, partial-payment allocation and previous-balance migration.
6. Grading/report-card rules per pilot school.
7. Who can create/invite parent accounts and how guardian contact is verified.
8. Exact fields permitted for student/guardian/staff records and document retention rules.
9. Excel/CSV templates and duplicate-handling rules.
10. Included support/training/migration boundary for the proposed ₹999/month entry plan.
11. Backup restore expectations, incident contact and data export procedure.
12. Final product name, domain and customer terms/privacy notice before production launch.
13. Academic-year promotion, withdrawn students, year locking and opening balance carry-forward.
14. Specific invoice/charge/payment allocation model, financial approval thresholds, daily close and reversal rules.
15. MFA recovery, tenant context for caches/queues/exports, and parent login identity verification.
16. Whether basic TC/leaving certificate and ID card replace salary in the first paid release.
17. Explicit data retention/deletion, children’s data collection, privacy obligations, breach response and applicable Indian legal review.
18. Backup provider/restore tests sufficient for RPO ≤1 hour; if not, do not promise this operational target.
19. Agree measurable service/support limits, onboarding scope, and taxes on customer contracts.

## 15. Claude Code implementation protocol

Use this protocol for every development task:

1. Read this SRS and the relevant existing code before editing.
2. Work on one phase or small feature slice at a time; do not generate the entire ERP in one prompt.
3. Before coding, list proposed files, data-model changes, dependencies and test cases.
4. Implement migrations, models, policies/permissions, service/business logic, UI and tests as one coherent slice.
5. Never remove tenant checks or tests to make a test pass. Never trust a browser-provided `school_id` without server-side validation.
6. Run the relevant test suite, inspect failures and summarise the actual result; do not claim tests passed if they were not run.
7. Report migrations, environment variables, manual setup steps, risks and known limitations after each task.
8. Avoid real student data in prompts, fixtures, screenshots or logs. Use synthetic/anonymised data.
9. Ask for a product decision only when an unresolved requirement blocks safe implementation; otherwise follow the documented baseline and call out the assumption.
10. Do not add out-of-scope modules without approval.

### Suggested first implementation prompt

> Read `SCHOOL_ERP_V1_1_MASTER_PLAN.md` fully. Do not build all modules yet. First propose the implementation architecture and Phase 1 plan. Identify the Laravel/PHP version, frontend approach, authentication, tenant-context design, role/permission strategy, proposed initial migrations, private file-storage approach, audit logging, test strategy and deployment assumptions. Highlight any requirement conflicts or unresolved decisions. Do not start coding until the architecture proposal and initial schema are reviewed. The architecture must use one shared MySQL database with tenant-owned records scoped and authorised by `school_id`; include tests that prove cross-school access is denied.

## 16. Approval checklist

Use this checklist before treating the document as the development baseline:

- [ ] Phase 0 school workflows and sample templates reviewed.
- [ ] V1 scope and exclusions approved.
- [ ] User roles and permissions approved.
- [ ] Shared-database multi-tenant model approved.
- [ ] Fee and attendance calculation rules agreed.
- [ ] Parent/guardian verification and child-data access model agreed.
- [ ] Subscription expiry/grace-period behaviour agreed.
- [ ] Hosting, backup and restore approach agreed.
- [ ] Initial pricing/support/migration boundaries reviewed.
- [ ] Phase 1 architecture proposal approved before coding.

---

**Document status:** V1.1 proposed baseline. Update version/change log after Phase 0 sign-off. This is a product/engineering plan, **not** a completed security audit, legal compliance certification, measured hosting estimate or customer-validated pricing.


---

# V1.1 CONTROLLED ADDITIONS — NORMATIVE REQUIREMENTS

These sections are part of the same specification and **override any weaker or ambiguous language above**. The original phases and requirements remain in force unless explicitly re-prioritised below. Labels: **LOCKED PROPOSAL** = architecture direction chosen for planning but still subject to technical architecture sign-off; **PILOT VALIDATION** = requires school evidence; **LEGAL REVIEW** = no legal conclusion asserted; **RELEASE GATE** = must pass before paid production.

## 17. Architecture, tenant isolation and data model contract

### 17.1 Deployment and component boundaries

- **LOCKED PROPOSAL:** A modular Laravel monolith, server-rendered UI with selective Livewire/Alpine interactions, MySQL/InnoDB, and a single shared database. Use a supported PHP/Laravel combination confirmed from official documentation at project setup. No unnecessary services or per-school databases in V1.
- Separate modules/namespaces for `Platform`, `Identity`, `SchoolSetup`, `Students`, `Academics`, `Attendance`, `Fees`, `Staff`, `Communication`, `Reports`, `Imports`, `Documents` and `Audit`.
- Controllers handle requests and authorization; services/domain actions own rules; repositories/query builders apply tenant filters; database constraints enforce invariant relationships. Centralize policy checks rather than copying ad hoc `where('school_id', ...)` fragments.
- Separate platform backoffice routes/permissions from school-facing operational routes. Support administrative impersonation, if ever introduced, **only** with explicit school consent, a short-lived scoped token, prominent banner and audit trail; not in first release.
- Environment segregation: development, staging and production with distinct credentials, databases, storage and access policies. No production child data in development or AI coding prompts.

### 17.2 Tenant-context contract (**RELEASE GATE**)

1. Resolve an authenticated actor and membership, then derive the active tenant from a **server-validated** selection; never trust request-supplied `school_id` without explicit membership checks.
2. All tenant-owned reads/writes must use tenant-scoped services/query builders and policies. A request for an ID owned by another school must fail without data disclosure.
3. Prevent foreign-key collisions across schools: where feasible enforce composite tenant-aware relationships such as `(school_id, student_id)` and same-tenant foreign keys/unique keys. Add application ownership validation as a second layer.
4. For all async work, the job payload must identify a tenant, actor/service principal and job purpose; the worker revalidates authorization or applies a narrowly scoped service authorization before any database/storage operation.
5. Cache keys, rate-limit identities, export jobs, storage paths, temporary documents, search indexes, notifications and audit views must include a tenant boundary. Clear cached scoped data on permission or membership changes.
6. Parent/guardian rights are evaluated at **every** child-specific request using verified, currently valid guardian relationships. Don't assume login alone gives access to all children with the same surname/phone.
7. Explicitly test hostile ID substitution, nested relationships, mass update, export filters, background jobs, cache collisions and document links for at least two schools with overlapping identifiers.
8. Index all hot paths (typical pattern: `(school_id, academic_year_id, class_id, section_id, date)` where applicable); document performance justification of each index and avoid excessive redundant indexes.

### 17.3 Canonical entities and integrity patterns

| Domain | Suggested entities | Mandatory rule |
|---|---|---|
| Identity | users, schools, school_memberships, roles, permissions, invitations, MFA recovery | Users can have multiple memberships but every action is scoped |
| Student lifecycle | students, guardians, student_guardians, academic_years, student_enrollments, enrollment_changes | Permanent student identity; mutable enrollment/history by academic year |
| Attendance | attendance_sheets, student_attendance, staff_attendance, attendance_corrections | One approved status per student/session/date, unique constraints and correction audit |
| Fees | fee_heads, fee_plans, student_charges, charge_lines, fee_payments, payment_allocations, adjustments, receipt_sequences, cashier_closes | Posted ledger is immutable; allocations transactional, idempotent |
| Academics | exams, exam_components, grading_rules, marks, result_publications, report_card_templates | Grade calculation reproducible; publish/lock enforced |
| Documents | file_metadata, access_logs, export_jobs | Private storage and tenant ownership validated at download time |
| Operations | import_jobs, import_rows_errors, audit_events, incident_records, support_tickets | Tenant-scoped visibility, clear retention policies |
| Billing | subscription_plans, school_subscriptions, subscription_payments, renewal_events | Separate from student fee ledger |

**Schema conventions:** `school_id` on tenant tables; explicit academic-year/enrollment references; globally unique internal identifiers; school-scoped human-readable admission, employee and receipt identifiers; timestamps in UTC, display local timezone; status enums/check constraints or documented state machines; fixed-precision money; optimistic versioning or row locks on highly concurrent edits. Decide nullable/optional child data only after pilot review.

### 17.4 Financial control requirements (**RELEASE GATE**)

- One transaction atomically creates a posted payment, receipt number, allocations and audit event. Do not show a successful receipt if any part failed.
- Unique idempotency token per posting intent stops double-clicks/timeouts from posting duplicate payments.
- Agree allocation order: oldest due first, specific charge selected, or other school configuration. Do not silently reallocate historical money.
- Define fee discounts as authorised line adjustments with reason, approver and date; define fine calculations and caps by school.
- Advance payments are liabilities/credits until allocated to school-approved charges; define handling of excess and refunds. An open credit should not be shown as a negative due without deliberate presentation.
- Store opening balances distinctly with source import and reconciliation approval. Avoid editing old invoices to fit present totals.
- Freeze/close a collection day with counted cash, ledger cash, UPI/bank collection, differences, approved corrections and reviewer sign-off; avoid making reconciliation a substitute for bank reconciliation (out of scope).
- Receipt numbering policy, gap handling, duplicate handling and cancelled receipt markings must be agreed with school before release.

## 18. Functional workflow additions and usability criteria

### 18.1 Academic lifecycle and certificates

Academic-year rollover is mandatory for V1. A controlled, reversible-by-authorised-correction preview maps all active enrollments to promote/retain/graduate/withdraw outcomes. Historic year data stays unchanged. Fee carry-forward is a new period entry linked to its source, never a rewrite of history. A basic transfer/leaving certificate and student ID-card template are **PILOT VALIDATION** items; implement in the first paid release only if at least two pilots prioritise them over salary/payroll.

### 18.2 Report card configuration

Pilot schools supply anonymised current report cards. The engine supports per-school grading scheme and subject components, missing/absent handling, applicable maximum, rounding rules, publish/lock workflow, logo/signatures and a **small finite number of configurable print templates**. Never promise arbitrary designer-style custom layouts at entry-level pricing. An approved test pack must include a manually checked report for every pilot grading variant.

### 18.3 UX contract

- Teacher class attendance should load with defaults (school/year/class derived from assignment) and require minimal actions: open class, mark statuses, save; provide **Mark All Present**, exceptions and clear saved state.
- Admin fee entry should allow student lookup by admission number/name, show unambiguous outstanding charges, allow partial collection and issue a receipt without complex navigation.
- Parent portal prioritises low data transfer, clear last-updated dates and secure multi-child switching; no native-app dependency.
- Use accessible contrast and large touch targets. Test slow mobile connections, older Android browsers and small screens; no horizontal scrolling for core forms.
- At least the critical teacher/admin flows must have safe retry behaviour and avoid duplicate submission; offline attendance syncing is **not promised** in V1.

### 18.4 Module priority after Phase 0

| Tier | Candidate features | Decision rule |
|---|---|---|
| P0: first release | school setup; users/roles; student/staff registry; enrollment/year rollover; attendance; fees/receipts/dues/reversals; exam/marks/report cards; basic parent view; Excel import; backoffice/subscription; audit/recovery | Required unless pilot demonstrates a documented exception |
| P1: first release if low cost/validated | homework/notices; daily collection close (required with fee module); lightweight operational reports; TC/leaving certificate; ID card | Prefer daily-use workflows; decide based on 3–5 school interviews |
| P2: after pilot or only if strongly demanded | salary management; further report card designs; simple timetable; more advanced reports | May be deferred even though in original V1 draft; record change explicitly |
| Out of scope | native apps, GPS, biometric, automatic timetable, library/inventory, payment gateway, paid messaging, AI generation, statutory payroll | No development without approved change request |

**Note:** Homework and notices remain planned V1 features from the original baseline unless explicitly de-scoped in the Phase 0 approval. Salary's release priority is conditional, not automatically deleted.

## 19. Security, privacy, resilience and performance hardening

### 19.1 Identity and access

- Super Admin **MFA required**. Use app-based authenticator/TOTP or stronger phishing-resistant factor where feasible; hashed single-use recovery codes stored securely. Enforce secure password reset and session invalidation on credential/role changes.
- School Admin/Accountant should have MFA available; consider mandatory MFA for users who post/reverse payments. Define account lock/rate limits without enabling trivial denial-of-service against a school.
- No default shared teacher/admin logins. Account invitation and verified guardian linking must be auditable. Granular policies cover CRUD, mark publish, finance reverse, import, export and sensitive file download.
- Restrict Super Admin operational access to minimum required metadata; access to raw school data should not be the normal support path.

### 19.2 Application/document safeguards

- HTTPS, secure/HttpOnly/SameSite cookies, CSRF protection, validated data transfer objects/form requests, encoded output, parameterised queries and strict file authorization.
- Private storage for all student/guardian/staff documents; file-size and extension allowlist plus server-side MIME/content checks, randomized keys, no code execution, short-lived signed or proxied authenticated access. Add malware scanning if supported at affordable cost; don't claim it exists until deployed.
- Guard against formula injection in CSV/Excel exports and imports, SSRF in any future URL upload, unsafe dependency packages, secrets exposure and verbose error pages.
- Rate-limit password reset, login, invitations, file downloads and exports. Audit data edits and sensitive reads/exports without logging passwords, document contents or unnecessary minor personal data.
- Backoffice actions such as school suspension, plan change and permission escalation must require appropriate permission and generate immutable audit events.

### 19.3 Privacy and legal review (**LEGAL REVIEW**)

- Obtain India-specific counsel review of DPDP and applicable rules/commencement/contract obligations before production. Do not assume blanket school consent covers every processing activity or a parent account implies lawful consent for every purpose.
- Prepare: school service agreement; processing/data responsibilities schedule; privacy notice; retention/deletion schedule; data-subject request handling; subprocessor/hosting list; incident notification and school exit/export procedure.
- Minimise child data and never require Aadhaar or other high-risk identifiers unless a documented lawful need exists. Avoid behavioural ad tracking and advertising pixels in authenticated student/parent interfaces.
- Define deletion vs statutory/business record retention at termination, export format, backup retention and inability to instantly delete every backup. Access logs and retention periods need counsel approval.
- Evaluate CERT-In incident-reporting/log-retention applicability and current requirements as part of counsel/security review; don't treat these notes as legal certification.

### 19.4 Resilience (**RELEASE GATE**)

- Production hosting must support automated encrypted daily full backups and suitable incremental/binlog/PITR backups, with encrypted off-server storage and limited restoration credentials.
- **Internal objectives:** RPO ≤1 hour, RTO ≤4 hours for active school use. These are not customer SLAs until proven through measured restore drills; if the chosen host cannot meet them, revise hosting before launch or explicitly defer taking live financial data.
- Restore into isolated staging at least once before launch and periodically afterward; verify fee totals, student counts, attachments, permissions and platform operations after restore.
- Use deployment versioning, database migration rollback/recovery plans, health checks, error monitoring, hosting cost alerts and an incident on-call contact. Write severity-based incident playbooks and school communication templates.

### 19.5 Performance measurement

| Workflow | Engineering target (p95, provisional) | Test note |
|---|---:|---|
| Common navigations / basic lists | 1–1.5 seconds | Target-host server response + practical UX budget; distinguish total browser render |
| Student search | ≤1 second | Warm and cold query; school-scoped results |
| Class attendance load | ≤1.5 seconds | Typical class 30–60 students; on simulated mobile conditions |
| Save class attendance | ≤2 seconds | Includes validation and committed transaction |
| Post fee payment / receipt response | ≤2 seconds | No duplicate postings under retries |
| Dashboard | ≤2 seconds | Cached aggregated queries correctly tenant-keyed |
| Complex reports, bulk report cards, imports | Async with progress and error handling | May exceed 3 seconds; no blocking request timeout |
| Release ceiling | p95 ≤3 seconds on common screens | Test baseline at ≥50 concurrent users and realistic data mix |

Test bursty attendance, fee-month-end, results publication, import jobs, mixed-school sessions and background processing. Targets are engineering objectives rather than measured performance or guaranteed SLA. Record DB query counts, p95, throughput, error rate, CPU/RAM and support costs for each load-test run.

## 20. Commercial model, service scope and financial viability

### 20.1 Pricing hypotheses — NOT final offers

| Plan | Monthly price hypothesis | Student band hypothesis | Included | Validate |
|---|---:|---:|---|---|
| Starter | ₹999–₹1,199 | Up to 200–250 | Core academic/fees/attendance workflows, defined remote support | willingness to pay, feature fit |
| Standard | ₹1,499–₹1,999 | Up to 500 | Starter + higher student limit and agreed service allotment | conversion of 250–500 student schools |
| Growth | ₹2,499–₹3,499 | Up to 1,000 | More capacity and negotiated onboarding/support allotment | larger-school expectations |
| Custom | Quoted separately | 1,000+ or unusual requirements | Signed scope | economics of bespoke requests |

All fees above are **illustrative positioning hypotheses**, not validated 2026 market rates or tax advice. Specify applicable GST/taxes and billing period in the eventual price sheet. Annual discount and grace period must be approved.

### 20.2 Onboarding and service limits

- Indicative one-time onboarding/migration: **₹2,500–₹7,500**, finalised by actual student count, data quality, onsite travel and training requirement. Promotional waiver should be visible as a discount, not an unlimited free-service promise.
- Define an included standard spreadsheet template, data-cleanup cap, number/duration of training sessions, included support channel/hours, response targets and escalation path.
- Price separately or approve exceptions for scans/register transcription, repeated migrations, extra onsite visits, custom forms/reports, custom code and integration requests.
- Record minutes spent on each onboarding and monthly support ticket. Evaluate gross margin per school, not just hosting bills.
- Pricing changes to existing schools require contractual notice and renewal policy; expiry must retain an agreed export/recovery path rather than deleting data.

### 20.3 Unit-economics worksheet

Calculate for each plan: `net realised subscription revenue (ex tax) – payment collection costs – allocated hosting/backup/monitoring – monthly support labour – amortised onboarding subsidy – allocation for maintenance = contribution`. Track one-time onboarding contribution separately. Do not treat developer/founder support as free.

**Illustration, not forecast:** ₹999 × 12 = ₹11,988 annual gross subscription receipts before tax adjustments. If annual setup + support totals 17 hours and internal labour cost is ₹300/hour, direct labour alone is ₹5,100; 29 hours would cost ₹8,700. This excludes hosting, sales, development and other costs. The model only works if onboarding and ongoing support are predictable.

### 20.4 Sales positioning

Lead with **fast school adoption, simple fee receipts, attendance, reports, local onboarding and honest support**. Avoid competing on the number of modules. Demonstrate real workflows on an anonymised school dataset rather than promising every potential feature. Do not promise unlimited schools, unlimited students or indefinite free add-ons in a ₹999 plan.

## 21. Delivery gates, success metrics and decision register

### 21.1 Before coding: discovery gate

Interview at least three, preferably five, target schools with differing student counts and IT maturity. Collect *anonymised* receipt and fee-register samples, student spreadsheet, year-end promotion procedure, attendance sheet, marksheet/report card and parent communications. Record frequency, owner, current workaround, exceptions, must-have vs nice-to-have and willingness to pay. Sign off decision register before permanent schema work.

### 21.2 Phase ownership and sign-off table

| Gate | Exit evidence | Responsible sign-off |
|---|---|---|
| G0 Discovery | 3–5 interviews, workflow map, sample templates, price/support assumptions | Product owner + pilot representatives |
| G1 Architecture | Threat model, tenancy architecture, schema ERD, permission matrix, hosting/backup design | Lead developer + independent technical reviewer |
| G2 Foundation | Automated adversarial tenant tests; MFA, private storage, audit and environment boundaries | QA/security reviewer |
| G3 Data + lifecycle | Migration test, student/enrollment history, year rollover preserved | School admin pilot representative |
| G4 Finance | Manually reconciled sample cases, duplicate-post test, daily close, reversal audit | Pilot accountant + QA |
| G5 Academics/parents | Correct mark calculations, report card variants, parental access regression | Pilot teacher + guardian test users |
| G6 Operations | Performance/load tests, incident drill, actual backup restore, export/exit test | Ops owner + product owner |
| G7 Paid launch | 2–3 pilot schools signed off; no critical auth/tenant/finance/data-loss defects; pricing & legal terms approved | Product owner |

### 21.3 Pilot metrics (measure weekly)

- Adoption: percentage of target teachers taking attendance in ERP, fraction of intended student/fee records migrated, parent login activation and repeat usage.
- Reliability: errors per key transaction, failed imports, missed backups, restore test result, critical incident count.
- Finance: count and amount of reconciled fee postings, void/reversal rate, unsupported balances, wrong/duplicate receipts (target zero).
- UX: time to record attendance for typical class, time to find student, complete receipt and print report card.
- Commercial: onboarding hours, support minutes per school/month, paid renewal intent, objections and realised monthly contribution per school.
- Security: cross-tenant test suite pass rate (must be 100%), unresolved high/critical vulnerabilities (must be zero for paid launch).

### 21.4 Decisions register (do not silently guess)

| ID | Decision to validate | Proposed starting default | Required evidence/owner |
|---|---|---|---|
| D01 | Laravel/PHP/Livewire versions | Supported versions; PHP 8.4 candidate | Technical architecture sign-off |
| D02 | Hosting/database/backup budget | Managed or well-operated single regional deployment | Costed load and restore tests |
| D03 | Fee allocation and reversal | Invoice/charge ledger and correction events | Accountant-approved example cases |
| D04 | Receipt numbering and voids | Unique per school + agreed year scope | Receipt examples + financial sign-off |
| D05 | Annual rollover and dues carryforward | Separate student_enrollments; carry as opening entry | School admin workflows |
| D06 | Report card grading/templates | 1–3 validated configurable print templates | Anonymised pilot report cards |
| D07 | Parent verification/MFA | School-verified guardian link, account invitation | Pilot school guardian procedure |
| D08 | Salary vs certificates/ID cards | Salary conditionally deferred | Priority ranking from schools |
| D09 | Support and migration inclusions | Bound time/quantity; bill overruns | Pilot actual hours and price acceptance |
| D10 | Student limits and price | ₹999 entry plan, tiers as hypothesis | WTP interviews and costs |
| D11 | Retention/export/deletion/child privacy | Minimise; private default; legal review | Indian counsel + school contracts |
| D12 | Grace/expired access | Defined grace; restricted read/export | Approved subscription terms |
| D13 | Recovery targets | ≤1h RPO and ≤4h RTO internal | Verified backup/restore runbook |
| D14 | Cash close and payments modes | Daily cashier report with mode split | Pilot accountant sign-off |

## 22. Claude Code development kickoff protocol and prompts

### 22.1 Working rules

Treat this document as an approved contract **only after G0/G1 sign-offs**. Build in short vertical slices (migration/model + authorization + business action + UI + tests), not one giant generation request. Include malicious/cross-tenant cases in every slice. Any unclear financial, privacy or year-end rule blocks that part of implementation until decided; do not invent defaults that affect real money/child data. Make all code reviewable and keep the actual executed test results in the task summary.

### 22.2 Prompt 1 — architecture only (do not code)

> Read `SCHOOL_ERP_V1_1_MASTER_PLAN.md` in full. Do not generate code yet. Produce a concise architecture decision record for a shared-MySQL, modular-Laravel school ERP: supported runtime versions; server-rendered UI approach; identity and school membership; per-request tenant context; cross-tenant prevention for database joins, jobs, cache, storage, exports and search; planned schema ERD including student_enrollments and immutable fee ledger; roles/permissions; MFA; audit logging; private files; performance budgets; backup/PITR restore plan; staging/production separation; costs and operational risks. Identify every D01–D14 decision that must be signed off before its module can be coded. End with a review checklist. **Do not create migrations or UI until I approve this architecture.**

### 22.3 Prompt 2 — secure foundation only (after approval)

> Implement only Phase 1 secure foundation from `SCHOOL_ERP_V1_1_MASTER_PLAN.md` using the approved architecture record. Provide migrations, validated school-context resolver, permission policies, membership model, mandatory Super Admin MFA, private storage adapter, security middleware, audit events and test factories. Implement automated cross-tenant attacks for direct IDs, nested relationships, cache keys, exports and queued jobs. Do not implement fees or academic modules yet. Run and report actual automated tests, including failures and known limitations. Do not claim production readiness without external review and restore drill.

### 22.4 Prompt 3 — student academic lifecycle (after G2)

> Implement school setup, student/guardian models, enrollment history and a preview-and-commit year-end promotion workflow with mixed-case fixtures. Prevent duplicate enrollments and preserve previous-year marks/fees/attendance associations. Add idempotence, authorized corrections and tests for concurrent submissions and cross-tenant references. Deliver short UX notes and verified tests.

### 22.5 Prompt 4 — fee ledger design review (before coding fees)

> Using anonymised pilot receipt and fee register examples and the V1.1 plan, draft the exact fee state machine, charge/line/payment/allocation/reversal schema, money representation, receipt numbering transaction and daily close rules. Include at least 20 numerical reconciliation cases and 10 hostile/concurrent submission tests. Do not code the fee module until the school accountant approves the proposed rules.

### 22.6 Change control / version log

| Version | Date | Summary |
|---|---|---|
| 1.0 | 28 Oct 2026 | Initial baseline SRS and phase plan supplied by product owner |
| 1.1 | 28 Oct 2026 | Consolidated review: tenancy defence-in-depth, annual rollover, immutable fee ledger/daily close, MFA, recovery goals, performance, commercial unit economics, legal/privacy and release gates |

**Final caution:** This is an implementation plan; it is not a completed cybersecurity penetration test, compliance opinion, measured cloud bill, product-market fit proof or final customer price sheet.
