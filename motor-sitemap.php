<?php
declare(strict_types=1);

require_once __DIR__ . '/marine-rocket-catalog.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900, stale-while-revalidate=3600');
header('X-Content-Type-Options: nosniff');

try {
    $catalog = load_catalog_payload();
} catch (Throwable $error) {
    http_response_code(503);
    header('Retry-After: 900');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}

$siteUrl = 'https://tuning.bodrbo.ru/lodochnye-motory-marine-rocket/';
$lastModified = gmdate('Y-m-d', strtotime((string) ($catalog['fetched_at'] ?? 'now')) ?: time());

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($catalog['products'] ?? [] as $product) {
    $slug = (string) ($product['slug'] ?? '');
    if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        continue;
    }
    $location = htmlspecialchars($siteUrl . $slug . '/', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    echo '  <url><loc>', $location, '</loc><lastmod>', $lastModified, '</lastmod></url>', "\n";
}
echo '</urlset>', "\n";
