<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/video-functions.php';
$config = admin_load_config();
if (!$config) admin_redirect('/admin/');
admin_start_session($config);
if (!admin_is_authenticated()) admin_redirect('/admin/');
$projects = content_load_projects();
$videos = video_load();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!admin_verify_csrf((string) ($_POST['csrf'] ?? ''))) throw new RuntimeException('Обновите страницу и повторите сохранение.');
        if (($_POST['action'] ?? '') === 'preview') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(video_parse_url((string) ($_POST['source'] ?? '')), JSON_THROW_ON_ERROR);
            exit;
        }
        $input = json_decode((string) ($_POST['videos'] ?? ''), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new InvalidArgumentException('Некорректные данные выпусков.');
        $validated = video_validate($input, $projects);
        video_save($validated);
        $_SESSION['admin_authenticated_at'] = time();
        admin_redirect('/admin/videos.php?saved=1');
    } catch (Throwable $exception) {
        if (($_POST['action'] ?? '') === 'preview') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $exception->getMessage()]);
            exit;
        }
        // Preserve the submitted editor values if validation fails.
        if (isset($input) && is_array($input)) $videos = $input;
        $error = $exception->getMessage();
    }
}
$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!doctype html><html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="admin-csrf" content="<?= admin_escape(admin_csrf_token()) ?>">
<title>Видеоблог — Бодрый Боцман</title><link rel="stylesheet" href="/admin/admin.css"><link rel="stylesheet" href="/admin/videos.css">
</head><body>
<header class="admin-header"><a class="admin-brand" href="/admin/">Бодрый Боцман</a><a href="/admin/">← Контакты и кейсы</a><a href="/#video-blog" target="_blank" rel="noopener">Открыть сайт ↗</a></header>
<main class="video-editor-shell">
<div class="admin-title"><div><p class="admin-kicker">VK Video</p><h1>Видеоблог</h1></div><button type="button" class="primary-button" id="add-video">+ Добавить выпуск</button></div>
<p>Верхний опубликованный выпуск станет главным. На главной показываются три выпуска с отметкой «На главной». Привязка к этапу сохраняется при перестановке этапов.</p>
<?php if (isset($_GET['saved'])): ?><p class="admin-alert admin-alert--success" role="status">Выпуски сохранены.</p><?php endif; ?>
<?php if ($error): ?><p class="admin-alert admin-alert--error" role="alert"><?= admin_escape($error) ?></p><?php endif; ?>
<p class="admin-alert" id="video-status" role="status"></p>
<form method="post" id="videos-form">
<input type="hidden" name="csrf" value="<?= admin_escape(admin_csrf_token()) ?>">
<input type="hidden" name="videos" id="videos-input" value="<?= admin_escape(json_encode($videos, $flags)) ?>">
<div id="video-list"></div>
<footer class="admin-savebar"><span id="video-save-state">Изменений нет</span><button type="submit">Сохранить выпуски</button></footer>
</form>
<script type="application/json" id="video-projects"><?= json_encode($projects, $flags) ?></script>
<template id="video-template">
<article class="video-editor">
  <div class="video-editor-tools"><b data-heading>Новый выпуск</b><button type="button" data-up aria-label="Поднять выпуск">↑</button><button type="button" data-down aria-label="Опустить выпуск">↓</button><button type="button" data-remove>Удалить</button></div>
  <div class="field-grid">
    <label>Название<input data-field="title" maxlength="220" required></label>
    <label>Длительность <small>необязательно, например 12:40</small><input data-field="duration" placeholder="12:40"></label>
    <label class="field-wide">Ссылка VK Video или код для вставки<textarea data-field="source" rows="3" required placeholder="https://vkvideo.ru/video-123456_456239000"></textarea><small>Если ролик не воспроизводится по обычной ссылке, вставьте полный код из VK Video → Поделиться → Код для вставки.</small></label>
    <label class="field-wide">Описание<textarea data-field="description" rows="2" maxlength="1000"></textarea></label>
    <div class="image-control field-wide"><img data-cover-preview alt="Обложка выпуска"><div><label>Обложка<input type="file" data-cover-upload accept="image/jpeg,image/png,image/webp"></label><input type="hidden" data-field="cover"><p>JPG, PNG или WebP, до 8 МБ. Рекомендуем горизонтальную обложку.</p></div></div>
    <label>Кейс<select data-field="project"></select></label>
    <label>Этап<select data-field="step"></select><small>Можно показать выпуск под всей статьёй или под конкретным этапом.</small></label>
    <label>Услуга<select data-field="service"><option value="">Без привязки</option><option value="mechanics">Механика</option><option value="hull">Корпус</option><option value="electrics">Электрика</option><option value="electronics">Электроника</option><option value="interior">Интерьер</option><option value="tuning">Тюнинг</option></select></label>
    <div class="video-checkboxes"><label><input type="checkbox" data-field="published">Опубликован</label><label><input type="checkbox" data-field="home">На главной</label></div>
  </div>
  <button type="button" class="secondary-button" data-preview>Проверить плеер</button>
  <div data-player></div>
</article>
</template>
</main><script src="/admin/videos.js?v=1"></script></body></html>
