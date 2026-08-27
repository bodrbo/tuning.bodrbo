<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond_json($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function text_length($value)
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function post_text($key)
{
    $value = isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
    return trim($value);
}

function rate_limit_exceeded($identity)
{
    $directory = dirname(__DIR__) . '/.lead-rate-limit';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        return false;
    }

    $path = $directory . '/' . hash('sha256', $identity) . '.log';
    $handle = @fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return false;
    }

    $now = time();
    $windowStart = $now - 600;
    $contents = stream_get_contents($handle);
    $timestamps = preg_split('/\s+/', trim((string) $contents), -1, PREG_SPLIT_NO_EMPTY);
    $timestamps = array_values(array_filter(array_map('intval', $timestamps), function ($timestamp) use ($windowStart) {
        return $timestamp >= $windowStart;
    }));

    $limited = count($timestamps) >= 5;
    if (!$limited) {
        $timestamps[] = $now;
    }

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, implode("\n", $timestamps) . "\n");
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $limited;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond_json(405, ['ok' => false, 'message' => 'Метод не поддерживается']);
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > 65536) {
    respond_json(413, ['ok' => false, 'message' => 'Слишком большой запрос']);
}

// Honeypot: обычный посетитель это поле не видит и не заполняет.
if (post_text('company_website') !== '') {
    respond_json(202, ['ok' => true]);
}

$remoteAddress = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
if (rate_limit_exceeded($remoteAddress)) {
    header('Retry-After: 600');
    respond_json(429, ['ok' => false, 'message' => 'Слишком много попыток']);
}

$requestId = post_text('request_id');
$name = post_text('name');
$phone = post_text('phone');
$boatModel = post_text('boat_model');
$message = post_text('message');
$sourceUrl = post_text('source_url');

$errors = [];
if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $requestId)) {
    $errors[] = 'Некорректный идентификатор заявки';
}
if (text_length($name) < 2 || text_length($name) > 100) {
    $errors[] = 'Укажите имя';
}
$phoneDigits = preg_replace('/\D+/', '', $phone);
if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15 || text_length($phone) > 40) {
    $errors[] = 'Укажите корректный телефон';
}
if (text_length($boatModel) > 160) {
    $errors[] = 'Слишком длинное название модели';
}
if (text_length($message) > 2000) {
    $errors[] = 'Слишком длинное описание';
}
if (text_length($sourceUrl) > 500) {
    $errors[] = 'Некорректный адрес страницы';
}
if ($errors) {
    respond_json(422, ['ok' => false, 'message' => $errors[0]]);
}

$configPath = getenv('BODRY_BUSINESS_INTEGRATION_CONFIG');
if (!$configPath) {
    $configPath = dirname(__DIR__) . '/integration-config.php';
}
$config = is_file($configPath) ? require $configPath : null;
$endpoint = is_array($config) && isset($config['endpoint']) ? trim((string) $config['endpoint']) : '';
$secret = is_array($config) && isset($config['secret']) ? trim((string) $config['secret']) : '';
$endpointParts = $endpoint ? parse_url($endpoint) : false;

if (!$endpointParts || ($endpointParts['scheme'] ?? '') !== 'https' || !$secret) {
    error_log('Tuning lead integration is not configured');
    respond_json(503, ['ok' => false, 'message' => 'Отправка заявок временно недоступна']);
}
if (!function_exists('curl_init')) {
    error_log('Tuning lead integration requires the PHP cURL extension');
    respond_json(503, ['ok' => false, 'message' => 'Отправка заявок временно недоступна']);
}

$payload = json_encode([
    'request_id' => $requestId,
    'name' => $name,
    'phone' => $phone,
    'boat_model' => $boatModel,
    'message' => $message,
    'source_url' => $sourceUrl,
    'submitted_at' => gmdate('c'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    respond_json(422, ['ok' => false, 'message' => 'Не удалось обработать данные']);
}

$curl = curl_init($endpoint);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Bearer ' . $secret,
        'X-Request-ID: ' . $requestId,
    ],
    CURLOPT_POSTFIELDS => $payload,
]);

$upstreamBody = curl_exec($curl);
$upstreamStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if ($upstreamBody === false || $upstreamStatus < 200 || $upstreamStatus >= 300) {
    error_log(sprintf(
        'Tuning lead delivery failed: request_id=%s status=%d transport=%s',
        $requestId,
        $upstreamStatus,
        $curlError ? 'error' : 'ok'
    ));
    respond_json(502, ['ok' => false, 'message' => 'Корпоративная система временно недоступна']);
}

$upstreamPayload = json_decode((string) $upstreamBody, true);
if (!is_array($upstreamPayload) || empty($upstreamPayload['ok'])) {
    error_log(sprintf('Tuning lead delivery returned an invalid response: request_id=%s', $requestId));
    respond_json(502, ['ok' => false, 'message' => 'Некорректный ответ корпоративной системы']);
}

respond_json(202, ['ok' => true]);
