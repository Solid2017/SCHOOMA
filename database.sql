-- ============================================================================
-- SCHOOMA - SaaS de gestion des services universitaires
-- Script d'initialisation de la base de données MySQL
-- Compatible hébergement mutualisé (LWS) - MySQL 5.7+/8+
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. GESTION DU SAAS (Clients / Plans / Facturation)
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    billing_cycle ENUM('mensuel','annuel') NOT NULL DEFAULT 'annuel',
    max_students INT UNSIGNED NOT NULL DEFAULT 100,
    max_teachers INT UNSIGNED NOT NULL DEFAULT 20,
    features TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    logo VARCHAR(255) NULL,
    plan_id INT UNSIGNED NULL,
    status ENUM('actif','suspendu','essai','resilie') NOT NULL DEFAULT 'essai',
    trial_ends_at DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status ENUM('en_attente','payee','en_retard','annulee') NOT NULL DEFAULT 'en_attente',
    paid_at DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 2. UTILISATEURS & ROLES (panel admin)
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NULL, -- NULL = super-admin SaaS
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin_ecole','assistant') NOT NULL DEFAULT 'assistant',
    photo VARCHAR(255) NULL,
    status ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 3. ANNEES UNIVERSITAIRES / FILIERES / MATIERES
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS academic_years (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    label VARCHAR(20) NOT NULL, -- ex: 2025-2026
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL, -- ex: Licence 1 Informatique
    level VARCHAR(50) NULL,
    tuition_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Unites d'enseignement (UE) du systeme LMD : 30 credits par semestre, 60 par annee
CREATE TABLE IF NOT EXISTS teaching_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL, -- numero LMD : 1..6 (Licence), 7..10 (Master)
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

-- Matieres = elements constitutifs (ECUE) d'une UE
CREATE TABLE IF NOT EXISTS subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    teaching_unit_id INT UNSIGNED NULL,
    code VARCHAR(20) NULL,
    name VARCHAR(150) NOT NULL,
    credits DECIMAL(4,1) NULL,
    coefficient DECIMAL(4,2) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teaching_unit_id) REFERENCES teaching_units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 4. ENSEIGNANTS & PAIEMENTS ENSEIGNANTS
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS teachers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    matricule VARCHAR(30) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    specialty VARCHAR(150) NULL,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
    photo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teacher_courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    hours_per_week DECIMAL(5,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teacher_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    period VARCHAR(20) NOT NULL, -- ex: 2026-01
    amount DECIMAL(10,2) NOT NULL,
    hours_done DECIMAL(6,2) NOT NULL DEFAULT 0,
    status ENUM('en_attente','paye') NOT NULL DEFAULT 'en_attente',
    paid_date DATE NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 5. ETUDIANTS / INSCRIPTIONS / PAIEMENTS PAR TRANCHES
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    matricule VARCHAR(30) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    dob DATE NULL,
    gender ENUM('M','F') NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    photo VARCHAR(255) NULL,
    status ENUM('actif','inactif','diplome','abandon') NOT NULL DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_matricule_client (client_id, matricule)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    registration_date DATE NOT NULL,
    amount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('en_cours','validee','annulee') NOT NULL DEFAULT 'en_cours',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    registration_id INT UNSIGNED NOT NULL,
    tranche_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    due_date DATE NULL,
    paid_date DATE NULL,
    method ENUM('especes','mobile_money','virement','cheque','autre') NOT NULL DEFAULT 'especes',
    receipt_number VARCHAR(50) NULL,
    status ENUM('impaye','partiel','paye') NOT NULL DEFAULT 'impaye',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Demandes d'admission publiques (formulaire du site)
CREATE TABLE IF NOT EXISTS admission_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    program_wanted VARCHAR(150) NULL,
    message TEXT NULL,
    status ENUM('nouveau','contacte','converti','rejete') NOT NULL DEFAULT 'nouveau',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 6. EMPLOI DU TEMPS
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NULL,
    day_of_week TINYINT UNSIGNED NOT NULL, -- 1=Lundi ... 7=Dimanche
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 7. NOTES & RESULTATS ANNUELS
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS grades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    term ENUM('semestre1','semestre2') NOT NULL DEFAULT 'semestre1',
    eval_type ENUM('devoir','examen','tp','rattrapage') NOT NULL DEFAULT 'devoir',
    grade_value DECIMAL(5,2) NOT NULL,
    max_value DECIMAL(5,2) NOT NULL DEFAULT 20,
    eval_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    average DECIMAL(5,2) NOT NULL DEFAULT 0, -- moyenne annuelle finale (points de jury inclus)
    average_s1 DECIMAL(5,2) NULL,
    average_s2 DECIMAL(5,2) NULL,
    credits_s1 DECIMAL(4,1) NOT NULL DEFAULT 0,
    credits_s2 DECIMAL(4,1) NOT NULL DEFAULT 0,
    credits_total DECIMAL(4,1) NOT NULL DEFAULT 0, -- sur 60
    is_complete TINYINT(1) NOT NULL DEFAULT 0,
    class_rank INT UNSIGNED NULL,
    decision ENUM('admis','admis_dettes','ajourne','redouble','exclu') NOT NULL DEFAULT 'ajourne',
    jury_decision ENUM('admis','admis_dettes','ajourne','redouble','exclu') NULL, -- decision imposee par le jury
    jury_points DECIMAL(4,2) NOT NULL DEFAULT 0,
    observation VARCHAR(255) NULL,
    jury_id INT UNSIGNED NULL,
    mention VARCHAR(50) NULL,
    published TINYINT(1) NOT NULL DEFAULT 0,
    computed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (jury_id) REFERENCES juries(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_result (student_id, academic_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 8. JURYS
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS juries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    session_type ENUM('normale','rattrapage') NOT NULL DEFAULT 'normale',
    session_date DATE NULL,
    location VARCHAR(150) NULL,
    status ENUM('planifie','en_cours','cloture') NOT NULL DEFAULT 'planifie',
    deliberated_at DATETIME NULL,
    observations TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Membres designes : enseignant de l'etablissement (teacher_id) ou personnalite externe (full_name)
CREATE TABLE IF NOT EXISTS jury_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jury_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NULL,
    full_name VARCHAR(150) NULL,
    title VARCHAR(150) NULL, -- grade / fonction
    role ENUM('president','vice_president','secretaire','rapporteur','membre') NOT NULL DEFAULT 'membre',
    FOREIGN KEY (jury_id) REFERENCES juries(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_jury_teacher (jury_id, teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 9. SITE VITRINE : CONTACTS / NEWSLETTER / BLOG / CMS
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    subject VARCHAR(200) NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    status ENUM('actif','desabonne') NOT NULL DEFAULT 'actif',
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    excerpt VARCHAR(500) NULL,
    content LONGTEXT NOT NULL,
    image VARCHAR(255) NULL,
    meta_title VARCHAR(200) NULL,
    meta_description VARCHAR(300) NULL,
    author_id INT UNSIGNED NULL,
    status ENUM('brouillon','publie') NOT NULL DEFAULT 'brouillon',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS site_content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NULL,
    content LONGTEXT NULL,
    image VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- DONNEES INITIALES
-- ============================================================================

INSERT INTO subscription_plans (name, slug, price, billing_cycle, max_students, max_teachers, features, is_active) VALUES
('Starter', 'starter', 150000.00, 'annuel', 200, 20, 'Inscriptions,Paiements,Notes', 1),
('Pro', 'pro', 350000.00, 'annuel', 800, 80, 'Inscriptions,Paiements,Notes,Emploi du temps,Resultats,Jurys', 1),
('Enterprise', 'enterprise', 750000.00, 'annuel', 5000, 300, 'Toutes fonctionnalites + support prioritaire', 1);

INSERT INTO clients (name, slug, email, phone, address, city, plan_id, status, trial_ends_at) VALUES
('Institut Superieur SCHOOMA (Demo)', 'schooma-demo', 'contact@schooma.igotech.tech', '+000000000', 'Adresse a definir', 'N''Djamena', 2, 'actif', NULL);

-- Mot de passe par defaut : Admin@2026 (a changer immediatement apres 1re connexion)
-- Hash genere avec password_hash('Admin@2026', PASSWORD_BCRYPT)
INSERT INTO users (client_id, name, email, password_hash, role, status) VALUES
(NULL, 'Super Administrateur', 'superadmin@schooma.app', '$2y$12$b35RoQ8AQExMKNUumVj3euZ0ex0i3yFvONXwU9A2olRxuIodkf/.a', 'super_admin', 'actif'),
(1, 'Administrateur Ecole', 'admin@schooma.igotech.tech', '$2y$12$b35RoQ8AQExMKNUumVj3euZ0ex0i3yFvONXwU9A2olRxuIodkf/.a', 'admin_ecole', 'actif');

INSERT INTO academic_years (client_id, label, start_date, end_date, is_current) VALUES
(1, '2025-2026', '2025-10-01', '2026-07-31', 1);

INSERT INTO site_content (section_key, title, content) VALUES
('hero', 'Bienvenue a SCHOOMA', 'La plateforme SaaS de gestion des services universitaires : inscriptions, paiements, enseignants, emplois du temps, notes et resultats.'),
('about', 'A propos de SCHOOMA', 'SCHOOMA accompagne les etablissements superieurs dans la digitalisation complete de leur gestion academique et administrative.'),
('contact_info', 'Nos coordonnees', 'Email: contact@schooma.igotech.tech | Tel: +000 00 00 00 00');
