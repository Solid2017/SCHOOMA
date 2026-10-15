<?php
/**
 * SCHOOMA - Moteur de calcul LMD
 * 30 credits par semestre, 60 par annee universitaire.
 *
 * Note d'un ECUE   = CC x LMD_CC_WEIGHT + Examen x (1 - LMD_CC_WEIGHT)
 *                    (la session de rattrapage remplace l'examen si elle est plus favorable)
 * Moyenne d'une UE = moyenne des ECUE ponderee par leur coefficient
 * Moyenne semestre = moyenne des UE ponderee par leurs credits
 * Une UE est validee si sa moyenne >= 10, ou par compensation si la moyenne du semestre >= 10.
 * L'annee est validee (60 credits) si la moyenne annuelle (+ points de jury) >= 10 avec compensation annuelle.
 *
 * Les constantes ci-dessous peuvent etre redefinies dans includes/config.php.
 */

defined('LMD_CC_WEIGHT') || define('LMD_CC_WEIGHT', 0.4);
defined('LMD_PASS_MARK') || define('LMD_PASS_MARK', 10.0);
defined('LMD_SEMESTER_COMPENSATION') || define('LMD_SEMESTER_COMPENSATION', true);
defined('LMD_ANNUAL_COMPENSATION') || define('LMD_ANNUAL_COMPENSATION', true);
defined('LMD_YEAR_CREDITS') || define('LMD_YEAR_CREDITS', 60);
defined('LMD_CONDITIONAL_CREDITS') || define('LMD_CONDITIONAL_CREDITS', 45);
defined('LMD_MAX_JURY_POINTS') || define('LMD_MAX_JURY_POINTS', 1.0);

const LMD_DECISIONS = [
    'admis'        => 'Admis(e)',
    'admis_dettes' => 'Admis(e) avec dettes',
    'ajourne'      => 'Ajourné(e)',
    'redouble'     => 'Redouble',
    'exclu'        => 'Exclu(e)',
];

const LMD_UE_CATEGORIES = [
    'fondamentale'   => 'Fondamentale',
    'methodologique' => 'Méthodologique',
    'transversale'   => 'Transversale',
    'decouverte'     => 'Découverte',
];

const LMD_JURY_ROLES = [
    'president'      => 'Président(e)',
    'vice_president' => 'Vice-président(e)',
    'secretaire'     => 'Secrétaire',
    'rapporteur'     => 'Rapporteur',
    'membre'         => 'Membre',
];

function lmdDecisionLabel(?string $decision): string
{
    return $decision === null ? 'En attente' : (LMD_DECISIONS[$decision] ?? ucfirst($decision));
}

function lmdDecisionBadge(?string $decision): string
{
    return match ($decision) {
        'admis' => 'success',
        'admis_dettes' => 'info',
        'ajourne', 'redouble', 'exclu' => 'danger',
        default => 'gray',
    };
}

function lmdMention(?float $average): ?string
{
    if ($average === null || $average < LMD_PASS_MARK) {
        return null;
    }
    return match (true) {
        $average >= 16 => 'Très Bien',
        $average >= 14 => 'Bien',
        $average >= 12 => 'Assez Bien',
        default => 'Passable',
    };
}

function lmdStatusLabel(string $status): string
{
    return match ($status) {
        'valide' => 'Validée',
        'compense' => 'Validée par compensation',
        'non_valide' => 'Non validée',
        default => 'Non évaluée',
    };
}

function lmdSemesterStatusLabel(string $status): string
{
    return match ($status) {
        'valide' => 'Validé',
        'compense' => 'Validé par compensation',
        'non_valide' => 'Non validé',
        default => 'Non évalué',
    };
}

function lmdStatusShort(string $status): string
{
    return match ($status) {
        'valide' => 'V',
        'compense' => 'VC',
        'non_valide' => 'NV',
        default => '-',
    };
}

/** Periode de saisie (champ grades.term) correspondant a un semestre LMD */
function lmdSemesterTerm(int $semester): string
{
    return $semester % 2 === 1 ? 'semestre1' : 'semestre2';
}

/** Format francais d'une note : 12,50 */
function lmdFormat(?float $value, int $decimals = 2): string
{
    return $value === null ? '-' : number_format($value, $decimals, ',', ' ');
}

function lmdFormatCredits(?float $value): string
{
    if ($value === null) {
        return '-';
    }
    return floor($value) == $value ? (string) (int) $value : number_format($value, 1, ',', '');
}

/**
 * Maquette d'une filiere : [semestre => ['number', 'credits', 'units' => [ue_id => UE + 'subjects' => [...]]]]
 */
function lmdLoadCurriculum(PDO $db, int $clientId, int $classId): array
{
    $stmt = $db->prepare('SELECT * FROM teaching_units WHERE client_id = ? AND class_id = ? ORDER BY semester, sort_order, code');
    $stmt->execute([$clientId, $classId]);
    $curriculum = [];
    foreach ($stmt->fetchAll() as $ue) {
        $semester = (int) $ue['semester'];
        $curriculum[$semester] ??= ['number' => $semester, 'credits' => 0.0, 'units' => []];
        $curriculum[$semester]['credits'] += (float) $ue['credits'];
        $curriculum[$semester]['units'][(int) $ue['id']] = $ue + ['subjects' => []];
    }

    $stmt = $db->prepare('SELECT s.*, tu.semester FROM subjects s JOIN teaching_units tu ON tu.id = s.teaching_unit_id
                          WHERE s.client_id = ? AND s.class_id = ? ORDER BY s.code, s.name');
    $stmt->execute([$clientId, $classId]);
    foreach ($stmt->fetchAll() as $subject) {
        $semester = (int) $subject['semester'];
        $ueId = (int) $subject['teaching_unit_id'];
        if (isset($curriculum[$semester]['units'][$ueId])) {
            $curriculum[$semester]['units'][$ueId]['subjects'][] = $subject;
        }
    }

    ksort($curriculum);
    return $curriculum;
}

/** Notes normalisees sur 20 : [student_id][subject_id]['cc'|'exam'|'ratt'] => [notes] */
function lmdLoadGrades(PDO $db, int $clientId, int $academicYearId, int $classId, ?int $studentId = null): array
{
    $sql = 'SELECT g.student_id, g.subject_id, g.eval_type, g.grade_value, g.max_value
            FROM grades g JOIN subjects s ON s.id = g.subject_id
            WHERE g.client_id = ? AND g.academic_year_id = ? AND s.class_id = ?';
    $params = [$clientId, $academicYearId, $classId];
    if ($studentId !== null) {
        $sql .= ' AND g.student_id = ?';
        $params[] = $studentId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $map = ['devoir' => 'cc', 'tp' => 'cc', 'examen' => 'exam', 'rattrapage' => 'ratt'];
    $grades = [];
    foreach ($stmt->fetchAll() as $g) {
        $max = (float) $g['max_value'] > 0 ? (float) $g['max_value'] : 20.0;
        $grades[(int) $g['student_id']][(int) $g['subject_id']][$map[$g['eval_type']]][] = (float) $g['grade_value'] / $max * 20;
    }
    return $grades;
}

/** Note d'un ECUE a partir de ses evaluations */
function lmdSubjectMark(array $evaluations): array
{
    $avg = static fn(?array $values): ?float => $values ? array_sum($values) / count($values) : null;
    $cc = $avg($evaluations['cc'] ?? null);
    $exam = $avg($evaluations['exam'] ?? null);
    $ratt = $avg($evaluations['ratt'] ?? null);
    $w = LMD_CC_WEIGHT;

    $session1 = match (true) {
        $exam !== null && $cc !== null => $cc * $w + $exam * (1 - $w),
        $exam !== null => $exam,
        $cc !== null => $cc,
        default => null,
    };
    $session2 = $ratt === null ? null : ($cc !== null ? $cc * $w + $ratt * (1 - $w) : $ratt);

    $final = $session1;
    $session = 1;
    if ($session2 !== null && ($session1 === null || $session2 > $session1)) {
        $final = $session2;
        $session = 2;
    }

    $round = static fn(?float $v): ?float => $v === null ? null : round($v, 2);
    return [
        'cc' => $round($cc),
        'exam' => $round($exam),
        'ratt' => $round($ratt),
        'final' => $round($final),
        'session' => $session,
    ];
}

/**
 * Resultat LMD complet d'un etudiant.
 * $studentGrades : [subject_id => evaluations] tel que fourni par lmdLoadGrades()
 */
function lmdComputeStudent(array $curriculum, array $studentGrades, float $juryPoints = 0.0): array
{
    $semesters = [];
    $creditsTotal = 0.0;
    $missingTotal = 0;

    foreach ($curriculum as $number => $sem) {
        $units = [];
        $semWeighted = 0.0;
        $semWeight = 0.0;
        $semHasMarks = false;
        $semMissing = 0;

        foreach ($sem['units'] as $ueId => $ue) {
            $subjects = [];
            $ueWeighted = 0.0;
            $ueWeight = 0.0;
            $ueHasMarks = false;
            $ueMissing = 0;

            foreach ($ue['subjects'] as $subject) {
                $mark = lmdSubjectMark($studentGrades[(int) $subject['id']] ?? []);
                $coef = (float) $subject['coefficient'] > 0 ? (float) $subject['coefficient'] : 1.0;
                if ($mark['final'] === null) {
                    $ueMissing++;
                } else {
                    $ueHasMarks = true;
                    $ueWeighted += $mark['final'] * $coef;
                }
                $ueWeight += $coef;
                $subjects[] = $subject + ['mark' => $mark];
            }

            $ueAverage = $ueHasMarks && $ueWeight > 0 ? round($ueWeighted / $ueWeight, 2) : null;
            $ueCredits = (float) $ue['credits'];
            $weight = $ueCredits > 0 ? $ueCredits : 1.0;
            $semWeight += $weight;
            if ($ueAverage !== null) {
                $semHasMarks = true;
                $semWeighted += $ueAverage * $weight;
            }
            $semMissing += $ueMissing;

            $units[$ueId] = array_merge($ue, [
                'subjects' => $subjects,
                'average' => $ueAverage,
                'missing' => $ueMissing,
                'status' => 'non_evalue',
                'acquired' => 0.0,
            ]);
        }

        $semAverage = $semHasMarks && $semWeight > 0 ? round($semWeighted / $semWeight, 2) : null;
        $compensated = LMD_SEMESTER_COMPENSATION && $semAverage !== null && $semAverage >= LMD_PASS_MARK;
        $acquired = 0.0;
        foreach ($units as &$unit) {
            if ($semAverage === null) {
                continue;
            }
            if ($unit['average'] !== null && $unit['average'] >= LMD_PASS_MARK) {
                $unit['status'] = 'valide';
            } elseif ($compensated) {
                $unit['status'] = 'compense';
            } else {
                $unit['status'] = 'non_valide';
            }
            $unit['acquired'] = $unit['status'] === 'non_valide' ? 0.0 : (float) $unit['credits'];
            $acquired += $unit['acquired'];
        }
        unset($unit);

        $semStatus = 'non_evalue';
        if ($semAverage !== null) {
            $allDirect = !array_filter($units, static fn($u) => $u['status'] !== 'valide');
            $semStatus = $acquired >= $sem['credits'] ? ($allDirect ? 'valide' : 'compense') : 'non_valide';
        }

        $semesters[$number] = [
            'number' => $number,
            'credits' => (float) $sem['credits'],
            'acquired' => $acquired,
            'average' => $semAverage,
            'status' => $semStatus,
            'missing' => $semMissing,
            'units' => $units,
        ];
        $creditsTotal += (float) $sem['credits'];
        $missingTotal += $semMissing;
    }

    $evaluated = $semesters && !array_filter($semesters, static fn($s) => $s['average'] === null);
    $annualAverage = null;
    $finalAverage = null;
    if ($evaluated) {
        $weighted = 0.0;
        $weight = 0.0;
        foreach ($semesters as $s) {
            $w = $s['credits'] > 0 ? $s['credits'] : 1.0;
            $weighted += $s['average'] * $w;
            $weight += $w;
        }
        $annualAverage = round($weighted / $weight, 2);
        $finalAverage = round(min(20.0, $annualAverage + $juryPoints), 2);

        if (LMD_ANNUAL_COMPENSATION && $finalAverage >= LMD_PASS_MARK) {
            foreach ($semesters as &$s) {
                foreach ($s['units'] as &$unit) {
                    if ($unit['status'] === 'non_valide') {
                        $unit['status'] = 'compense';
                        $unit['acquired'] = (float) $unit['credits'];
                    }
                }
                unset($unit);
                if ($s['status'] === 'non_valide') {
                    $s['status'] = 'compense';
                }
                $s['acquired'] = $s['credits'];
            }
            unset($s);
        }
    }

    $creditsAcquired = array_sum(array_column($semesters, 'acquired'));
    $decision = null;
    if ($evaluated && $creditsTotal > 0) {
        $conditional = LMD_CONDITIONAL_CREDITS * $creditsTotal / LMD_YEAR_CREDITS;
        $decision = match (true) {
            $creditsAcquired >= $creditsTotal => 'admis',
            $creditsAcquired >= $conditional => 'admis_dettes',
            default => 'ajourne',
        };
    }

    return [
        'semesters' => $semesters,
        'credits_total' => $creditsTotal,
        'credits_acquired' => $creditsAcquired,
        'annual_average' => $annualAverage,
        'jury_points' => $juryPoints,
        'final_average' => $finalAverage,
        'evaluated' => $evaluated,
        'complete' => $evaluated && $missingTotal === 0,
        'missing' => $missingTotal,
        'decision' => $decision,
    ];
}

/**
 * Resultats LMD de toute une filiere pour une annee, avec decisions de jury, mentions et rangs.
 */
function lmdComputeClass(PDO $db, int $clientId, int $classId, int $academicYearId): array
{
    $stmt = $db->prepare('SELECT s.* FROM students s JOIN registrations r ON r.student_id = s.id
                          WHERE r.client_id = ? AND r.class_id = ? AND r.academic_year_id = ? AND r.status <> "annulee"
                          ORDER BY s.last_name, s.first_name');
    $stmt->execute([$clientId, $classId, $academicYearId]);
    $students = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM results WHERE client_id = ? AND academic_year_id = ? AND class_id = ?');
    $stmt->execute([$clientId, $academicYearId, $classId]);
    $stored = [];
    foreach ($stmt->fetchAll() as $row) {
        $stored[(int) $row['student_id']] = $row;
    }

    $curriculum = lmdLoadCurriculum($db, $clientId, $classId);
    $grades = lmdLoadGrades($db, $clientId, $academicYearId, $classId);

    $rows = [];
    foreach ($students as $student) {
        $sid = (int) $student['id'];
        $saved = $stored[$sid] ?? null;
        $result = lmdComputeStudent($curriculum, $grades[$sid] ?? [], (float) ($saved['jury_points'] ?? 0));
        $finalDecision = $saved['jury_decision'] ?? $result['decision'];
        $rows[$sid] = [
            'student' => $student,
            'result' => $result,
            'stored' => $saved,
            'final_decision' => $finalDecision,
            'mention' => $finalDecision === 'admis' ? lmdMention($result['final_average']) : null,
            'rank' => null,
        ];
    }

    $ranked = array_filter($rows, static fn($r) => $r['result']['final_average'] !== null);
    uasort($ranked, static fn($a, $b) => $b['result']['final_average'] <=> $a['result']['final_average']);
    $rank = 0;
    $position = 0;
    $previous = null;
    foreach ($ranked as $sid => $row) {
        $position++;
        if ($row['result']['final_average'] !== $previous) {
            $rank = $position;
            $previous = $row['result']['final_average'];
        }
        $rows[$sid]['rank'] = $rank;
    }

    $stats = ['effectif' => count($rows), 'attente' => 0] + array_fill_keys(array_keys(LMD_DECISIONS), 0);
    foreach ($rows as $row) {
        $stats[$row['final_decision'] ?? 'attente']++;
    }
    $decided = $stats['effectif'] - $stats['attente'];
    $stats['taux_reussite'] = $decided > 0 ? round(($stats['admis'] + $stats['admis_dettes']) / $decided * 100, 1) : null;

    return ['curriculum' => $curriculum, 'rows' => $rows, 'stats' => $stats];
}

/**
 * Enregistre les resultats calcules (table results). Les points/decisions/observations du jury sont conserves.
 * Retourne le nombre d'etudiants enregistres (seuls les etudiants evalues sur tous les semestres le sont).
 */
function lmdStoreClassResults(PDO $db, int $clientId, int $classId, int $academicYearId, array $classData, ?int $juryId = null, ?bool $publish = null): int
{
    $sql = 'INSERT INTO results (client_id, student_id, class_id, academic_year_id, average, average_s1, average_s2,
                credits_s1, credits_s2, credits_total, is_complete, class_rank, decision, mention, jury_id, published, computed_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), average = VALUES(average), average_s1 = VALUES(average_s1),
                average_s2 = VALUES(average_s2), credits_s1 = VALUES(credits_s1), credits_s2 = VALUES(credits_s2),
                credits_total = VALUES(credits_total), is_complete = VALUES(is_complete), class_rank = VALUES(class_rank),
                decision = VALUES(decision), mention = VALUES(mention), computed_at = NOW()'
        . ($juryId !== null ? ', jury_id = VALUES(jury_id)' : '')
        . ($publish !== null ? ', published = VALUES(published)' : '');
    $stmt = $db->prepare($sql);

    $count = 0;
    foreach ($classData['rows'] as $sid => $row) {
        $r = $row['result'];
        if (!$r['evaluated']) {
            continue;
        }
        $semesters = array_values($r['semesters']);
        $stmt->execute([
            $clientId, $sid, $classId, $academicYearId,
            $r['final_average'],
            $semesters[0]['average'] ?? null,
            $semesters[1]['average'] ?? null,
            $semesters[0]['acquired'] ?? 0,
            $semesters[1]['acquired'] ?? 0,
            $r['credits_acquired'],
            $r['complete'] ? 1 : 0,
            $row['rank'],
            $r['decision'],
            $row['mention'],
            $juryId ?? ($row['stored']['jury_id'] ?? null),
            $publish !== null ? (int) $publish : (int) ($row['stored']['published'] ?? 0),
        ]);
        $count++;
    }
    return $count;
}

/** Nom du president du jury de la filiere (jury cloture en priorite) */
function lmdJuryPresident(PDO $db, int $clientId, int $classId, int $academicYearId): ?string
{
    $stmt = $db->prepare("SELECT COALESCE(CONCAT(t.first_name, ' ', t.last_name), jm.full_name) AS name
                          FROM jury_members jm JOIN juries j ON j.id = jm.jury_id LEFT JOIN teachers t ON t.id = jm.teacher_id
                          WHERE j.client_id = ? AND j.class_id = ? AND j.academic_year_id = ? AND jm.role = 'president'
                          ORDER BY j.status = 'cloture' DESC, j.session_date DESC LIMIT 1");
    $stmt->execute([$clientId, $classId, $academicYearId]);
    $name = $stmt->fetchColumn();
    return $name ?: null;
}

/** Validation de la deliberation par la Direction generale : ['validated_at', 'name', 'job_title'] ou null */
function lmdJuryValidation(PDO $db, int $clientId, int $classId, int $academicYearId): ?array
{
    $stmt = $db->prepare('SELECT j.validated_at, u.name, u.job_title FROM juries j LEFT JOIN users u ON u.id = j.validated_by
                          WHERE j.client_id = ? AND j.class_id = ? AND j.academic_year_id = ? AND j.validated_at IS NOT NULL
                          ORDER BY j.validated_at DESC LIMIT 1');
    $stmt->execute([$clientId, $classId, $academicYearId]);
    return $stmt->fetch() ?: null;
}

/** Responsable d'une ecole pour un role donne (ex : Directeur des etudes signataire des releves) */
function lmdSchoolOfficial(PDO $db, int $clientId, string $role): ?array
{
    $stmt = $db->prepare('SELECT name, job_title FROM users WHERE client_id = ? AND role = ? AND status = "actif" ORDER BY id LIMIT 1');
    $stmt->execute([$clientId, $role]);
    return $stmt->fetch() ?: null;
}

/** Annee universitaire de l'inscription d'un etudiant (la plus recente par defaut) */
function lmdStudentRegistration(PDO $db, int $clientId, int $studentId, ?int $academicYearId = null): ?array
{
    $sql = 'SELECT r.*, c.name AS class_name, c.level AS class_level, ay.label AS year_label
            FROM registrations r JOIN classes c ON c.id = r.class_id JOIN academic_years ay ON ay.id = r.academic_year_id
            WHERE r.client_id = ? AND r.student_id = ?';
    $params = [$clientId, $studentId];
    if ($academicYearId !== null) {
        $sql .= ' AND r.academic_year_id = ?';
        $params[] = $academicYearId;
    }
    $stmt = $db->prepare($sql . ' ORDER BY ay.start_date DESC LIMIT 1');
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}
