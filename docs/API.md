# FitFlow Pro — API & Integration Notes

FitFlow Pro is a server-rendered Core PHP application (no framework) with a
first-party **JSON API** in `api/` that powers the AJAX layer of the
upgraded dashboards (live tables, modal CRUD, dashboard charts, toasts).

## Route map (server-rendered)

| Area | Entry points |
|---|---|
| Public website | `public/index.php`, `about.php`, `features.php`, `plans.php`, `trainers.php`, `programs.php`, `facilities.php`, `gallery.php`, `pricing.php`, `blog.php`, `blog-details.php`, `contact.php`, `privacy.php`, `terms.php` |
| Auth | `public/login.php`, `register.php`, `logout.php`, `forgot-password.php`, `reset-password.php`, `accept-invite.php` |
| Super Admin | `super-admin/dashboard.php`, `gyms.php`, `users.php`, `plans.php`, `subscriptions.php`, `payments.php`, `reports.php`, `settings.php` + classic modules in `admin/` (coupons, invoices, tickets, announcements, logs) |
| Gym Admin | `admin/dashboard.php`, `members.php`, `memberships.php`, `trainers.php`, `attendance.php`, `payments.php`, `receipt.php`, `workouts.php`, `diet-plans.php`, `classes.php`, `equipment.php`, `staff.php`, `reports.php`, `settings.php` |
| Classic Gym workspace | `tenant/*.php` (23 pages: members, enroll, renewals, payments, attendance, checkin, trainers, schedules, workouts, exercises, diet, classes, bookings, equipment, maintenance, staff, branches, reports, subscription, support, settings) |
| Trainer portal | `trainer/dashboard.php`, `members.php`, `schedule.php`, `workouts.php`, `diet-plans.php`, `attendance.php`, `progress.php` |
| Member portal | `member/dashboard.php`, `index.php`, `membership.php`, `payments.php`, `attendance.php`, `workouts.php`, `diet.php`, `goals.php` |

Login redirects by role: Super Admin → `super-admin/dashboard.php`,
Trainer → `trainer/dashboard.php`, Member → `member/dashboard.php`,
gym staff roles → `admin/dashboard.php`.

## JSON API (`api/`)

**Conventions**

- Session-cookie auth (same login as the pages). Unauthenticated calls
  return `401 {"ok":false,"error":"…"}` — never an HTML redirect.
- Every response is JSON: success `{"ok":true, …}`, errors
  `{"ok":false,"error":"message"}` with HTTP 401 / 403 / 404 / 419 / 422 /
  500 as appropriate. SQL errors are never leaked.
- All state-changing `POST` calls require the CSRF token: fetch it from
  `GET api/csrf.php`, then send it as the `csrf_token` form field or the
  `X-CSRF-Token` header. A missing/invalid token returns `419`.
- Tenant isolation: every query is scoped to the caller's `tenant_id`;
  role + granular permission checks mirror the page guards. Medical data
  additionally requires the `members.medical` permission.
- The shared JavaScript layer lives in `public/assets/js/app.js`
  (`window.FF`): `FF.api()`, `FF.toast()`, `FF.confirm()`,
  `FF.liveTable()`, AJAX modal forms (`form[data-ajax]`), dashboard
  refresh loaders (`FF.onRefresh`) and Chart.js registry (`FF.charts`).

**Endpoints**

| Endpoint | Methods | Purpose |
|---|---|---|
| `api/csrf.php` | GET | Session CSRF token for AJAX clients |
| `api/dashboard-stats.php?from&to` | GET | Gym dashboard KPIs: total/active members, new registrations, expired + expiring-soon, verified revenue (range), pending payments, today's check-ins, trainers, active classes, equipment alerts + expiring/expired/recent lists |
| `api/charts.php` | GET | Chart.js series from real rows: monthly revenue (6 mo), membership growth, daily attendance (14 d), plan distribution, trainer performance |
| `api/members.php` | GET list (q/status/branch_id/plan/trainer_id/page/per_page), GET `?id=` detail | Members with computed membership status + days left; detail includes membership history, goals, progress, workout logs, trainer, and medical record (permission-gated) |
| `api/members.php` | POST `action=create\|update\|delete\|progress\|medical_save\|membership_status\|change_plan` | Member CRUD, body-measurement logging, medical clearance save (restricted), membership suspend/reactivate/cancel/renew, plan upgrade (old active membership cancelled, new one enrolled) |
| `api/members-export.php` | GET (same filters as list) | CSV download of members (no medical data, ever) |
| `api/attendance.php` | GET `?view=today\|trainers\|report&period=daily\|weekly\|monthly\|corrections` | Today's sessions, trainer attendance, aggregated reports, correction audit trail |
| `api/attendance.php` | POST `action=scan\|manual_in\|out\|trainer_toggle\|correct` | QR toggle in/out; manual check-in (duplicate active session refused); check-out; trainer in/out; correction with mandatory reason → `attendance_corrections` + audit log |
| `api/payments.php` | GET (status/purpose filters, pagination), POST `action=record\|verify\|fail` | Recorded payments start **pending**; `verify` is the only path to `verified` and marks the linked invoice paid |
| `api/finance.php` | GET `?view=summary\|expenses\|salaries&period` , POST `action=expense_create\|expense_delete\|salary_create\|salary_pay\|salary_delete` | Revenue vs expenses vs salaries + net; expense and salary management |
| `api/notifications.php` | GET, POST `action=read\|read_all\|send` | Notification bell data; staff broadcast to the gym |
| `api/super-stats.php` | GET (Super Admin only) | Platform KPIs (tenants, users, members, MRR/ARR, verified/pending revenue, open tickets), revenue series, subscriptions by plan, pending gym approvals, recent tenants/payments |

## Model layer (internal API)

All data access goes through PDO models in `app/Models/`. Key contracts:

- `Auth::attempt($email, $password)` — rate-limited login; returns user row.
- `Tenant::register($gym, $owner, $trialPlanId, $trialDays)` — transactional
  gym onboarding (tenant + owner + settings + trial subscription).
- `Membership::enrol($tenantId, $memberId, $planArray, $couponId)` — creates
  a membership with computed end date/price; `renew()` extends from the
  later of today / current end; `sweepExpired()` expires lapsed rows;
  `computedStatus()` / `daysLeft()` give automatic display status;
  `suspend()` / `reactivate()` / `cancelMembership()` / `changePlan()`
  cover the lifecycle (upgrades).
- `Attendance::scanQr($tenantId, $qrToken)` — toggles check-in/check-out;
  `manualCheckIn()` refuses duplicate active sessions; `correct()` writes
  an audit row per changed field before updating.
- `TrainerAttendance::toggle()` — trainer shift in/out with the same
  duplicate protection.
- `ClassBooking::book($tenantId, $classId, $memberId)` — capacity-checked.
- `Payment::recordManual()` — always inserts `pending`;
  `Payment::verify($id, $verifierId)` — the only transition to `verified`.
- `Subscription::changePlan($tenantId, $planId, $cycle)` — SaaS plan
  change with a fresh billing period; `stats()` returns MRR/ARR.
- `plan_allows($tenantId, $resource)` — plan-limit enforcement used before
  creating members/trainers/branches/classes/staff.
- Upgrade models: `Expense`, `StaffSalary`, `MedicalRecord`,
  `TrainerAttendance`, `WorkoutLog` (see `database/migrations/`).

## Webhooks (payment gateways)

When a gateway is integrated, its webhook handler must verify the gateway
signature server-side and only then call `Payment::verify()`. Client-side
"payment succeeded" flags must never be trusted — this mirrors the manual
payment rule enforced throughout the product.
