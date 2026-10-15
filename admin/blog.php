<?php
require_once __DIR__ . '/auth.php';
requireRole(ROLES_DIRECTION);
$db = getDB();
$user = currentUser();
$pageTitle = 'Blog / Actualites';
$breadcrumb = 'Site web / Blog';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Session expiree, veuillez reessayer.');
        redirect('blog.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $excerpt = sanitize($_POST['excerpt'] ?? '');
        $content = $_POST['content'] ?? ''; // contenu HTML gere par l'admin, non echappe a l'enregistrement
        $metaTitle = sanitize($_POST['meta_title'] ?? '');
        $metaDescription = sanitize($_POST['meta_description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['brouillon', 'publie'], true) ? $_POST['status'] : 'brouillon';

        if ($title === '' || trim(strip_tags($content)) === '') {
            setFlash('error', 'Titre et contenu sont obligatoires.');
            redirect('blog.php');
        }

        $slug = slugify($title);
        if ($id > 0) {
            $existing = $db->prepare('SELECT id FROM blog_posts WHERE slug = ? AND id != ?');
            $existing->execute([$slug, $id]);
            if ($existing->fetch()) $slug .= '-' . $id;

            $publishedAt = null;
            $current = $db->prepare('SELECT status, published_at FROM blog_posts WHERE id = ?');
            $current->execute([$id]);
            $currentRow = $current->fetch();
            $publishedAt = $currentRow['published_at'];
            if ($status === 'publie' && !$publishedAt) $publishedAt = date('Y-m-d H:i:s');

            $stmt = $db->prepare('UPDATE blog_posts SET title=?, slug=?, excerpt=?, content=?, meta_title=?, meta_description=?, status=?, published_at=? WHERE id=?');
            $stmt->execute([$title, $slug, $excerpt, $content, $metaTitle, $metaDescription, $status, $publishedAt, $id]);
            setFlash('success', 'Article mis a jour.');
        } else {
            $existing = $db->prepare('SELECT id FROM blog_posts WHERE slug = ?');
            $existing->execute([$slug]);
            if ($existing->fetch()) $slug .= '-' . substr(uniqid(), -4);

            $publishedAt = $status === 'publie' ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare('INSERT INTO blog_posts (title, slug, excerpt, content, meta_title, meta_description, author_id, status, published_at) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$title, $slug, $excerpt, $content, $metaTitle, $metaDescription, $user['id'], $status, $publishedAt]);
            setFlash('success', 'Article cree.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
        setFlash('success', 'Article supprime.');
    }
    redirect('blog.php');
}

$posts = $db->query('SELECT * FROM blog_posts ORDER BY created_at DESC')->fetchAll();

require_once __DIR__ . '/inc_header.php';
?>

<div class="admin-content-header">
    <div><h2>Blog / Actualites</h2></div>
    <button class="btn btn-primary" data-modal-open="postModal" onclick="resetPostForm()"><i class="fa-solid fa-plus"></i> Nouvel article</button>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Titre</th><th>Statut</th><th>Publie le</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($posts as $p): ?>
                <tr>
                    <td><strong><?= sanitize($p['title']) ?></strong><br><small style="color:var(--gray-500);">/blog-article.php?slug=<?= sanitize($p['slug']) ?></small></td>
                    <td><span class="badge badge-<?= $p['status'] === 'publie' ? 'success' : 'gray' ?>"><?= sanitize($p['status']) ?></span></td>
                    <td><?= formatDate($p['published_at']) ?></td>
                    <td class="action-icons">
                        <a class="view" title="Voir" href="<?= SITE_URL ?>/blog-article.php?slug=<?= urlencode($p['slug']) ?>" target="_blank"><i class="fa-solid fa-eye"></i></a>
                        <button class="edit" title="Modifier" onclick='editPost(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cet article ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="delete" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($posts)): ?><tr><td colspan="4" class="text-center">Aucun article.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="postModal">
    <div class="modal-box" style="max-width:720px;">
        <form method="post">
            <div class="modal-header"><h3 id="postModalTitle">Nouvel article</h3><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="post_id" value="">
                <div class="form-group"><label>Titre *</label><input type="text" class="form-control" name="title" id="post_title" required></div>
                <div class="form-group"><label>Extrait (resume court)</label><textarea class="form-control" name="excerpt" id="post_excerpt" rows="2"></textarea></div>
                <div class="form-group"><label>Contenu * (HTML autorise : &lt;p&gt;, &lt;h2&gt;, &lt;img&gt;...)</label><textarea class="form-control" name="content" id="post_content" rows="8" required></textarea></div>
                <div class="grid grid-2" style="gap:16px;">
                    <div class="form-group"><label>Titre SEO (meta title)</label><input type="text" class="form-control" name="meta_title" id="post_meta_title"></div>
                    <div class="form-group"><label>Statut</label>
                        <select class="form-control" name="status" id="post_status">
                            <option value="brouillon">Brouillon</option><option value="publie">Publie</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Description SEO (meta description)</label><textarea class="form-control" name="meta_description" id="post_meta_description" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetPostForm() {
    document.getElementById('postModalTitle').textContent = 'Nouvel article';
    ['post_id','post_title','post_excerpt','post_content','post_meta_title','post_meta_description'].forEach(function(id){ document.getElementById(id).value = ''; });
    document.getElementById('post_status').value = 'brouillon';
}
function editPost(p) {
    document.getElementById('postModalTitle').textContent = "Modifier l'article";
    document.getElementById('post_id').value = p.id;
    document.getElementById('post_title').value = p.title;
    document.getElementById('post_excerpt').value = p.excerpt || '';
    document.getElementById('post_content').value = p.content;
    document.getElementById('post_meta_title').value = p.meta_title || '';
    document.getElementById('post_meta_description').value = p.meta_description || '';
    document.getElementById('post_status').value = p.status;
    document.getElementById('postModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/inc_footer.php'; ?>
