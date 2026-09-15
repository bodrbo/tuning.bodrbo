<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function new_case_upload(mixed $value, string $label): array
{
    if (!is_array($value)) {
        throw new InvalidArgumentException('Добавьте ' . $label . '.');
    }

    $error = (int) ($value['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('Файл «' . $label . '» превышает лимит сервера.');
        }
        throw new InvalidArgumentException('Добавьте ' . $label . '.');
    }

    $size = (int) ($value['size'] ?? 0);
    if ($size < 1 || $size > 8 * 1024 * 1024) {
        throw new InvalidArgumentException('Файл «' . $label . '» должен быть не больше 8 МБ.');
    }

    $temporary = (string) ($value['tmp_name'] ?? '');
    $imageInfo = @getimagesize($temporary);
    if ($imageInfo === false) {
        throw new InvalidArgumentException('Файл «' . $label . '» не является корректным изображением.');
    }

    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (string) ($imageInfo['mime'] ?? '');
    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('Для «' . $label . '» допустимы только JPG, PNG и WebP.');
    }

    return ['temporary' => $temporary, 'extension' => $extensions[$mime]];
}

$config = admin_load_config();
if ($config === null) {
    admin_redirect('/admin/');
}

admin_start_session($config);
if (!admin_is_authenticated()) {
    admin_redirect('/admin/');
}

$error = '';
$values = [
    'title' => '',
    'short_title' => '',
    'subtitle' => '',
    'card_summary' => '',
    'category' => 'Тюнинг и ремонт',
    'step_title' => '',
    'step_text' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    foreach ($values as $name => $default) {
        $values[$name] = is_string($_POST[$name] ?? null) ? (string) $_POST[$name] : $default;
    }

    try {
        if (!admin_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Сессия устарела. Обновите страницу и повторите.');
        }

        $title = admin_text($values['title'], 240, true);
        $shortTitle = admin_text($values['short_title'], 180, true);
        $subtitle = admin_text($values['subtitle'], 700, true);
        $cardSummary = admin_text($values['card_summary'], 700, true);
        $category = admin_text($values['category'], 180, true);
        $stepTitle = admin_text($values['step_title'], 220, true);
        $stepText = admin_text($values['step_text'], 5000, true);
        $coverUpload = new_case_upload($_FILES['cover'] ?? null, 'обложку кейса');
        $stepUpload = new_case_upload($_FILES['step_image'] ?? null, 'фотографию первого этапа');

        $projects = content_load_projects();
        if (count($projects) >= 100) {
            throw new RuntimeException('В админке уже 100 кейсов. Свяжитесь с разработчиком перед добавлением следующего.');
        }

        $slug = admin_unique_slug(admin_slugify($title), $projects);
        $projectKey = admin_unique_project_key($slug, $projects);
        $uploadDirectory = dirname(__DIR__) . '/assets/projects/' . $projectKey . '/uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Сервер не смог создать папку нового кейса.');
        }

        $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $coverName = 'cover-' . $stamp . '.' . $coverUpload['extension'];
        $stepName = 'step-01-' . $stamp . '.' . $stepUpload['extension'];
        $coverDestination = $uploadDirectory . '/' . $coverName;
        $stepDestination = $uploadDirectory . '/' . $stepName;

        if (!move_uploaded_file($coverUpload['temporary'], $coverDestination)) {
            throw new RuntimeException('Сервер не смог сохранить обложку.');
        }
        if (!move_uploaded_file($stepUpload['temporary'], $stepDestination)) {
            @unlink($coverDestination);
            throw new RuntimeException('Сервер не смог сохранить фотографию этапа.');
        }
        @chmod($coverDestination, 0664);
        @chmod($stepDestination, 0664);

        $coverPath = 'assets/projects/' . $projectKey . '/uploads/' . $coverName;
        $stepPath = 'uploads/' . $stepName;
        $projects[$projectKey] = [
            'slug' => $slug,
            'title' => $title,
            'shortTitle' => $shortTitle,
            'subtitle' => $subtitle,
            'cardSummary' => $cardSummary,
            'category' => $category,
            'cover' => $coverPath,
            'cardImage' => $coverPath,
            'facts' => [],
            'summary' => '',
            'result' => '',
            'steps' => [[$stepTitle, $stepText, $stepPath]],
        ];

        try {
            content_save_projects($projects);
        } catch (Throwable $exception) {
            @unlink($coverDestination);
            @unlink($stepDestination);
            throw $exception;
        }

        $_SESSION['admin_authenticated_at'] = time();
        admin_redirect('/admin/?created=1#case-' . rawurlencode($projectKey));
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="admin-csrf" content="<?= admin_escape(admin_csrf_token()) ?>">
  <title>Новый кейс — Бодрый Боцман</title>
  <link rel="icon" href="/assets/boatswain-face-web.png" type="image/png">
  <link rel="stylesheet" href="/admin/admin.css?v=2">
</head>
<body class="new-case-page">
  <header class="admin-header">
    <a class="admin-brand" href="/admin/"><img src="/assets/boatswain-face-web.png" alt=""><span>Бодрый Боцман</span><small>CONTENT DECK</small></a>
    <div class="admin-header__actions"><a href="/admin/">← Вернуться в редактор</a></div>
  </header>

  <main class="new-case-shell">
    <header class="new-case-intro">
      <div><p class="admin-kicker">// Новая история</p><h1>Соберите основу кейса</h1></div>
      <p>После создания вы сможете добавить остальные этапы, менять их местами и дополнять статью.</p>
    </header>

    <?php if ($error): ?><p class="admin-alert admin-alert--error" role="alert"><?= admin_escape($error) ?></p><?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="new-case-form" class="new-case-layout">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf" value="<?= admin_escape(admin_csrf_token()) ?>">

      <div class="new-case-fields">
        <section class="field-section">
          <h2>01 / Карточка и статья</h2>
          <div class="field-grid">
            <label class="field-wide">Заголовок статьи<input name="title" value="<?= admin_escape($values['title']) ?>" maxlength="240" required autofocus data-new-title placeholder="Например, Комплексный тюнинг катера NorthSilver 610"></label>
            <label>Заголовок карточки<input name="short_title" value="<?= admin_escape($values['short_title']) ?>" maxlength="180" required data-new-short-title></label>
            <label>Категория<input name="category" value="<?= admin_escape($values['category']) ?>" maxlength="180" required></label>
            <label class="field-wide">Подзаголовок<textarea name="subtitle" rows="3" maxlength="700" required data-new-subtitle placeholder="Кратко: что сделали и какую задачу решили"><?= admin_escape($values['subtitle']) ?></textarea></label>
            <label class="field-wide">Описание в карточке<textarea name="card_summary" rows="3" maxlength="700" required data-new-card-summary><?= admin_escape($values['card_summary']) ?></textarea></label>
            <label class="field-wide">Обложка кейса <small>JPG, PNG или WebP до 8 МБ</small><input type="file" name="cover" accept="image/jpeg,image/png,image/webp" required data-new-image></label>
          </div>
        </section>

        <section class="field-section">
          <h2>02 / Первый этап</h2>
          <div class="field-grid">
            <label class="field-wide">Заголовок этапа<input name="step_title" value="<?= admin_escape($values['step_title']) ?>" maxlength="220" required></label>
            <label class="field-wide">Текст этапа<textarea name="step_text" rows="6" maxlength="5000" required><?= admin_escape($values['step_text']) ?></textarea></label>
            <label class="field-wide">Фотография этапа <small>JPG, PNG или WebP до 8 МБ</small><input type="file" name="step_image" accept="image/jpeg,image/png,image/webp" required data-new-image></label>
          </div>
        </section>
      </div>

      <aside class="seo-console" aria-live="polite">
        <p class="admin-kicker">// SEO автоматически</p>
        <h2>Поисковой предпросмотр</h2>
        <div class="seo-serp">
          <span>bodrbo-tuning.ru</span>
          <strong data-seo-title>Название кейса — Бодрый Боцман</strong>
          <p data-seo-description>Краткое описание проекта появится здесь.</p>
        </div>
        <dl class="seo-generated">
          <div><dt>URL</dt><dd data-seo-url>/proekty/novyi-keis/</dd></div>
          <div><dt>Canonical</dt><dd>Совпадает с URL</dd></div>
          <div><dt>Open Graph</dt><dd>Заголовок, текст и обложка</dd></div>
          <div><dt>Schema.org</dt><dd>Article + BreadcrumbList</dd></div>
          <div><dt>Sitemap</dt><dd>Добавится после создания</dd></div>
        </dl>
        <div class="new-case-preview" data-new-image-preview>Обложка появится здесь</div>
        <button type="submit" class="primary-button">Создать и открыть кейс</button>
        <p class="seo-console__note">Кейс сразу появится в архиве проектов. После этого добавьте остальные этапы в обычном редакторе.</p>
      </aside>
    </form>
  </main>
  <script src="/admin/new-case.js?v=1"></script>
</body>
</html>
