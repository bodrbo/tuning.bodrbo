<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function catalog_respond_json($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function catalog_text_length($value)
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    catalog_respond_json(405, ['ok' => false, 'message' => 'Метод не поддерживается']);
}

$query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$translit = isset($_GET['translit']) && is_string($_GET['translit']) ? trim($_GET['translit']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 8;
$limit = max(1, min(10, $limit));

if (catalog_text_length($query) < 2) {
    catalog_respond_json(200, ['ok' => true, 'items' => []]);
}
if (catalog_text_length($query) > 100 || catalog_text_length($translit) > 120) {
    catalog_respond_json(422, ['ok' => false, 'message' => 'Слишком длинный поисковый запрос']);
}

$configPath = getenv('BODRY_BUSINESS_INTEGRATION_CONFIG');
if (!$configPath) {
    $configPath = dirname(__DIR__) . '/integration-config.php';
}
$config = is_file($configPath) ? require $configPath : null;
$leadEndpoint = is_array($config) && isset($config['endpoint']) ? trim((string) $config['endpoint']) : '';
$catalogEndpoint = is_array($config) && isset($config['catalog_endpoint'])
    ? trim((string) $config['catalog_endpoint'])
    : '';
$secret = is_array($config) && isset($config['secret']) ? trim((string) $config['secret']) : '';

if (!$catalogEndpoint && $leadEndpoint) {
    $catalogEndpoint = (string) preg_replace(
        '~/tuning-leads/?$~',
        '/tuning-boat-catalog',
        $leadEndpoint
    );
}
$endpointParts = $catalogEndpoint ? parse_url($catalogEndpoint) : false;
if (!$endpointParts || ($endpointParts['scheme'] ?? '') !== 'https' || !$secret) {
    error_log('Tuning boat catalog integration is not configured');
    catalog_respond_json(503, ['ok' => false, 'message' => 'Поиск моделей временно недоступен']);
}
if (!function_exists('curl_init')) {
    error_log('Tuning boat catalog integration requires the PHP cURL extension');
    catalog_respond_json(503, ['ok' => false, 'message' => 'Поиск моделей временно недоступен']);
}

$separator = strpos($catalogEndpoint, '?') === false ? '?' : '&';
$requestUrl = $catalogEndpoint . $separator . http_build_query([
    'q' => $query,
    'translit' => $translit,
    'limit' => $limit,
]);

$curl = curl_init($requestUrl);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 7,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $secret,
    ],
]);

$upstreamBody = curl_exec($curl);
$upstreamStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if ($upstreamBody === false || $upstreamStatus < 200 || $upstreamStatus >= 300) {
    error_log(sprintf(
        'Tuning boat catalog search failed: status=%d transport=%s',
        $upstreamStatus,
        $curlError ? 'error' : 'ok'
    ));
    catalog_respond_json(502, ['ok' => false, 'message' => 'Поиск моделей временно недоступен']);
}

$upstreamPayload = json_decode((string) $upstreamBody, true);
if (!is_array($upstreamPayload) || empty($upstreamPayload['ok']) || !isset($upstreamPayload['items']) || !is_array($upstreamPayload['items'])) {
    error_log('Tuning boat catalog search returned an invalid response');
    catalog_respond_json(502, ['ok' => false, 'message' => 'Некорректный ответ каталога']);
}

$items = [];
foreach (array_slice($upstreamPayload['items'], 0, $limit) as $item) {
    if (!is_array($item)) {
        continue;
    }
    $id = isset($item['id']) ? (int) $item['id'] : 0;
    $modelName = isset($item['model_name']) ? trim((string) $item['model_name']) : '';
    $brand = isset($item['brand']) ? trim((string) $item['brand']) : '';
    if ($id < 1 || $modelName === '' || catalog_text_length($modelName) > 200) {
        continue;
    }
    $items[] = [
        'id' => $id,
        'model_name' => $modelName,
        'brand' => catalog_text_length($brand) <= 120 ? $brand : '',
    ];
}

catalog_respond_json(200, ['ok' => true, 'items' => $items]);
