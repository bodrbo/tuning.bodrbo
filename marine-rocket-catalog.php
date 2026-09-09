<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=1800');
header('X-Content-Type-Options: nosniff');

const MARINE_ROCKET_FEED = 'https://dealers.marinerocket.ru/uploads/yml/f2e1ee4bb8b3895bb069ea2fe1bcb61f33e3c15c/export.yml';
const MARINE_ROCKET_CACHE_TTL = 1800;
const MARINE_ROCKET_MAX_FEED_BYTES = 5242880;

function respond_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function read_catalog_cache(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $contents = @file_get_contents($path);
    if ($contents === false) {
        return null;
    }

    $payload = json_decode($contents, true);
    if (!is_array($payload) || ($payload['schema_version'] ?? null) !== 1 || empty($payload['products'])) {
        return null;
    }

    $payload['_cache_mtime'] = (int) @filemtime($path);
    return $payload;
}

function write_catalog_cache(string $path, array $payload): void
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($json === false) {
        return;
    }

    $temporaryPath = $path . '.' . getmypid() . '.tmp';
    if (@file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
        return;
    }
    @chmod($temporaryPath, 0600);
    if (!@rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
    }
}

function fetch_catalog_feed(): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is unavailable');
    }

    $body = '';
    $curl = curl_init(MARINE_ROCKET_FEED);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'BodryBoatswainCatalog/1.0 (+https://tuning.bodrbo.ru)',
        CURLOPT_HTTPHEADER => ['Accept: application/xml,text/xml;q=0.9,*/*;q=0.5'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > MARINE_ROCKET_MAX_FEED_BYTES) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);

    $result = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($result === false || $status !== 200 || $body === '') {
        throw new RuntimeException(sprintf('Feed request failed: status=%d transport=%s', $status, $error ? 'error' : 'ok'));
    }

    return $body;
}

function normalise_space(string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

function safe_image_url(string $url): string
{
    $parts = $url !== '' ? parse_url($url) : false;
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'static.marinerocket.ru') {
        return '';
    }
    return $url;
}

function catalog_category_key(string $name): string
{
    if (strpos($name, 'Двухтакт') !== false) {
        return 'two-stroke';
    }
    if (strpos($name, 'Четырехтакт') !== false || strpos($name, 'Четырёхтакт') !== false) {
        return 'four-stroke';
    }
    if (strpos($name, 'водомет') !== false || strpos($name, 'водомёт') !== false) {
        return 'jet';
    }
    if (strpos($name, 'Комбо') !== false) {
        return 'combo';
    }
    return 'other';
}

function parse_catalog_feed(string $xml): array
{
    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('PHP SimpleXML extension is unavailable');
    }

    $previous = libxml_use_internal_errors(true);
    $catalog = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($catalog === false || !isset($catalog->shop)) {
        throw new RuntimeException('Invalid YML catalog');
    }

    $shop = $catalog->shop;
    $categories = [];
    $motorRootId = null;

    foreach ($shop->categories->category as $category) {
        $id = trim((string) $category['id']);
        $name = normalise_space((string) $category);
        $parent = trim((string) $category['parentId']);
        if ($id === '') {
            continue;
        }
        $categories[$id] = ['name' => $name, 'parent' => $parent];
        if ($id === '2238869' || $name === 'Моторы Marine Rocket') {
            $motorRootId = $id;
        }
    }

    if ($motorRootId === null) {
        throw new RuntimeException('Motor category is missing');
    }

    $motorCategoryIds = [$motorRootId => true];
    do {
        $added = false;
        foreach ($categories as $id => $category) {
            if (isset($motorCategoryIds[$category['parent']]) && !isset($motorCategoryIds[$id])) {
                $motorCategoryIds[$id] = true;
                $added = true;
            }
        }
    } while ($added);

    $products = [];
    foreach ($shop->offers->offer as $offer) {
        $categoryId = trim((string) $offer->categoryId);
        if (!isset($motorCategoryIds[$categoryId]) || $categoryId === $motorRootId) {
            continue;
        }

        $name = normalise_space((string) $offer->name);
        $model = normalise_space((string) preg_replace('/^Мотор лодочный Marine Rocket\s+/u', '', $name));
        $price = (int) round((float) str_replace(',', '.', (string) $offer->price));
        $available = strtolower(trim((string) $offer['available'])) !== 'false';
        $categoryName = $categories[$categoryId]['name'] ?? 'Моторы Marine Rocket';

        $params = [];
        $pictures = [];
        $primaryPicture = safe_image_url(trim((string) $offer->picture));
        if ($primaryPicture !== '') {
            $pictures[] = $primaryPicture;
        }

        foreach ($offer->param as $param) {
            $paramName = normalise_space((string) $param['name']);
            $value = normalise_space((string) $param);
            if ($paramName === 'picture') {
                $picture = safe_image_url($value);
                if ($picture !== '' && !in_array($picture, $pictures, true) && count($pictures) < 5) {
                    $pictures[] = $picture;
                }
                continue;
            }
            if ($paramName !== '' && $value !== '') {
                $params[$paramName] = $value;
            }
        }

        $powerText = $params['Мощность двигателя'] ?? '';
        $power = (float) str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $powerText));
        $stockTotal = 0;
        foreach ($offer->quantity as $quantity) {
            $stockTotal += max(0, (int) $quantity);
        }

        $specs = [
            'Тактность' => $params['Тактность'] ?? '',
            'Мощность' => $powerText !== '' ? $powerText . ' л.с.' : '',
            'Объём двигателя' => isset($params['Объем двигателя']) ? $params['Объем двигателя'] . ' см³' : '',
            'Запуск' => $params['Система запуска'] ?? '',
            'Управление' => $params['Управление'] ?? '',
            'Подъём' => $params['Подъем'] ?? '',
            'Высота транца' => $params['Высота транца'] ?? '',
            'Вес' => isset($params['Вес']) ? $params['Вес'] . ' кг' : '',
            'Цилиндры' => $params['Количество цилиндров'] ?? '',
            'Подача топлива' => $params['Подача топлива'] ?? '',
            'Топливный бак' => isset($params['Топливный бак']) ? $params['Топливный бак'] . ' л' : '',
            'Гарантия производителя' => $params['Гарантия производителя'] ?? '',
        ];
        $specs = array_filter($specs, static fn($value): bool => $value !== '');

        if ($name === '' || $model === '' || $price <= 0 || empty($pictures)) {
            continue;
        }

        $products[] = [
            'id' => trim((string) $offer['id']),
            'name' => $name,
            'model' => $model,
            'category' => $categoryName,
            'category_key' => catalog_category_key($categoryName),
            'price' => $price,
            'power' => $power,
            'available' => $available,
            'supplier_stock' => $stockTotal > 0,
            'image' => $pictures[0],
            'pictures' => $pictures,
            'specs' => $specs,
        ];
    }

    usort($products, static function (array $left, array $right): int {
        return [$left['power'], $left['price'], $left['model']] <=> [$right['power'], $right['price'], $right['model']];
    });

    if (!$products) {
        throw new RuntimeException('No motor offers found');
    }

    return [
        'schema_version' => 1,
        'ok' => true,
        'stale' => false,
        'source_updated_at' => normalise_space((string) $catalog['date']),
        'fetched_at' => gmdate('c'),
        'total' => count($products),
        'products' => $products,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    respond_json(405, ['ok' => false, 'message' => 'Метод не поддерживается']);
}

$cachePath = dirname(__DIR__) . '/.marine-rocket-catalog.json';
$cached = read_catalog_cache($cachePath);
$cacheMtime = $cached['_cache_mtime'] ?? 0;
if ($cached && $cacheMtime > time() - MARINE_ROCKET_CACHE_TTL) {
    unset($cached['_cache_mtime']);
    respond_json(200, $cached);
}

try {
    $payload = parse_catalog_feed(fetch_catalog_feed());
    write_catalog_cache($cachePath, $payload);
    respond_json(200, $payload);
} catch (Throwable $error) {
    error_log('Marine Rocket catalog update failed: ' . $error->getMessage());
    if ($cached) {
        unset($cached['_cache_mtime']);
        $cached['stale'] = true;
        respond_json(200, $cached);
    }
    respond_json(503, [
        'ok' => false,
        'message' => 'Каталог временно недоступен. Позвоните нам — поможем подобрать мотор.',
    ]);
}
