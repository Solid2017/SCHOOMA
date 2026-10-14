<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle       = 'Actualites | ' . SITE_NAME;
$pageDescription = 'Toutes les actualites, annonces et actualites academiques de ' . SITE_NAME . '.';

$perPage = 9;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = (int) getDB()->query('SELECT COUNT(*) FROM blog_posts WHERE status = "publie"')->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = getDB()->prepare('SELECT * FROM blog_posts WHERE status = "publie" ORDER BY published_at DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <h1>Actualites</h1>
        <p class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Accueil</a> / Actualites</p>
    </div>
</section>

<section>
    <div class="container">
        <?php if (empty($posts)): ?>
            <p class="text-center" style="color:var(--gray-500);">Aucun article publie pour le moment. Revenez bientot !</p>
        <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($posts as $post): ?>
            <article class="blog-card" data-aos="fade-up">
                <a href="<?= SITE_URL ?>/blog-article.php?slug=<?= urlencode($post['slug']) ?>">
                    <img src="<?= $post['image'] ? ASSETS_URL . '/uploads/' . sanitize($post['image']) : ASSETS_URL . '/img/blog-placeholder.jpg' ?>" alt="<?= sanitize($post['title']) ?>" loading="lazy" onerror="this.src='data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22200%22%3E%3Crect width=%22400%22 height=%22200%22 fill=%22%231c5fd6%22/%3E%3C/svg%3E'">
                </a>
                <div class="blog-card-body">
                    <div class="blog-meta"><i class="fa-regular fa-calendar"></i> <?= formatDate($post['published_at'] ?? $post['created_at']) ?></div>
                    <h3><a href="<?= SITE_URL ?>/blog-article.php?slug=<?= urlencode($post['slug']) ?>"><?= sanitize($post['title']) ?></a></h3>
                    <p><?= sanitize($post['excerpt'] ?? '') ?></p>
                    <a href="<?= SITE_URL ?>/blog-article.php?slug=<?= urlencode($post['slug']) ?>" class="btn btn-outline btn-sm mt-2">Lire l'article</a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
