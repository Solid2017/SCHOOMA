<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN]);
$db = getDB();
$pageTitle = "Plans d'abonnement";
$breadcrumb = 'Gestion SaaS / Plans';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('plans.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $cycle = in_array($_POST['billing_cycle'] ?? '', ['mensuel', 'annuel'], true) ? $_POST['billing_cycle'] : 'annuel';
        $maxStudents = (int) ($_POST['max_students'] ?? 0);
        $maxTeachers = (int) ($_POST['max_teachers'] ?? 0);
        $features = sanitize($_POST['features'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $price < 0) {
            setFlash('error', 'Veuillez renseigner un nom et un prix valides.');
        } else {
            $slug = slugify($name);
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE subscription_plans SET name=?, slug=?, price=?, billing_cycle=?, max_students=?, max_teachers=?, features=?, is_active=? WHERE id=?');
                $stmt->execute([$name, $slug, $price, $cycle, $maxStudents, $maxTeachers, $features, $isActive, $id]);
                setFlash('success', 'Plan mis a jour avec succes.');
            } else {
                $stmt = $db->prepare('INSERT INTO subscription_plans (name, slug, price, billing_cycle, max_students, max_teachers, features, is_active) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$name, $slug, $price, $cycle, $maxStudents, $maxTeachers, $features, $isActive]);
                setFlash('success', 'Plan cree avec succes.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM subscription_plans WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Plan supprime.');
    }
    redirect('plans.php');
}

$plans = $db->query('SELECT p.*, (SELECT COUNT(*) FROM clients c WHERE c.plan_id = p.id) AS clients_count FROM subscription_plans p ORDER BY p.price ASC')->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<div class="admin-content-header">
    <div><h2>Plans d'abonnement</h2></div>
    <button class="btn btn-primary" data-modal-open="planModal" onclick="resetPlanForm()"><i class="fa-solid fa-plus"></i> Nouveau plan</button>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Nom</th><th>Prix</th><th>Cycle</th><th>Max etudiants</th><th>Max enseignants</th><th>Ecoles</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($plans as $p): ?>
                <tr>
                    <td><strong><?= sanitize($p['name']) ?></strong></td>
                    <td><?= formatMoney($p['price']) ?></td>
                    <td><?= sanitize($p['billing_cycle']) ?></td>
                    <td><?= (int) $p['max_students'] ?></td>
                    <td><?= (int) $p['max_teachers'] ?></td>
                    <td><?= (int) $p['clients_count'] ?></td>
                    <td><span class="badge badge-<?= $p['is_active'] ? 'success' : 'gray' ?>"><?= $p['is_active'] ? 'Actif' : 'Inactif' ?></span></td>
                    <td class="action-icons">
                        <button class="edit" title="Modifier" onclick='editPlan(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce plan ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($plans)): ?><tr><td colspan="8" class="text-center">Aucun plan cree.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="planModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="planModalTitle">Nouveau plan</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="plan_id" value="">
                <div class="form-group"><label>Nom du plan *</label><input type="text" class="form-control" name="name" id="plan_name" required></div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Prix (FCFA) *</label><input type="number" step="0.01" min="0" class="form-control" name="price" id="plan_price" required></div>
                    <div class="form-group"><label>Cycle de facturation</label>
                        <select class="form-control" name="billing_cycle" id="plan_cycle">
                            <option value="annuel">Annuel</option>
                            <option value="mensuel">Mensuel</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Max etudiants</label><input type="number" min="0" class="form-control" name="max_students" id="plan_max_students" value="100"></div>
                    <div class="form-group"><label>Max enseignants</label><input type="number" min="0" class="form-control" name="max_teachers" id="plan_max_teachers" value="20"></div>
                </div>
                <div class="form-group"><label>Fonctionnalites (separees par une virgule)</label><textarea class="form-control" name="features" id="plan_features" rows="3"></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="is_active" id="plan_active" checked style="width:auto;"> Plan actif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetPlanForm() {
    document.getElementById('planModalTitle').textContent = 'Nouveau plan';
    document.getElementById('plan_id').value = '';
    document.getElementById('plan_name').value = '';
    document.getElementById('plan_price').value = '';
    document.getElementById('plan_cycle').value = 'annuel';
    document.getElementById('plan_max_students').value = 100;
    document.getElementById('plan_max_teachers').value = 20;
    document.getElementById('plan_features').value = '';
    document.getElementById('plan_active').checked = true;
}
function editPlan(p) {
    document.getElementById('planModalTitle').textContent = 'Modifier le plan';
    document.getElementById('plan_id').value = p.id;
    document.getElementById('plan_name').value = p.name;
    document.getElementById('plan_price').value = p.price;
    document.getElementById('plan_cycle').value = p.billing_cycle;
    document.getElementById('plan_max_students').value = p.max_students;
    document.getElementById('plan_max_teachers').value = p.max_teachers;
    document.getElementById('plan_features').value = p.features || '';
    document.getElementById('plan_active').checked = p.is_active == 1;
    document.getElementById('planModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
