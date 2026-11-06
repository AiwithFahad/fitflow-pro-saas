-- =====================================================================
-- FitFlow Pro — Migration 003: upgrade permissions
-- Adds restricted medical access, finance management, attendance
-- correction and trainer-attendance permissions, with role grants.
-- Safe to re-run (INSERT IGNORE).
--   mysql -u root -p fitflow_pro < database/migrations/003_permissions_upgrade.sql
-- =====================================================================

INSERT IGNORE INTO permissions (slug, description) VALUES
('members.medical',    'View and manage member medical clearance records (restricted)'),
('finance.view',       'View expenses, salaries and financial summaries'),
('finance.manage',     'Manage expenses and salary records'),
('attendance.correct', 'Correct attendance records with audit logging'),
('trainers.attendance','Manage trainer attendance');

-- Gym Owner (2): everything tenant-side, as before.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT 2, id FROM permissions WHERE slug IN ('members.medical','finance.view','finance.manage','attendance.correct','trainers.attendance');
-- Gym Admin (3): medical + finance + corrections.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT 3, id FROM permissions WHERE slug IN ('members.medical','finance.view','finance.manage','attendance.correct','trainers.attendance');
-- Branch Manager (4): corrections + trainer attendance at their branch.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT 4, id FROM permissions WHERE slug IN ('attendance.correct','trainers.attendance');
-- Accountant (7): finance.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT 7, id FROM permissions WHERE slug IN ('finance.view','finance.manage');
