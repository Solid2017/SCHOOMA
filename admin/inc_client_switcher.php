<?php
/**
 * Selecteur d'ecole (visible uniquement pour le super-admin SaaS)
 * A inclure dans les pages de modules academiques/pedagogiques.
 * Necessite : $workingClientId deja resolu via resolveWorkingClientId()
 */
if (scopeClientId() === null):
    $allClients = getDB()->query('SELECT id, name FROM clients ORDER BY name ASC')->fetchAll();
?>
<form method="get" class="filters-bar" style="margin-bottom:18px;">
    <label style="font-weight:600;font-size:0.85rem;display:flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-building"></i> Ecole :
        <select class="form-control" name="client_id" onchange="this.form.submit()">
            <?php foreach ($allClients as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $workingClientId ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>
<?php endif; ?>
