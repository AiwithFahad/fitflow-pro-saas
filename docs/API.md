# FitFlow Pro — API & Integration Notes

FitFlow Pro is a server-rendered Core PHP application (no framework). This
document describes its internal integration surface and how to extend it
with a JSON API.

## Route map (server-rendered)

| Area | Entry points |
|---|---|
| Public website | `public/index.php`, `about.php`, `features.php`, `plans.php`, `trainers.php`, `programs.php`, `facilities.php`, `gallery.php`, `pricing.php`, `blog.php`, `blog-details.php`, `contact.php`, `privacy.php`, `terms.php` |
| Auth | `public/login.php`, `register.php`, `logout.php`, `forgot-password.php`, `reset-password.php`, `accept-invite.php` |
| Super Admin | `admin/index.php`, `tenants.php`, `plans.php`, `coupons.php`, `users.php`, `payments.php`, `invoices.php`, `tickets.php`, `announcements.php`, `analytics.php`, `logs.php`, `settings.php` |
| Gym Admin | `tenant/*.php` (23 pages: members, enroll, renewals, payments, attendance, checkin, trainers, schedules, workouts, exercises, diet, classes, bookings, equipment, maintenance, staff, branches, reports, subscription, support, settings) |
| Member portal | `member/index.php`, `membership.php`, `payments.php`, `attendance.php`, `workouts.php`, `diet.php`, `goals.php` |

## Model layer (internal API)

All data access goes through PDO models in `app/Models/`. Key contracts:

- `Auth::attempt($email, $password)` — rate-limited login; returns user row.
- `Tenant::register($data)` — transactional gym onboarding (tenant + owner +
  settings + trial subscription).
- `Membership::enrol($tenantId, $memberId, $planId, $startDate)` — creates a
  membership with computed end date/price; `renew()` extends it;
  `sweepExpired()` expires lapsed memberships.
- `Attendance::scan($tenantId, $qrToken)` — toggles check-in/check-out.
- `ClassBooking::book($tenantId, $classId, $memberId)` — capacity-checked.
- `Payment::verify($id, $verifierId)` — the only transition to `verified`.
- `Subscription::changePlan($tenant, $planId, $cycle, $coupon)` — upgrade /
  downgrade with invoice + pending payment creation.
- `plan_allows($tenantId, $resource)` — plan-limit enforcement used before
  creating members/trainers/branches/classes/staff.

## Adding a JSON API

1. Create `public/api/` endpoints that bootstrap via `app/bootstrap.php`.
2. Reuse the same models — tenant scoping and RBAC stay identical.
3. Authenticate with bearer tokens stored hashed in a new `api_tokens`
   table (mirror the `password_resets` pattern), or reuse session auth for
   first-party frontends.
4. Return errors as `{ "error": { "code": "...", "message": "..." } }` with
   proper HTTP status codes; never leak SQL errors (keep `APP_DEBUG=false`).

## Webhooks (payment gateways)

When a gateway is integrated, its webhook handler must verify the gateway
signature server-side and only then call `Payment::verify()`. Client-side
"payment succeeded" flags must never be trusted — this mirrors the manual
payment rule enforced throughout the product.
