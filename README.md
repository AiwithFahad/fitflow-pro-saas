# FitFlow Pro — Men's Gym Management SaaS

**FitFlow Pro** is a complete, commercial, multi-tenant SaaS platform for men's gyms,
built in **Core PHP 8 + MySQL** (PDO, no framework) with Bootstrap 5 + Chart.js.
One installation powers many independent gyms — each with its own members,
trainers, branches, classes, payments and subscription — while members get
their own portal for memberships, workouts, diet plans and progress tracking.

Brand: **black `#0a0a0b` · charcoal `#141416`/`#1c1c1f` · orange `#f97316`** (dark `#ea580c`, amber `#fbbf24`), white text.

---

## What's included

### Public marketing website (`public/`)
Hero with Join Now CTA, live gym statistics, about, platform features,
membership pricing carousel (from DB), trainer carousel, programs
(Strength / Bodybuilding / Cardio & Weight Loss), equipment gallery,
transformation gallery, testimonials, facilities showcase, **working BMI
calculator**, fitness-goals section, dashboard preview, SaaS pricing with
monthly/yearly toggle, 4-step registration guide, FAQ, blog + article page,
contact form (creates a support ticket) + newsletter signup, Privacy & Terms.

### Authentication & onboarding
Gym registration with profile, slug, logo-ready settings and a **14-day free
trial subscription**; login/logout; forgot/reset password (hashed, expiring
tokens); staff **invitations** with acceptance links; secure sessions
(HttpOnly, SameSite, regeneration); **login rate limiting** (5 attempts /
15 min); **RBAC** with granular permissions across 8 roles: Super Admin,
Gym Owner, Gym Admin, Branch Manager, Trainer, Receptionist, Accountant,
Member.

### Super Admin (`super-admin/`)
Live-computed totals (tenants, active/suspended, users, active/expired
subscriptions, **MRR/ARR**, transactions), gym approvals /
suspend / reactivate / cancel with trial extensions, subscription plan
CRUD with usage limits, subscription management (change plan / extend /
cancel), coupon management, user management, payment verification,
invoices, support tickets, announcements (broadcast to all tenants),
revenue & growth analytics (Chart.js) with CSV export, activity + audit
logs, platform settings. **No hardcoded stats** — everything is a real
query served by the pages or the JSON API (`api/super-stats.php`).
The classic platform modules (coupons, invoices, tickets, announcements,
logs) remain available from the same sidebar.

### Gym Admin (`admin/` — upgraded dashboard tree)
AJAX-powered dashboard with date filters and one-click refresh: live KPIs
(total/active members, new registrations, expired + expiring memberships,
verified revenue, pending payments, today's check-ins, trainers, active
classes, equipment alerts) and Chart.js charts fed by the JSON API
(monthly revenue, membership growth, daily attendance, plan distribution,
trainer performance). **Members**: live search / filters / pagination,
modal CRUD with photos, unique membership IDs (FFP-M…), emergency
contacts, restricted **medical clearance records**, progress logging, CSV
export. **Memberships**: monthly / quarterly / half-yearly / yearly plans,
assignment with coupons, automatic expiry + status calculation, renew /
suspend / reactivate / cancel / upgrade, printable receipts, renewal
reminders. **Attendance**: QR + manual check-in (duplicate active
check-ins refused), trainer attendance, daily/weekly/monthly reports,
corrections with a full audit trail. Plus trainers (qualifications,
schedules), workouts (structured exercise builder + completion logs),
diet plans, capacity-checked class bookings, equipment + maintenance,
staff + **salary records**, **expenses** and finance summaries, reports
with CSV exports, and gym branding/settings.

### Classic Gym workspace (`tenant/`)
The original 23-page workspace remains fully available and linked from
the new sidebar: enrolment, renewals, branches, schedules, maintenance,
subscription & billing, support tickets, and more.

### Trainer portal (`trainer/`)
A dedicated dashboard for coaches: assigned members only (enforced
server-side), training schedules, workout plans, diet plans, own shift
check-in/out, member attendance, and member progress (weight/BMI charts,
goals, personal bests).

### Member portal (`member/`)
New rich **dashboard** (`member/dashboard.php`): active membership with
expiry countdown + progress bar, attendance history, today's workout,
assigned trainer card, diet summary, payment history, fitness goals and
notifications — alongside the classic pages: membership status + QR token
card, self-service renew/switch (with coupons), payment & invoice
history, attendance history + streaks, assigned workouts, class booking,
diet plans, BMI + goal tracking with progress charts and weigh-ins.

### Subscription system
Free Trial / Starter / Professional / Business / Enterprise plans, monthly
& yearly cycles, upgrade / downgrade / cancel / renew, coupons
(e.g. **WELCOME10** = 10% off), invoices, payment history, trial + grace
handling, and **plan limits enforced** for members, trainers, branches,
classes and staff (Enterprise = unlimited).

> **Payment integrity rule:** manually recorded payments start as
> `pending`. They become `verified` only through an explicit, permission-
> gated verification action. Nothing in the codebase auto-marks a payment
> successful.

---

## Requirements

- PHP **8.1+** with `pdo_mysql`
- MySQL **8.0+** or MariaDB **10.6+**
- Apache/Nginx (or PHP's built-in server for local development)
- No Composer dependencies

## Installation

1. **Copy the project** to your server, e.g. `/var/www/fitflow-pro`.
2. **Create the database** and import the schema + demo data:
   ```sql
   CREATE DATABASE fitflow_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   ```bash
   mysql -u root -p fitflow_pro < database/database.sql
   ```
   …or run the web/CLI installer once: `php database/install.php`
   (delete `database/install.php` afterwards in production).
3. **Configure environment:** copy `.env.example` to `.env` and set your
   `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_URL`.
4. **Serve:** point your web server at the project root (Apache vhost or
   Nginx with PHP-FPM). For a quick local run:
   ```bash
   php -S localhost:8000
   ```
   then open `http://localhost:8000/public/`.
5. **Sign in** with the demo accounts below and change all passwords.

## Demo credentials (password for all: `password`)

| Role | Email |
|---|---|
| Super Admin | `super@fitflow.test` |
| Gym Owner | `owner@demo.com` |
| Gym Admin | `admin@demo.com` |
| Branch Manager | `manager@demo.com` |
| Trainer | `trainer@demo.com` |
| Receptionist | `frontdesk@demo.com` |
| Accountant | `accounts@demo.com` |
| Member | `member@demo.com` |

Demo tenant: **Iron Paradise Gym** (2 branches, 4 trainers, 10 members).
Member QR tokens: `FFP-DEMO0001` … `FFP-DEMO0010`.
Coupon codes: `WELCOME10` (10% off), `FIT20` (20% off).

## Project structure

```
config/            App + database configuration (env-driven)
app/               Bootstrap, Database (PDO), Auth, helpers, Models (26)
api/               JSON API for the AJAX layer (11 endpoints, session + CSRF)
database/          database.sql (42 tables + seeds), install.php,
                   migrations/ (numbered non-destructive upgrades 001–003)
public/            Marketing website + auth pages (entry: public/index.php)
super-admin/       Super Admin dashboard tree (8 pages)
admin/             Gym Admin dashboard tree (13 pages + printable receipt);
                   legacy platform modules (coupons, invoices, tickets,
                   announcements, logs) also live here
tenant/            Classic Gym workspace (23 pages)
trainer/           Trainer portal (7 pages)
member/            Member portal (8 pages incl. new dashboard)
views/             Layouts (website / admin / tenant / member /
                   super-admin / gym-admin / trainer)
storage/           logs, uploads, backups (runtime)
docs/              SECURITY.md, API.md
vercel-demo/       Static HTML/CSS/JS demo (landing + role dashboards UI)
```

## Upgrading an existing installation

Run the numbered, non-destructive migrations in order — they only add
columns/tables/permissions and backfill member codes:

```bash
mysql -u root -p fitflow_pro < database/migrations/001_member_trainer_enrichment.sql
mysql -u root -p fitflow_pro < database/migrations/002_finance_medical_attendance_tables.sql
mysql -u root -p fitflow_pro < database/migrations/003_permissions_upgrade.sql
```

Fresh installs need nothing extra: `database.sql` already includes
everything. See `UPGRADE-SUMMARY.md` for the full upgrade notes.

## Deployment notes

- **Full application:** deploy to any PHP/MySQL hosting (VPS, cPanel,
  Laravel Forge, etc.). Set `APP_DEBUG=false`, use HTTPS, keep `.env` out
  of version control, and delete `database/install.php` after setup.
- **Vercel demo:** the `vercel-demo/` folder is a **static showcase** of the
  landing page and dashboard UI with mock data. Vercel does not run PHP or
  MySQL, so the live full product requires PHP/MySQL hosting — the Vercel
  deployment exists so anyone can explore the design and flows instantly.

## Security

See [`docs/SECURITY.md`](docs/SECURITY.md) — password hashing, CSRF,
session hardening, login rate limiting, RBAC, tenant isolation, payment
verification and upload validation. Integration notes: [`docs/API.md`](docs/API.md).
