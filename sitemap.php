<?php
declare(strict_types=1);

require_once __DIR__ . '/content-functions.php';

function sitemap_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$siteUrl = 'https://bodrbo-tuning.ru';
$urls = [
    $siteUrl . '/',
    $siteUrl . '/proekty/',
    $siteUrl . '/lodochnye-motory-marine-rocket/',
    $siteUrl . '/privacy/',
];

foreach (content_load_projects() as $project) {
    if (!is_array($project)) {
        continue;
    }
    $slug = (string) ($project['slug'] ?? '');
    if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $urls[] = $siteUrl . '/proekty/' . $slug . '/';
    }
}

$runtimePath = content_runtime_dir() . '/projects.json';
$fallbackPath = __DIR__ . '/projects-data.js';
$modified = @filemtime(is_file($runtimePath) ? $runtimePath : $fallbackPath);
$lastModified = $modified === false ? gmdate('Y-m-d') : gmdate('Y-m-d', $modified);

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=300');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (array_values(array_unique($urls)) as $url) {
    echo '  <url><loc>' . sitemap_escape($url) . '</loc><lastmod>' . $lastModified . '</lastmod></url>' . "\n";
}
echo '</urlset>' . "\n";

