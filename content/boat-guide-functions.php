<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/content-functions.php';

function guide_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function guide_slug(string $value): bool
{
    return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value);
}

function guide_key(string $value): string
{
    $upper = preg_split('//u', 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ', -1, PREG_SPLIT_NO_EMPTY);
    $lower = preg_split('//u', 'абвгдеежзийклмнопрстуфхцчшщъыьэюя', -1, PREG_SPLIT_NO_EMPTY);
    $value = strtr(strtolower($value), array_combine($upper, $lower));
    return preg_replace('/[^a-z0-9а-я]+/u', '', str_replace('ё', 'е', $value)) ?? '';
}

function guide_json(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function guide_name(array $row): string
{
    $name = trim($row['model_name'] ?? $row['name']);
    $brand = is_string($row['brand'] ?? null) ? trim($row['brand']) : '';
    return $brand !== '' && !str_starts_with(guide_key($name), guide_key($brand)) ? $brand . ' ' . $name : $name;
}

function guide_editorial_slug(array $row, array $edited): string
{
    $explicit = $row['profile_slug'] ?? '';
    if (is_string($explicit) && isset($edited[$explicit])) {
        return $explicit;
    }
    $key = guide_key(guide_name($row));
    foreach ($edited as $slug => $model) {
        // A search alias can describe a family member and need not identify an exact version.
        foreach ([$model['name'], ...($model['catalog_names'] ?? [])] as $name) {
            if ($key === guide_key($name)) {
                return $slug;
            }
        }
    }
    return '';
}

function guide_facts(array $row): array
{
    return array_filter((array) ($row['facts'] ?? []), static fn($value, $label) =>
        is_string($label) && trim($label) !== '' && ((is_string($value) && trim($value) !== '') || is_int($value) || is_float($value)),
        ARRAY_FILTER_USE_BOTH);
}

function guide_photos(array $row): array
{
    return array_values(array_filter((array) ($row['photos'] ?? []), static fn($path) =>
        is_string($path) && preg_match('~^/assets/boat-guide/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp|avif)$~iD', $path)
        && is_file(dirname(__DIR__) . $path)));
}

function guide_models(): array
{
    $edited = guide_json(__DIR__ . '/boat-guide.json');
    $snapshot = guide_json(content_runtime_dir() . '/boat-guide-catalog.json');
    $models = $edited;
    foreach ($snapshot as $row) {
        if (!is_array($row) || !is_int($row['id'] ?? null) || $row['id'] < 1 || !is_string($row['name'] ?? null) || trim($row['name']) === '') {
            continue;
        }
        $name = guide_name($row);
        $brand = is_string($row['brand'] ?? null) ? trim($row['brand']) : '';
        $slug = guide_editorial_slug($row, $edited) ?: 'model-' . $row['id'];
        $aliases = array_values(array_filter((array) ($row['aliases'] ?? []), 'is_string'));
        $editorial = $edited[$slug] ?? [];
        $models[$slug] = array_replace([
            'name' => $name,
            'brand' => $brand,
        ], $editorial, [
            'aliases' => array_unique(array_merge($aliases, $editorial['aliases'] ?? [])),
            'facts' => array_replace(guide_facts($row), $editorial['facts'] ?? []),
            'photos' => guide_photos($row),
        ]);
    }

    return $models;
}

function guide_indexable(array $model): bool
{
    return !empty($model['intro']) && (!empty($model['facts']) || !empty($model['sources']));
}
