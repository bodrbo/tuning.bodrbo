<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function upload_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$config = admin_load_config();
if ($config === null) {
    upload_response(503, ['ok' => false, 'message' => 'Админка ещё не настроена.']);
}

admin_start_session($config);
if (!admin_is_authenticated()) {
    upload_response(401, ['ok' => false, 'message' => 'Сессия завершилась. Войдите в админку снова.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    upload_response(405, ['ok' => false, 'message' => 'Используйте POST-запрос.']);
}
if (!admin_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
    upload_response(403, ['ok' => false, 'message' => 'Сессия устарела. Обновите страницу.']);
}

$projectKey = (string) ($_POST['project_key'] ?? '');
$kind = (string) ($_POST['kind'] ?? 'step');
$projects = content_load_projects();
if (!isset($projects[$projectKey]) || !preg_match('/^[a-z0-9_-]+$/', $projectKey)) {
    upload_response(400, ['ok' => false, 'message' => 'Неверно выбран кейс.']);
}
if (!in_array($kind, ['cover', 'step'], true)) {
    upload_response(400, ['ok' => false, 'message' => 'Неверный тип фотографии.']);
}
if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    upload_response(400, ['ok' => false, 'message' => 'Выберите фотографию.']);
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $message = ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE
        ? 'Фотография превышает лимит сервера.'
        : 'Не удалось загрузить фотографию.';
    upload_response(400, ['ok' => false, 'message' => $message]);
}
if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 8 * 1024 * 1024) {
    upload_response(400, ['ok' => false, 'message' => 'Размер файла должен быть не больше 8 МБ.']);
}

$temporary = (string) ($file['tmp_name'] ?? '');
$imageInfo = @getimagesize($temporary);
if ($imageInfo === false) {
    upload_response(400, ['ok' => false, 'message' => 'Файл не является корректным изображением.']);
}

$mime = (string) ($imageInfo['mime'] ?? '');
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extensions[$mime])) {
    upload_response(400, ['ok' => false, 'message' => 'Допустимы только JPG, PNG и WebP.']);
}

$uploadDirectory = dirname(__DIR__) . '/assets/projects/' . $projectKey . '/uploads';
if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
    upload_response(500, ['ok' => false, 'message' => 'Сервер не может создать папку для фотографий.']);
}

$filename = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $extensions[$mime];
$destination = $uploadDirectory . '/' . $filename;
if (!move_uploaded_file($temporary, $destination)) {
    upload_response(500, ['ok' => false, 'message' => 'Сервер не смог сохранить фотографию.']);
}
@chmod($destination, 0664);

$relative = 'uploads/' . $filename;
$value = $kind === 'cover' ? 'assets/projects/' . $projectKey . '/' . $relative : $relative;
$url = '/assets/projects/' . rawurlencode($projectKey) . '/' . $relative;
upload_response(200, ['ok' => true, 'value' => $value, 'url' => $url]);
