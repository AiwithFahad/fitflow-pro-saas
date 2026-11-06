-- =====================================================================
-- FitFlow Pro — Migration 004: columns the admin UI already uses.
-- The Super Admin plans screen reads/writes tagline, trial_days and
-- features on subscription_plans; the equipment maintenance log
-- reads/writes vendor on equipment_maintenance. Both were missing
-- from the original schema, causing "Undefined array key" warnings
-- on reads and failed inserts on writes. Run once on existing installs:
--   mysql -u root -p fitflow_pro < database/migrations/004_plan_and_maintenance_columns.sql
-- (Fresh installs use database.sql, which already includes these.)
-- =====================================================================

ALTER TABLE subscription_plans
  ADD COLUMN tagline    VARCHAR(120) NULL AFTER slug,
  ADD COLUMN trial_days INT NOT NULL DEFAULT 0 AFTER price_yearly,
  ADD COLUMN features   TEXT NULL AFTER trial_days;

UPDATE subscription_plans SET trial_days = 14 WHERE slug = 'free-trial' AND trial_days = 0;

ALTER TABLE equipment_maintenance
  ADD COLUMN vendor VARCHAR(120) NULL AFTER description;
