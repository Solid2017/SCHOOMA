<?php
require_once __DIR__ . '/auth.php';
requireRole([ROLE_SUPER_ADMIN]);
$db = getDB();
$pageTitle = 'Contenu du site';
$breadcrumb = 'Site web / Contenu (CMS)';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('content.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $sectionKey = sanitize($_POST['section_key'] ?? '');
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');

        if ($sectionKey === '') {
            setFlash('error', 'Cle de section obligatoire.');
        } else {
            $check = $db->prepare('SELECT id FROM site_content WHERE section_key = ?');
            $check->execute([$sectionKey]);
            if ($check->fetch()) {
                $stmt = $db->prepare('UPDATE site_content SET title=?, content=? WHERE section_key=?');
                $stmt->execute([$title, $content, $sectionKey]);
            } else {
                $stmt = $db->prepare('INSERT INTO site_content (section_key, title, content) VALUES (?,?,?)');
                $stmt->execute([$sectionKey, $title, $content]);
            }
            setFlash('success', 'Contenu mis a jour avec succes.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM site_content WHERE id = ?')->execute([$id]);
        setFlash('success', 'Section supprimee.');
    }
    redirect('content.php');
}

$sections = $db->query('SELECT * FROM site_content ORDER BY section_key ASC')->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<div class="admin-content-header">
    <div><h2>Contenu du site (CMS)</h2></div>
    <button class="btn btn-primary" data-modal-open="contentModal" onclick="resetContentForm()"><i class="fa-solid fa-plus"></i> Nouvelle section</button>
</div>

<div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> Les sections <strong>hero</strong>, <strong>about</strong> et <strong>contact_info</strong> sont utilisees directement sur les pages publiques du site.</div>

<div class="grid grid-2">
    <?php foreach ($sections as $s): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fa-solid fa-layer-group"></i> <?= sanitize($s['section_key']) ?></h3>
                <button class="btn btn-outline btn-sm" onclick='editContent(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i> Modifier</button>
            </div>
            <p><strong><?= sanitize($s['title'] ?? '') ?></strong></p>
            <p style="color:var(--gray-700);font-size:0.9rem;margin-top:8px;"><?= sanitize(mb_strimwidth($s['content'] ?? '', 0, 200, '...')) ?></p>
        </div>
    <?php endforeach; ?>
    <?php if (empty($sections)): ?><p class="text-center" style="grid-column:1/-1;">Aucune section de contenu.</p><?php endif; ?>
</div>

<div class="modal-overlay" id="contentModal">
    <div class="modal-box">
        <form method="post">
            <div class="modal-header"><h3 id="contentModalTitle">Nouvelle section</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <div class="form-group"><label>Cle de section * (identifiant unique, ex: hero)</label><input type="text" class="form-control" name="section_key" id="content_key" required></div>
                <div class="form-group"><label>Titre</label><input type="text" class="form-control" name="title" id="content_title"></div>
                <div class="form-group"><label>Contenu</label><textarea class="form-control" name="content" id="content_body" rows="6"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetContentForm() {
    document.getElementById('contentModalTitle').textContent = 'Nouvelle section';
    document.getElementById('content_key').value = '';
    document.getElementById('content_key').removeAttribute('readonly');
    document.getElementById('content_title').value = '';
    document.getElementById('content_body').value = '';
}
function editContent(s) {
    document.getElementById('contentModalTitle').textContent = 'Modifier : ' + s.section_key;
    document.getElementById('content_key').value = s.section_key;
    document.getElementById('content_key').setAttribute('readonly', 'readonly');
    document.getElementById('content_title').value = s.title || '';
    document.getElementById('content_body').value = s.content || '';
    document.getElementById('contentModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
