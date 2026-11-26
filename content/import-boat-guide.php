<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/boat-guide-functions.php';

try {
    if ($argc !== 2 || !is_file($argv[1])) {
        throw new RuntimeException('Использование: php content/import-boat-guide.php /путь/к/выгрузке.json');
    }
    $data = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $items = $data['items'] ?? $data;
    if (!is_array($items) || $items === [] || array_keys($items) !== range(0, count($items) - 1)) {
        throw new RuntimeException('Ожидается непустой массив моделей.');
    }

    $editorial = guide_json(__DIR__ . '/boat-guide.json');
    $usedProfiles = [];
    $snapshot = [];
    foreach ($items as $item) {
        $id = $item['id'] ?? null;
        $name = $item['model_name'] ?? $item['name'] ?? null;
        if (!is_int($id) || $id < 1 || isset($snapshot[$id]) || !is_string($name) || trim($name) === '') {
            throw new RuntimeException('У каждой модели должны быть уникальный числовой id и название.');
        }
        $profile = guide_editorial_slug($item, $editorial);
        if ($profile !== '' && isset($usedProfiles[$profile])) {
            throw new RuntimeException('Выгрузка содержит несколько записей для профиля ' . $profile . '.');
        }
        if ($profile !== '') {
            $usedProfiles[$profile] = true;
        }
        $photos = guide_photos($item);
        if (count($photos) !== count((array) ($item['photos'] ?? []))) {
            throw new RuntimeException('ID ' . $id . ': часть фотографий отсутствует в assets/boat-guide/ или имеет неподдерживаемый формат.');
        }
        $snapshot[$id] = [
            'id' => $id,
            'name' => trim($name),
            'brand' => is_string($item['brand'] ?? null) ? trim($item['brand']) : '',
            'profile_slug' => $profile,
            'aliases' => array_values(array_filter((array) ($item['aliases'] ?? []), 'is_string')),
            'photos' => $photos,
            'facts' => guide_facts($item),
        ];
    }

    $directory = content_runtime_dir();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Не удалось создать каталог данных.');
    }
    $payload = json_encode(array_values($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $temporary = tempnam($directory, 'boat-guide-');
    if ($temporary === false) {
        throw new RuntimeException('Не удалось создать временный файл выгрузки.');
    }
    try {
        if (file_put_contents($temporary, $payload) !== strlen($payload)
            || !chmod($temporary, 0640)
            || !rename($temporary, $directory . '/boat-guide-catalog.json')) {
            throw new RuntimeException('Не удалось сохранить выгрузку.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }

    echo 'Импортировано моделей: ' . count($snapshot) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
