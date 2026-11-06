-- =====================================================================
-- FitFlow Pro — Migration 002: finance, medical, trainer attendance,
-- attendance corrections and workout completion logs.
-- Non-destructive: CREATE TABLE IF NOT EXISTS only.
--   mysql -u root -p fitflow_pro < database/migrations/002_finance_medical_attendance_tables.sql
-- =====================================================================

-- Member medical clearance records (RESTRICTED: 'members.medical' permission).
CREATE TABLE IF NOT EXISTS member_medical_records (
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

-- Staff salary records (trainers + staff users).
CREATE TABLE IF NOT EXISTS staff_salaries (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NULL,
  trainer_id   INT UNSIGNED NULL,
  person_name  VARCHAR(120) NOT NULL,
  role_label   VARCHAR(80) NULL,
  period_month VARCHAR(7) NOT NULL,             -- YYYY-MM
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

-- Operating expenses (rent, utilities, marketing, ...).
CREATE TABLE IF NOT EXISTS expenses (
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

-- Trainer attendance (staff check-in / check-out).
CREATE TABLE IF NOT EXISTS trainer_attendance (
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

-- Attendance correction audit trail (every manual fix is recorded).
CREATE TABLE IF NOT EXISTS attendance_corrections (
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

-- Workout completion logs (per-exercise sets/reps/weight tracking).
CREATE TABLE IF NOT EXISTS workout_logs (
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
