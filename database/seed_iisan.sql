-- ============================================================================
-- SCHOOMA - Donnees de test / migration pour l'IISAN N'Djamena
-- (Institut International des Sciences et des Arts du Numerique)
-- Genere automatiquement. A executer sur une base deja initialisee avec database.sql.
-- ============================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ---- Ecole cliente ----
INSERT INTO clients (name, slug, email, phone, address, plan_id, status, trial_ends_at)
VALUES ('IISAN N\'Djamena', 'iisan-ndjamena', 'contact@iisan.td', '+235 66 65 66 34', "Avenue Pascal Yoadimnadji, Chagoua, N'Djamena, Tchad",
        (SELECT id FROM subscription_plans WHERE slug = 'pro'), 'actif', NULL);
SET @client_id = LAST_INSERT_ID();

-- ---- Annee universitaire en cours ----
INSERT INTO academic_years (client_id, label, start_date, end_date, is_current)
VALUES (@client_id, '2025-2026', '2025-10-01', '2026-07-31', 1);
SET @ay_id = LAST_INSERT_ID();

-- ---- Compte administrateur de l'ecole (mot de passe : Iisan@2026 - a changer) ----
INSERT INTO users (client_id, name, email, password_hash, role, status)
VALUES (@client_id, 'Administrateur IISAN', 'admin@iisan.td', '$2y$10$xWO6lfivRdMfc.ibqFD4Ze2gk15hif/1rrsKHsxiB4pimlOu65t5K', 'admin_ecole', 'actif');

-- ---- Filieres ----
INSERT INTO classes (client_id, academic_year_id, name, level, tuition_amount) VALUES (@client_id, @ay_id, 'Developpement d\'Applications Web', 'Licence 1', 650000);
SET @class_1 = LAST_INSERT_ID();
INSERT INTO classes (client_id, academic_year_id, name, level, tuition_amount) VALUES (@client_id, @ay_id, 'Developpement d\'Applications Mobiles', 'Licence 1', 650000);
SET @class_2 = LAST_INSERT_ID();
INSERT INTO classes (client_id, academic_year_id, name, level, tuition_amount) VALUES (@client_id, @ay_id, 'Marketing et Communication Digitale', 'Licence 1', 575000);
SET @class_3 = LAST_INSERT_ID();
INSERT INTO classes (client_id, academic_year_id, name, level, tuition_amount) VALUES (@client_id, @ay_id, 'Analyse de Donnees et Intelligence Artificielle', 'Licence 1', 700000);
SET @class_4 = LAST_INSERT_ID();
INSERT INTO classes (client_id, academic_year_id, name, level, tuition_amount) VALUES (@client_id, @ay_id, 'Internet Industriel des Objets', 'Licence 1', 675000);
SET @class_5 = LAST_INSERT_ID();

-- ---- Matieres ----
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_1, 'Developpement Front-End', 2);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_1, 'Developpement Back-End', 4);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_1, 'Bases de Donnees', 3);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_1, 'Deploiement et DevOps', 2);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_2, 'Programmation Mobile Android', 3);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_2, 'Programmation Mobile iOS', 2);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_2, 'UX/UI Design Mobile', 3);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_2, 'Architecture d\'Applications Mobiles', 4);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_3, 'Marketing Digital', 4);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_3, 'Strategie de Marque', 3);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_3, 'Community Management', 4);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_3, 'Analyse de Performance Web', 2);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_4, 'Statistiques et Probabilites', 3);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_4, 'Machine Learning', 2);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_4, 'Intelligence Artificielle', 3);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_4, 'Visualisation de Donnees', 2);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_5, 'Systemes Embarques', 3);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_5, 'Capteurs et Reseaux IoT', 2);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_5, 'Protocoles de Communication', 4);
INSERT INTO subjects (client_id, class_id, name, coefficient) VALUES (@client_id, @class_5, 'Cloud et Edge Computing', 2);

-- ---- Enseignants ----
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-001', 'Younous', 'Abdelkerim', 'younous.abdelkerim@iisan.td', '+235 65696637', 'Developpement Web', 10000, 'actif');
SET @teacher_1 = LAST_INSERT_ID();
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-002', 'Halime', 'Djime', 'halime.djime@iisan.td', '+235 61388481', 'Developpement Web', 10000, 'actif');
SET @teacher_2 = LAST_INSERT_ID();
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-003', 'Issa', 'Ngarmbatina', 'issa.ngarmbatina@iisan.td', '+235 66738488', 'Developpement Mobile', 9000, 'actif');
SET @teacher_3 = LAST_INSERT_ID();
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-004', 'Ramatou', 'Ousmane', 'ramatou.ousmane@iisan.td', '+235 66924923', 'Developpement Mobile', 6000, 'actif');
SET @teacher_4 = LAST_INSERT_ID();
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-005', 'Ahmat', 'Yacoub', 'ahmat.yacoub@iisan.td', '+235 61040452', 'Marketing Digital', 9000, 'actif');
SET @teacher_5 = LAST_INSERT_ID();
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-006', 'Djamila', 'Ramadane', 'djamila.ramadane@iisan.td', '+235 67420857', 'Marketing Digital', 9000, 'actif');
SET @teacher_6 = LAST_INSERT_ID();
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-007', 'Abdoulaye', 'Togoi', 'abdoulaye.togoi@iisan.td', '+235 67215643', 'Data & IA', 7000, 'actif');
SET @teacher_7 = LAST_INSERT_ID();
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-008', 'Mahadjir', 'Barka', 'mahadjir.barka@iisan.td', '+235 62768909', 'Data & IA', 10000, 'actif');
SET @teacher_8 = LAST_INSERT_ID();
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-009', 'Nour', 'Gredaye', 'nour.gredaye@iisan.td', '+235 68821237', 'Internet des Objets', 10000, 'actif');
SET @teacher_9 = LAST_INSERT_ID();
INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, 'ENS-IISAN-010', 'Khalil', 'Moussa', 'khalil.moussa@iisan.td', '+235 69486126', 'Internet des Objets', 8000, 'actif');
SET @teacher_10 = LAST_INSERT_ID();

-- ---- Etudiants, inscriptions, paiements ----
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0001', 'Ali', 'Abakar', '2006-08-23', 'M', '+235 93163977', 'ali.abakar1@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_1 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_1, @class_1, @ay_id, '2025-10-03', 650000, 'validee');
SET @reg_1 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_1, 1, 325000, 325000, '2025-11-01', '2025-11-08', 'especes', 'IISAN-REC-2001-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_1, 2, 325000, 0, '2026-02-01', NULL, 'especes', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0002', 'Oumar', 'Souleymane', '2005-05-21', 'M', '+235 97176374', 'oumar.souleymane2@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_2 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_2, @class_1, @ay_id, '2025-10-03', 650000, 'validee');
SET @reg_2 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_2, 1, 325000, 162500, '2025-11-01', '2025-11-12', 'virement', 'IISAN-REC-2002-1', 'partiel');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_2, 2, 325000, 0, '2026-02-01', NULL, 'virement', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0003', 'Fatime', 'Gredaye', '2002-11-25', 'F', '+235 94480100', 'fatime.gredaye3@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_3 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_3, @class_1, @ay_id, '2025-10-07', 650000, 'validee');
SET @reg_3 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_3, 1, 325000, 325000, '2025-11-01', '2025-10-27', 'mobile_money', 'IISAN-REC-2003-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_3, 2, 325000, 325000, '2026-02-01', '2026-01-20', 'mobile_money', 'IISAN-REC-2003-2', 'paye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0004', 'Abakar', 'Nadjirou', '2001-08-09', 'M', '+235 99841144', 'abakar.nadjirou4@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_4 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_4, @class_1, @ay_id, '2025-10-04', 650000, 'validee');
SET @reg_4 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_4, 1, 325000, 325000, '2025-11-01', '2025-11-08', 'especes', 'IISAN-REC-2004-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_4, 2, 325000, 0, '2026-02-01', NULL, 'especes', NULL, 'impaye');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_1, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Developpement Front-End'), @ay_id, 'semestre1', 'devoir', 12.8, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_2, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Developpement Front-End'), @ay_id, 'semestre1', 'devoir', 17.7, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_3, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Developpement Front-End'), @ay_id, 'semestre1', 'devoir', 12.2, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_4, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Developpement Front-End'), @ay_id, 'semestre1', 'devoir', 12.8, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_1, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Developpement Back-End'), @ay_id, 'semestre1', 'devoir', 10.1, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_2, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Developpement Back-End'), @ay_id, 'semestre1', 'devoir', 11.8, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_3, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Developpement Back-End'), @ay_id, 'semestre1', 'devoir', 17.4, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_4, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Developpement Back-End'), @ay_id, 'semestre1', 'devoir', 13.8, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_1, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Bases de Donnees'), @ay_id, 'semestre1', 'devoir', 13.4, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_2, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Bases de Donnees'), @ay_id, 'semestre1', 'devoir', 11.3, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_3, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Bases de Donnees'), @ay_id, 'semestre1', 'devoir', 13.2, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_4, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Bases de Donnees'), @ay_id, 'semestre1', 'devoir', 11.8, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_1, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Deploiement et DevOps'), @ay_id, 'semestre1', 'devoir', 13.8, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_2, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Deploiement et DevOps'), @ay_id, 'semestre1', 'devoir', 15, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_3, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Deploiement et DevOps'), @ay_id, 'semestre1', 'devoir', 15.2, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_4, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_1 AND name = 'Deploiement et DevOps'), @ay_id, 'semestre1', 'devoir', 13, 20, '2025-12-12');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0005', 'Moussa', 'Djime', '2002-10-03', 'M', '+235 92016152', 'moussa.djime5@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_5 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_5, @class_2, @ay_id, '2025-10-03', 650000, 'validee');
SET @reg_5 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_5, 1, 325000, 162500, '2025-11-01', '2025-11-12', 'virement', 'IISAN-REC-2005-1', 'partiel');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_5, 2, 325000, 0, '2026-02-01', NULL, 'virement', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0006', 'Halime', 'Tahir', '2003-05-05', 'F', '+235 95676030', 'halime.tahir6@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_6 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_6, @class_2, @ay_id, '2025-10-07', 650000, 'validee');
SET @reg_6 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_6, 1, 325000, 325000, '2025-11-01', '2025-10-27', 'mobile_money', 'IISAN-REC-2006-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_6, 2, 325000, 325000, '2026-02-01', '2026-01-20', 'mobile_money', 'IISAN-REC-2006-2', 'paye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0007', 'Idriss', 'Ousmane', '2003-09-14', 'M', '+235 98018129', 'idriss.ousmane7@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_7 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_7, @class_2, @ay_id, '2025-10-02', 650000, 'validee');
SET @reg_7 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_7, 1, 325000, 325000, '2025-11-01', '2025-11-08', 'especes', 'IISAN-REC-2007-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_7, 2, 325000, 0, '2026-02-01', NULL, 'especes', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0008', 'Hissein', 'Hissein', '2001-05-27', 'M', '+235 91410635', 'hissein.hissein8@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_8 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_8, @class_2, @ay_id, '2025-10-04', 650000, 'validee');
SET @reg_8 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_8, 1, 325000, 162500, '2025-11-01', '2025-11-12', 'virement', 'IISAN-REC-2008-1', 'partiel');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_8, 2, 325000, 0, '2026-02-01', NULL, 'virement', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0009', 'Zeinab', 'Togoi', '2004-12-11', 'F', '+235 97479827', 'zeinab.togoi9@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_9 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_9, @class_3, @ay_id, '2025-10-03', 575000, 'validee');
SET @reg_9 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_9, 1, 287500, 287500, '2025-11-01', '2025-10-27', 'mobile_money', 'IISAN-REC-2009-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_9, 2, 287500, 287500, '2026-02-01', '2026-01-20', 'mobile_money', 'IISAN-REC-2009-2', 'paye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0010', 'Djamila', 'Djime', '2004-10-09', 'F', '+235 91147479', 'djamila.djime10@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_10 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_10, @class_3, @ay_id, '2025-10-02', 575000, 'validee');
SET @reg_10 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_10, 1, 287500, 287500, '2025-11-01', '2025-11-08', 'especes', 'IISAN-REC-2010-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_10, 2, 287500, 0, '2026-02-01', NULL, 'especes', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0011', 'Mariam', 'Togoi', '2003-06-10', 'F', '+235 98103742', 'mariam.togoi11@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_11 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_11, @class_3, @ay_id, '2025-10-04', 575000, 'validee');
SET @reg_11 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_11, 1, 287500, 143750, '2025-11-01', '2025-11-12', 'virement', 'IISAN-REC-2011-1', 'partiel');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_11, 2, 287500, 0, '2026-02-01', NULL, 'virement', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0012', 'Hawa', 'Allamine', '2002-11-18', 'F', '+235 95576016', 'hawa.allamine12@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_12 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_12, @class_3, @ay_id, '2025-10-09', 575000, 'validee');
SET @reg_12 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_12, 1, 287500, 287500, '2025-11-01', '2025-10-27', 'mobile_money', 'IISAN-REC-2012-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_12, 2, 287500, 287500, '2026-02-01', '2026-01-20', 'mobile_money', 'IISAN-REC-2012-2', 'paye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0013', 'Amina', 'Yacoub', '2001-01-05', 'F', '+235 98075849', 'amina.yacoub13@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_13 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_13, @class_4, @ay_id, '2025-10-07', 700000, 'validee');
SET @reg_13 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_13, 1, 350000, 350000, '2025-11-01', '2025-11-08', 'especes', 'IISAN-REC-2013-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_13, 2, 350000, 0, '2026-02-01', NULL, 'especes', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0014', 'Halime', 'Ngarmbatina', '2003-07-20', 'F', '+235 99754777', 'halime.ngarmbatina14@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_14 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_14, @class_4, @ay_id, '2025-10-05', 700000, 'validee');
SET @reg_14 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_14, 1, 350000, 175000, '2025-11-01', '2025-11-12', 'virement', 'IISAN-REC-2014-1', 'partiel');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_14, 2, 350000, 0, '2026-02-01', NULL, 'virement', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0015', 'Hadjara', 'Ngarmbatina', '2004-06-04', 'F', '+235 92415482', 'hadjara.ngarmbatina15@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_15 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_15, @class_4, @ay_id, '2025-10-04', 700000, 'validee');
SET @reg_15 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_15, 1, 350000, 350000, '2025-11-01', '2025-10-27', 'mobile_money', 'IISAN-REC-2015-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_15, 2, 350000, 350000, '2026-02-01', '2026-01-20', 'mobile_money', 'IISAN-REC-2015-2', 'paye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0016', 'Abdoulaye', 'Adoum', '2004-11-19', 'M', '+235 93635672', 'abdoulaye.adoum16@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_16 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_16, @class_4, @ay_id, '2025-10-03', 700000, 'validee');
SET @reg_16 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_16, 1, 350000, 350000, '2025-11-01', '2025-11-08', 'especes', 'IISAN-REC-2016-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_16, 2, 350000, 0, '2026-02-01', NULL, 'especes', NULL, 'impaye');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_13, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Statistiques et Probabilites'), @ay_id, 'semestre1', 'devoir', 11.5, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_14, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Statistiques et Probabilites'), @ay_id, 'semestre1', 'devoir', 16.2, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_15, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Statistiques et Probabilites'), @ay_id, 'semestre1', 'devoir', 10.1, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_16, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Statistiques et Probabilites'), @ay_id, 'semestre1', 'devoir', 17.3, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_13, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Machine Learning'), @ay_id, 'semestre1', 'devoir', 12.5, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_14, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Machine Learning'), @ay_id, 'semestre1', 'devoir', 9.7, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_15, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Machine Learning'), @ay_id, 'semestre1', 'devoir', 9.9, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_16, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Machine Learning'), @ay_id, 'semestre1', 'devoir', 10.1, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_13, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Intelligence Artificielle'), @ay_id, 'semestre1', 'devoir', 14.2, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_14, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Intelligence Artificielle'), @ay_id, 'semestre1', 'devoir', 9.1, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_15, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Intelligence Artificielle'), @ay_id, 'semestre1', 'devoir', 10, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_16, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Intelligence Artificielle'), @ay_id, 'semestre1', 'devoir', 16.2, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_13, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Visualisation de Donnees'), @ay_id, 'semestre1', 'devoir', 14.6, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_14, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Visualisation de Donnees'), @ay_id, 'semestre1', 'devoir', 10.4, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_15, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Visualisation de Donnees'), @ay_id, 'semestre1', 'devoir', 12.7, 20, '2025-12-12');
INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES (@client_id, @student_16, (SELECT id FROM subjects WHERE client_id = @client_id AND class_id = @class_4 AND name = 'Visualisation de Donnees'), @ay_id, 'semestre1', 'devoir', 17.5, 20, '2025-12-12');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0017', 'Djamila', 'Adoum', '2005-11-18', 'F', '+235 91388277', 'djamila.adoum17@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_17 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_17, @class_5, @ay_id, '2025-10-04', 675000, 'validee');
SET @reg_17 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_17, 1, 337500, 168750, '2025-11-01', '2025-11-12', 'virement', 'IISAN-REC-2017-1', 'partiel');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_17, 2, 337500, 0, '2026-02-01', NULL, 'virement', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0018', 'Oumar', 'Barka', '2005-05-01', 'M', '+235 95717190', 'oumar.barka18@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_18 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_18, @class_5, @ay_id, '2025-10-01', 675000, 'validee');
SET @reg_18 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_18, 1, 337500, 337500, '2025-11-01', '2025-10-27', 'mobile_money', 'IISAN-REC-2018-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_18, 2, 337500, 337500, '2026-02-01', '2026-01-20', 'mobile_money', 'IISAN-REC-2018-2', 'paye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0019', 'Fatime', 'Mahamat', '2001-04-22', 'F', '+235 91822144', 'fatime.mahamat19@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_19 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_19, @class_5, @ay_id, '2025-10-07', 675000, 'validee');
SET @reg_19 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_19, 1, 337500, 337500, '2025-11-01', '2025-11-08', 'especes', 'IISAN-REC-2019-1', 'paye');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_19, 2, 337500, 0, '2026-02-01', NULL, 'especes', NULL, 'impaye');
INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, 'IISAN-25-0020', 'Zara', 'Hissein', '2002-07-21', 'F', '+235 91347842', 'zara.hissein20@iisan-etudiant.td', "N'Djamena", 'actif');
SET @student_20 = LAST_INSERT_ID();
INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student_20, @class_5, @ay_id, '2025-10-08', 675000, 'validee');
SET @reg_20 = LAST_INSERT_ID();
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_20, 1, 337500, 168750, '2025-11-01', '2025-11-12', 'virement', 'IISAN-REC-2020-1', 'partiel');
INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES (@client_id, @reg_20, 2, 337500, 0, '2026-02-01', NULL, 'virement', NULL, 'impaye');

-- ---- Paiements enseignants (exemple sur la periode 2025-11) ----
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_1, @ay_id, '2025-11', 168000, 24, 'paye', '2025-12-05', 'Cours du mois de novembre');
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_2, @ay_id, '2025-11', 196000, 28, 'en_attente', NULL, 'Cours du mois de novembre');
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_3, @ay_id, '2025-11', 196000, 28, 'paye', '2025-12-05', 'Cours du mois de novembre');
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_4, @ay_id, '2025-11', 217000, 31, 'en_attente', NULL, 'Cours du mois de novembre');
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_5, @ay_id, '2025-11', 182000, 26, 'paye', '2025-12-05', 'Cours du mois de novembre');
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_6, @ay_id, '2025-11', 140000, 20, 'en_attente', NULL, 'Cours du mois de novembre');
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_7, @ay_id, '2025-11', 154000, 22, 'paye', '2025-12-05', 'Cours du mois de novembre');
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_8, @ay_id, '2025-11', 224000, 32, 'en_attente', NULL, 'Cours du mois de novembre');
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_9, @ay_id, '2025-11', 147000, 21, 'paye', '2025-12-05', 'Cours du mois de novembre');
INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_10, @ay_id, '2025-11', 231000, 33, 'en_attente', NULL, 'Cours du mois de novembre');

COMMIT;

-- Compte admin IISAN : admin@iisan.td / Iisan@2026 (a changer des la premiere connexion)
-- Source des informations : recherche web (iisan.co non accessible directement) - a verifier/ajuster.
