<?php
declare(strict_types=1);

require_once __DIR__ . '/content-functions.php';

function projects_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function projects_image_url(string $path, string $projectKey): string
{
    if (str_starts_with($path, 'assets/')) {
        return '/' . ltrim($path, '/');
    }

    return '/assets/projects/' . rawurlencode($projectKey) . '/' . str_replace('%2F', '/', rawurlencode($path));
}

$template = @file_get_contents(__DIR__ . '/projects.html');
if ($template === false) {
    http_response_code(500);
    exit('Не удалось загрузить архив проектов.');
}

$opening = '<section class="projects-index-grid" aria-label="Все проекты">';
$closing = '</section>';
$sectionStart = strpos($template, $opening);
$sectionEnd = $sectionStart === false ? false : strpos($template, $closing, $sectionStart + strlen($opening));

if ($sectionStart === false || $sectionEnd === false) {
    http_response_code(500);
    exit('Не удалось собрать архив проектов.');
}

$existingGrid = substr($template, $sectionStart + strlen($opening), $sectionEnd - $sectionStart - strlen($opening));
$previewCards = '';
if (preg_match_all('/<article class="project-index-card project-index-card--preview">.*?<\/article>/s', $existingGrid, $matches)) {
    $previewCards = implode("\n      ", $matches[0]);
}

$cards = [];
$version = rawurlencode(content_projects_version());
foreach (content_load_projects() as $key => $project) {
    if (!is_array($project)) {
        continue;
    }

    $slug = (string) ($project['slug'] ?? '');
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        continue;
    }

    $title = (string) ($project['shortTitle'] ?? $project['title'] ?? 'Проект');
    $category = (string) ($project['category'] ?? 'Тюнинг и ремонт');
    $summary = (string) ($project['cardSummary'] ?? $project['subtitle'] ?? '');
    $imagePath = (string) ($project['cardImage'] ?? $project['cover'] ?? '');
    $imageUrl = projects_image_url($imagePath, (string) $key) . '?v=' . $version;
    $class = 'project-index-card' . ($cards === [] ? ' project-index-card--large' : '');
    $loading = $cards === [] ? ' fetchpriority="high"' : ' loading="lazy"';

    $cards[] = '      <a class="' . $class . '" data-case-card="' . projects_escape((string) $key) . '" href="/proekty/' . projects_escape($slug) . '/">'
        . '<img src="' . projects_escape($imageUrl) . '" alt="' . projects_escape($title) . '"' . $loading . '>'
        . '<div><span>' . projects_escape($category) . '</span><h2>' . projects_escape($title) . '</h2><p>' . projects_escape($summary) . '</p><b>Читать кейс ↗</b></div></a>';
}

$generatedGrid = "\n" . implode("\n", $cards);
if ($previewCards !== '') {
    $generatedGrid .= "\n      " . $previewCards;
}
$generatedGrid .= "\n    ";

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
echo substr($template, 0, $sectionStart + strlen($opening));
echo $generatedGrid;
echo substr($template, $sectionEnd);

