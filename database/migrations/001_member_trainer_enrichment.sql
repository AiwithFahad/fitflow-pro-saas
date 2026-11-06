-- =====================================================================
-- FitFlow Pro — Migration 001: member & trainer enrichment
-- Non-destructive: only ADDs columns / widens ENUMs / backfills codes.
-- Run once, in order, against an existing fitflow_pro database:
--   mysql -u root -p fitflow_pro < database/migrations/001_member_trainer_enrichment.sql
-- =====================================================================

-- Members: photo, address, emergency contacts, human-friendly member code.
ALTER TABLE members
  ADD COLUMN photo_url              VARCHAR(255) NULL AFTER phone,
  ADD COLUMN address                VARCHAR(255) NULL AFTER photo_url,
  ADD COLUMN emergency_contact_name VARCHAR(120) NULL AFTER address,
  ADD COLUMN emergency_contact_phone VARCHAR(40) NULL AFTER emergency_contact_name,
  ADD COLUMN member_code            VARCHAR(20)  NULL AFTER emergency_contact_phone;

-- Backfill unique membership IDs (FFP-M0001, ...) for existing members.
UPDATE members SET member_code = CONCAT('FFP-M', LPAD(id, 4, '0')) WHERE member_code IS NULL;
ALTER TABLE members ADD UNIQUE KEY uq_member_code (member_code);

-- Membership lifecycle: allow suspension (pause) alongside active/expired/cancelled.
ALTER TABLE memberships
  MODIFY status ENUM('active','expired','cancelled','suspended') NOT NULL DEFAULT 'active';
ALTER TABLE members
  MODIFY status ENUM('active','inactive','cancelled','suspended') NOT NULL DEFAULT 'active';

-- Trainers: formal qualifications (certifications, licences).
ALTER TABLE trainers
  ADD COLUMN qualifications VARCHAR(255) NULL AFTER specialty;
