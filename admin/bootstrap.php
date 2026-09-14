<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/content-functions.php';

const ADMIN_ROOT = __DIR__;
const ADMIN_PUBLIC_ROOT = __DIR__ . '/..';

function admin_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_config_path(): string
{
    return dirname(__DIR__, 2) . '/admin-config.php';
}

function admin_load_config(): ?array
{
    $path = admin_config_path();
    if (!is_file($path)) {
        return null;
    }

    $config = require $path;
    if (!is_array($config) || empty($config['password_hash']) || $config['password_hash'] === 'PASTE_PASSWORD_HASH_HERE') {
        return null;
    }

    return $config;
}

function admin_start_session(array $config): void
{
    $name = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($config['session_name'] ?? 'bodrbo_admin')) ?: 'bodrbo_admin';
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    if (!empty($_SESSION['admin_authenticated_at']) && time() - (int) $_SESSION['admin_authenticated_at'] > 14400) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}

function admin_is_authenticated(): bool
{
    return !empty($_SESSION['admin_authenticated']);
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['admin_csrf'];
}

function admin_verify_csrf(string $token): bool
{
    return !empty($_SESSION['admin_csrf']) && hash_equals((string) $_SESSION['admin_csrf'], $token);
}

function admin_text(mixed $value, int $maxLength, bool $required = false): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", is_string($value) ? $value : ''));
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($required && $text === '') {
        throw new InvalidArgumentException('Заполните все обязательные поля.');
    }
    if ($length > $maxLength) {
        throw new InvalidArgumentException('Одно из полей превышает допустимую длину.');
    }
    return $text;
}

function admin_url(mixed $value, bool $required = false): string
{
    $url = admin_text($value, 2000, $required);
    if ($url === '') {
        return '';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
        throw new InvalidArgumentException('Ссылки должны быть полными и начинаться с https://.');
    }
    return $url;
}

function admin_validate_site(array $input): array
{
    $phoneHref = preg_replace('/[^+0-9]/', '', admin_text($input['phone_href'] ?? '', 30, true));
    if (!preg_match('/^\+[1-9][0-9]{9,14}$/', (string) $phoneHref)) {
        throw new InvalidArgumentException('Телефон для ссылки укажите в формате +79219676115.');
    }

    $email = admin_text($input['email'] ?? '', 254, true);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Проверьте адрес электронной почты.');
    }

    $latitude = admin_text($input['latitude'] ?? '', 30, true);
    $longitude = admin_text($input['longitude'] ?? '', 30, true);
    if (!is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90 || !is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180) {
        throw new InvalidArgumentException('Проверьте координаты тюнинг-центра.');
    }

    $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $openingDays = array_values(array_intersect($allowedDays, is_array($input['opening_days'] ?? null) ? $input['opening_days'] : []));
    if (!$openingDays) {
        throw new InvalidArgumentException('Выберите хотя бы один рабочий день.');
    }
    $opens = admin_text($input['opens'] ?? '', 5, true);
    $closes = admin_text($input['closes'] ?? '', 5, true);
    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $opens) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $closes)) {
        throw new InvalidArgumentException('Время работы нужно указать в формате 10:00.');
    }

    return [
        'phone_display' => admin_text($input['phone_display'] ?? '', 60, true),
        'phone_href' => (string) $phoneHref,
        'email' => $email,
        'hours_header' => admin_text($input['hours_header'] ?? '', 100, true),
        'hours_short' => admin_text($input['hours_short'] ?? '', 100, true),
        'opening_days' => $openingDays,
        'opens' => $opens,
        'closes' => $closes,
        'location_label' => admin_text($input['location_label'] ?? '', 160, true),
        'location_heading' => admin_text($input['location_heading'] ?? '', 160, true),
        'address' => admin_text($input['address'] ?? '', 700, true),
        'route_url' => admin_url($input['route_url'] ?? '', true),
        'map_embed_url' => admin_url($input['map_embed_url'] ?? '', true),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'telegram_url' => admin_url($input['telegram_url'] ?? ''),
        'whatsapp_url' => admin_url($input['whatsapp_url'] ?? ''),
    ];
}

function admin_safe_image(mixed $value, string $prefix = ''): string
{
    $image = admin_text($value, 500, true);
    if (str_contains($image, '..') || !preg_match('#^[A-Za-z0-9_./-]+\.(?:jpe?g|png|webp)$#i', $image)) {
        throw new InvalidArgumentException('Некорректный путь к фотографии.');
    }
    if ($prefix !== '' && !str_starts_with($image, $prefix)) {
        throw new InvalidArgumentException('Фотография находится вне папки проекта.');
    }
    return $image;
}

function admin_validate_projects(array $input, array $existing): array
{
    $validated = [];
    foreach ($existing as $key => $current) {
        if (!isset($input[$key]) || !is_array($input[$key])) {
            throw new InvalidArgumentException('В данных нет одного из кейсов. Обновите страницу админки.');
        }
        $project = $input[$key];
        $facts = [];
        foreach (array_slice(is_array($project['facts'] ?? null) ? $project['facts'] : [], 0, 12) as $fact) {
            $fact = admin_text($fact, 160);
            if ($fact !== '') $facts[] = $fact;
        }

        $steps = [];
        $rawSteps = is_array($project['steps'] ?? null) ? $project['steps'] : [];
        if (!$rawSteps || count($rawSteps) > 60) {
            throw new InvalidArgumentException('В каждом кейсе должен быть хотя бы один этап.');
        }
        foreach ($rawSteps as $step) {
            if (!is_array($step)) continue;
            $steps[] = [
                admin_text($step[0] ?? '', 220, true),
                admin_text($step[1] ?? '', 5000, true),
                admin_safe_image($step[2] ?? ''),
            ];
        }
        if (!$steps) {
            throw new InvalidArgumentException('В каждом кейсе должен быть хотя бы один этап.');
        }

        $validated[$key] = [
            'slug' => (string) ($current['slug'] ?? ''),
            'title' => admin_text($project['title'] ?? '', 240, true),
            'shortTitle' => admin_text($project['shortTitle'] ?? '', 180, true),
            'subtitle' => admin_text($project['subtitle'] ?? '', 700, true),
            'category' => admin_text($project['category'] ?? '', 180, true),
            'cover' => admin_safe_image($project['cover'] ?? ''),
            'facts' => $facts,
            'summary' => admin_text($project['summary'] ?? '', 5000, true),
            'result' => admin_text($project['result'] ?? '', 5000, true),
            'steps' => $steps,
        ];
    }

    return $validated;
}

function admin_redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; script-src 'self'; style-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
