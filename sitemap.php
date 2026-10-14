<?php
/**
 * SCHOOMA - Sitemap XML dynamique (SEO)
 * Inclut les pages statiques et les articles de blog publies.
 */
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/xml; charset=utf-8');

$staticPages = [
    ['loc' => '/index.php', 'priority' => '1.0'],
    ['loc' => '/a-propos.php', 'priority' => '0.8'],
    ['loc' => '/programmes.php', 'priority' => '0.8'],
    ['loc' => '/admissions.php', 'priority' => '0.9'],
    ['loc' => '/contact.php', 'priority' => '0.7'],
    ['loc' => '/blog.php', 'priority' => '0.7'],
    ['loc' => '/resultats.php', 'priority' => '0.6'],
];

$posts = getDB()->query('SELECT slug, updated_at FROM blog_posts WHERE status = "publie" ORDER BY published_at DESC')->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
    <url>
        <loc><?= SITE_URL . $page['loc'] ?></loc>
        <changefreq>weekly</changefreq>
        <priority><?= $page['priority'] ?></priority>
    </url>
<?php endforeach; ?>
<?php foreach ($posts as $post): ?>
    <url>
        <loc><?= SITE_URL . '/blog-article.php?slug=' . urlencode($post['slug']) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($post['updated_at'])) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
<?php endforeach; ?>
</urlset>
