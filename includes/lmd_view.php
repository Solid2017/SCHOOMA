<?php
/**
 * SCHOOMA - Rendu HTML des documents LMD (releve de notes).
 * Partage entre l'impression admin (admin/transcript.php) et la consultation publique (resultats.php).
 */
require_once __DIR__ . '/lmd.php';

function lmdSchoolHeaderHtml(array $school, string $title, array $subtitles): string
{
    $meta = array_filter([
        trim(($school['address'] ?? '') . (!empty($school['city']) && stripos($school['address'] ?? '', $school['city']) === false ? ', ' . $school['city'] : '')),
        !empty($school['phone']) ? 'Tél. ' . $school['phone'] : '',
        $school['email'] ?? '',
    ]);
    $logo = '';
    if (!empty($school['logo']) && is_file(UPLOAD_DIR . $school['logo'])) {
        $logo = '<img class="lmd-school-logo" src="' . e(UPLOAD_URL . $school['logo']) . '" alt="">';
    }
    $html = '<header class="lmd-doc-header"><div class="lmd-school">' . $logo . '<div>';
    $html .= '<div class="lmd-school-name">' . e($school['name']) . '</div>';
    $html .= '<div class="lmd-school-meta">' . e(implode(' · ', $meta)) . '</div></div></div>';
    $html .= '<div class="lmd-doc-title"><div class="lmd-title">' . e($title) . '</div>';
    foreach ($subtitles as $subtitle) {
        $html .= '<div>' . e($subtitle) . '</div>';
    }
    return $html . '</div></header>';
}

/**
 * $ctx : school, student, registration (class_name, class_level, year_label), result (lmdComputeStudent),
 *        final_decision, mention, rank, effectif, semesters (null = annuel), president, published
 */
function lmdTranscriptHtml(array $ctx): string
{
    $student = $ctx['student'];
    $reg = $ctx['registration'];
    $result = $ctx['result'];
    $onlySemesters = $ctx['semesters'] ?? null;
    $semesters = $onlySemesters === null
        ? $result['semesters']
        : array_intersect_key($result['semesters'], array_flip($onlySemesters));
    $annual = $onlySemesters === null;

    $title = $annual ? 'Relevé de notes' : 'Relevé de notes – Semestre ' . implode(' & ', array_keys($semesters));
    $reference = 'N° RN-' . preg_replace('/[^0-9]/', '', substr($reg['year_label'], 0, 4)) . '-' . $student['matricule'];

    $html = '<article class="lmd-doc">';
    $html .= lmdSchoolHeaderHtml($ctx['school'], $title, ['Année universitaire ' . $reg['year_label'], $reference]);

    $html .= '<table class="lmd-identity"><tr>'
        . '<th>Nom et prénom(s)</th><td><strong>' . e(mb_strtoupper($student['last_name']) . ' ' . $student['first_name']) . '</strong></td>'
        . '<th>Matricule</th><td><strong>' . e($student['matricule']) . '</strong></td></tr><tr>'
        . '<th>Né(e) le</th><td>' . e(formatDate($student['dob'])) . '</td>'
        . '<th>Niveau</th><td>' . e($reg['class_level'] ?: '-') . '</td></tr><tr>'
        . '<th>Filière</th><td colspan="3">' . e($reg['class_name']) . ' <span class="lmd-muted">— Système LMD, ' . LMD_YEAR_CREDITS . ' crédits par an</span></td>'
        . '</tr></table>';

    if (!$semesters) {
        return $html . '<p class="lmd-empty">Aucune unité d\'enseignement n\'est encore définie pour cette filière.</p></article>';
    }

    foreach ($semesters as $sem) {
        $html .= '<h3 class="lmd-sem-title">Semestre ' . (int) $sem['number'] . '</h3>';
        $html .= '<table class="lmd-table"><thead><tr>'
            . '<th class="c-code">Code</th><th class="c-name">Unités d\'enseignement / Éléments constitutifs</th>'
            . '<th>Crédits</th><th>Coef.</th><th>CC</th><th>Examen</th><th>Ratt.</th><th>Note /20</th>'
            . '<th>Moy. UE</th><th>Crédits acquis</th><th>Résultat</th></tr></thead><tbody>';

        foreach ($sem['units'] as $unit) {
            $statusClass = 'st-' . $unit['status'];
            $html .= '<tr class="lmd-ue">'
                . '<td>' . e($unit['code']) . '</td>'
                . '<td>' . e($unit['name']) . ' <span class="lmd-cat">' . e(LMD_UE_CATEGORIES[$unit['category']] ?? '') . '</span></td>'
                . '<td>' . lmdFormatCredits((float) $unit['credits']) . '</td><td></td><td colspan="4"></td>'
                . '<td class="num"><strong>' . lmdFormat($unit['average']) . '</strong></td>'
                . '<td>' . lmdFormatCredits($unit['acquired']) . '</td>'
                . '<td class="' . $statusClass . '"><strong>' . lmdStatusShort($unit['status']) . '</strong></td></tr>';

            foreach ($unit['subjects'] as $subject) {
                $mark = $subject['mark'];
                $final = $mark['final'] === null ? 'ABS' : lmdFormat($mark['final']) . ($mark['session'] === 2 ? '*' : '');
                $html .= '<tr class="lmd-ecue">'
                    . '<td>' . e($subject['code'] ?? '') . '</td>'
                    . '<td class="indent">' . e($subject['name']) . '</td>'
                    . '<td>' . lmdFormatCredits($subject['credits'] !== null ? (float) $subject['credits'] : null) . '</td>'
                    . '<td>' . lmdFormatCredits((float) $subject['coefficient']) . '</td>'
                    . '<td class="num">' . lmdFormat($mark['cc']) . '</td>'
                    . '<td class="num">' . lmdFormat($mark['exam']) . '</td>'
                    . '<td class="num">' . lmdFormat($mark['ratt']) . '</td>'
                    . '<td class="num">' . $final . '</td><td></td><td></td><td></td></tr>';
            }
        }

        $html .= '</tbody><tfoot><tr><td colspan="8">Moyenne du semestre ' . (int) $sem['number'] . '</td>'
            . '<td class="num">' . lmdFormat($sem['average']) . '</td>'
            . '<td>' . lmdFormatCredits($sem['acquired']) . ' / ' . lmdFormatCredits($sem['credits']) . '</td>'
            . '<td class="st-' . $sem['status'] . '">' . lmdStatusShort($sem['status']) . '</td></tr></tfoot></table>';
        $html .= '<p class="lmd-sem-result">Semestre ' . (int) $sem['number'] . ' : <strong>' . e(lmdSemesterStatusLabel($sem['status'])) . '</strong>'
            . ' — ' . lmdFormatCredits($sem['acquired']) . ' crédit(s) capitalisé(s) sur ' . lmdFormatCredits($sem['credits']) . '</p>';
    }

    if ($annual) {
        $semList = array_values($result['semesters']);
        $decision = $ctx['final_decision'] ?? $result['decision'];
        $html .= '<table class="lmd-summary"><thead><tr>';
        foreach ($semList as $s) {
            $html .= '<th>Moy. S' . (int) $s['number'] . '</th><th>Crédits S' . (int) $s['number'] . '</th>';
        }
        $html .= '<th>Moy. annuelle</th><th>Points jury</th><th>Moy. finale</th><th>Crédits</th><th>Décision du jury</th><th>Mention</th><th>Rang</th></tr></thead><tbody><tr>';
        foreach ($semList as $s) {
            $html .= '<td>' . lmdFormat($s['average']) . '</td><td>' . lmdFormatCredits($s['acquired']) . '/' . lmdFormatCredits($s['credits']) . '</td>';
        }
        $html .= '<td>' . lmdFormat($result['annual_average']) . '</td>'
            . '<td>' . ($result['jury_points'] > 0 ? '+' . lmdFormat($result['jury_points']) : '-') . '</td>'
            . '<td><strong>' . lmdFormat($result['final_average']) . '</strong></td>'
            . '<td><strong>' . lmdFormatCredits($result['credits_acquired']) . ' / ' . lmdFormatCredits($result['credits_total']) . '</strong></td>'
            . '<td><strong>' . e(lmdDecisionLabel($decision)) . '</strong></td>'
            . '<td>' . e($ctx['mention'] ?? '-') . '</td>'
            . '<td>' . (!empty($ctx['rank']) ? (int) $ctx['rank'] . ($ctx['rank'] == 1 ? 'er' : 'e') . (!empty($ctx['effectif']) ? ' / ' . (int) $ctx['effectif'] : '') : '-') . '</td>'
            . '</tr></tbody></table>';
    }

    if (!$result['complete'] && $result['missing'] > 0) {
        $html .= '<p class="lmd-warning">' . (int) $result['missing'] . ' note(s) manquante(s), comptée(s) 0 (ABS).</p>';
    }
    $html .= '<p class="lmd-legend">V : UE validée · VC : UE validée par compensation · NV : UE non validée · * : note de la session de rattrapage · '
        . 'Note ECUE = CC × ' . (int) round(LMD_CC_WEIGHT * 100) . '% + Examen × ' . (int) round((1 - LMD_CC_WEIGHT) * 100) . '%</p>';

    $city = $ctx['school']['city'] ?? '';
    $html .= '<footer class="lmd-doc-footer"><div class="lmd-place">Fait' . ($city !== '' ? ' à ' . e($city) : '') . ', le ' . date('d/m/Y') . '</div>'
        . '<div class="lmd-signatures"><div><span>Le Président du jury</span>' . (!empty($ctx['president']) ? '<em>' . e($ctx['president']) . '</em>' : '') . '</div>'
        . '<div><span>Le Directeur des études</span></div></div>'
        . '<p class="lmd-disclaimer">Il n\'est délivré qu\'un seul exemplaire du présent relevé. Document généré par ' . e(SITE_NAME) . '.</p></footer>';

    return $html . '</article>';
}
