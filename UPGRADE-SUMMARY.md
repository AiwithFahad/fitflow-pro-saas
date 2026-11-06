# FitFlow Pro — Upgrade Summary

Upgrade of the existing batch-2 application (no rebuild, no redesign).
The black/charcoal/orange branding, logo, typography, homepage, sidebar
design and dashboard layout system (`.kpi` / `.panel` / `.status-pill`,
Bootstrap 5 + Chart.js, `public/assets/css/style.css`) are unchanged —
every new page reuses the same shells and classes. All classic pages in
`tenant/`, the legacy platform modules in `admin/`, and the member pages
still work exactly as before.

## What changed at a glance

- **4 role dashboard trees** at the required paths (separate navigation,
  server-side role + permission enforcement, `tenant_id` isolation on
  every query): `super-admin/`, `admin/` (gym), `trainer/`, `member/`.
- **Real JS layer** in `public/assets/js/app.js` (`window.FF`): Fetch/AJAX
  with CSRF, toast notifications, confirm dialogs, live tables (search +
  filters + pagination), AJAX modal forms, dashboard date filters +
  refresh, Chart.js registry.
- **JSON API** in `api/` (11 endpoints) returning real database
  aggregates — no hardcoded chart data, no dummy buttons.
- **3 numbered, non-destructive migrations**, folded into `database.sql`
  (now 42 tables). Demo seeds and logins unchanged.

## Pages by role

### Super Admin — `super-admin/` (shell: `views/super-admin/`)
| Page | Functionality |
|---|---|
| `dashboard.php` | Platform KPIs (gyms by status, users, members, MRR/ARR, verified vs pending revenue), revenue + plan charts fed by `api/super-stats.php`, refresh button, pending-gym approval queue (approve/suspend) |
| `gyms.php` | All tenants with plan, usage vs limits (members/trainers/accounts), subscription state; approve / activate / suspend / reactivate / cancel (subscription kept consistent), trial extension (+days modal) |
| `users.php` | All platform accounts with role/gym filters; suspend/reactivate (cannot suspend yourself) |
| `plans.php` | SaaS plan CRUD: prices, trial days, usage limits (members/trainers/branches/classes/staff/storage, −1 = unlimited), features, active flag; delete blocked while subscriptions exist |
| `subscriptions.php` | All subscriptions with MRR/ARR stats; change plan (fresh period), extend (+days), cancel |
| `payments.php` | All payments with totals; verify (only pending → verified, linked invoice paid) / fail. Payments are never auto-verified |
| `reports.php` | Date-range analytics: verified revenue, new gyms/members, revenue trend + plan charts, top gyms, churn list, revenue CSV export |
| `settings.php` | Live platform configuration (read from real config files — no fake form), announcement management (create/hide/delete), links to coupons/invoices/tickets/logs modules |

Legacy note: the old `admin/payments.php` and `admin/settings.php` held
Super Admin pages; those functions now live in `super-admin/` (the
`admin/` path is the gym tree, per the required structure). Coupons,
invoices, tickets, announcements and logs remain in `admin/` and are
linked from the new super-admin sidebar. `views/admin/header.php`
navigation was repointed accordingly.

### Gym Admin — `admin/` (shell: `views/gym-admin/`)
| Page | Functionality |
|---|---|
| `dashboard.php` | Date-filterable KPIs (total/active members, new registrations, expired, expiring ≤7d, verified revenue, pending payments, today's check-ins, trainers, active classes, equipment alerts) + 5 charts (revenue, attendance 14d, growth, plan distribution, trainer performance); refresh re-fetches `api/dashboard-stats.php` + `api/charts.php` |
| `members.php` | AJAX live table (search incl. member ID + QR, status/branch/plan filters, pagination), modal create/edit (photo URL, address, emergency contacts, auto member code `FFP-M…` + QR token, plan + coupon at signup), profile modal (membership countdown bar, goals, progress, workout logs), restricted medical section, progress logging, membership ops (renew/suspend/reactivate/cancel), CSV export of the filtered list |
| `memberships.php` | Assign membership (coupon-aware, payment recorded **pending** + invoice), plan CRUD with monthly/quarterly/half-yearly/yearly durations, full membership table with auto status + days left, renew/suspend/reactivate/cancel via API, printable receipt links, “send renewal reminders” (real in-app notifications to member portal accounts) |
| `trainers.php` | Trainer CRUD incl. **qualifications**, specializations, experience; per-trainer weekly schedule editor; assigned-member counts |
| `attendance.php` | Check-in desk: QR scan (toggle in/out), manual check-in with **duplicate active-session refusal**, check-out, trainer attendance in/out, daily/weekly/monthly report chart + table, correction modal (reason required) + audit trail table |
| `payments.php` | AJAX payments table (status/purpose filters), record-payment modal (starts pending), verify/fail with confirm dialogs, receipt links; finance strip (month revenue/expenses/salaries/net) + expenses table with add/delete |
| `receipt.php` | Standalone printable receipt (payment + linked invoice, honest pending wording) |
| `workouts.php` | Workout builder: structured exercise rows (day, sets, reps, weight, duration) stored as JSON, assign to member + coach, exercise library CRUD, completion logging, recent logs |
| `diet-plans.php` | Diet plan CRUD with structured meals (time, items, calories, protein), macros, member + trainer assignment |
| `classes.php` | Class CRUD with capacity bars, capacity-checked bookings (no overbooking, no duplicates), booking cancellation |
| `equipment.php` | Inventory CRUD, maintenance alerts (due ≤14d / overdue), maintenance logging that updates last/next dates and restores status |
| `staff.php` | Staff accounts (suspend/reactivate), invitations with accept links, trainer-portal account linking, **salary records** per month (create/mark paid/delete, payroll totals) |
| `reports.php` | Finance summary (revenue vs expenses vs salaries vs net), charts (revenue, attendance, growth, plans), trainer performance table, expenses by category, CSV exports (finance, attendance, members) |
| `settings.php` | Gym profile, branding (colour/currency/timezone/check-in method/tagline → `tenant_settings`), gym-wide notification sender, branches overview, subscription + limits summary |

The classic workspace (`tenant/`, 23 pages) is untouched and linked from
the new sidebar (“Full Classic Modules”).

### Trainer — `trainer/` (shell: `views/trainer/`)
Trainer resolved strictly via `trainers.user_id = current user`; a
Trainer account with no linked profile is told to ask the gym admin
(linking UI lives in gym `staff.php`). Trainers only ever see their own
assigned members — enforced in every query.
`dashboard.php` (assigned members, members in today, classes today/week,
own shift check-in/out, expiring member memberships) ·
`members.php` (searchable assigned list, visits 30d) ·
`schedule.php` (own shift CRUD + classes taught with bookings) ·
`workouts.php` (own plans only: builder + completion logging, cannot
assign to other trainers' members) · `diet-plans.php` (same scoping) ·
`attendance.php` (own attendance history + toggle; members' today/7-day
attendance) · `progress.php` (weight/BMI chart, goals create/update/
delete, measurements, personal bests, training history).

### Member — `member/`
New `dashboard.php` (nav + login home updated): active membership with
computed status, **expiry countdown + period progress bar**, member ID +
QR token, visits KPIs, BMI, today's scheduled exercises, personal bests,
diet summary with meals/macros, trainer card (incl. qualifications),
upcoming class bookings, attendance history, last payments (pending
shown as pending), goals with progress bars, notifications. All classic
member pages (`index`, `membership`, `payments`, `attendance`,
`workouts`, `diet`, `goals`) are unchanged.

## JSON API (`api/`)
Session auth (JSON 401, never a redirect), CSRF on all POSTs (419 on
failure), role + permission guards, tenant scoping, clean JSON errors.

| Endpoint | What it returns / does |
|---|---|
| `csrf.php` | Session CSRF token |
| `dashboard-stats.php?from&to` | All gym KPIs + expiring/expired/recent lists + equipment alerts |
| `charts.php` | Revenue (6 mo), membership growth, attendance (14 d), plan distribution, trainer performance — all from real rows, zero-filled |
| `members.php` | GET list (search/filters/pagination, computed status + days left); GET detail (membership, history, goals, progress, logs, trainer; medical only with `members.medical`); POST create/update/delete/progress/medical_save/membership_status/change_plan (upgrade) |
| `members-export.php` | CSV of the filtered member list (never includes medical data) |
| `attendance.php` | GET today/trainers/report(daily\|weekly\|monthly)/corrections; POST scan/manual_in (duplicates refused)/out/trainer_toggle/correct (audited) |
| `payments.php` | GET filtered list + totals; POST record (pending), verify, fail |
| `finance.php` | GET summary/expenses/salaries; POST expense + salary CRUD |
| `notifications.php` | GET list + unread; POST read/read_all/send |
| `super-stats.php` | Platform KPIs, revenue series, plan mix, pending gyms, recent tenants/payments (Super Admin only) |

## JavaScript layer (`public/assets/js/app.js`, `window.FF`)
Existing behaviour (sidebar, BMI calculator, pricing toggle, dashboard
search) preserved. Added: `FF.api()` fetch wrapper (CSRF auto-fetch,
JSON errors), `FF.toast()`, `FF.confirm()` (Bootstrap modal, Promise),
`FF.liveTable()` + declarative `data-live-table` binding (debounced
search, filter selects, pagination), `form[data-ajax]` modal forms with
spinner + toast + `ff:saved` event, `form[data-confirm]` guards (also
used by classic server-posted forms), notification “mark all read”,
`FF.onRefresh()` dashboard loaders behind `[data-ff-refresh]` buttons,
Chart.js registry (`FF.registerChart` / `FF.updateChart`), and
`FF.esc/money/pill` render helpers.

## Database — migrations (`database/migrations/`, all folded into `database.sql`)
- **001_member_trainer_enrichment.sql** — members: `photo_url`,
  `address`, `emergency_contact_name/phone`, unique `member_code`
  (backfilled `FFP-M0001…`); `suspended` added to members + memberships
  status enums; trainers: `qualifications`.
- **002_finance_medical_attendance_tables.sql** — new tables:
  `member_medical_records`, `staff_salaries`, `expenses`,
  `trainer_attendance`, `attendance_corrections`, `workout_logs`.
- **003_permissions_upgrade.sql** — new permissions `members.medical`
  (restricted), `finance.view`, `finance.manage`, `attendance.correct`,
  `trainers.attendance` + role grants (Owner/Admin full; Branch Manager
  corrections + trainer attendance; Accountant finance).
- Extra seed data folded in: a Half-Yearly membership plan, 2 medical
  records (1 cleared, 1 restricted), 5 expenses, 4 salary records,
  3 trainer-attendance rows, 1 audited correction, 6 workout logs.
  All demo logins still use password `password`
  (`super@fitflow.test`, `admin@demo.com`, `trainer@demo.com`,
  `member@demo.com`, …) and coupons `WELCOME10` / `FIT20` still work.

### Model changes
New: `Expense`, `StaffSalary`, `MedicalRecord`, `TrainerAttendance`,
`WorkoutLog`. Extended: `Attendance` (+ openSession, manualCheckIn with
duplicate prevention, checkOut, correct with audit trail, report,
corrections), `Membership` (+ computedStatus, daysLeft, suspend,
reactivate, cancelMembership, changePlan for upgrades), `Member`
(+ nextMemberCode). Wiring: `Auth::homeFor()` now routes Super Admin →
`super-admin/dashboard.php`, Trainer → `trainer/dashboard.php`, Member →
`member/dashboard.php`, gym roles → `admin/dashboard.php`;
`helpers::url()` knows the new folders.

## Business rules honoured
- **Payments stay `pending` until verified** — record flows (API,
  membership assignment) never mark success; only the permission-gated
  verify action transitions to `verified` (and pays the linked invoice).
- **Duplicate active check-ins are refused**, for members (manual path)
  and trainers (toggle path); QR scans toggle the open session.
- **Attendance corrections are never silent**: old/new values, reason,
  and the correcting user are stored per field.
- **Medical records are restricted**: API omits them without
  `members.medical`, pages show a locked notice, CSV exports never
  include them, trainers don't see them.
- **Plan limits** still enforced (`plan_allows`) for members, trainers,
  classes, staff.
- **No dummy buttons**: every control either performs a real server
  action or was removed; the Super Admin settings page shows real
  configuration instead of a fake form (the old settings file it wrote
  was never read by anything).

## Vercel demo (`vercel-demo/`)
Design untouched. `dashboard.html` gains a **role view switcher**
(Gym Admin / Trainer / Member) mirroring the upgraded product: trainer
view with performance chart + member goal progress, member view with
membership countdown bar, weight chart, today's workout, diet and honest
pending-payment display; sidebar identity swaps per role. `demo.js`
adds the switcher logic, trainer/member charts, a member **status
filter** (works with the existing search), toast notifications, and a
working renewal-reminders mock action. QR check-in mock and BMI
calculator behave as before.

## Tests performed
- **PHP lint**: `/tmp/phpbin/php -l` (PHP 8.3.13) on **all 150 PHP
  files — 0 failures** (includes every new/changed file).
- **JS syntax**: `node --check` passes for `public/assets/js/app.js`
  and `vercel-demo/assets/js/demo.js`.
- **Smoke test** (PHP built-in server, no DB): `public/index.php` and
  `public/login.php` return 200 (no-DB fallbacks intact);
  `admin/dashboard.php`, `super-admin/dashboard.php`,
  `trainer/dashboard.php`, `member/dashboard.php`, `admin/members.php`
  all 302 → login when unauthenticated (guards work, no fatals);
  `api/csrf.php`, `api/dashboard-stats.php`, `api/members.php`,
  `api/super-stats.php`, `api/members-export.php` all return clean
  `401` JSON/plain auth errors.
- **Static review**: every new query checked for tenant scoping and
  against the real model signatures in this codebase (the codebase uses
  `Database::` statics and `find($id, $tenantId)` ordering).

## Known limitations (honest)
- **No live MySQL exists in this environment**, so authenticated,
  DB-backed flows (real logins, CRUD round-trips, chart payloads with
  data) could **not** be runtime-tested end-to-end. They are verified by
  full-tree lint, the no-DB smoke test above, and code-level review
  against the existing working patterns. First live run should: import
  `database.sql`, log in as each demo role, and click through the new
  trees once.
- `database.sql` contains MySQL-specific SQL (ENUMs, `DATE_FORMAT`,
  `FIELD()`, …) consistent with the original schema; the migrations
  target MySQL 8 / MariaDB 10.2+ and are written to be run once, in
  order (plain `ALTER`s, standard practice).
- Renewal reminders are in-app notifications (the product has no mailer
  configured); members without a portal login are counted and surfaced
  for front-desk follow-up.
- The trainer portal requires the trainer's user account to be linked
  to the trainer profile (`trainers.user_id`) — seeded for Jake Thompson
  (`trainer@demo.com`); the gym Staff page has the linking form for
  others.

## Runtime verification (live MariaDB, 2026-11-06)

The upgrade above was originally verified without a database. This pass
re-tested the whole product **live**: MariaDB 10.11 + PHP 8.5 built-in
server, scratch database imported fresh from `database.sql`, every flow
driven over HTTP with per-role cookie jars and page-**content**
assertions (not just status codes), cross-checked against SQL.

**Environment note:** the scratch DB/user and temporary `.env` used for
testing were removed afterwards; no test data ships in this tree.

### Flows tested — all passing
- **140+ checks across four suites.** Public pages (17) render clean;
  logins for Super Admin / Gym Owner / Gym Admin / Trainer / Member land
  on their own trees (`super-admin/`, `admin/`, `trainer/`, `member/`);
  role isolation holds (member → other trees redirect to member home,
  member → admin stats API = 403 JSON, anonymous → login, gym admin →
  super-admin tree blocked).
- **Full page sweep with content assertions** (no PHP warnings/fatals in
  output): 23 admin pages, 8 super-admin, 7 trainer, 8 member, 22 tenant
  pages.
- **Dashboard stats API vs SQL**: total/active members, pending
  payments, trainers all match live queries; charts API returns real
  series.
- **Member lifecycle**: create via API generates `FFP-M…` code;
  Quarterly plan enrolment sets `end_date = start_date + 90 days`;
  suspend/reactivate via API; CSV export downloads real rows.
- **Attendance**: manual check-in works; **duplicate check-in refused
  (422)**; correction writes an `attendance_corrections` audit row and
  updates the record; QR scan toggles check-out → check-in; trainer
  attendance toggle works.
- **Payments (verified-only rule)**: recorded payments start `pending`;
  API verify moves pending → verified (double-verify refused, 422);
  fail moves pending → failed; Super Admin verify works.
- **SaaS flows**: public gym registration creates tenant + trial
  subscription + default branch/plans; new owner lands on a working
  (empty-gym) dashboard; Super Admin suspend/reactivate takes effect;
  forgot-password issues a reset token.
- **Classes**: booking created; duplicate booking refused; capacity
  limit enforced. **Finance**: summary API, expense create/delete.
  Notifications API OK.
- **Fresh-install proof**: updated `database.sql` re-imported into a
  brand-new database — 42 tables, new columns present, seeds intact.

### Bugs found by live testing and fixed
1. `super-admin/plans.php` + `database/database.sql` — the
   `subscription_plans` table lacked `tagline`, `trial_days` and
   `features`, which the plans screen reads and writes: list rendering
   emitted "Undefined array key trial_days" warnings and plan
   create/update would fail. Columns added to `database.sql`, trial
   seeded (Free Trial = 14 days), and migration
   `database/migrations/004_plan_and_maintenance_columns.sql` added for
   existing installs.
2. `admin/equipment.php` + `database/database.sql` — the
   `equipment_maintenance` table lacked the `vendor` column the
   maintenance log writes and renders ("Undefined array key vendor"
   warnings; maintenance save would fail). Column added to
   `database.sql` and to migration 004.
3. `super-admin/plans.php` — creating a plan **fatal-errored**:
   `Field 'slug' doesn't have a default value`, because the save
   handler never supplied the NOT NULL UNIQUE `slug`. Create now
   derives a unique slug from the plan name (same approach as tenant
   registration); updates never touch the slug, so the `free-trial`
   lookup stays stable.
4. `api/members.php` — creating a member without an explicit `gender`
   field emitted "Undefined array key gender" (corrupting the JSON
   response) and then failed the insert with `Column 'gender' cannot
   be null`: the defaulting ternary read `$in['gender']` unguarded in
   its true-branch. Fixed to fall back to `'male'` properly.

### Files changed by this verification pass
- `api/members.php` (bug 4)
- `super-admin/plans.php` (bug 3)
- `database/database.sql` (bugs 1–2: new columns + trial seed)
- `database/migrations/004_plan_and_maintenance_columns.sql` (new; bugs 1–2 for existing installs)
- `UPGRADE-SUMMARY.md` (this section)

### Final state
- `php -l` on all **150 PHP files: 0 failures**; `node --check` passes
  on both JS files.
- The earlier "Known limitations" note about DB-backed flows being
  untested no longer applies — they are now runtime-verified above.
  One by-design note: `GET /` is a 404 under a project-root docroot;
  the site home is `public/index.php`, matching the README's serving
  instructions.
