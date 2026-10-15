<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_DIRECTEUR_ETUDES, ROLE_ASSISTANT]);
$db = getDB();
$workingClientId = resolveWorkingClientId();
$pageTitle = 'Emploi du temps';
$breadcrumb = 'Pedagogie / Emploi du temps hebdomadaire';

$slots = [
    ['start' => '08:00', 'end' => '10:00', 'label' => '08h - 10h'],
    ['start' => '10:00', 'end' => '12:00', 'label' => '10h - 12h'],
    ['start' => '12:00', 'end' => '14:00', 'label' => '12h - 14h'],
    ['start' => '14:00', 'end' => '16:00', 'label' => '14h - 16h'],
    ['start' => '16:00', 'end' => '18:00', 'label' => '16h - 18h'],
];
$days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'];

$classesStmt = $db->prepare('SELECT c.id, c.name, ay.label AS year_label FROM classes c JOIN academic_years ay ON ay.id = c.academic_year_id WHERE c.client_id = ? ORDER BY ay.is_current DESC, c.name ASC');
$classesStmt->execute([$workingClientId]);
$classesList = $classesStmt->fetchAll();
$selectedClassId = (int) ($_GET['class_id_view'] ?? ($classesList[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('schedule.php?client_id=' . $workingClientId . '&class_id_view=' . $selectedClassId);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $classId = (int) ($_POST['class_id'] ?? 0);
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? 0) ?: null;
        $day = (int) ($_POST['day_of_week'] ?? 0);
        $slotIndex = (int) ($_POST['slot_index'] ?? -1);
        $room = sanitize($_POST['room'] ?? '');

        $classStmt = $db->prepare('SELECT academic_year_id FROM classes WHERE id = ? AND client_id = ?');
        $classStmt->execute([$classId, $workingClientId]);
        $class = $classStmt->fetch();

        if (!$class || $subjectId <= 0 || $day < 1 || $day > 6 || !isset($slots[$slotIndex])) {
            setFlash('error', 'Veuillez remplir tous les champs obligatoires.');
        } else {
            $slot = $slots[$slotIndex];
            $stmt = $db->prepare('INSERT INTO schedules (client_id, academic_year_id, class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$workingClientId, $class['academic_year_id'], $classId, $subjectId, $teacherId, $day, $slot['start'], $slot['end'], $room]);
            setFlash('success', 'Creneau ajoute a l\'emploi du temps.');
        }
        $selectedClassId = $classId;
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM schedules WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $workingClientId]);
        setFlash('success', 'Creneau supprime.');
    }
    redirect('schedule.php?client_id=' . $workingClientId . '&class_id_view=' . $selectedClassId);
}

$scheduleIndex = [];
if ($selectedClassId) {
    $stmt = $db->prepare(
        'SELECT s.*, sub.name AS subject_name, t.first_name AS teacher_first, t.last_name AS teacher_last
         FROM schedules s JOIN subjects sub ON sub.id = s.subject_id LEFT JOIN teachers t ON t.id = s.teacher_id
         WHERE s.client_id = ? AND s.class_id = ?'
    );
    $stmt->execute([$workingClientId, $selectedClassId]);
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['day_of_week'] . '_' . substr($row['start_time'], 0, 5);
        $scheduleIndex[$key] = $row;
    }
}

$subjectsStmt = $db->prepare('SELECT id, name FROM subjects WHERE client_id = ? AND class_id = ? ORDER BY name ASC');
$subjectsStmt->execute([$workingClientId, $selectedClassId]);
$subjectsList = $subjectsStmt->fetchAll();

$teachersStmt = $db->prepare('SELECT id, first_name, last_name FROM teachers WHERE client_id = ? AND status = "actif" ORDER BY last_name ASC');
$teachersStmt->execute([$workingClientId]);
$teachersList = $teachersStmt->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<?php require __DIR__ . '/inc_client_switcher.php'; ?>

<div class="admin-content-header">
    <div><h2>Emploi du temps hebdomadaire</h2></div>
    <button class="btn btn-primary" data-modal-open="scheduleModal" <?= empty($subjectsList) ? 'disabled title="Creez des matieres pour cette filiere d\'abord"' : '' ?>><i class="fa-solid fa-plus"></i> Ajouter un creneau</button>
</div>

<div class="admin-card">
    <form class="filters-bar" method="get">
        <input type="hidden" name="client_id" value="<?= $workingClientId ?>">
        <label style="font-weight:600;font-size:0.85rem;">Filiere :</label>
        <select class="form-control" name="class_id_view" onchange="this.form.submit()">
            <?php foreach ($classesList as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $selectedClassId ? 'selected' : '' ?>><?= sanitize($c['name']) ?> (<?= sanitize($c['year_label']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (empty($classesList)): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Creez une filiere avant de definir un emploi du temps.</div>
    <?php else: ?>
    <div class="table-responsive">
        <div class="timetable">
            <div class="tt-time"></div>
            <?php foreach ($days as $label): ?><div class="tt-head"><?= sanitize($label) ?></div><?php endforeach; ?>

            <?php foreach ($slots as $slot): ?>
                <div class="tt-time"><?= sanitize($slot['label']) ?></div>
                <?php foreach (array_keys($days) as $dayNum):
                    $key = $dayNum . '_' . $slot['start'];
                    $entry = $scheduleIndex[$key] ?? null;
                ?>
                    <?php if ($entry): ?>
                        <div class="tt-slot">
                            <form method="post" onsubmit="return confirm('Retirer ce creneau ?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                                <button type="submit" class="tt-remove" title="Supprimer"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                            <strong><?= sanitize($entry['subject_name']) ?></strong>
                            <?php if ($entry['teacher_first']): ?><span><?= sanitize($entry['teacher_first'] . ' ' . $entry['teacher_last']) ?></span><br><?php endif; ?>
                            <?php if ($entry['room']): ?><span><i class="fa-solid fa-door-open"></i> <?= sanitize($entry['room']) ?></span><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="tt-empty"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="scheduleModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3>Ajouter un creneau</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
                <div class="form-group"><label>Matiere *</label>
                    <select class="form-control" name="subject_id" required>
                        <?php foreach ($subjectsList as $s): ?><option value="<?= (int) $s['id'] ?>"><?= sanitize($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Enseignant</label>
                    <select class="form-control" name="teacher_id">
                        <option value="">-- Non assigne --</option>
                        <?php foreach ($teachersList as $t): ?><option value="<?= (int) $t['id'] ?>"><?= sanitize($t['last_name'] . ' ' . $t['first_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Jour *</label>
                        <select class="form-control" name="day_of_week" required>
                            <?php foreach ($days as $num => $label): ?><option value="<?= $num ?>"><?= sanitize($label) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Creneau horaire *</label>
                        <select class="form-control" name="slot_index" required>
                            <?php foreach ($slots as $i => $s): ?><option value="<?= $i ?>"><?= sanitize($s['label']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Salle</label><input type="text" class="form-control" name="room" placeholder="Ex: Salle A12"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
