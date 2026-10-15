<?php
/**
 * SCHOOMA - Generateur des seeds de demonstration SHIMT et IISAN N'Djamena (maquettes LMD completes).
 * Usage (CLI uniquement) : php database/generate_seeds.php
 * Produit database/seed_shimt.sql et database/seed_iisan.sql (resultats deterministes).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// ---------------------------------------------------------------------------
// UE communes (methodologiques, transversales, decouverte) - 5 + 4 + 3 credits
// ---------------------------------------------------------------------------
const METHODO = [
    'gestion' => [
        1 => ['Méthodologie et outils quantitatifs', [['Méthodologie du travail universitaire', 2], ['Mathématiques générales', 3]]],
        2 => ['Outils quantitatifs de gestion', [['Statistiques appliquées à la gestion', 3], ['Méthodologie de la recherche', 2]]],
    ],
    'humaines' => [
        1 => ['Méthodologie universitaire', [['Méthodologie du travail universitaire', 2], ['Méthodes des sciences sociales', 3]]],
        2 => ["Méthodes et techniques d'enquête", [['Méthodologie de la recherche', 3], ['Statistiques descriptives', 2]]],
    ],
    'droit' => [
        1 => ['Méthodologie juridique', [['Méthodologie du travail universitaire', 2], ['Méthodologie juridique', 3]]],
        2 => ['Recherche documentaire juridique', [['Commentaire et dissertation juridiques', 3], ['Documentation juridique', 2]]],
    ],
    'tech' => [
        1 => ['Mathématiques', [['Analyse mathématique', 3], ['Logique et mathématiques discrètes', 2]]],
        2 => ['Mathématiques appliquées', [['Algèbre linéaire', 3], ['Probabilités et statistiques', 2]]],
    ],
    'data' => [
        1 => ['Mathématiques', [['Analyse mathématique', 3], ['Logique et mathématiques discrètes', 2]]],
        2 => ["Mathématiques pour l'IA", [['Analyse numérique', 3], ['Optimisation', 2]]],
    ],
];
const TRANSVERSAL = [
    1 => ['Langues et communication', [['Anglais 1', 2], ["Techniques d'expression écrite et orale", 2]]],
    2 => [false => ['Langues et outils numériques', [['Anglais 2', 2], ['Informatique bureautique', 2]]],
          true => ['Langues et gestion de projet', [['Anglais technique', 2], ['Gestion de projet', 2]]]],
];
const DISCOVERY = [
    1 => ['Citoyenneté et éthique', [['Éthique, citoyenneté et droits humains', 3]]],
    2 => [false => ['Entrepreneuriat', [["Entrepreneuriat et création d'entreprise", 3]]],
          true => ['Numérique et société', [['Droit du numérique et cybersécurité', 3]]]],
];

// UE fondamentales : 3 par semestre, 6 credits (2 ECUE de 3 credits)
function fund(array $s1, array $s2): array
{
    $build = static fn(array $ues) => array_map(static fn($ue) => [$ue[0], [[$ue[1], 3], [$ue[2], 3]]], $ues);
    return [1 => $build($s1), 2 => $build($s2)];
}

$schools = [
    'shimt' => [
        'file' => 'seed_shimt.sql',
        'title' => "l'institut SHIMT",
        'client' => ['SHIMT', 'shimt', 'contact@shimt-institute.com', '+235 60 00 00 00', "Quartier Moursal, N'Djamena, Tchad", "N'Djamena"],
        'admin' => ['Administrateur SHIMT', 'admin@shimt-institute.com', 'Shimt@2026'],
        'prefix' => 'SHIMT', 'teacher_domain' => 'shimt-institute.com', 'student_domain' => 'shimt-etudiant.com',
        'students_per_class' => 4,
        'teachers' => [
            ['Mahamat', 'Hissein', 'Gestion et management', 'Docteur en sciences de gestion'],
            ['Khadidja', 'Abakar', 'Comptabilité et finance', 'Expert-comptable'],
            ['Youssouf', 'Idriss', 'Marketing et communication', 'Docteur en sciences de la communication'],
            ['Fatimé', 'Ngarmbatina', 'Sociologie', 'Maître-assistant en sociologie'],
            ['Haroun', 'Adoum', 'Tourisme et hôtellerie', 'Consultant en hôtellerie'],
            ['Aïcha', 'Souleymane', 'Informatique', 'Ingénieure informaticienne'],
            ['Ahmat', 'Brahim', 'Logistique et transport', 'Ingénieur logisticien'],
            ['Mariam', 'Allamine', 'Droit', 'Docteur en droit privé'],
            ['Oumar', 'Tahir', 'Journalisme et médias', 'Journaliste, rédacteur en chef'],
            ['Hawa', 'Kodji', 'Électrotechnique', 'Ingénieure électrotechnicienne'],
            ['Abakar', 'Moussa', 'Économie', 'Docteur en économie'],
            ['Zara', 'Nadjirou', 'Langues et communication', "Professeure d'anglais"],
        ],
        'filieres' => [
            'GAE' => ['Gestion et Administration des Entreprises', 550000, 'gestion', false, fund(
                [['Fondamentaux du management', 'Introduction au management', 'Économie générale'],
                 ['Comptabilité et gestion', 'Comptabilité générale 1', 'Mathématiques financières'],
                 ['Environnement juridique', 'Introduction au droit', 'Droit des affaires']],
                [['Organisation et gestion des entreprises', 'Théorie des organisations', 'Gestion des ressources humaines'],
                 ['Gestion comptable et financière', 'Comptabilité générale 2', 'Gestion financière'],
                 ['Marketing et commerce', 'Marketing fondamental', 'Techniques commerciales']])],
            'CFI' => ['Comptabilité et Finances', 550000, 'gestion', false, fund(
                [['Comptabilité financière', 'Comptabilité générale 1', 'Système comptable OHADA (SYSCOHADA)'],
                 ['Mathématiques pour la gestion', 'Mathématiques financières', 'Statistiques descriptives'],
                 ['Économie et environnement', 'Microéconomie', 'Introduction au droit des affaires']],
                [['Comptabilité approfondie', 'Comptabilité générale 2', 'Comptabilité analytique'],
                 ["Finance d'entreprise", 'Analyse financière', 'Gestion de trésorerie'],
                 ['Fiscalité et contrôle', 'Fiscalité des entreprises', "Introduction à l'audit"]])],
            'MDC' => ["Marketing Digital et Communication d'Entreprise", 575000, 'gestion', false, fund(
                [['Fondamentaux du marketing', 'Marketing fondamental', 'Comportement du consommateur'],
                 ["Communication d'entreprise", 'Théories de la communication', 'Communication interne et externe'],
                 ['Environnement numérique', 'Culture web et usages numériques', 'Introduction au marketing digital']],
                [['Marketing digital', 'Référencement SEO / SEA', 'E-mailing et marketing automation'],
                 ['Médias sociaux', 'Community management', 'Stratégie de contenu'],
                 ['Création et image de marque', 'Stratégie de marque', 'Communication visuelle']])],
            'SDE' => ['Sociologie de Développement', 450000, 'humaines', false, fund(
                [['Fondements de la sociologie', 'Introduction à la sociologie', 'Histoire de la pensée sociologique'],
                 ['Sciences sociales du développement', 'Théories du développement', 'Démographie'],
                 ['Société tchadienne', 'Sociologie de la société tchadienne', 'Anthropologie sociale']],
                [['Sociologie du développement', 'Sociologie rurale et urbaine', 'Développement local et communautaire'],
                 ['Économie et politiques de développement', 'Économie du développement', 'Politiques publiques et ODD'],
                 ['Acteurs du développement', 'Sociologie des organisations', 'ONG et coopération internationale']])],
            'THO' => ['Tourisme et Hôtellerie', 500000, 'gestion', false, fund(
                [['Introduction au tourisme', 'Fondamentaux du tourisme', "Géographie touristique du Tchad et de l'Afrique"],
                 ['Hébergement', "Techniques d'hébergement", "Techniques d'accueil et de réception"],
                 ['Restauration', 'Techniques de restauration', 'Hygiène et sécurité alimentaire']],
                [['Gestion hôtelière', 'Gestion des établissements hôteliers', 'Revenue management'],
                 ['Produits touristiques', 'Conception de circuits touristiques', 'Patrimoine et écotourisme'],
                 ['Service client', 'Qualité de service', 'Marketing touristique']])],
            'IAG' => ['Informatique Appliquée à la Gestion', 600000, 'tech', true, fund(
                [['Algorithmique et programmation', 'Algorithmique', 'Programmation en Python'],
                 ['Systèmes informatiques', 'Architecture des ordinateurs', "Systèmes d'exploitation"],
                 ['Gestion et organisation', 'Introduction à la gestion', 'Comptabilité générale']],
                [['Bases de données', 'Modélisation des données (Merise)', 'SQL et SGBD'],
                 ['Développement web', 'HTML et CSS', 'Programmation web PHP'],
                 ["Systèmes d'information de gestion", "Systèmes d'information", 'Progiciels de gestion intégrés (ERP)']])],
            'TTL' => ['Transport, Transit et Logistique', 525000, 'gestion', false, fund(
                [['Fondamentaux de la logistique', 'Introduction à la logistique', 'Gestion des stocks'],
                 ['Transport', 'Économie des transports', 'Transport routier et ferroviaire'],
                 ['Commerce international', 'Introduction au commerce international', 'Incoterms']],
                [['Transit et douane', 'Procédures douanières', 'Transit et dédouanement'],
                 ['Transport multimodal', 'Transport maritime et aérien', 'Transport multimodal et corridors'],
                 ['Chaîne logistique', 'Supply chain management', 'Entreposage et distribution']])],
            'DCJ' => ['Droit et Carrières Judiciaires', 500000, 'droit', false, fund(
                [['Introduction au droit', 'Introduction générale au droit', 'Institutions judiciaires'],
                 ['Droit public', 'Droit constitutionnel', 'Institutions administratives'],
                 ['Droit privé', 'Droit civil : les personnes', 'Droit de la famille']],
                [['Droit pénal', 'Droit pénal général', 'Procédure pénale'],
                 ['Droit des obligations', 'Droit des obligations', 'Droit des biens'],
                 ['Procédures et carrières judiciaires', 'Procédure civile', 'Organisation des professions judiciaires']])],
            'JOU' => ['Journalisme', 475000, 'humaines', false, fund(
                [['Fondamentaux du journalisme', 'Introduction au journalisme', 'Histoire des médias'],
                 ['Écriture journalistique', 'Techniques rédactionnelles', 'Les genres journalistiques'],
                 ['Société et actualité', 'Institutions politiques du Tchad', 'Géopolitique africaine']],
                [['Journalisme audiovisuel', 'Journalisme radio', 'Journalisme télévisé'],
                 ['Presse et numérique', 'Presse écrite et mise en page', 'Journalisme web et réseaux sociaux'],
                 ['Éthique et droit des médias', 'Déontologie du journalisme', 'Droit de la communication']])],
            'ELT' => ['Électrotechnique', 525000, 'tech', true, fund(
                [['Électricité générale', 'Circuits électriques en courant continu', 'Électromagnétisme'],
                 ['Sciences physiques', 'Physique générale', 'Mécanique appliquée'],
                 ['Dessin et technologie', 'Dessin technique', 'Technologie des matériaux électriques']],
                [['Électrotechnique', 'Circuits en courant alternatif', 'Machines électriques'],
                 ['Électronique', 'Électronique analogique', 'Électronique numérique'],
                 ['Installations électriques', 'Installations électriques domestiques', 'Normes et sécurité électrique']])],
        ],
    ],
    'iisan' => [
        'file' => 'seed_iisan.sql',
        'title' => "l'IISAN N'Djamena (Institut International des Sciences et des Arts du Numérique)",
        'client' => ["IISAN N'Djamena", 'iisan-ndjamena', 'contact@iisan.td', '+235 66 65 66 34', 'Avenue Pascal Yoadimnadji, Chagoua, Tchad', "N'Djamena"],
        'admin' => ['Administrateur IISAN', 'admin@iisan.td', 'Iisan@2026'],
        'prefix' => 'IISAN', 'teacher_domain' => 'iisan.td', 'student_domain' => 'iisan-etudiant.td',
        'students_per_class' => 4,
        'teachers' => [
            ['Younous', 'Abdelkerim', 'Développement web', 'Ingénieur logiciel'],
            ['Halimé', 'Djimé', 'Développement web', 'Développeuse full-stack'],
            ['Issa', 'Ngarmbatina', 'Développement mobile', 'Ingénieur mobile'],
            ['Ramatou', 'Ousmane', 'Design UX/UI', 'Designer UX'],
            ['Ahmat', 'Yacoub', 'Marketing digital', 'Consultant en marketing digital'],
            ['Djamila', 'Ramadane', 'Communication et création', 'Directrice artistique'],
            ['Abdoulaye', 'Togoï', 'Data et IA', 'Docteur en informatique'],
            ['Mahadjir', 'Barka', 'Mathématiques', 'Maître-assistant en mathématiques'],
            ['Nour', 'Grédaye', 'Internet des objets', 'Ingénieure systèmes embarqués'],
            ['Khalil', 'Moussa', 'Réseaux et systèmes', 'Ingénieur réseaux'],
        ],
        'filieres' => [
            'DAW' => ["Développement d'Applications Web", 650000, 'tech', true, fund(
                [['Algorithmique et programmation', 'Algorithmique', 'Programmation en JavaScript'],
                 ['Développement front-end', 'HTML5 et CSS3', 'Design responsive et accessibilité'],
                 ['Environnement du développeur', 'Systèmes et réseaux', 'Outils du développeur (Git, Linux)']],
                [['Développement back-end', 'Programmation PHP', 'Conception d\'API REST'],
                 ['Bases de données', 'Modélisation des données', 'SQL et MySQL'],
                 ['Frameworks web', 'Framework front-end (React)', 'Framework back-end (Laravel)']])],
            'DAM' => ["Développement d'Applications Mobiles", 650000, 'tech', true, fund(
                [['Algorithmique et programmation', 'Algorithmique', 'Programmation orientée objet (Java)'],
                 ['Fondamentaux du mobile', 'Écosystèmes mobiles Android et iOS', 'UX/UI design mobile'],
                 ['Environnement du développeur', 'Systèmes et réseaux', 'Outils du développeur (Git, Linux)']],
                [['Développement Android', 'Programmation Kotlin', 'Développement Android natif'],
                 ['Développement multiplateforme', 'Flutter et Dart', 'Publication sur les stores'],
                 ['Données et services', 'Bases de données mobiles', "Consommation d'API REST"]])],
            'MCD' => ['Marketing et Communication Digitale', 575000, 'gestion', true, fund(
                [['Fondamentaux du marketing', 'Marketing fondamental', 'Comportement du consommateur digital'],
                 ['Communication digitale', 'Théories de la communication', 'Rédaction web'],
                 ['Création numérique', 'Infographie (suite Adobe)', 'Culture visuelle et design graphique']],
                [['Marketing digital', 'SEO et SEA', 'Publicité en ligne (Meta et Google Ads)'],
                 ['Médias sociaux', 'Community management', 'Stratégie de contenu et storytelling'],
                 ['Création audiovisuelle', 'Montage vidéo', 'Motion design']])],
            'ADI' => ['Analyse de Données et Intelligence Artificielle', 700000, 'data', true, fund(
                [['Mathématiques pour la data', 'Algèbre linéaire', 'Probabilités et statistiques'],
                 ['Programmation', 'Algorithmique', 'Programmation Python'],
                 ['Données', 'Introduction à la science des données', 'Bases de données et SQL']],
                [['Analyse de données', 'Analyse exploratoire (Pandas)', 'Visualisation de données'],
                 ['Apprentissage automatique', 'Machine learning supervisé', 'Machine learning non supervisé'],
                 ['Intelligence artificielle', "Introduction à l'intelligence artificielle", "Éthique de l'IA et des données"]])],
            'IIO' => ['Internet Industriel des Objets', 675000, 'tech', true, fund(
                [['Électronique et électricité', 'Électricité générale', 'Électronique numérique'],
                 ['Programmation', 'Algorithmique', 'Programmation en C'],
                 ['Systèmes et réseaux', 'Architecture des ordinateurs', 'Réseaux informatiques']],
                [['Systèmes embarqués', 'Microcontrôleurs (Arduino, ESP32)', 'Programmation embarquée'],
                 ['Objets connectés', 'Capteurs et actionneurs', 'Protocoles IoT (MQTT, LoRa)'],
                 ['Cloud et industrie 4.0', 'Cloud et edge computing', 'Automatisme industriel']])],
        ],
    ],
];

$maleFirst = ['Mahamat', 'Idriss', 'Hissein', 'Youssouf', 'Haroun', 'Abakar', 'Adoum', 'Oumar', 'Ahmat', 'Brahim', 'Moussa', 'Ali', 'Issa', 'Younous', 'Abdelkerim', 'Khalil', 'Abdoulaye', 'Djibrine'];
$femaleFirst = ['Khadidja', 'Fatimé', 'Aïcha', 'Mariam', 'Zara', 'Hawa', 'Amina', 'Achta', 'Halimé', 'Nour', 'Zeinab', 'Hadjara', 'Djamila', 'Ramatou', 'Kaltouma'];
$lastNames = ['Mahamat', 'Idriss', 'Hissein', 'Adoum', 'Abakar', 'Djimé', 'Ngarmbatina', 'Ousmane', 'Souleymane', 'Brahim', 'Allamine', 'Tahir', 'Kodji', 'Nadjirou', 'Ndilbé', 'Yacoub', 'Ramadane', 'Togoï', 'Barka', 'Grédaye', 'Hamid', 'Saleh'];

function q(?string $v): string
{
    return $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";
}
function clampMark(float $v): float
{
    return max(0.0, min(20.0, round($v * 4) / 4));
}
function noise(float $amplitude): float
{
    return (mt_rand(-1000, 1000) / 1000) * $amplitude;
}

const EVAL_DATES = [1 => ['2025-12-15', '2026-02-05', '2026-03-02'], 2 => ['2026-05-12', '2026-06-18', '2026-07-06']];

foreach ($schools as $key => $school) {
    mt_srand(crc32($key));
    [$cName, $cSlug, $cEmail, $cPhone, $cAddress, $cCity] = $school['client'];
    [$aName, $aEmail, $aPassword] = $school['admin'];
    $out = [];
    $out[] = '-- ============================================================================';
    $out[] = "-- SCHOOMA - Donnees de demonstration LMD pour {$school['title']}";
    $out[] = '-- Genere par database/generate_seeds.php. Prerequis : database.sql (ou migration 001_lmd.sql).';
    $out[] = '-- ATTENTION : supprime puis recree l\'etablissement "' . $cSlug . '" et TOUTES ses donnees.';
    $out[] = '-- ============================================================================';
    $out[] = '';
    $out[] = 'SET NAMES utf8mb4;';
    $out[] = 'START TRANSACTION;';
    $out[] = '';
    $out[] = 'DELETE FROM clients WHERE slug = ' . q($cSlug) . ';';
    $out[] = 'DELETE FROM users WHERE email = ' . q($aEmail) . ';';
    $out[] = '';
    $out[] = 'INSERT INTO clients (name, slug, email, phone, address, city, plan_id, status) VALUES ('
        . implode(', ', array_map('q', [$cName, $cSlug, $cEmail, $cPhone, $cAddress, $cCity])) . ", (SELECT id FROM subscription_plans WHERE slug = 'pro'), 'actif');";
    $out[] = 'SET @client_id = LAST_INSERT_ID();';
    $out[] = "INSERT INTO academic_years (client_id, label, start_date, end_date, is_current) VALUES (@client_id, '2025-2026', '2025-10-01', '2026-07-31', 1);";
    $out[] = 'SET @ay_id = LAST_INSERT_ID();';
    $out[] = "-- Compte administrateur : $aEmail / $aPassword (a changer des la premiere connexion)";
    $out[] = 'INSERT INTO users (client_id, name, email, password_hash, role, status) VALUES (@client_id, ' . q($aName) . ', ' . q($aEmail) . ', ' . q(password_hash($aPassword, PASSWORD_BCRYPT)) . ", 'admin_ecole', 'actif');";
    $out[] = '';

    // Enseignants
    $out[] = '-- ---- Enseignants ----';
    $teacherCount = count($school['teachers']);
    foreach ($school['teachers'] as $i => [$first, $last, $specialty, $title]) {
        $n = $i + 1;
        $email = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', "$first.$last")) . '@' . $school['teacher_domain'];
        $out[] = 'INSERT INTO teachers (client_id, matricule, first_name, last_name, email, phone, specialty, hourly_rate, status) VALUES (@client_id, '
            . q(sprintf('ENS-%s-%03d', $school['prefix'], $n)) . ', ' . q($first) . ', ' . q($last) . ', ' . q(preg_replace('/[^a-z0-9.@-]/', '', $email)) . ', '
            . q('+235 66 ' . mt_rand(10, 99) . ' ' . mt_rand(10, 99) . ' ' . mt_rand(10, 99)) . ', ' . q($specialty) . ', ' . (mt_rand(6, 10) * 1000) . ", 'actif');";
        $out[] = "SET @teacher_$n = LAST_INSERT_ID();";
    }
    $out[] = '';

    $studentCounter = 0;
    $classIndex = 0;
    $stats = ['ue' => 0, 'ecue' => 0, 'grades' => 0];

    foreach ($school['filieres'] as $code => [$filiereName, $tuition, $methodo, $digital, $fundamentals]) {
        $classIndex++;
        $out[] = "-- ============ Filiere $code : $filiereName ============";
        $out[] = 'INSERT INTO classes (client_id, academic_year_id, name, level, tuition_amount) VALUES (@client_id, @ay_id, ' . q($filiereName) . ", 'Licence 1', $tuition);";
        $out[] = 'SET @class_id = LAST_INSERT_ID();';

        // Maquette : 6 UE par semestre = 30 credits
        $subjects = []; // [var, semester, difficulty]
        foreach ([1, 2] as $semester) {
            $ues = [];
            foreach ($fundamentals[$semester] as $ue) {
                $ues[] = ['fondamentale', $ue[0], $ue[1]];
            }
            $ues[] = ['methodologique', METHODO[$methodo][$semester][0], METHODO[$methodo][$semester][1]];
            $transversal = $semester === 1 ? TRANSVERSAL[1] : TRANSVERSAL[2][$digital];
            $discovery = $semester === 1 ? DISCOVERY[1] : DISCOVERY[2][$digital];
            $ues[] = ['transversale', $transversal[0], $transversal[1]];
            $ues[] = ['decouverte', $discovery[0], $discovery[1]];

            foreach ($ues as $order => [$category, $ueName, $ecues]) {
                $ueCode = sprintf('%s%d%02d', $code, $semester, $order + 1);
                $credits = array_sum(array_column($ecues, 1));
                $out[] = "INSERT INTO teaching_units (client_id, class_id, semester, code, name, category, credits, sort_order) VALUES (@client_id, @class_id, $semester, "
                    . q($ueCode) . ', ' . q($ueName) . ", '$category', $credits, " . ($order + 1) . ');';
                $out[] = 'SET @ue = LAST_INSERT_ID();';
                $stats['ue']++;
                foreach ($ecues as $j => [$ecueName, $ecueCredits]) {
                    $var = 'subj_' . (++$stats['ecue']);
                    $out[] = "INSERT INTO subjects (client_id, class_id, teaching_unit_id, code, name, credits, coefficient) VALUES (@client_id, @class_id, @ue, "
                        . q($ueCode . ($j + 1)) . ', ' . q($ecueName) . ", $ecueCredits, $ecueCredits);";
                    $out[] = "SET @$var = LAST_INSERT_ID();";
                    $subjects[] = [$var, $semester, noise(1.5), $order];
                }
            }
        }

        // Jury de deliberation de la filiere
        $members = [];
        foreach (['president', 'secretaire', 'membre', 'membre'] as $k => $role) {
            $members[] = [($classIndex - 1 + $k * 3) % $teacherCount + 1, $role];
        }
        $out[] = 'INSERT INTO juries (client_id, academic_year_id, class_id, name, session_type, session_date, location, status) VALUES (@client_id, @ay_id, @class_id, '
            . q("Jury de délibération L1 $code — Session normale") . ", 'normale', '2026-07-15', 'Salle du conseil', 'planifie');";
        $out[] = 'SET @jury_id = LAST_INSERT_ID();';
        $seen = [];
        foreach ($members as [$teacherNumber, $role]) {
            if (isset($seen[$teacherNumber])) {
                continue;
            }
            $seen[$teacherNumber] = true;
            $out[] = "INSERT INTO jury_members (jury_id, teacher_id, role) VALUES (@jury_id, @teacher_$teacherNumber, '$role');";
        }

        // Etudiants : niveau par semestre [S1, S2, contraste S2] -> excellent, regulier, admis avec dettes
        // (UE fondamentales de S2 reussies, autres UE echouees), limite (cas ou les points de jury comptent)
        $profiles = [[14.5, 14.5], [11.5, 11.2], [10.6, 9.0, true], [9.1, 9.3]];
        shuffle($profiles);
        for ($s = 0; $s < $school['students_per_class']; $s++) {
            $studentCounter++;
            $male = mt_rand(0, 1) === 1;
            $first = $male ? $maleFirst[array_rand($maleFirst)] : $femaleFirst[array_rand($femaleFirst)];
            $last = $lastNames[array_rand($lastNames)];
            $matricule = sprintf('%s-25-%04d', $school['prefix'], $studentCounter);
            $dob = sprintf('%04d-%02d-%02d', mt_rand(2002, 2007), mt_rand(1, 12), mt_rand(1, 28));
            $email = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', "$first.$last$studentCounter")) . '@' . $school['student_domain'];
            $out[] = 'INSERT INTO students (client_id, matricule, first_name, last_name, dob, gender, phone, email, address, status) VALUES (@client_id, '
                . q($matricule) . ', ' . q($first) . ', ' . q($last) . ', ' . q($dob) . ', ' . q($male ? 'M' : 'F') . ', '
                . q('+235 9' . mt_rand(0, 9) . ' ' . mt_rand(10, 99) . ' ' . mt_rand(10, 99) . ' ' . mt_rand(10, 99)) . ', ' . q(preg_replace('/[^a-z0-9.@-]/', '', $email)) . ", 'N''Djamena', 'actif');";
            $out[] = 'SET @student = LAST_INSERT_ID();';
            $out[] = 'INSERT INTO registrations (client_id, student_id, class_id, academic_year_id, registration_date, amount_total, status) VALUES (@client_id, @student, @class_id, @ay_id, '
                . q(sprintf('2025-10-%02d', mt_rand(1, 10))) . ", $tuition, 'validee');";
            $out[] = 'SET @reg = LAST_INSERT_ID();';

            $half = (int) round($tuition / 2);
            $receipt = sprintf('%s-REC-%04d', $school['prefix'], $studentCounter);
            $payments = match ($studentCounter % 3) {
                0 => [[1, $half, "'2025-10-28'", 'mobile_money', 'paye'], [2, $half, "'2026-01-25'", 'mobile_money', 'paye']],
                1 => [[1, $half, "'2025-11-05'", 'especes', 'paye'], [2, 0, 'NULL', 'especes', 'impaye']],
                default => [[1, (int) round($half * 0.5), "'2025-11-10'", 'virement', 'partiel'], [2, 0, 'NULL', 'virement', 'impaye']],
            };
            $rows = [];
            foreach ($payments as [$tranche, $paid, $paidDate, $method, $status]) {
                $rows[] = "(@client_id, @reg, $tranche, $half, $paid, '" . ($tranche === 1 ? '2025-11-01' : '2026-02-01') . "', $paidDate, '$method', "
                    . ($paid > 0 ? q("$receipt-$tranche") : 'NULL') . ", '$status')";
            }
            $out[] = 'INSERT INTO payments (client_id, registration_id, tranche_number, amount_due, amount_paid, due_date, paid_date, method, receipt_number, status) VALUES ' . implode(', ', $rows) . ';';

            // Notes : CC + examen pour tous les ECUE, rattrapage pour une partie des ECUE < 10
            $profile = $profiles[$s % count($profiles)];
            $gradeRows = [];
            foreach ($subjects as [$var, $semester, $difficulty, $ueOrder]) {
                $level = $profile[$semester - 1] + (!empty($profile[2]) && $semester === 2 ? ($ueOrder < 3 ? 2.0 : -4.0) : 0.0);
                $term = $semester === 1 ? 'semestre1' : 'semestre2';
                [$ccDate, $examDate, $rattDate] = EVAL_DATES[$semester];
                $cc = clampMark($level + $difficulty + noise(2.0));
                $exam = clampMark($level + $difficulty + noise(2.5));
                $gradeRows[] = "(@client_id, @student, @$var, @ay_id, '$term', 'devoir', $cc, 20, '$ccDate')";
                $gradeRows[] = "(@client_id, @student, @$var, @ay_id, '$term', 'examen', $exam, 20, '$examDate')";
                if ($cc * 0.4 + $exam * 0.6 < 10 && mt_rand(1, 100) <= 60) {
                    $gradeRows[] = "(@client_id, @student, @$var, @ay_id, '$term', 'rattrapage', " . clampMark($level + 1.0 + noise(2.0)) . ", 20, '$rattDate')";
                }
            }
            $stats['grades'] += count($gradeRows);
            $out[] = 'INSERT INTO grades (client_id, student_id, subject_id, academic_year_id, term, eval_type, grade_value, max_value, eval_date) VALUES ' . implode(', ', $gradeRows) . ';';
        }
        $out[] = '';
    }

    $out[] = '-- ---- Paiements enseignants (novembre 2025) ----';
    for ($n = 1; $n <= $teacherCount; $n++) {
        $hours = mt_rand(20, 40);
        $paid = $n % 2 === 1;
        $out[] = "INSERT INTO teacher_payments (client_id, teacher_id, academic_year_id, period, amount, hours_done, status, paid_date, note) VALUES (@client_id, @teacher_$n, @ay_id, '2025-11', "
            . ($hours * 7000) . ", $hours, '" . ($paid ? 'paye' : 'en_attente') . "', " . ($paid ? "'2025-12-05'" : 'NULL') . ", 'Cours du mois de novembre');";
    }
    $out[] = '';
    $out[] = 'COMMIT;';

    file_put_contents(__DIR__ . '/' . $school['file'], implode("\n", $out) . "\n");
    printf("%s : %d filieres, %d UE, %d ECUE, %d etudiants, %d notes\n", $school['file'], count($school['filieres']), $stats['ue'], $stats['ecue'], $studentCounter, $stats['grades']);
}
