-- ============================================================================
-- SCHOOMA - Migration 002 : roles de direction des ecoles
--   * admin_ecole       = Directeur General / PCA (aspects decisionnels)
--   * directeur_etudes  = Directeur des etudes (aspects academiques)
--   * validation des deliberations par la Direction generale avant publication
-- A executer UNE SEULE FOIS, apres 001_lmd.sql (inutile sur une installation
-- neuve faite avec le database.sql actuel).
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE users
    MODIFY role ENUM('super_admin','admin_ecole','directeur_etudes','assistant') NOT NULL DEFAULT 'assistant',
    ADD COLUMN job_title VARCHAR(100) NULL AFTER role;

UPDATE users SET job_title = 'Directeur General' WHERE role = 'admin_ecole' AND job_title IS NULL;

ALTER TABLE juries
    ADD COLUMN validated_by INT UNSIGNED NULL AFTER observations,
    ADD COLUMN validated_at DATETIME NULL AFTER validated_by,
    ADD CONSTRAINT fk_juries_validated_by FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL;

-- Les jurys deja clotures dont les resultats sont publies sont consideres comme valides
UPDATE juries j SET j.validated_at = j.deliberated_at
WHERE j.status = 'cloture' AND EXISTS (SELECT 1 FROM results r WHERE r.jury_id = j.id AND r.published = 1);
