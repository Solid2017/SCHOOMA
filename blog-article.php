<?php
require_once __DIR__ . '/includes/config.php';

$slug = sanitize($_GET['slug'] ?? '');
if ($slug === '') {
    redirect(SITE_URL . '/blog.php');
}

$stmt = getDB()->prepare('SELECT bp.*, u.name AS author_name FROM blog_posts bp LEFT JOIN users u ON u.id = bp.author_id WHERE bp.slug = ? AND bp.status = "publie" LIMIT 1');
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Article introuvable | ' . SITE_NAME;
    require_once __DIR__ . '/includes/header.php';
    echo '<section><div class="container text-center"><h1>Article introuvable</h1><p><a href="' . SITE_URL . '/blog.php" class="btn btn-primary mt-2">Retour aux actualites</a></p></div></section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle       = sanitize($post['meta_title'] ?: $post['title']) . ' | ' . SITE_NAME;
$pageDescription = sanitize($post['meta_description'] ?: $post['excerpt'] ?: '');
$ogImage         = $post['image'] ? ASSETS_URL . '/uploads/' . sanitize($post['image']) : (ASSETS_URL . '/img/og-cover.jpg');

$related = getDB()->prepare('SELECT * FROM blog_posts WHERE status = "publie" AND id != ? ORDER BY published_at DESC LIMIT 3');
$related->execute([$post['id']]);
$relatedPosts = $related->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <h1><?= sanitize($post['title']) ?></h1>
        <p class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Accueil</a> / <a href="<?= SITE_URL ?>/blog.php">Actualites</a> / Article</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="blog-single-content" data-aos="fade-up">
            <div class="blog-meta" style="margin-bottom:20px;">
                <i class="fa-regular fa-calendar"></i> <?= formatDate($post['published_at'] ?? $post['created_at']) ?>
                <?php if (!empty($post['author_name'])): ?> &middot; <i class="fa-regular fa-user"></i> <?= sanitize($post['author_name']) ?><?php endif; ?>
            </div>
            <?php if ($post['image']): ?>
                <img src="<?= ASSETS_URL ?>/uploads/<?= sanitize($post['image']) ?>" alt="<?= sanitize($post['title']) ?>" loading="lazy">
            <?php endif; ?>
            <div class="article-body">
                <?= $post['content'] // contenu gere par l'admin CMS - HTML autorise volontairement ?>
            </div>
        </div>

        <?php if (!empty($relatedPosts)): ?>
        <div class="mt-2" style="margin-top:60px;">
            <h3 class="text-center mb-2">A lire aussi</h3>
            <div class="grid grid-3">
                <?php foreach ($relatedPosts as $rp): ?>
                <article class="blog-card" data-aos="fade-up">
                    <a href="<?= SITE_URL ?>/blog-article.php?slug=<?= urlencode($rp['slug']) ?>">
                        <img src="<?= $rp['image'] ? ASSETS_URL . '/uploads/' . sanitize($rp['image']) : ASSETS_URL . '/img/blog-placeholder.jpg' ?>" alt="<?= sanitize($rp['title']) ?>" loading="lazy" onerror="this.src='data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22200%22%3E%3Crect width=%22400%22 height=%22200%22 fill=%22%231c5fd6%22/%3E%3C/svg%3E'">
                    </a>
                    <div class="blog-card-body">
                        <h3><a href="<?= SITE_URL ?>/blog-article.php?slug=<?= urlencode($rp['slug']) ?>"><?= sanitize($rp['title']) ?></a></h3>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
