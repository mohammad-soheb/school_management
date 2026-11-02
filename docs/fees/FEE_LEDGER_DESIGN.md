# Fee ledger design (Phase 7A)

Status: **DRAFT FOR ACCOUNTANT REVIEW** (master plan §22.5, plan.md Phase 7A).
Scope: FR-401 – FR-416, §5 rules 4–5, §8.3, §10.3, §17.4 (release gate), §18.3, decisions D03, D04, D05, D14.
The 7B build implements exactly this document; every numbered case below is an automated test
(`tests/Feature/Fees/ReconciliationCasesTest.php`, `tests/Feature/Fees/HostileCasesTest.php`).

Rules marked **[SIGN-OFF]** are proposed defaults taken from the decision register (§21.4). They are implemented,
configurable where noted, and **must be confirmed by the school accountant before real money is handled**.
A consolidated list is in §13.

---

## 1. Principles

1. **Money is integer paise.** Every amount column is `BIGINT` paise named `*_paise`; input is parsed with
   `Money::parse()` (max two decimals, no exponent, no floats) and shown with `Money::format()`. No `float`,
   `double` or `DECIMAL` column exists in the fee schema (asserted by a test).
2. **Posted means immutable.** Charges, charge lines, allocations, reversals and opening balances are insert-only;
   model hooks throw on update/delete. A payment row may change only once: `posted → reversed` (status + who/when).
   An adjustment may change only while `pending`. A cashier day may change only while `open` (then only review fields).
3. **Corrections are new rows**: reversal rows with compensating (negative) allocations, adjustment rows, opening-balance rows.
   Nothing is ever re-calculated "to fit" (FR-409, FR-415, FR-416, §17.4).
4. **One transaction per business event**, with explicit row locks and a fixed lock order (§7). Either everything
   (payment, receipt number, allocations, audit event) commits, or nothing does and no receipt is shown (§17.4).
5. **Tenant isolation**: every table has `school_id`, composite foreign keys `(school_id, x_id) → x(school_id, id)`,
   business keys unique per school, and every id arriving from a browser is validated with `TenantRule` and then
   re-checked in the service against the student it is used for (FR-408).
6. **Audit**: every write records `AuditLogger::record()` with actor and timestamp (§17.4 "traceable").

## 2. Vocabulary and derived amounts

| Term | Meaning |
|---|---|
| Fee head | A kind of fee (Tuition, Annual charges, Exam fee, Transport …). |
| Fee plan | The fee structure of one class in one academic year. |
| Plan line | One head in a plan: amount per instalment, frequency, number of instalments, first due date. |
| Charge | A posted fee bill for one student (header). |
| Charge line | One dated obligation on a charge: head, instalment, amount, due date. **The unit everything else points at.** |
| Adjustment | Approved change to what a line owes: discount / scholarship / waiver (reduce), fine (increase), carry-forward (system, reduce). |
| Payment | Money received, with one receipt number. |
| Allocation | Link "x paise of payment P settle line L". Signed: positive allocates, negative (reversal) un-allocates. |
| Credit | The unallocated part of non-reversed payments (an advance). A liability, never shown as a negative due. |

For a charge line *L*:

```
line_due(L)        = amount_paise + Σ approved fines(L) − Σ approved reductions(L)
line_allocated(L)  = Σ allocations(L).amount_paise          (reversals contribute negative rows)
line_outstanding(L)= line_due(L) − line_allocated(L)         INVARIANT: 0 ≤ outstanding ≤ due
```

For a student *S* (`FeeLedger::summaryFor()`, all amounts paise):

```
charged  = Σ amount_paise of all charge lines (plan charges + opening balances)
adjusted = Σ fines − Σ reductions (signed; negative means concessions/carry-forward exceed fines)
paid     = Σ amount_paise of payments with status "posted" (reversed payments excluded)
balance  = Σ line_outstanding                      (what is still owed; never negative)
credit   = paid − Σ allocations of posted payments (unapplied advance; never negative)

IDENTITY (tested in every case):  charged + adjusted − paid  =  balance − credit
```

### 2.1 Derived line status (not stored — it can never go stale)

| Status | Condition |
|---|---|
| `due` | outstanding > 0, allocated = 0, due_on ≥ today |
| `overdue` | outstanding > 0 and due_on < today (any allocation state) |
| `partially_paid` | outstanding > 0, allocated > 0, due_on ≥ today |
| `paid` | outstanding = 0 and allocated > 0 |
| `waived` | outstanding = 0, allocated = 0, reductions > 0 |
| `carried_forward` | outstanding = 0 because of a carry-forward adjustment |

## 3. State machines

```
Payment          posted ──(reverse: fees.payments.reverse, reason, open day)──▶ reversed      (terminal)
Adjustment       pending ──approve (fees.adjustments.approve, ≠ requester)──▶ approved     (terminal, immutable)
                 pending ──reject (approver, note)──▶ rejected                              (terminal)
                 pending ──cancel (requester or approver)──▶ cancelled                      (terminal)
                 carry_forward adjustments are created directly as approved by the system.
Cashier day      (no row) ─first payment/reversal or close─▶ open ──close (fees.close.manage)──▶ closed
                 closed ──review (fees.close.review, ≠ closer)──▶ closed + reviewed          (no reopen in V1)
Charge / line    posted (terminal, immutable). Status is derived (§2.1).
Opening balance  posted (terminal, immutable) — creates a charge with source "opening_balance".
Receipt          issued with the payment; a reversed payment's receipt keeps its number and prints "REVERSED".
```

There is **no** "draft" payment, "edit" payment or "delete" action anywhere.

## 4. Schema (migrations `2026_11_05_*`)

All tables: `id`, `school_id` (via `$table->tenant()`: FK + `UNIQUE(school_id,id)`), timestamps unless noted.
"→" means a composite tenant FK. User columns are plain FKs to `users`.

| Table | Columns (besides id/school_id) | Keys |
|---|---|---|
| `fee_heads` | name, code, description?, is_active, sort_order | UNIQUE(school_id, code), UNIQUE(school_id, name) |
| `fee_plans` | →academic_year_id, →school_class_id, name, notes?, is_active, created_by | UNIQUE(school_id, academic_year_id, school_class_id) |
| `fee_plan_lines` | →fee_plan_id, →fee_head_id, amount_paise, frequency, installments, first_due_on, is_active, sort_order | UNIQUE(school_id, fee_plan_id, fee_head_id) |
| `fee_charge_batches` | →academic_year_id, →fee_plan_id?, due_until, idempotency_key, students_count, lines_count, total_paise, posted_by | UNIQUE(school_id, idempotency_key) |
| `fee_charges` | →fee_charge_batch_id?, →student_id, →student_enrollment_id, →academic_year_id, source (plan/opening_balance), total_paise, posted_on, posted_by | INDEX(school_id, student_id) |
| `fee_charge_lines` | →fee_charge_id, →student_id, →fee_head_id?, →fee_plan_line_id?, installment_no?, description, amount_paise, due_on | **UNIQUE(school_id, student_id, fee_plan_line_id, installment_no)** = posting idempotency; INDEX(school_id, due_on) |
| `fee_adjustments` | →student_id, →fee_charge_line_id, type, amount_paise (>0), effect_paise (signed), reason, status, requested_by, decided_by?, decided_at?, decision_note?, →fee_opening_balance_id?, idempotency_key? | UNIQUE(school_id, idempotency_key), INDEX(school_id, status) |
| `fee_receipt_sequences` | scope_key ('2026-27' or 'all'), last_number | **UNIQUE(school_id, scope_key)** — the row locked FOR UPDATE |
| `fee_payments` | →student_id, →academic_year_id, receipt_no, receipt_number, amount_paise (>0), mode, reference?, instrument_date?, paid_on, received_by, remarks?, status, idempotency_key, reversed_at?, reversed_by? | **UNIQUE(school_id, receipt_no)**, **UNIQUE(school_id, idempotency_key)**, INDEX(school_id, paid_on) |
| `fee_payment_reversals` | →fee_payment_id, amount_paise, reason, reversed_on, reversed_by | **UNIQUE(school_id, fee_payment_id)** — one reversal per payment |
| `fee_allocations` | →fee_payment_id, →fee_charge_line_id, →student_id, amount_paise (signed), kind (payment/credit/reversal), allocated_on, →fee_payment_reversal_id?, created_by | INDEX(school_id, fee_charge_line_id), INDEX(school_id, fee_payment_id) |
| `fee_opening_balances` | →student_id, →student_enrollment_id, →academic_year_id, amount_paise, source (manual/import/carry_forward), source_reference?, notes?, →carried_from_enrollment_id?, →fee_charge_id?, idempotency_key?, created_by | **UNIQUE(school_id, carried_from_enrollment_id, student_enrollment_id)** (carry-forward idempotency), UNIQUE(school_id, idempotency_key) |
| `fee_cashier_days` | business_date, status (open/closed), system totals per mode (cash/upi/bank_transfer/cheque/other)_paise, gross_paise, reversed_paise, net_paise, receipts_count, reversals_count, counted_cash_paise?, difference_paise?, notes?, breakdown (json, by user × mode), closed_by?, closed_at?, reviewed_by?, reviewed_at?, review_note? | **UNIQUE(school_id, business_date)** — the row locked FOR UPDATE |

Mapping to plan.md names: `student_charges → fee_charges`, `charge_lines → fee_charge_lines`,
`payment_allocations → fee_allocations`, `receipt_sequences → fee_receipt_sequences`,
`opening_balances → fee_opening_balances`, `cashier_closes → fee_cashier_days` (the `fee_` prefix avoids collisions
with the salary and subscription modules).

## 5. Fee setup and charge posting

* **Frequencies** (FR-402): `one_time` (1), `yearly` (1), `half_yearly` (2, every 6 months), `quarterly` (4, every 3
  months), `monthly` (up to 12, every month). `installments` may be lowered (e.g. 11 monthly instalments, no May)
  but not raised. Instalment *k* is due `first_due_on + (k−1) × step months` (no overflow: 31 Jan + 1 month = 28 Feb).
  All due dates must fall inside the plan's academic year.
* **`amount_paise` is the amount of ONE instalment** (monthly tuition ₹1,500 = ₹18,000 a year). **[SIGN-OFF]**
* A plan line whose charges have been posted is frozen (amount, frequency, instalments, first due date); it can only
  be deactivated. A changed fee is a new line/plan for future postings — posted lines never change.
* **Posting** (`ChargePostingService`): choose a plan (or every active plan of the year) and "post instalments due on or
  before *date*". **Preview** (no writes) lists every student × line × instalment that would be created, the total,
  and how many are skipped because they were already posted. **Post** creates one `fee_charge_batches` row (idempotency
  key from the form), one `fee_charges` header per student and one `fee_charge_lines` row per instalment.
* Idempotency per (student, plan line, instalment) is a **database unique key**; re-posting the same window creates
  nothing and a second click with the same form key returns the same batch.
* Who is charged: enrollments of the plan's class in the plan's year with status `active`. **[SIGN-OFF]** A student
  who joined mid-year is not charged monthly/quarterly/half-yearly instalments due before the first day of the month
  they joined; one-time and yearly lines are always charged. **[SIGN-OFF]** `one_time` lines (e.g. admission fee) are
  charged once per student ever: skipped if the student already has a one-time line for the same head in any year.
* Posting into a **locked** academic year is refused.

## 6. Payments, receipts and allocation

### 6.1 Posting a payment (`PaymentPostingService::post`) — one transaction

```
BEGIN
  1. lock day row     INSERT IGNORE fee_cashier_days(school, paid_on, 'open'); SELECT … FOR UPDATE
                      → refuse if status = closed
  2. idempotency      SELECT fee_payments WHERE (school, idempotency_key) FOR UPDATE
                      → found: same student & amount → return it (no new rows); different → refuse
  3. lock student     SELECT students WHERE id FOR UPDATE   (serialises all money for this student)
  4. plan allocation  read open lines (consistent under the student lock), apply allocation order (§6.3)
  5. receipt number   INSERT IGNORE fee_receipt_sequences(school, scope); SELECT … FOR UPDATE;
                      last_number + 1 → "RCP/2026-27/000123"; UPDATE sequence
  6. insert payment, allocations (kind=payment), audit "fee_payment.posted"
COMMIT  → only now redirect to the receipt
on UNIQUE(idempotency_key) violation (a racing twin committed first): ROLLBACK, re-read by key, return it.
```

Validation: amount > 0 and ≤ `max_payment_paise` (₹10,00,000 default); mode in the school's `payment_modes`;
`reference` required for UPI / bank transfer / cheque **[SIGN-OFF]**; `paid_on` not in the future, not more than
`max_backdate_days` (7) in the past **[SIGN-OFF]**, inside an academic year, not on a closed day; selected lines must
belong to this student and school and be outstanding; an advance (amount above total outstanding) is allowed only if
`allow_advance` (default true) **[SIGN-OFF]**.

### 6.2 Receipt numbering (D04)

* Format `{prefix}/{scope}/{number padded to 6}` → `RCP/2026-27/000123`; with `receipt_scope = never` → `RCP/000123`.
* Scope = the academic year **containing `paid_on`** (not the "current" year). **[SIGN-OFF]**
* Sequence per school + scope, incremented inside the payment transaction under `FOR UPDATE`: a failed transaction
  rolls the increment back, so **numbers have no gaps**; `UNIQUE(school_id, receipt_no)` makes a collision impossible
  even if locking were bypassed.
* Reversal never frees or reuses a number; the reversed receipt keeps its number and prints a "REVERSED" stamp.
  The next payment simply takes the next number. **[SIGN-OFF]** (no "cancelled receipt" re-issue in V1).
* Reprints are always allowed and identical; reversed receipts are marked. **[SIGN-OFF]** (no "duplicate copy" mark).

### 6.3 Allocation order (D03)

* **Oldest due first** (school default, `allocation_order = oldest_due_first`): open lines ordered by
  `due_on`, then charge id, then line id. Each line gets `min(remaining, line_outstanding)`.
* **Selected charges**: the cashier ticks lines; they are filled in due-date order; **the remainder becomes credit**
  — it is *not* spread to unticked lines. **[SIGN-OFF]**
* Fines raise the line's due, so a fined line absorbs its fine first (case 12).
* Allocations are never moved later: a mistake is corrected by reversing the payment and posting it again.

### 6.4 Credit / advance

* Credit = unallocated part of posted payments; held per student across academic years; shown as **"Advance / credit"**,
  never as a negative due (§17.4).
* **[SIGN-OFF]** `auto_apply_credit = true`: whenever dues are added (charge posting, opening balance, fine approval,
  carry-forward) credit is applied immediately, oldest payment first → oldest due line first, as allocations of
  `kind = credit` dated that day (no new receipt). With the setting off, the cashier uses **Apply credit** on the ledger.
* Refunds of credit are **out of scope for V1** **[SIGN-OFF]**: an unallocated advance is returned by reversing the
  payment (full reversal only).

### 6.5 Reversal (`ReversalService`)

* Permission `fees.payments.reverse`, a reason (min 5 characters), dated **today** on an **open** day. The original
  payment's day may already be closed: the reversal appears in *today's* close as a reversal. **[SIGN-OFF]**
* One transaction: lock day → lock student → lock payment row; refuse if already reversed (and
  `UNIQUE(fee_payment_id)` on reversals); insert `fee_payment_reversals`; for every line with a positive net allocation
  from this payment (including credit applied later) insert a negative allocation `kind = reversal`; set payment
  `status = reversed, reversed_at, reversed_by`; audit `fee_payment.reversed` with the reason.
* Full reversal only (no partial reversal) **[SIGN-OFF]**. Bounced cheques are handled as reversals with reason.
* No second approver for reversals (permission + 2FA policy only) **[SIGN-OFF]**.

## 7. Locking and concurrency

Lock order (always this order, so no deadlock cycles are possible):
**cashier day row → student row(s) by ascending id → payment row → receipt sequence row.**

| Operation | Locks |
|---|---|
| Post payment | day(paid_on), student, sequence(scope) |
| Reverse payment | day(today), student, payment |
| Post charges (batch) | students (ascending, all in batch) |
| Approve adjustment / apply credit / opening balance / carry-forward | student |
| Close day | day(date) |

Because every payment and reversal holds the **day row** while it writes, a close cannot compute totals while a
payment for that day is being written, and a payment cannot slip in after a close (it waits, then sees `closed`).
This does not depend on MySQL gap locks or the isolation level.

## 8. Adjustments (discount, scholarship, waiver, fine)

* Requested with `fees.adjustments.create` against **one charge line** with an amount and a reason (min 5 chars).
  Status `pending` — **a pending adjustment has no effect on any balance**.
* Approved with `fees.adjustments.approve`. **[SIGN-OFF]** Maker-checker: the approver must be a different user
  (setting `adjustments_self_approval = false`).
* At approval, under the student lock: a reduction may not exceed the line's outstanding (a fully paid line cannot be
  discounted — reverse the payment first) **[SIGN-OFF]**; a fine may not exceed `fine_max_paise` if set **[SIGN-OFF]**.
  Late fines are entered manually; automatic fine calculation is **not** in V1 **[SIGN-OFF]**.
* An approved adjustment is final. A mistake is offset by a new adjustment of the opposite effect with a reason
  **[SIGN-OFF]** (known limitation: it is then reported under that type).

## 9. Opening balances and carry-forward (FR-416, D05)

* Opening balance (`fees.charges.post`): amount, academic year/enrollment, `source` = manual | import |
  carry_forward, source reference (old register / import file), notes. It creates a charge with
  `source = opening_balance` and one line (no fee head) due on the date entered (default: the year's start), so it is
  settled first by oldest-due-first. Reconciliation approval of opening balances is recorded as `created_by` + audit
  only in V1 **[SIGN-OFF]**.
* **Carry-forward** (`App\Contracts\Fees\DuesCarryForward::carryForward($from, $to, $actor)`, called by promotion):
  1. same student, `to` year starts after `from` year, `to` year not locked;
  2. lock the student; if a carry-forward row for (from, to) exists, return its amount (**idempotent**);
  3. outstanding = Σ outstanding of lines on charges of the `from` enrollment;
  4. for each such line insert an approved `carry_forward` adjustment (reduction = its outstanding) so the old year
     shows "Carried forward" and **0 due — the old charges are not edited**; create the opening balance charge in the
     new year (due = new year start) linked to `carried_from_enrollment_id`; apply credit (§6.4);
  5. return the paise carried (0 is recorded too, so a retry returns 0).
  Carry-forward writes adjustments into a locked old year: allowed because it is explicit, linked and audited
  **[SIGN-OFF]**.

## 10. Daily cashier close (FR-407, FR-414, D14)

* One close per school per **business date** (school-local date).
* System totals for the date = payments with `paid_on = date` by mode **minus** reversals with `reversed_on = date`
  by the reversed payment's mode; plus breakdown by receiving user × mode, receipt and reversal counts.
* The closer enters **counted cash**; `difference = counted cash − system cash`. A non-zero difference requires a note.
  UPI / bank / cheque totals are shown for information; this is **not** bank reconciliation (§17.4).
* Closing writes the totals snapshot and locks the date: payments and reversals dated that day are refused.
  Days without collections can be closed (zero totals). Future dates cannot be closed.
* Review: a second user with `fees.close.review` signs off (cannot be the closer). **[SIGN-OFF]** No re-open in V1:
  corrections after close are reversals on an open day.

## 11. Reports and exports

Daily collection (by mode × user, with reversals), monthly collection (per day × mode), defaulters (overdue
outstanding with ageing buckets **1–30, 31–60, 61–90, 90+ days past due date** **[SIGN-OFF]**, filter by class/section),
class-wise dues summary, payment history. All CSV exports neutralise formula injection: any text cell starting with
`= + - @`, tab or CR is prefixed with `'` (plain numbers such as `-500.00` are left numeric).

## 12. Numbered cases (all automated)

Fixture unless stated: academic year **2026-27** (1 Apr 2026 – 31 Mar 2027), "today" = **29 Oct 2026**, Class 5
plan: **TUI** Tuition monthly ₹1,500 × 12 from 10 Apr; **ANN** Annual charges yearly ₹6,000 due 15 Apr;
**EXM** Exam fee half-yearly ₹800 × 2 from 15 Sep. "Post ≤ 30 Apr" = TUI-Apr + ANN = ₹7,500.
Payments are cash on 29 Oct unless stated. Amounts in ₹ (tests use paise).

### 12.1 Reconciliation cases

| # | Scenario | Expected |
|---|---|---|
| R1 | Post ≤ 30 Apr; pay 7,500 | allocations TUI-Apr 1,500, ANN 6,000; charged 7,500, paid 7,500, balance 0, credit 0 |
| R2 | Post ≤ 30 Apr; pay 5,000 then 2,500 | after 1st: TUI-Apr 1,500 + ANN 3,500, balance 2,500; after 2nd balance 0; receipts RCP/2026-27/000001, …000002 |
| R3 | Post ≤ 30 Jun (10,500); pay 8,000 | TUI-Apr 1,500, ANN 6,000, TUI-May 500; balance 2,500 (May 1,000 + Jun 1,500) |
| R4 | Post ≤ 30 Jun; pay 2,000 **selecting TUI-Jun** | TUI-Jun 1,500; credit 500; balance 9,000 (Apr/May/ANN untouched) |
| R5 | Post ≤ 30 Apr; pay 10,500 | allocations 7,500; credit 3,000; balance 0; paid 10,500 |
| R6 | R5, then post ≤ 30 Jun | credit applied TUI-May 1,500 + TUI-Jun 1,500 (kind credit); balance 0, credit 0; still one receipt |
| R7 | Post ≤ 30 Apr; pay 8,500 (credit 1,000); post ≤ 30 Jun | TUI-May 1,000 from credit; balance 2,000 (May 500 + Jun 1,500); credit 0 |
| R8 | Post ≤ 30 Apr; discount 600 on ANN approved; pay 6,900 | charged 7,500, adjusted −600, paid 6,900, balance 0 |
| R9 | Post ≤ 30 Apr; discount 600 on ANN **pending**; pay 7,500; then approve | balance 7,500 before payment (pending has no effect); approval refused (line fully paid); stays pending; balance 0 |
| R10 | Post ≤ 30 Apr; scholarship 750 on TUI-Apr; pay 6,750 | TUI-Apr due 750; balance 0; adjusted −750 |
| R11 | Post ≤ 30 Sep (TUI Apr–Sep 9,000 + ANN 6,000 + EXM-1 800 = 15,800); waive EXM-1 800; pay 15,500 | balance 0; credit 500; EXM-1 status waived (allocated 0) |
| R12 | Post ≤ 30 Apr; fine 100 on TUI-Apr; pay 7,500 | TUI-Apr 1,600, ANN 5,900; balance 100; adjusted +100 |
| R13 | Post ≤ 30 Apr; pay 7,500 (000001); reverse; pay 1,500 | after reversal balance 7,500, paid 0, 000001 reversed, audit `fee_payment.reversed`; new receipt 000002 → TUI-Apr; balance 6,000 |
| R14 | Post ≤ 30 Apr; pay 10,500 (credit 3,000); post ≤ 30 Jun (credit applied); reverse the payment | balance 10,500 (all four lines open); credit 0; paid 0 |
| R15 | Post ≤ 30 Apr; pay 5,000 (A) and 2,500 (B); reverse A | balance 5,000 = TUI-Apr 1,500 + ANN 3,500; B's ANN 2,500 stays |
| R16 | Opening balance 2,500 (import, due 1 Apr, ref OLD-REG-17); post ≤ 30 Apr; pay 4,000 | opening 2,500 then TUI-Apr 1,500; balance 6,000; charged 10,000 |
| R17 | 2026-27: post ≤ 30 Apr, pay 5,500 (ANN 2,000 open). Carry forward to a 2027-28 enrollment; call it twice | returns 200000 both times; old year balance 0 (carry_forward −2,000 on ANN); new opening line 2,000 due 1 Apr 2027; summary charged 9,500, adjusted −2,000, paid 5,500, balance 2,000; one opening balance row |
| R18 | 29 Oct: cash 1,500, UPI 2,000, cash 700, cheque 1,000; reverse the 700 the same day; counted cash 1,450 | system cash 1,500, UPI 2,000, cheque 1,000, gross 5,200, reversed 700, net 4,500; difference −50 (note required); afterwards a payment dated 29 Oct is refused |
| R19 | Class 5 with S1, S2, S3; post ≤ 31 Oct (17,300 each); S1 pays 17,300, S2 pays 9,000, S3 nothing | class charged 51,900, collected 26,300, outstanding 25,600; defaulters S3 17,300 (1–30: 1,500 · 31–60: 2,300 · 61–90: 1,500 · 90+: 12,000), S2 8,300 (1,500 · 2,300 · 1,500 · 3,000) |
| R20 | Post ≤ 30 Apr; discount 1,000 on ANN; fine 50 on TUI-Apr; pay 7,000 | TUI-Apr 1,550, ANN 5,000; balance 0; credit 450; charged 7,500, adjusted −950, paid 7,000; identity −450 = 0 − 450 |

### 12.2 Hostile and concurrent cases

| # | Attack / race | Expected |
|---|---|---|
| H1 | Double click: same form (same idempotency key) submitted twice | one payment, one receipt; both responses lead to the same receipt |
| H2 | Retry with the same key but a different amount or student | refused; still one payment |
| H3 | Racing twin commits first (unique key violation inside the transaction) | `UNIQUE(school_id, idempotency_key)` rejects the row; the service returns the existing receipt |
| H4 | Receipt contention | sequence row read `FOR UPDATE`; 5 payments + 1 reversal + 1 payment → 000001…000006 contiguous, unique; a duplicate `receipt_no` insert is rejected by the database |
| H5 | Two schools post at the same time | each school's sequence starts at 000001 independently |
| H6 | Cross-school ids in bodies (student, line, head, plan, class) | validation error; nothing written |
| H7 | Tampered money: `0`, `-5`, `1e3`, `12.345`, `abc`, above maximum; selected line of another student | validation error; nothing written |
| H8 | Reverse twice (double click on Reverse) | second attempt refused; one reversal row (`UNIQUE(fee_payment_id)`) |
| H9 | Closed day: post or reverse dated on a closed day; post in the future or beyond the back-date limit | refused; close/payment serialise on the day row (`FOR UPDATE`) |
| H10 | Privilege: teacher/parent → 403 on every fee route; accountant without `payments.reverse` → 403; requester approving own adjustment → refused | as stated |
| H11 | Charge posting run twice (same key, then a new key) | same batch returned; second run creates 0 lines |
| H12 | Editing or deleting posted rows through the model; mass-assigning `school_id`, `receipt_no`, `status` | exception / ignored |

## 13. Rules needing accountant sign-off

1. Plan line amount is per instalment (§5).
2. Mid-year joiners skip periodic instalments due before their joining month; one-time lines charged once per student ever (§5).
3. Only `active` enrollments are charged (§5).
4. Allocation default oldest-due-first; tie-break charge id, line id (§6.3).
5. Selected-charge payments put the remainder into credit, not into other lines (§6.3).
6. Advance payments allowed by default (`allow_advance`) (§6.1).
7. Credit auto-applied when new dues are added (`auto_apply_credit`) (§6.4).
8. No credit refunds in V1; advance returned only by full reversal (§6.4).
9. Receipt number scope = academic year containing the payment date; format `RCP/2026-27/000123` (§6.2).
10. Reversed receipts keep their number, never reused; no re-issue/duplicate marking (§6.2).
11. Full reversals only, dated today, allowed for payments of already-closed days; no second approver (§6.5).
12. Reference mandatory for UPI, bank transfer and cheque; cheque counted on receipt date (§6.1).
13. Back-dating limit 7 days; no future dates (§6.1).
14. Adjustment maker-checker (approver ≠ requester) (§8).
15. Reductions capped at line outstanding (no discount on a fully paid line) (§8).
16. Fines manual only, optional cap `fine_max_paise` (§8).
17. Approved adjustments corrected only by an offsetting adjustment (§8).
18. Opening balances: no separate reconciliation approval in V1 (§9).
19. Carry-forward allowed into a locked old year as linked adjustments (§9).
20. Daily close per school per date, counted cash only, reviewer ≠ closer, no re-open (§10).
21. Ageing buckets 1–30 / 31–60 / 61–90 / 90+ days past due (§11).
