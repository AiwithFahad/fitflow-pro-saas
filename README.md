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

### Super Admin (`admin/`)
Live-computed totals (tenants, active/suspended, users, active/expired
subscriptions, **MRR/ARR**, transactions), subscription plan CRUD, coupon
management, tenant suspend/reactivate, user management, payment
verification, invoices, support tickets, announcements (broadcast to all
tenants), revenue & growth analytics (Chart.js), activity + audit logs,
platform settings. **No hardcoded stats** — everything is a real query.

### Gym Admin (`tenant/`)
Dark charcoal + orange dashboard with sidebar, topbar, global member
search, notifications and profile menu:
Overview KPIs & charts · Members CRUD · Membership Plans · **Membership
Registration (enrolment)** · Renewals (expiry alerts + reminders) ·
Payments & Invoices (record → **verify** → receipt) · Attendance ·
**QR-Based Check-in** (unique member QR token, scan toggles in/out) ·
Trainers & Schedules · Workout Programs · Exercise Library · Diet Plans ·
Fitness Classes & Bookings (capacity-checked) · Equipment & Maintenance ·
Staff invitations · Multi-Branch management · Reports & Analytics with CSV
export & print · Subscription management · Support tickets · Settings
(logo upload, branding, password, 2FA secret).

### Member portal (`member/`)
Membership status + countdown, QR token card, self-service renew/switch
(with coupons), payment & invoice history, attendance history + streaks,
assigned workouts, class booking, diet plans, BMI + goal tracking with
progress charts and weigh-ins.

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
app/               Bootstrap, Database (PDO), Auth, helpers, Models (21)
database/          database.sql (36 tables + seeds), install.php
public/            Marketing website + auth pages (entry: public/index.php)
admin/             Super Admin dashboard (12 pages)
tenant/            Gym Admin dashboard (23 pages)
member/            Member portal (7 pages)
views/             Layouts (website / admin / tenant / member)
storage/           logs, uploads, backups (runtime)
docs/              SECURITY.md, API.md
vercel-demo/       Static HTML/CSS/JS demo (landing + dashboard UI)
```

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
