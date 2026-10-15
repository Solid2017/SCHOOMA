-- ============================================================================
-- SCHOOMA - Migration 001 : systeme LMD (UE / credits), releves de notes, jurys
-- A executer UNE SEULE FOIS sur une base creee avec l'ancienne version de
-- database.sql (les nouvelles installations n'en ont pas besoin).
-- ============================================================================

SET NAMES utf8mb4;

-- Ville de l'etablissement (utilisee sur les releves et les PV : "Fait a ...")
ALTER TABLE clients ADD COLUMN city VARCHAR(100) NULL AFTER address;

-- Unites d'enseignement
CREATE TABLE IF NOT EXISTS teaching_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    category ENUM('fondamentale','methodologique','transversale','decouverte') NOT NULL DEFAULT 'fondamentale',
    credits DECIMAL(4,1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    KEY idx_tu_class_semester (class_id, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Matieres = ECUE rattaches a une UE
ALTER TABLE subjects
    ADD COLUMN teaching_unit_id INT UNSIGNED NULL AFTER class_id,
    ADD COLUMN code VARCHAR(20) NULL AFTER teaching_unit_id,
    ADD COLUMN credits DECIMAL(4,1) NULL AFTER name,
    ADD CONSTRAINT fk_subjects_tu FOREIGN KEY (teaching_unit_id) REFERENCES teaching_units(id) ON DELETE SET NULL;

-- Jurys de deliberation
ALTER TABLE juries
    ADD COLUMN session_type ENUM('normale','rattrapage') NOT NULL DEFAULT 'normale' AFTER name,
    ADD COLUMN location VARCHAR(150) NULL AFTER session_date,
    ADD COLUMN deliberated_at DATETIME NULL AFTER status,
    ADD COLUMN observations TEXT NULL AFTER deliberated_at;

UPDATE jury_members SET role = 'membre'
WHERE role NOT IN ('president','vice_president','secretaire','rapporteur','membre');

ALTER TABLE jury_members
    MODIFY teacher_id INT UNSIGNED NULL,
    ADD COLUMN full_name VARCHAR(150) NULL AFTER teacher_id,
    ADD COLUMN title VARCHAR(150) NULL AFTER full_name,
    MODIFY role ENUM('president','vice_president','secretaire','rapporteur','membre') NOT NULL DEFAULT 'membre',
    ADD UNIQUE KEY uniq_jury_teacher (jury_id, teacher_id);

-- Resultats annuels au format LMD (60 credits)
ALTER TABLE results
    ADD COLUMN average_s1 DECIMAL(5,2) NULL AFTER average,
    ADD COLUMN average_s2 DECIMAL(5,2) NULL AFTER average_s1,
    ADD COLUMN credits_s1 DECIMAL(4,1) NOT NULL DEFAULT 0 AFTER average_s2,
    ADD COLUMN credits_s2 DECIMAL(4,1) NOT NULL DEFAULT 0 AFTER credits_s1,
    ADD COLUMN credits_total DECIMAL(4,1) NOT NULL DEFAULT 0 AFTER credits_s2,
    ADD COLUMN is_complete TINYINT(1) NOT NULL DEFAULT 0 AFTER credits_total,
    MODIFY decision ENUM('admis','admis_dettes','ajourne','redouble','exclu') NOT NULL DEFAULT 'ajourne',
    ADD COLUMN jury_decision ENUM('admis','admis_dettes','ajourne','redouble','exclu') NULL AFTER decision,
    ADD COLUMN jury_points DECIMAL(4,2) NOT NULL DEFAULT 0 AFTER jury_decision,
    ADD COLUMN observation VARCHAR(255) NULL AFTER jury_points,
    ADD COLUMN jury_id INT UNSIGNED NULL AFTER observation,
    ADD COLUMN computed_at DATETIME NULL AFTER published,
    ADD CONSTRAINT fk_results_jury FOREIGN KEY (jury_id) REFERENCES juries(id) ON DELETE SET NULL;
