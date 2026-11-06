-- =====================================================================
-- FitFlow Pro — Men's Gym Management SaaS
-- Complete MySQL schema (normalized, multi-tenant) + demo seed data.
-- Import:  mysql -u root -p < database.sql
-- All passwords in seed data are the literal word: password
-- (stored as bcrypt hashes via password_hash()).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS fitflow_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fitflow_pro;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs, activity_logs, support_tickets, notifications, coupons,
  invoices, payments, subscription_usage, subscriptions, subscription_plans, tenant_settings,
  invitations, password_resets, login_attempts, announcements, users, role_permissions,
  permissions, roles, tenants, class_bookings, classes, equipment_maintenance, equipment,
  progress_records, fitness_goals, diet_plans, workouts, exercises, trainer_schedules,
  attendance, memberships, membership_plans, trainers, members, branches,
  member_medical_records, staff_salaries, expenses, trainer_attendance,
  attendance_corrections, workout_logs;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- PLATFORM / COMMON SAAS TABLES
-- ---------------------------------------------------------------------

CREATE TABLE tenants (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  slug          VARCHAR(80)  NOT NULL UNIQUE,
  email         VARCHAR(150) NOT NULL,
  phone         VARCHAR(40)  NULL,
  logo_url      VARCHAR(255) NULL,
  city          VARCHAR(100) NULL,
  country       VARCHAR(100) NULL,
  status        ENUM('trial','active','suspended','cancelled') NOT NULL DEFAULT 'trial',
  trial_ends_at DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenants_status (status)
) ENGINE=InnoDB;

CREATE TABLE roles (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(60) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id       INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id           INT UNSIGNED NULL,
  role_id             INT UNSIGNED NOT NULL,
  name                VARCHAR(120) NOT NULL,
  email               VARCHAR(150) NOT NULL UNIQUE,
  phone               VARCHAR(40) NULL,
  password_hash       VARCHAR(255) NOT NULL,
  status              ENUM('active','invited','suspended') NOT NULL DEFAULT 'active',
  email_verified_at   DATETIME NULL,
  two_factor_secret   VARCHAR(100) NULL,
  last_login_at       DATETIME NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_tenant (tenant_id),
  INDEX idx_users_role (role_id),
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE tenant_settings (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL UNIQUE,
  brand_color VARCHAR(20) NOT NULL DEFAULT '#f97316',
  currency    VARCHAR(10) NOT NULL DEFAULT 'USD',
  timezone    VARCHAR(60) NOT NULL DEFAULT 'UTC',
  settings    JSON NULL,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE subscription_plans (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(60) NOT NULL,
  slug           VARCHAR(60) NOT NULL UNIQUE,
  tagline        VARCHAR(120) NULL,
  description    VARCHAR(255) NULL,
  price_monthly  DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_yearly   DECIMAL(10,2) NOT NULL DEFAULT 0,
  trial_days     INT NOT NULL DEFAULT 0,
  features       TEXT NULL,
  max_members    INT NOT NULL DEFAULT 50,
  max_trainers   INT NOT NULL DEFAULT 3,
  max_branches   INT NOT NULL DEFAULT 1,
  max_classes    INT NOT NULL DEFAULT 10,
  max_staff      INT NOT NULL DEFAULT 5,
  max_storage_mb INT NOT NULL DEFAULT 500,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE subscriptions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  plan_id       INT UNSIGNED NOT NULL,
  status        ENUM('trial','active','grace','expired','cancelled') NOT NULL DEFAULT 'trial',
  billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
  starts_at     DATETIME NULL,
  ends_at       DATETIME NULL,
  trial_ends_at DATETIME NULL,
  cancelled_at  DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sub_tenant (tenant_id),
  INDEX idx_sub_status (status),
  CONSTRAINT fk_sub_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB;

CREATE TABLE subscription_usage (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  metric       VARCHAR(60) NOT NULL,
  used         INT NOT NULL DEFAULT 0,
  period_start DATE NOT NULL,
  period_end   DATE NOT NULL,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usage (tenant_id, metric, period_start),
  CONSTRAINT fk_usage_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NULL,
  user_id       INT UNSIGNED NULL,
  member_id     INT UNSIGNED NULL,
  purpose       ENUM('subscription','membership','other') NOT NULL DEFAULT 'subscription',
  description   VARCHAR(255) NULL,
  amount        DECIMAL(10,2) NOT NULL,
  currency      VARCHAR(10) NOT NULL DEFAULT 'USD',
  method        VARCHAR(40) NOT NULL DEFAULT 'manual',
  -- A manually recorded payment stays 'pending' until verification confirms it.
  status        ENUM('pending','verified','failed','refunded') NOT NULL DEFAULT 'pending',
  reference     VARCHAR(100) NULL,
  verified_by   INT UNSIGNED NULL,
  paid_at       DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pay_tenant (tenant_id),
  INDEX idx_pay_status (status),
  CONSTRAINT fk_pay_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NULL,
  payment_id  INT UNSIGNED NULL,
  number      VARCHAR(40) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  amount      DECIMAL(10,2) NOT NULL DEFAULT 0,
  status      ENUM('unpaid','paid','void') NOT NULL DEFAULT 'unpaid',
  due_at      DATETIME NULL,
  issued_at   DATETIME NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inv_tenant (tenant_id),
  CONSTRAINT fk_inv_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE coupons (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(40) NOT NULL UNIQUE,
  type       ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value      DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_uses   INT NULL,
  used_count INT NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id  INT UNSIGNED NULL,
  user_id    INT UNSIGNED NULL,
  title      VARCHAR(180) NOT NULL,
  body       TEXT NULL,
  type       VARCHAR(40) NOT NULL DEFAULT 'info',
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_tenant (tenant_id),
  INDEX idx_notif_user (user_id),
  CONSTRAINT fk_notif_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE support_tickets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id  INT UNSIGNED NULL,
  user_id    INT UNSIGNED NULL,
  subject    VARCHAR(180) NOT NULL,
  message    TEXT NOT NULL,
  priority   ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  status     ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  reply      TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ticket_status (status),
  CONSTRAINT fk_ticket_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id  INT UNSIGNED NULL,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(100) NOT NULL,
  entity     VARCHAR(60) NULL,
  entity_id  INT UNSIGNED NULL,
  details    TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_tenant (tenant_id),
  INDEX idx_activity_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id  INT UNSIGNED NULL,
  user_id    INT UNSIGNED NULL,
  event      VARCHAR(100) NOT NULL,
  entity     VARCHAR(60) NULL,
  entity_id  INT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_tenant (tenant_id)
) ENGINE=InnoDB;

CREATE TABLE invitations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  email       VARCHAR(150) NOT NULL,
  role_id     INT UNSIGNED NOT NULL,
  token       VARCHAR(100) NOT NULL UNIQUE,
  expires_at  DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_invite_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_invite_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE announcements (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(180) NOT NULL,
  body       TEXT NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ann_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45) NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempts_email (email, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- GYM BUSINESS TABLES (all tenant-isolated via tenant_id)
-- ---------------------------------------------------------------------

CREATE TABLE branches (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id  INT UNSIGNED NOT NULL,
  name       VARCHAR(120) NOT NULL,
  address    VARCHAR(255) NULL,
  city       VARCHAR(100) NULL,
  phone      VARCHAR(40) NULL,
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_branch_tenant (tenant_id),
  CONSTRAINT fk_branch_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE trainers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NULL,
  branch_id     INT UNSIGNED NULL,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NULL,
  phone         VARCHAR(40) NULL,
  photo_url     VARCHAR(255) NULL,
  specialty     VARCHAR(120) NULL,
  qualifications VARCHAR(255) NULL,
  experience_years INT NOT NULL DEFAULT 0,
  bio           TEXT NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_trainer_tenant (tenant_id),
  CONSTRAINT fk_trainer_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_trainer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_trainer_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE trainer_schedules (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  trainer_id  INT UNSIGNED NOT NULL,
  day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sched_trainer (trainer_id),
  CONSTRAINT fk_sched_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_sched_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE members (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NULL,
  branch_id    INT UNSIGNED NULL,
  trainer_id   INT UNSIGNED NULL,
  name         VARCHAR(120) NOT NULL,
  email        VARCHAR(150) NULL,
  phone        VARCHAR(40) NULL,
  photo_url    VARCHAR(255) NULL,
  address      VARCHAR(255) NULL,
  emergency_contact_name  VARCHAR(120) NULL,
  emergency_contact_phone VARCHAR(40) NULL,
  member_code  VARCHAR(20) NULL,
  gender       ENUM('male','female','other') NOT NULL DEFAULT 'male',
  date_of_birth DATE NULL,
  height_cm    DECIMAL(5,1) NULL,
  weight_kg    DECIMAL(5,1) NULL,
  bmi          DECIMAL(4,1) NULL,
  qr_token     VARCHAR(40) NOT NULL UNIQUE,
  status       ENUM('active','inactive','cancelled','suspended') NOT NULL DEFAULT 'active',
  joined_at    DATE NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_code (member_code),
  INDEX idx_member_tenant (tenant_id),
  INDEX idx_member_trainer (trainer_id),
  CONSTRAINT fk_member_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_member_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_member_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE membership_plans (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  name          VARCHAR(80) NOT NULL,
  price         DECIMAL(10,2) NOT NULL DEFAULT 0,
  duration_days INT NOT NULL DEFAULT 30,
  description   VARCHAR(255) NULL,
  features      TEXT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mplan_tenant (tenant_id),
  CONSTRAINT fk_mplan_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE memberships (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id  INT UNSIGNED NOT NULL,
  member_id  INT UNSIGNED NOT NULL,
  plan_name  VARCHAR(80) NOT NULL,
  price      DECIMAL(10,2) NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  status     ENUM('active','expired','cancelled','suspended') NOT NULL DEFAULT 'active',
  coupon_id  INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ms_member (member_id),
  INDEX idx_ms_end (end_date),
  CONSTRAINT fk_ms_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_ms_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_ms_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE attendance (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  branch_id    INT UNSIGNED NULL,
  member_id    INT UNSIGNED NOT NULL,
  check_in_at  DATETIME NOT NULL,
  check_out_at DATETIME NULL,
  method       ENUM('qr','manual') NOT NULL DEFAULT 'manual',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_att_tenant_day (tenant_id, check_in_at),
  INDEX idx_att_member (member_id),
  CONSTRAINT fk_att_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_att_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE exercises (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  name         VARCHAR(120) NOT NULL,
  category     VARCHAR(60) NOT NULL DEFAULT 'Strength',
  muscle_group VARCHAR(60) NULL,
  equipment    VARCHAR(80) NULL,
  difficulty   ENUM('Beginner','Intermediate','Advanced') NOT NULL DEFAULT 'Beginner',
  description  TEXT NULL,
  video_url    VARCHAR(255) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ex_tenant (tenant_id),
  CONSTRAINT fk_ex_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE workouts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  member_id   INT UNSIGNED NULL,
  trainer_id  INT UNSIGNED NULL,
  name        VARCHAR(150) NOT NULL,
  category    ENUM('Strength','Bodybuilding','Cardio','Weight Loss','Flexibility','Sports') NOT NULL DEFAULT 'Strength',
  level       ENUM('Beginner','Intermediate','Advanced') NOT NULL DEFAULT 'Beginner',
  duration_weeks INT NOT NULL DEFAULT 4,
  description TEXT NULL,
  exercises   JSON NULL,
  status      ENUM('active','completed','archived') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_wo_tenant (tenant_id),
  INDEX idx_wo_member (member_id),
  CONSTRAINT fk_wo_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_wo_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_wo_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE diet_plans (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  member_id    INT UNSIGNED NULL,
  trainer_id   INT UNSIGNED NULL,
  name         VARCHAR(150) NOT NULL,
  goal         ENUM('Muscle Gain','Weight Loss','Maintenance','Performance') NOT NULL DEFAULT 'Muscle Gain',
  calories     INT NULL,
  protein_g    INT NULL,
  carbs_g      INT NULL,
  fats_g       INT NULL,
  meals        JSON NULL,
  notes        TEXT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_diet_tenant (tenant_id),
  CONSTRAINT fk_diet_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_diet_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_diet_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE fitness_goals (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  member_id     INT UNSIGNED NOT NULL,
  title         VARCHAR(150) NOT NULL,
  metric        VARCHAR(60) NOT NULL DEFAULT 'weight_kg',
  start_value   DECIMAL(8,2) NULL,
  target_value  DECIMAL(8,2) NULL,
  current_value DECIMAL(8,2) NULL,
  target_date   DATE NULL,
  status        ENUM('active','achieved','abandoned') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_goal_member (member_id),
  CONSTRAINT fk_goal_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_goal_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE progress_records (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id        INT UNSIGNED NOT NULL,
  member_id        INT UNSIGNED NOT NULL,
  weight_kg        DECIMAL(5,1) NULL,
  body_fat_percent DECIMAL(4,1) NULL,
  bmi              DECIMAL(4,1) NULL,
  notes            VARCHAR(255) NULL,
  recorded_at      DATETIME NOT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_prog_member (member_id, recorded_at),
  CONSTRAINT fk_prog_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE classes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  branch_id   INT UNSIGNED NULL,
  trainer_id  INT UNSIGNED NULL,
  name        VARCHAR(120) NOT NULL,
  category    VARCHAR(60) NOT NULL DEFAULT 'General',
  day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  capacity    INT NOT NULL DEFAULT 20,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_class_tenant (tenant_id),
  CONSTRAINT fk_class_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_class_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_class_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE class_bookings (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id  INT UNSIGNED NOT NULL,
  class_id   INT UNSIGNED NOT NULL,
  member_id  INT UNSIGNED NOT NULL,
  status     ENUM('booked','attended','cancelled','no_show') NOT NULL DEFAULT 'booked',
  booked_at  DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_booking_class (class_id),
  CONSTRAINT fk_booking_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE equipment (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id           INT UNSIGNED NOT NULL,
  branch_id           INT UNSIGNED NULL,
  name                VARCHAR(120) NOT NULL,
  category            VARCHAR(60) NOT NULL DEFAULT 'Cardio',
  brand               VARCHAR(80) NULL,
  serial_number       VARCHAR(80) NULL,
  quantity            INT NOT NULL DEFAULT 1,
  status              ENUM('working','maintenance','broken','retired') NOT NULL DEFAULT 'working',
  purchased_at        DATE NULL,
  last_maintenance_at DATE NULL,
  next_maintenance_at DATE NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_eq_tenant (tenant_id),
  CONSTRAINT fk_eq_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_eq_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE equipment_maintenance (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  equipment_id INT UNSIGNED NOT NULL,
  type         ENUM('routine','repair','inspection') NOT NULL DEFAULT 'routine',
  description  TEXT NULL,
  vendor       VARCHAR(120) NULL,
  cost         DECIMAL(10,2) NOT NULL DEFAULT 0,
  performed_at DATE NULL,
  next_due_at  DATE NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_maint_eq (equipment_id),
  CONSTRAINT fk_maint_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_maint_eq FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- UPGRADE TABLES (migrations 001–003 folded in)
-- ---------------------------------------------------------------------

CREATE TABLE member_medical_records (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id        INT UNSIGNED NOT NULL,
  member_id        INT UNSIGNED NOT NULL,
  clearance_status ENUM('cleared','pending','restricted','expired') NOT NULL DEFAULT 'pending',
  conditions       TEXT NULL,
  allergies        TEXT NULL,
  medications      TEXT NULL,
  doctor_name      VARCHAR(120) NULL,
  clearance_date   DATE NULL,
  expiry_date      DATE NULL,
  notes            TEXT NULL,
  recorded_by      INT UNSIGNED NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_med_member (member_id),
  CONSTRAINT fk_med_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_med_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_med_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE staff_salaries (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NULL,
  trainer_id   INT UNSIGNED NULL,
  person_name  VARCHAR(120) NOT NULL,
  role_label   VARCHAR(80) NULL,
  period_month VARCHAR(7) NOT NULL,
  base_salary  DECIMAL(10,2) NOT NULL DEFAULT 0,
  allowances   DECIMAL(10,2) NOT NULL DEFAULT 0,
  deductions   DECIMAL(10,2) NOT NULL DEFAULT 0,
  method       VARCHAR(40) NOT NULL DEFAULT 'bank',
  status       ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  paid_at      DATETIME NULL,
  notes        VARCHAR(255) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sal_tenant (tenant_id, period_month),
  CONSTRAINT fk_sal_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_sal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_sal_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE expenses (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  branch_id    INT UNSIGNED NULL,
  category     VARCHAR(60) NOT NULL DEFAULT 'General',
  title        VARCHAR(180) NOT NULL,
  amount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  expense_date DATE NOT NULL,
  method       VARCHAR(40) NOT NULL DEFAULT 'bank',
  reference    VARCHAR(100) NULL,
  recorded_by  INT UNSIGNED NULL,
  notes        VARCHAR(255) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_exp_tenant (tenant_id, expense_date),
  CONSTRAINT fk_exp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_exp_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE trainer_attendance (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  trainer_id   INT UNSIGNED NOT NULL,
  branch_id    INT UNSIGNED NULL,
  check_in_at  DATETIME NOT NULL,
  check_out_at DATETIME NULL,
  method       ENUM('manual','qr') NOT NULL DEFAULT 'manual',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tatt_trainer (trainer_id, check_in_at),
  CONSTRAINT fk_tatt_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tatt_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
  CONSTRAINT fk_tatt_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE attendance_corrections (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  attendance_id INT UNSIGNED NULL,
  subject_type  ENUM('member','trainer') NOT NULL DEFAULT 'member',
  subject_id    INT UNSIGNED NOT NULL,
  corrected_by  INT UNSIGNED NULL,
  field         VARCHAR(40) NOT NULL,
  old_value     VARCHAR(100) NULL,
  new_value     VARCHAR(100) NULL,
  reason        VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_corr_tenant (tenant_id, created_at),
  CONSTRAINT fk_corr_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_corr_user FOREIGN KEY (corrected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE workout_logs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  member_id     INT UNSIGNED NOT NULL,
  workout_id    INT UNSIGNED NULL,
  exercise_name VARCHAR(120) NOT NULL,
  sets          INT NULL,
  reps          INT NULL,
  weight_kg     DECIMAL(6,1) NULL,
  duration_min  INT NULL,
  notes         VARCHAR(255) NULL,
  completed_at  DATETIME NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_wlog_member (member_id, completed_at),
  CONSTRAINT fk_wlog_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_wlog_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_wlog_workout FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SEED DATA — roles, permissions, plans
-- Bcrypt hash below = the word "password" (password_hash compatible).
-- ---------------------------------------------------------------------

INSERT INTO roles (id, name, description) VALUES
(1, 'Super Admin',    'Platform owner with full access'),
(2, 'Gym Owner',      'Tenant owner: full access to their gym'),
(3, 'Gym Admin',      'Day-to-day gym administrator'),
(4, 'Branch Manager', 'Manages one branch: members, attendance, classes'),
(5, 'Trainer',        'Manages assigned members, workouts and diet plans'),
(6, 'Receptionist',   'Front desk: members, memberships, check-ins'),
(7, 'Accountant',     'Payments, invoices and financial reports'),
(8, 'Member',         'Gym member portal access');

INSERT INTO permissions (slug, description) VALUES
('dashboard.view','View dashboard'), ('members.view','View members'), ('members.create','Create members'),
('members.edit','Edit members'), ('members.delete','Delete members'), ('attendance.view','View attendance'),
('attendance.manage','Manage check-ins'), ('memberships.view','View memberships'), ('memberships.manage','Enrol and renew memberships'),
('payments.view','View payments'), ('payments.manage','Record payments'), ('payments.verify','Verify payments'),
('invoices.view','View invoices'), ('trainers.view','View trainers'), ('trainers.manage','Manage trainers'),
('workouts.view','View workouts'), ('workouts.manage','Manage workouts'), ('diet.view','View diet plans'),
('diet.manage','Manage diet plans'), ('classes.view','View classes'), ('classes.manage','Manage classes and bookings'),
('equipment.view','View equipment'), ('equipment.manage','Manage equipment and maintenance'),
('staff.view','View staff'), ('staff.manage','Invite and manage staff'), ('branches.view','View branches'),
('branches.manage','Manage branches'), ('reports.view','View reports and analytics'), ('settings.manage','Manage gym settings'),
('subscription.manage','Manage SaaS subscription'), ('tickets.manage','Manage support tickets'),
('platform.tenants','Manage platform tenants'), ('platform.plans','Manage platform plans'),
('platform.payments','Manage platform payments'), ('portal.access','Access member portal'),
('members.medical','View and manage member medical clearance records (restricted)'),
('finance.view','View expenses, salaries and financial summaries'), ('finance.manage','Manage expenses and salary records'),
('attendance.correct','Correct attendance records with audit logging'), ('trainers.attendance','Manage trainer attendance');

-- Gym Owner: everything tenant-side. Super Admin: everything (checked in code too).
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 2, id FROM permissions WHERE slug NOT LIKE 'platform.%';
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 1, id FROM permissions;
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 3, id FROM permissions WHERE slug NOT LIKE 'platform.%' AND slug NOT IN ('members.delete');
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 4, id FROM permissions WHERE slug IN ('dashboard.view','members.view','members.create','members.edit','attendance.view','attendance.manage','memberships.view','classes.view','classes.manage','equipment.view','reports.view','attendance.correct','trainers.attendance');
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 5, id FROM permissions WHERE slug IN ('dashboard.view','members.view','attendance.view','workouts.view','workouts.manage','diet.view','diet.manage','classes.view');
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 6, id FROM permissions WHERE slug IN ('dashboard.view','members.view','members.create','members.edit','attendance.view','attendance.manage','memberships.view','memberships.manage','payments.view','classes.view');
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 7, id FROM permissions WHERE slug IN ('dashboard.view','payments.view','payments.manage','payments.verify','invoices.view','reports.view','members.view','finance.view','finance.manage');
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 8, id FROM permissions WHERE slug IN ('portal.access');

INSERT INTO subscription_plans (id, name, slug, description, price_monthly, price_yearly, trial_days, max_members, max_trainers, max_branches, max_classes, max_staff, max_storage_mb) VALUES
(1, 'Free Trial',   'free-trial',   '14-day full-feature trial for new gyms',        0.00,   0.00,     14, 30,   2,  1,   5,   3,   250),
(2, 'Starter',      'starter',      'For small independent gyms getting started',    29.00,  290.00,   0,  150,   5,  1,  15,   5,  1000),
(3, 'Professional', 'professional', 'For growing gyms with multiple trainers',       59.00,  590.00,   0,  500,  15,  3,  40,  15,  5000),
(4, 'Business',     'business',     'For multi-branch gyms and fitness studios',     119.00, 1190.00,  0,  2000,  40,  8, 120,  40, 20000),
(5, 'Enterprise',   'enterprise',   'Unlimited scale, priority support, API access', 249.00, 2490.00,  0,    -1,  -1, -1,  -1,  -1, 100000);

INSERT INTO coupons (code, type, value, max_uses, used_count, expires_at, is_active) VALUES
('WELCOME10', 'percent', 10.00, NULL, 0, NULL, 1),
('FIT20',     'percent', 20.00, 100,  0, DATE_ADD(NOW(), INTERVAL 90 DAY), 1);

-- Demo tenant: Iron Paradise Gym --------------------------------------
INSERT INTO tenants (id, name, slug, email, phone, city, country, status, trial_ends_at) VALUES
(1, 'Iron Paradise Gym', 'iron-paradise', 'hello@ironparadise.example', '+1 555 010 2030', 'Austin', 'USA', 'active', NULL);

INSERT INTO tenant_settings (tenant_id, brand_color, currency, timezone, settings) VALUES
(1, '#f97316', 'USD', 'America/Chicago', JSON_OBJECT('onboarding_step', 5, 'checkin_method', 'qr'));

INSERT INTO branches (id, tenant_id, name, address, city, phone, status) VALUES
(1, 1, 'Main Branch',     '1200 Iron Street',  'Austin', '+1 555 010 2030', 'active'),
(2, 1, 'Downtown Branch', '88 Congress Ave',   'Austin', '+1 555 010 9040', 'active');

-- Users: every password is "password" ---------------------------------
INSERT INTO users (id, tenant_id, role_id, name, email, phone, password_hash, status, email_verified_at) VALUES
(1, NULL, 1, 'Platform Owner',   'super@fitflow.test',   NULL,              '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW()),
(2, 1,    2, 'Marcus Reed',      'owner@demo.com',       '+1 555 111 2233', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW()),
(3, 1,    3, 'Alicia Gomez',     'admin@demo.com',       '+1 555 222 3344', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW()),
(4, 1,    4, 'David Chen',       'manager@demo.com',     '+1 555 333 4455', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW()),
(5, 1,    5, 'Jake Thompson',    'trainer@demo.com',     '+1 555 444 5566', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW()),
(6, 1,    6, 'Sara Mitchell',    'frontdesk@demo.com',   '+1 555 555 6677', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW()),
(7, 1,    7, 'Omar Farouk',      'accounts@demo.com',    '+1 555 666 7788', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW()),
(8, 1,    8, 'Alex Carter',      'member@demo.com',      '+1 555 777 8899', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW());

-- Trainers -------------------------------------------------------------
INSERT INTO trainers (id, tenant_id, user_id, branch_id, name, email, phone, specialty, qualifications, experience_years, bio, status) VALUES
(1, 1, 5,    1, 'Jake Thompson', 'trainer@demo.com', '+1 555 444 5566', 'Strength & Conditioning', 'NASM-CPT, USA Powerlifting Coach', 8,  'Former powerlifting coach specialising in progressive overload programs.', 'active'),
(2, 1, NULL, 1, 'Ryan Delgado',  'ryan@ironparadise.example', '+1 555 101 2020', 'Bodybuilding', 'IFBB Pro, ISSA Bodybuilding Specialist', 10, 'IFBB competitor and hypertrophy specialist.', 'active'),
(3, 1, NULL, 2, 'Chris Novak',   'chris@ironparadise.example', '+1 555 303 4040', 'Cardio & Weight Loss', 'ACE-CPT, Precision Nutrition L1', 6, 'HIIT and metabolic conditioning coach.', 'active'),
(4, 1, NULL, 1, 'Sam Whitaker',  'sam@ironparadise.example', '+1 555 505 6060', 'Mobility & Recovery', 'FRC Mobility Specialist, FMS L2', 5, 'Movement quality, mobility and injury-prevention coach.', 'active');

INSERT INTO trainer_schedules (tenant_id, trainer_id, day_of_week, start_time, end_time) VALUES
(1, 1, 'Monday', '06:00:00', '14:00:00'), (1, 1, 'Wednesday', '06:00:00', '14:00:00'), (1, 1, 'Friday', '06:00:00', '14:00:00'),
(1, 2, 'Tuesday', '10:00:00', '18:00:00'), (1, 2, 'Thursday', '10:00:00', '18:00:00'), (1, 2, 'Saturday', '09:00:00', '13:00:00'),
(1, 3, 'Monday', '16:00:00', '20:00:00'), (1, 3, 'Thursday', '16:00:00', '20:00:00'),
(1, 4, 'Saturday', '08:00:00', '12:00:00'), (1, 4, 'Sunday', '08:00:00', '12:00:00');

-- Members ---------------------------------------------------------------
INSERT INTO members (id, tenant_id, user_id, branch_id, trainer_id, name, email, phone, photo_url, address, emergency_contact_name, emergency_contact_phone, member_code, gender, date_of_birth, height_cm, weight_kg, bmi, qr_token, status, joined_at) VALUES
(1,  1, 8,    1, 1, 'Alex Carter',   'member@demo.com',   '+1 555 777 8899', 'https://randomuser.me/api/portraits/men/32.jpg', '14 Oak Ridge Dr, Austin', 'Emma Carter', '+1 555 777 8800', 'FFP-M0001', 'male', '1996-04-12', 180.0, 86.0, 26.5, 'FFP-DEMO0001', 'active', DATE_SUB(CURDATE(), INTERVAL 90 DAY)),
(2,  1, NULL, 1, 2, 'Brian Cole',    'brian@example.com', '+1 555 201 3001', 'https://randomuser.me/api/portraits/men/45.jpg', '220 Pine St, Austin', 'Laura Cole', '+1 555 201 3101', 'FFP-M0002', 'male', '1993-08-03', 175.0, 78.5, 25.6, 'FFP-DEMO0002', 'active', DATE_SUB(CURDATE(), INTERVAL 75 DAY)),
(3,  1, NULL, 1, 1, 'Daniel Brooks', 'daniel@example.com','+1 555 201 3002', 'https://randomuser.me/api/portraits/men/67.jpg', '9 Birchwood Ln, Austin', 'Kate Brooks', '+1 555 201 3102', 'FFP-M0003', 'male', '1990-01-25', 182.0, 95.0, 28.7, 'FFP-DEMO0003', 'active', DATE_SUB(CURDATE(), INTERVAL 60 DAY)),
(4,  1, NULL, 2, 3, 'Ethan Wright',  'ethan@example.com', '+1 555 201 3003', NULL, '77 Cedar Ave, Austin', 'Paul Wright', '+1 555 201 3103', 'FFP-M0004', 'male', '1998-11-19', 170.0, 70.0, 24.2, 'FFP-DEMO0004', 'active', DATE_SUB(CURDATE(), INTERVAL 45 DAY)),
(5,  1, NULL, 1, 2, 'Frank Miller',  'frank@example.com', '+1 555 201 3004', 'https://randomuser.me/api/portraits/men/85.jpg', '310 Elm St, Austin', 'Grace Miller', '+1 555 201 3104', 'FFP-M0005', 'male', '1988-06-30', 178.0, 102.0, 32.2, 'FFP-DEMO0005', 'active', DATE_SUB(CURDATE(), INTERVAL 120 DAY)),
(6,  1, NULL, 1, 3, 'George Lane',   'george@example.com','+1 555 201 3005', NULL, '51 Maple Dr, Austin', 'Nina Lane', '+1 555 201 3105', 'FFP-M0006', 'male', '1995-02-14', 176.0, 74.0, 23.9, 'FFP-DEMO0006', 'active', DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
(7,  1, NULL, 2, 1, 'Henry Ford',    'henry@example.com', '+1 555 201 3006', NULL, '18 Walnut St, Austin', 'Amy Ford', '+1 555 201 3106', 'FFP-M0007', 'male', '1992-09-08', 185.0, 90.5, 26.4, 'FFP-DEMO0007', 'active', DATE_SUB(CURDATE(), INTERVAL 150 DAY)),
(8,  1, NULL, 1, 4, 'Ian Douglas',   'ian@example.com',   '+1 555 201 3007', NULL, '63 Cherry Ln, Austin', 'Tom Douglas', '+1 555 201 3107', 'FFP-M0008', 'male', '1999-12-01', 172.0, 68.0, 23.0, 'FFP-DEMO0008', 'active', DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
(9,  1, NULL, 1, 2, 'Jack Morris',   'jack@example.com',  '+1 555 201 3008', NULL, '102 Aspen Way, Austin', 'Ruth Morris', '+1 555 201 3108', 'FFP-M0009', 'male', '1994-05-17', 179.0, 82.0, 25.6, 'FFP-DEMO0009', 'active', DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
(10, 1, NULL, 2, 3, 'Kevin Shaw',    'kevin@example.com', '+1 555 201 3009', NULL, '240 Willow Ct, Austin', 'Dan Shaw', '+1 555 201 3109', 'FFP-M0010', 'male', '1991-03-22', 177.0, 88.0, 28.1, 'FFP-DEMO0010', 'active', DATE_SUB(CURDATE(), INTERVAL 200 DAY));

-- Membership plans: add a Half-Yearly duration option -------------------
INSERT INTO membership_plans (id, tenant_id, name, price, duration_days, description, features) VALUES
(5, 1, 'Half-Yearly', 159.00, 180, '6 months of full access', 'Full gym access, Unlimited classes, 1 PT session / month, Diet plan included');

-- Gym membership plans (sold to members) --------------------------------
INSERT INTO membership_plans (id, tenant_id, name, price, duration_days, description, features) VALUES
(1, 1, 'Monthly',          29.00,  30,  'Full gym access, billed monthly',        'Full gym access, 2 group classes / week, Locker room'),
(2, 1, 'Quarterly',        79.00,  90,  '3 months of full access, best value',    'Full gym access, Unlimited classes, 1 PT session / month'),
(3, 1, 'Annual',           290.00, 365, '12 months, two months free',             'Full gym access, Unlimited classes, 2 PT sessions / month, Diet plan included'),
(4, 1, 'Student Monthly',  19.00,  30,  'Discounted plan for students',           'Full gym access (off-peak), 1 group class / week');

INSERT INTO memberships (tenant_id, member_id, plan_name, price, start_date, end_date, status) VALUES
(1, 1,  'Quarterly', 79.00,  DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_ADD(CURDATE(), INTERVAL 50 DAY), 'active'),
(1, 2,  'Monthly',   29.00,  DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY),  'active'),
(1, 3,  'Annual',    290.00, DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_ADD(CURDATE(), INTERVAL 305 DAY),'active'),
(1, 4,  'Monthly',   29.00,  DATE_SUB(CURDATE(), INTERVAL 28 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY),  'active'),
(1, 5,  'Quarterly', 79.00,  DATE_SUB(CURDATE(), INTERVAL 95 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY),  'expired'),
(1, 6,  'Monthly',   29.00,  DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'active'),
(1, 7,  'Annual',    290.00, DATE_SUB(CURDATE(), INTERVAL 150 DAY),DATE_ADD(CURDATE(), INTERVAL 215 DAY),'active'),
(1, 8,  'Student Monthly', 19.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'active'),
(1, 9,  'Monthly',   29.00,  DATE_SUB(CURDATE(), INTERVAL 3 DAY),  DATE_ADD(CURDATE(), INTERVAL 27 DAY), 'active'),
(1, 10, 'Quarterly', 79.00,  DATE_SUB(CURDATE(), INTERVAL 80 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'active');

-- Tenant SaaS subscription ----------------------------------------------
INSERT INTO subscriptions (tenant_id, plan_id, status, billing_cycle, starts_at, ends_at) VALUES
(1, 3, 'active', 'monthly', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY));

INSERT INTO subscription_usage (tenant_id, metric, used, period_start, period_end) VALUES
(1, 'members', 10, DATE_FORMAT(NOW(), '%Y-%m-01'), LAST_DAY(NOW())),
(1, 'storage_mb', 320, DATE_FORMAT(NOW(), '%Y-%m-01'), LAST_DAY(NOW()));

-- Payments & invoices -----------------------------------------------------
INSERT INTO payments (id, tenant_id, user_id, member_id, purpose, description, amount, method, status, reference, verified_by, paid_at) VALUES
(1, 1, 2, NULL, 'subscription', 'Professional plan — monthly', 59.00, 'card',   'verified', 'SUB-2026-001', 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 1, 3, 1,    'membership',   'Alex Carter — Quarterly membership', 79.00, 'card', 'verified', 'MBR-0001', 7, DATE_SUB(NOW(), INTERVAL 40 DAY)),
(3, 1, 3, 3,    'membership',   'Daniel Brooks — Annual membership', 290.00, 'card', 'verified', 'MBR-0003', 7, DATE_SUB(NOW(), INTERVAL 60 DAY)),
(4, 1, 3, 9,    'membership',   'Jack Morris — Monthly membership', 29.00, 'cash', 'verified', 'MBR-0009', 7, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 1, 3, 5,    'membership',   'Frank Miller — Quarterly renewal (manual, unverified)', 79.00, 'manual', 'pending', 'MBR-0005', NULL, NULL);

INSERT INTO invoices (tenant_id, payment_id, number, description, amount, status, due_at, issued_at) VALUES
(1, 1, 'INV-2026-10001', 'Professional plan — monthly subscription', 59.00, 'paid', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(1, 2, 'INV-2026-10002', 'Quarterly membership — Alex Carter', 79.00, 'paid', DATE_SUB(NOW(), INTERVAL 33 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(1, 5, 'INV-2026-10003', 'Quarterly renewal — Frank Miller', 79.00, 'unpaid', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW());

-- Classes & bookings -------------------------------------------------------
INSERT INTO classes (id, tenant_id, branch_id, trainer_id, name, category, day_of_week, start_time, end_time, capacity) VALUES
(1, 1, 1, 1, 'Power Strength',   'Strength',    'Monday',    '07:00:00', '08:00:00', 20),
(2, 1, 1, 2, 'Hypertrophy Lab',  'Bodybuilding','Tuesday',   '18:00:00', '19:00:00', 16),
(3, 1, 1, 3, 'Inferno HIIT',     'Cardio',      'Thursday',  '17:30:00', '18:15:00', 24),
(4, 1, 2, 3, 'Fat Burn Express', 'Weight Loss', 'Wednesday', '07:30:00', '08:15:00', 20),
(5, 1, 1, 1, 'Deadlift Club',    'Strength',    'Friday',    '17:00:00', '18:00:00', 12),
(6, 1, 1, 4, 'Mobility Flow',    'Flexibility', 'Saturday',  '09:00:00', '09:45:00', 18);

INSERT INTO class_bookings (tenant_id, class_id, member_id, status, booked_at) VALUES
(1, 1, 1, 'booked', NOW()), (1, 1, 3, 'booked', NOW()), (1, 2, 2, 'booked', NOW()),
(1, 3, 4, 'booked', NOW()), (1, 5, 1, 'booked', NOW()), (1, 6, 8, 'booked', NOW());

-- Exercises ------------------------------------------------------------------
INSERT INTO exercises (tenant_id, name, category, muscle_group, equipment, difficulty, description) VALUES
(1, 'Barbell Back Squat',   'Strength',    'Quadriceps', 'Barbell',    'Intermediate', 'Compound lower-body lift. Brace, sit between the heels, drive up through mid-foot.'),
(1, 'Bench Press',          'Strength',    'Chest',      'Barbell',    'Intermediate', 'Retract shoulder blades, touch chest under control, press to lockout.'),
(1, 'Deadlift',             'Strength',    'Posterior Chain', 'Barbell','Advanced',   'Hip hinge with a neutral spine; push the floor away.'),
(1, 'Overhead Press',       'Strength',    'Shoulders',  'Barbell',    'Intermediate', 'Strict press with glutes squeezed and ribs down.'),
(1, 'Lat Pulldown',         'Bodybuilding','Back',       'Cable',      'Beginner',     'Drive elbows down to the ribs; full stretch at the top.'),
(1, 'Incline Dumbbell Press','Bodybuilding','Chest',     'Dumbbells',  'Beginner',     '30-degree incline, controlled tempo, deep stretch.'),
(1, 'Romanian Deadlift',    'Bodybuilding','Hamstrings', 'Barbell',    'Intermediate', 'Soft knees, hinge to mid-shin, keep lats tight.'),
(1, 'Treadmill Intervals',  'Cardio',      'Full Body',  'Treadmill',  'Beginner',     '1 minute hard / 1 minute easy intervals.'),
(1, 'Kettlebell Swing',     'Cardio',      'Glutes',     'Kettlebell', 'Intermediate', 'Explosive hip snap; the arms are ropes, not lifters.'),
(1, 'Plank Hold',           'Core',        'Core',       'Bodyweight', 'Beginner',     'Straight line from head to heels; breathe behind the brace.');

-- Workouts (library programs + assigned) --------------------------------------
INSERT INTO workouts (tenant_id, member_id, trainer_id, name, category, level, duration_weeks, description, exercises, status) VALUES
(1, NULL, 1, 'Forge Strength — 5x5',        'Strength',    'Intermediate', 8,  'Classic 5x5 barbell progression for raw strength.', JSON_ARRAY('Barbell Back Squat','Bench Press','Deadlift','Overhead Press'), 'active'),
(1, NULL, 2, 'Hypertrophy Split — Push/Pull/Legs', 'Bodybuilding', 'Intermediate', 12, 'Six-day PPL split focused on muscle growth.', JSON_ARRAY('Incline Dumbbell Press','Lat Pulldown','Romanian Deadlift'), 'active'),
(1, NULL, 3, 'Shred Protocol — HIIT + LISS','Weight Loss', 'Beginner',     6,  'Metabolic conditioning plus low-intensity steady state.', JSON_ARRAY('Treadmill Intervals','Kettlebell Swing','Plank Hold'), 'active'),
(1, NULL, 3, 'Engine Builder — Cardio Base','Cardio',      'Beginner',     4,  'Build aerobic capacity with zone-2 and intervals.', JSON_ARRAY('Treadmill Intervals'), 'active'),
(1, 1,    1, 'Alex — Strength Block A',     'Strength',    'Intermediate', 4,  'Assigned block for Alex Carter: squat and press focus.', JSON_ARRAY('Barbell Back Squat','Overhead Press','Lat Pulldown'), 'active'),
(1, 5,    2, 'Frank — Recomposition Start', 'Weight Loss', 'Beginner',     6,  'Gentle start for Frank Miller: full-body circuits.', JSON_ARRAY('Kettlebell Swing','Plank Hold','Treadmill Intervals'), 'active');

-- Diet plans --------------------------------------------------------------------
INSERT INTO diet_plans (tenant_id, member_id, trainer_id, name, goal, calories, protein_g, carbs_g, fats_g, meals, notes) VALUES
(1, 1, 1, 'Alex — Lean Bulk 3,100 kcal', 'Muscle Gain', 3100, 190, 380, 95,
 JSON_OBJECT('breakfast','Eggs, oats, banana', 'lunch','Chicken, rice, vegetables', 'pre_workout','Whey + dates', 'dinner','Beef, potatoes, salad', 'snack','Greek yoghurt'),
 'Weigh in every Friday morning; adjust +150 kcal if weight stalls for 2 weeks.'),
(1, 5, 2, 'Frank — Fat Loss 2,200 kcal', 'Weight Loss', 2200, 180, 180, 80,
 JSON_OBJECT('breakfast','Egg whites + oats', 'lunch','Grilled chicken salad', 'snack','Whey shake', 'dinner','Fish + vegetables'),
 '10k steps daily. No liquid calories.'),
(1, NULL, NULL, 'Template — Maintenance 2,600 kcal', 'Maintenance', 2600, 170, 300, 85, NULL, 'Reusable template for new members.');

-- Fitness goals + progress -------------------------------------------------------
INSERT INTO fitness_goals (tenant_id, member_id, title, metric, start_value, target_value, current_value, target_date, status) VALUES
(1, 1, 'Squat 140 kg',        'squat_kg',  100.00, 140.00, 120.00, DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'active'),
(1, 1, 'Reach 90 kg bodyweight', 'weight_kg', 82.00, 90.00, 86.00, DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'active'),
(1, 5, 'Lose 10 kg',          'weight_kg', 108.00, 98.00, 102.00, DATE_ADD(CURDATE(), INTERVAL 100 DAY), 'active');

INSERT INTO progress_records (tenant_id, member_id, weight_kg, body_fat_percent, bmi, notes, recorded_at) VALUES
(1, 1, 82.0, 18.5, 25.3, 'Block start', DATE_SUB(NOW(), INTERVAL 60 DAY)),
(1, 1, 84.2, 18.9, 26.0, 'Good progress', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(1, 1, 86.0, 19.4, 26.5, 'Latest check-in', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 5, 108.0, 31.0, 34.1, 'Program start', DATE_SUB(NOW(), INTERVAL 45 DAY)),
(1, 5, 102.0, 28.2, 32.2, 'Down 6 kg', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Equipment ------------------------------------------------------------------------
INSERT INTO equipment (id, tenant_id, branch_id, name, category, brand, serial_number, quantity, status, purchased_at, last_maintenance_at, next_maintenance_at) VALUES
(1, 1, 1, 'Power Rack Pro',       'Strength', 'IronCore', 'PR-1001', 4, 'working',     '2024-03-10', '2026-09-01', '2026-12-01'),
(2, 1, 1, 'Competition Bench',    'Strength', 'IronCore', 'CB-1002', 4, 'working',     '2024-03-10', '2026-09-01', '2026-12-01'),
(3, 1, 1, 'Treadmill X9',         'Cardio',   'CardioMax','TM-2001', 6, 'working',     '2023-11-05', '2026-10-10', DATE_ADD(CURDATE(), INTERVAL 6 DAY)),
(4, 1, 1, 'Air Bike Assault',     'Cardio',   'AirFit',   'AB-3001', 3, 'maintenance', '2024-06-18', '2026-08-15', DATE_ADD(CURDATE(), INTERVAL 2 DAY)),
(5, 1, 2, 'Cable Crossover Station','Strength','FlexLine', 'CC-4001', 2, 'working',     '2025-01-22', '2026-09-20', '2027-01-20'),
(6, 1, 1, 'Dumbbell Set 5-50 kg', 'Free Weights','IronCore','DB-5001', 1, 'working',    '2024-05-30', NULL,         '2026-12-15'),
(7, 1, 2, 'Rowing Machine R5',    'Cardio',   'CardioMax','RM-6001', 3, 'working',     '2025-02-14', '2026-10-01', '2027-02-01'),
(8, 1, 1, 'Leg Press 45°',        'Strength', 'FlexLine', 'LP-7001', 1, 'broken',      '2022-08-19', '2026-07-11', DATE_SUB(CURDATE(), INTERVAL 3 DAY));

INSERT INTO equipment_maintenance (tenant_id, equipment_id, type, description, cost, performed_at, next_due_at) VALUES
(1, 3, 'routine', 'Belt lubrication and deck inspection', 45.00, '2026-10-10', DATE_ADD(CURDATE(), INTERVAL 6 DAY)),
(1, 8, 'repair',  'Hydraulic piston leak reported; awaiting parts', 0.00, '2026-07-11', DATE_SUB(CURDATE(), INTERVAL 3 DAY));

-- Attendance today --------------------------------------------------------------------
INSERT INTO attendance (tenant_id, branch_id, member_id, check_in_at, check_out_at, method) VALUES
(1, 1, 1, TIMESTAMP(CURDATE(), '07:15:00'), TIMESTAMP(CURDATE(), '08:30:00'), 'qr'),
(1, 1, 3, TIMESTAMP(CURDATE(), '07:40:00'), NULL, 'qr'),
(1, 1, 6, TIMESTAMP(CURDATE(), '08:05:00'), NULL, 'manual'),
(1, 2, 4, TIMESTAMP(CURDATE(), '06:50:00'), TIMESTAMP(CURDATE(), '07:45:00'), 'qr'),
(1, 1, 1, TIMESTAMP(CURDATE(), '17:05:00'), NULL, 'qr');

-- Notifications, tickets, announcements, activity ---------------------------------------
INSERT INTO notifications (tenant_id, user_id, title, body, type) VALUES
(1, NULL, 'Memberships expiring soon', '2 memberships expire within 7 days. Send renewal reminders.', 'warning'),
(1, NULL, 'New member joined', 'Jack Morris joined on a Monthly plan.', 'success'),
(NULL, NULL, 'Platform update', 'FitFlow Pro November release is live.', 'info');

INSERT INTO support_tickets (tenant_id, user_id, subject, message, priority, status) VALUES
(1, 2, 'QR scanner at Downtown branch is slow', 'The check-in tablet at Downtown takes ~10 seconds per scan since yesterday.', 'medium', 'open');

INSERT INTO announcements (title, body, is_active, created_by) VALUES
('November platform update', 'New: QR check-in speed improvements and subscription grace periods are now live for all gyms.', 1, 1);

INSERT INTO activity_logs (tenant_id, user_id, action, entity, entity_id, details) VALUES
(1, 3, 'member.created', 'members', 9, 'Jack Morris registered at Main Branch'),
(1, 7, 'payment.verified', 'payments', 4, 'Membership payment MBR-0009 verified'),
(NULL, 1, 'tenant.status_checked', 'tenants', 1, 'Routine platform health check');

-- Upgrade seeds: medical records, expenses, salaries, trainer attendance,
-- one audited attendance correction and workout completion logs.
INSERT INTO member_medical_records (id, tenant_id, member_id, clearance_status, conditions, allergies, medications, doctor_name, clearance_date, expiry_date, notes, recorded_by) VALUES
(1, 1, 1, 'cleared',    'None reported', 'None known', 'None', 'Dr. Sarah Kim', DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 335 DAY), 'Full clearance for strength training including heavy compound lifts.', 3),
(2, 1, 5, 'restricted', 'Controlled hypertension', 'Penicillin', 'Lisinopril 10mg daily', 'Dr. Mark Webb', DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'Cleared for moderate cardio only; no maximal-effort lifts until review.', 3);

INSERT INTO expenses (id, tenant_id, branch_id, category, title, amount, expense_date, method, reference, recorded_by, notes) VALUES
(1, 1, 1, 'Rent',      'Main Branch monthly rent',        2400.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY),  'bank', 'TRX-RENT-1106', 3, 'November lease'),
(2, 1, 1, 'Utilities', 'Electricity & water — Main Branch', 385.50, DATE_SUB(CURDATE(), INTERVAL 9 DAY),  'card', 'BILL-88412',      7, NULL),
(3, 1, 2, 'Utilities', 'Electricity — Downtown Branch',    210.75, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'card', 'BILL-88455',      7, NULL),
(4, 1, 1, 'Marketing', 'Instagram ads — new member drive', 150.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'card', 'ADS-NOV-01',      3, NULL),
(5, 1, 1, 'Maintenance','Treadmill belt replacement',       95.00, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'cash', NULL,              3, 'Unit TM-02');

INSERT INTO staff_salaries (id, tenant_id, user_id, trainer_id, person_name, role_label, period_month, base_salary, allowances, deductions, method, status, paid_at, notes) VALUES
(1, 1, 5,    1, 'Jake Thompson', 'Head Trainer',     DATE_FORMAT(CURDATE(), '%Y-%m'), 2200.00, 150.00, 0.00,   'bank', 'paid',    DATE_SUB(NOW(), INTERVAL 2 DAY), 'Includes PT commission'),
(2, 1, NULL, 2, 'Ryan Delgado',  'Trainer',          DATE_FORMAT(CURDATE(), '%Y-%m'), 1800.00, 100.00, 0.00,   'bank', 'pending', NULL, NULL),
(3, 1, NULL, 3, 'Chris Novak',   'Trainer',          DATE_FORMAT(CURDATE(), '%Y-%m'), 1700.00, 100.00, 50.00,  'bank', 'pending', NULL, NULL),
(4, 1, 6,    NULL, 'Olivia Reed','Receptionist',     DATE_FORMAT(CURDATE(), '%Y-%m'), 1200.00, 0.00,   0.00,   'bank', 'paid',    DATE_SUB(NOW(), INTERVAL 2 DAY), NULL);

INSERT INTO trainer_attendance (id, tenant_id, trainer_id, branch_id, check_in_at, check_out_at, method) VALUES
(1, 1, 1, 1, TIMESTAMP(CURDATE(), '05:45:00'), NULL, 'manual'),
(2, 1, 2, 1, TIMESTAMP(CURDATE(), '06:00:00'), TIMESTAMP(CURDATE(), '10:00:00'), 'manual'),
(3, 1, 3, 2, TIMESTAMP(CURDATE(), '07:15:00'), NULL, 'manual');

INSERT INTO attendance_corrections (id, tenant_id, attendance_id, subject_type, subject_id, corrected_by, field, old_value, new_value, reason) VALUES
(1, 1, 3, 'member', 3, 3, 'check_in_at', DATE_FORMAT(TIMESTAMP(CURDATE(), '07:30:00'), '%Y-%m-%d %H:%i:%s'), DATE_FORMAT(TIMESTAMP(CURDATE(), '07:05:00'), '%Y-%m-%d %H:%i:%s'), 'Front desk clock error — member arrived at 07:05, confirmed on camera.');

INSERT INTO workout_logs (id, tenant_id, member_id, workout_id, exercise_name, sets, reps, weight_kg, duration_min, notes, completed_at) VALUES
(1, 1, 1, 1, 'Barbell Squat',      4, 8,  100.0, NULL, 'Top set felt strong', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 1, 1, 1, 'Bench Press',        4, 10, 80.0,  NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 1, 1, 1, 'Deadlift',           3, 5,  140.0, NULL, 'New personal best', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 1, 1, 2, 'Overhead Press',     4, 10, 50.0,  NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 1, 3, 1, 'Barbell Squat',      4, 8,  90.0,  NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 1, 2, 3, 'Romanian Deadlift',  4, 10, 90.0,  NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY));
