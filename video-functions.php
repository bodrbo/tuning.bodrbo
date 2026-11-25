<?php
declare(strict_types=1);

require_once __DIR__ . '/content-functions.php';

function video_load(): array
{
    $path = content_runtime_dir() . '/videos.json';
    $data = json_decode((string) @file_get_contents(is_file($path) ? $path : __DIR__ . '/content/videos.json'), true);
    return is_array($data) ? $data : [];
}

function video_save(array $videos): void
{
    $path = content_runtime_dir() . '/videos.json';
    content_backup($path, 'videos');
    content_atomic_write($path, json_encode($videos, content_json_flags() | JSON_THROW_ON_ERROR) . "\n");
}

// Only rebuild a known VK player URL. Never save pasted iframe HTML.
function video_parse_url(string $input): array
{
    $input = html_entity_decode(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('/<iframe\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $input, $match)) {
        $input = $match[1];
    }
    if (str_starts_with($input, '//')) $input = 'https:' . $input;
    $url = parse_url($input);
    if (!$url || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['port'])
        || !in_array(strtolower($url['host'] ?? ''), ['vk.com', 'www.vk.com', 'm.vk.com', 'vk.ru', 'www.vk.ru', 'vkvideo.ru', 'www.vkvideo.ru'], true)) {
        throw new InvalidArgumentException('Вставьте HTTPS-ссылку VK Video или код его плеера.');
    }
    parse_str($url['query'] ?? '', $query);
    $hash = '';
    if (($url['path'] ?? '') === '/video_ext.php') {
        $owner = $query['oid'] ?? '';
        $id = $query['id'] ?? '';
        $hash = $query['hash'] ?? '';
    } else {
        $target = ($url['path'] ?? '') === '/video' ? ($query['z'] ?? '') : ($url['path'] ?? '');
        if (!is_string($target) || !preg_match('#^/?video(-?[1-9][0-9]*)_([1-9][0-9]*)(?:/.*)?$#', $target, $match)) {
            throw new InvalidArgumentException('Нужна ссылка на конкретное видео. Также можно вставить код из «Поделиться → Код для вставки».');
        }
        [, $owner, $id] = $match;
    }
    if (!is_scalar($owner) || !is_scalar($id) || !preg_match('/^-?[1-9][0-9]{0,18}$/', (string) $owner)
        || !preg_match('/^[1-9][0-9]{0,18}$/', (string) $id) || !is_string($hash) || ($hash !== '' && !preg_match('/^[a-zA-Z0-9_-]{1,128}$/', $hash))) {
        throw new InvalidArgumentException('Некорректные параметры видео VK.');
    }
    $params = ['oid' => (string) $owner, 'id' => (string) $id, 'hd' => '2'];
    if ($hash !== '') $params['hash'] = $hash;
    return [
        'embed' => 'https://vk.com/video_ext.php?' . http_build_query($params),
        'url' => 'https://vkvideo.ru/video' . $owner . '_' . $id,
    ];
}

function video_validate(array $input, array $projects): array
{
    if (count($input) > 100) throw new InvalidArgumentException('Максимум 100 выпусков.');
    $videos = [];
    $ids = [];
    foreach ($input as $item) {
        if (!is_array($item)) throw new InvalidArgumentException('Некорректные данные выпуска.');
        $id = admin_text($item['id'] ?? '', 60, true);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $id) || isset($ids[$id])) throw new InvalidArgumentException('Повторяется идентификатор выпуска.');
        $ids[$id] = true;
        $parsed = video_parse_url(admin_text($item['source'] ?? '', 5000, true));
        $project = admin_text($item['project'] ?? '', 100);
        $step = admin_text($item['step'] ?? '', 500);
        $service = admin_text($item['service'] ?? '', 30);
        if ($project !== '' && !isset($projects[$project])) throw new InvalidArgumentException('Выбранный кейс больше не существует.');
        $stepIds = $project === '' ? [] : array_map(static fn(array $value): string => (string) ($value[3] ?? $value[2]), $projects[$project]['steps']);
        if ($step !== '' && !in_array($step, $stepIds, true)) {
            throw new InvalidArgumentException('Обновите привязку этапа у выпуска.');
        }
        if (!in_array($service, ['', 'mechanics', 'hull', 'electrics', 'electronics', 'interior', 'tuning'], true)) throw new InvalidArgumentException('Неверно выбрана услуга.');
        $cover = admin_safe_image($item['cover'] ?? '', 'assets/');
        if (!is_file(__DIR__ . '/' . $cover)) throw new InvalidArgumentException('Сначала загрузите обложку выпуска.');
        $duration = admin_text($item['duration'] ?? '', 12);
        if ($duration !== '' && !preg_match('/^(?:[0-9]{1,2}:)?[0-9]{1,3}:[0-5][0-9]$/', $duration)) throw new InvalidArgumentException('Длительность укажите в формате 12:40 или 1:12:40.');
        $videos[] = [
            'id' => $id, 'title' => admin_text($item['title'] ?? '', 220, true),
            'description' => admin_text($item['description'] ?? '', 1000),
            'source' => $parsed['embed'], 'embed' => $parsed['embed'], 'url' => $parsed['url'],
            'cover' => $cover, 'duration' => $duration, 'project' => $project, 'step' => $step, 'service' => $service,
            'published' => !empty($item['published']), 'home' => !empty($item['home']),
        ];
    }
    return $videos;
}

function video_public(): array
{
    return array_values(array_filter(video_load(), static fn(array $video): bool => !empty($video['published'])));
}
