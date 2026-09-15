<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = admin_load_config();
$configurationMissing = $config === null;
$error = '';

if (!$configurationMissing) {
    admin_start_session($config);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
        $lastAttempt = (int) ($_SESSION['admin_last_attempt'] ?? 0);
        if (time() - $lastAttempt < 2) {
            $error = 'Подождите пару секунд и повторите.';
        } else {
            $_SESSION['admin_last_attempt'] = time();
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (password_verify($password, (string) $config['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_authenticated'] = true;
                $_SESSION['admin_authenticated_at'] = time();
                admin_redirect('/admin/');
            }
            $error = 'Неверный пароль.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
        if (admin_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        admin_redirect('/admin/');
    }
}

$authenticated = !$configurationMissing && admin_is_authenticated();
$site = content_load_site();
$projects = content_load_projects();
$saved = isset($_GET['saved']);
$created = isset($_GET['created']);

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    try {
        if (!admin_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Сессия устарела. Обновите страницу и повторите сохранение.');
        }
        $siteInput = is_array($_POST['site'] ?? null) ? $_POST['site'] : [];
        $projectInput = json_decode((string) ($_POST['projects_json'] ?? ''), true);
        if (!is_array($projectInput)) {
            throw new InvalidArgumentException('Не удалось прочитать данные кейсов. Обновите страницу.');
        }

        $site = admin_validate_site($siteInput);
        $projects = admin_validate_projects($projectInput, $projects);
        content_save_site($site);
        content_save_projects($projects);
        $_SESSION['admin_authenticated_at'] = time();
        admin_redirect('/admin/?saved=1');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

function admin_image_url(string $path, string $projectKey = ''): string
{
    if (str_starts_with($path, 'assets/')) return '/' . ltrim($path, '/');
    return '/assets/projects/' . rawurlencode($projectKey) . '/' . str_replace('%2F', '/', rawurlencode($path));
}

$projectJson = json_encode($projects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <?php if ($authenticated): ?><meta name="admin-csrf" content="<?= admin_escape(admin_csrf_token()) ?>"><?php endif; ?>
  <title>Редактор сайта — Бодрый Боцман</title>
  <link rel="icon" href="/assets/boatswain-face-web.png" type="image/png">
  <link rel="stylesheet" href="/admin/admin.css?v=2">
</head>
<body>
<?php if ($configurationMissing): ?>
  <main class="setup-shell">
    <section class="setup-card">
      <p class="admin-kicker">// Первый запуск</p>
      <h1>Защитите админку паролем</h1>
      <p>Создайте файл <code>~/tuning.bodrbo.ru/admin-config.php</code> за пределами <code>public_html</code>.</p>
      <ol>
        <li>Создайте случайный пароль и хеш: <code>php8.3 -r '$p=bin2hex(random_bytes(12)); echo "PASSWORD=".$p.PHP_EOL."HASH=".password_hash($p,PASSWORD_DEFAULT).PHP_EOL;'</code></li>
        <li>Скопируйте <code>admin-config.example.php</code> на уровень выше <code>public_html</code>.</li>
        <li>Вставьте хеш вместо <code>PASTE_PASSWORD_HASH_HERE</code> и обновите страницу.</li>
      </ol>
      <p class="setup-note">Сам пароль в файле не хранится — только его безопасный хеш.</p>
    </section>
  </main>
<?php elseif (!$authenticated): ?>
  <main class="login-shell">
    <section class="login-card">
      <div class="admin-brand"><img src="/assets/boatswain-face-web.png" alt=""><span>Бодрый Боцман</span></div>
      <p class="admin-kicker">// Редактор сайта</p>
      <h1>Вход в админку</h1>
      <?php if ($error): ?><p class="admin-alert admin-alert--error" role="alert"><?= admin_escape($error) ?></p><?php endif; ?>
      <form method="post" class="login-form">
        <input type="hidden" name="action" value="login">
        <label>Пароль<input type="password" name="password" required autofocus autocomplete="current-password"></label>
        <button type="submit">Войти</button>
      </form>
    </section>
  </main>
<?php else: ?>
  <header class="admin-header">
    <a class="admin-brand" href="/admin/"><img src="/assets/boatswain-face-web.png" alt=""><span>Бодрый Боцман</span><small>CONTENT DECK</small></a>
    <div class="admin-header__actions"><a href="/" target="_blank" rel="noopener">Открыть сайт ↗</a><form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= admin_escape(admin_csrf_token()) ?>"><button type="submit">Выйти</button></form></div>
  </header>
  <form method="post" id="content-form" class="admin-workspace" novalidate>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="csrf" value="<?= admin_escape(admin_csrf_token()) ?>">
    <textarea name="projects_json" id="projects-json" hidden><?= admin_escape(is_string($projectJson) ? $projectJson : '{}') ?></textarea>

    <aside class="admin-rail">
      <p class="admin-kicker">// Разделы</p>
      <nav class="admin-tabs" aria-label="Разделы админки">
        <button type="button" class="is-active" data-admin-tab="contacts"><span>01</span>Контакты</button>
        <button type="button" data-admin-tab="cases"><span>02</span>Кейсы</button>
      </nav>
      <div class="admin-rail__note"><b>Автокопии</b><p>Перед каждым сохранением прежняя версия остаётся в архиве.</p></div>
    </aside>

    <main class="admin-main">
      <?php if ($created): ?><p class="admin-alert admin-alert--success" role="status">Новый кейс создан, добавлен в архив проектов и sitemap. Теперь можно добавить остальные этапы.</p><?php elseif ($saved): ?><p class="admin-alert admin-alert--success" role="status">Изменения сохранены и уже видны на сайте.</p><?php endif; ?>
      <?php if ($error): ?><p class="admin-alert admin-alert--error" role="alert"><?= admin_escape($error) ?></p><?php endif; ?>

      <section class="admin-panel is-active" data-admin-panel="contacts">
        <div class="admin-title"><div><p class="admin-kicker">01 / Общие данные</p><h1>Контакты и график</h1></div><p>Меняются сразу на всех публичных страницах.</p></div>
        <div class="field-section">
          <h2>Связь</h2>
          <div class="field-grid">
            <label>Телефон на сайте<input name="site[phone_display]" value="<?= admin_escape((string) $site['phone_display']) ?>" required></label>
            <label>Телефон для ссылки <small>без пробелов</small><input name="site[phone_href]" value="<?= admin_escape((string) $site['phone_href']) ?>" required></label>
            <label>Email<input type="email" name="site[email]" value="<?= admin_escape((string) $site['email']) ?>" required></label>
            <label>Telegram <small>необязательно</small><input type="url" name="site[telegram_url]" value="<?= admin_escape((string) $site['telegram_url']) ?>" placeholder="https://t.me/..."></label>
            <label>WhatsApp <small>необязательно</small><input type="url" name="site[whatsapp_url]" value="<?= admin_escape((string) $site['whatsapp_url']) ?>" placeholder="https://wa.me/..."></label>
          </div>
        </div>
        <div class="field-section">
          <h2>График</h2>
          <div class="field-grid">
            <label>В шапке<input name="site[hours_header]" value="<?= admin_escape((string) $site['hours_header']) ?>" required></label>
            <label>В подвале<input name="site[hours_short]" value="<?= admin_escape((string) $site['hours_short']) ?>" required></label>
            <label>Открытие <small>для поисковой разметки</small><input type="time" name="site[opens]" value="<?= admin_escape((string) $site['opens']) ?>" required></label>
            <label>Закрытие <small>для поисковой разметки</small><input type="time" name="site[closes]" value="<?= admin_escape((string) $site['closes']) ?>" required></label>
            <fieldset class="day-picker field-wide"><legend>Рабочие дни</legend><?php foreach (['Monday' => 'Пн', 'Tuesday' => 'Вт', 'Wednesday' => 'Ср', 'Thursday' => 'Чт', 'Friday' => 'Пт', 'Saturday' => 'Сб', 'Sunday' => 'Вс'] as $day => $label): ?><label><input type="checkbox" name="site[opening_days][]" value="<?= $day ?>" <?= in_array($day, (array) $site['opening_days'], true) ? 'checked' : '' ?>><span><?= $label ?></span></label><?php endforeach; ?></fieldset>
          </div>
        </div>
        <div class="field-section">
          <h2>Адрес и маршрут</h2>
          <div class="field-grid">
            <label>Короткая подпись<input name="site[location_label]" value="<?= admin_escape((string) $site['location_label']) ?>" required></label>
            <label>Заголовок блока<textarea name="site[location_heading]" rows="2" required><?= admin_escape((string) $site['location_heading']) ?></textarea></label>
            <label class="field-wide">Полный адрес <small>переносы строк сохранятся</small><textarea name="site[address]" rows="4" required><?= admin_escape((string) $site['address']) ?></textarea></label>
            <label class="field-wide">Ссылка «Открыть маршрут»<input type="url" name="site[route_url]" value="<?= admin_escape((string) $site['route_url']) ?>" required></label>
            <label class="field-wide">Ссылка встроенной карты<input type="url" name="site[map_embed_url]" value="<?= admin_escape((string) $site['map_embed_url']) ?>" required></label>
            <label>Широта<input name="site[latitude]" inputmode="decimal" value="<?= admin_escape((string) $site['latitude']) ?>" required></label>
            <label>Долгота<input name="site[longitude]" inputmode="decimal" value="<?= admin_escape((string) $site['longitude']) ?>" required></label>
          </div>
        </div>
      </section>

      <section class="admin-panel" data-admin-panel="cases">
        <div class="admin-title"><div><p class="admin-kicker">02 / Архив работ</p><h1>Редактор кейсов</h1></div><div class="admin-title__aside"><p>Меняйте текст, фотографии и порядок этапов. URL кейса остаётся стабильным.</p><a class="primary-button" href="/admin/new-case.php">+ Новый кейс</a></div></div>
        <div class="case-workbench">
          <nav class="case-list" aria-label="Выбор кейса">
            <?php foreach ($projects as $key => $project): ?><button type="button" data-case-select="<?= admin_escape((string) $key) ?>"><span><?= admin_escape((string) ($project['category'] ?? 'Кейс')) ?></span><b><?= admin_escape((string) ($project['shortTitle'] ?? $project['title'] ?? $key)) ?></b></button><?php endforeach; ?>
          </nav>
          <div class="case-editors">
            <?php foreach ($projects as $key => $project): ?>
              <article class="case-editor" data-case-editor="<?= admin_escape((string) $key) ?>">
                <header class="case-editor__header"><div><span>SEO URL</span><code>/proekty/<?= admin_escape((string) ($project['slug'] ?? '')) ?>/</code></div><a href="/proekty/<?= admin_escape((string) ($project['slug'] ?? '')) ?>/" target="_blank" rel="noopener">Предпросмотр ↗</a></header>
                <div class="field-section">
                  <h2>Обложка и вводная</h2>
                  <div class="field-grid">
                    <label class="field-wide">Заголовок статьи<input data-project-field="title" value="<?= admin_escape((string) ($project['title'] ?? '')) ?>" required></label>
                    <label>Заголовок карточки<input data-project-field="shortTitle" value="<?= admin_escape((string) ($project['shortTitle'] ?? '')) ?>" required></label>
                    <label>Категория<input data-project-field="category" value="<?= admin_escape((string) ($project['category'] ?? '')) ?>" required></label>
                    <label class="field-wide">Подзаголовок<textarea data-project-field="subtitle" rows="3" required><?= admin_escape((string) ($project['subtitle'] ?? '')) ?></textarea></label>
                    <label class="field-wide">Описание в карточке<textarea data-project-field="cardSummary" rows="3" required><?= admin_escape((string) ($project['cardSummary'] ?? $project['subtitle'] ?? '')) ?></textarea></label>
                    <label class="field-wide">Факты <small>один на строку</small><textarea data-project-field="facts" rows="3"><?= admin_escape(implode("\n", is_array($project['facts'] ?? null) ? $project['facts'] : [])) ?></textarea></label>
                    <label class="field-wide">Исходная задача <small>необязательно</small><textarea data-project-field="summary" rows="5"><?= admin_escape((string) ($project['summary'] ?? '')) ?></textarea></label>
                    <label class="field-wide">Результат <small>необязательно</small><textarea data-project-field="result" rows="5"><?= admin_escape((string) ($project['result'] ?? '')) ?></textarea></label>
                  </div>
                  <div class="image-control" data-image-control>
                    <img data-image-preview src="<?= admin_escape(admin_image_url((string) ($project['cover'] ?? ''), (string) $key)) ?>" alt="">
                    <div><b>Обложка кейса</b><p>Новая фотография заменит текущую после загрузки.</p><label class="upload-button">Выбрать фото<input type="file" accept="image/jpeg,image/png,image/webp" data-image-upload data-image-kind="cover" data-project-key="<?= admin_escape((string) $key) ?>"></label><input type="hidden" data-project-field="cover" data-image-value value="<?= admin_escape((string) ($project['cover'] ?? '')) ?>"></div>
                  </div>
                </div>
                <div class="field-section step-section">
                  <div class="step-section__title"><div><h2>Этапы работ</h2><p>Перетаскивайте карточки или используйте стрелки.</p></div><button type="button" class="secondary-button" data-add-step data-project-key="<?= admin_escape((string) $key) ?>">+ Добавить этап</button></div>
                  <div class="step-list" data-step-list>
                    <?php foreach (($project['steps'] ?? []) as $index => $step): ?>
                      <article class="step-card" draggable="false" data-step>
                        <div class="step-card__rail"><button type="button" class="drag-handle" aria-label="Перетащить этап">⋮⋮</button><strong data-step-number><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></strong><div><button type="button" data-step-up aria-label="Поднять этап">↑</button><button type="button" data-step-down aria-label="Опустить этап">↓</button></div></div>
                        <div class="step-card__body"><label>Заголовок<input data-step-field="title" value="<?= admin_escape((string) ($step[0] ?? '')) ?>" required></label><label>Текст<textarea data-step-field="text" rows="4" required><?= admin_escape((string) ($step[1] ?? '')) ?></textarea></label><button type="button" class="remove-step" data-remove-step>Удалить этап</button></div>
                        <div class="step-card__image" data-image-control><img data-image-preview src="<?= admin_escape(admin_image_url((string) ($step[2] ?? ''), (string) $key)) ?>" alt=""><label class="upload-button">Заменить фото<input type="file" accept="image/jpeg,image/png,image/webp" data-image-upload data-image-kind="step" data-project-key="<?= admin_escape((string) $key) ?>"></label><input type="hidden" data-step-field="image" data-image-value value="<?= admin_escape((string) ($step[2] ?? '')) ?>"></div>
                      </article>
                    <?php endforeach; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </main>

    <footer class="admin-savebar"><span data-save-state>Изменений нет</span><button type="submit">Сохранить изменения</button></footer>
  </form>
  <template id="step-template">
    <article class="step-card" draggable="false" data-step><div class="step-card__rail"><button type="button" class="drag-handle" aria-label="Перетащить этап">⋮⋮</button><strong data-step-number></strong><div><button type="button" data-step-up>↑</button><button type="button" data-step-down>↓</button></div></div><div class="step-card__body"><label>Заголовок<input data-step-field="title" required></label><label>Текст<textarea data-step-field="text" rows="4" required></textarea></label><button type="button" class="remove-step" data-remove-step>Удалить этап</button></div><div class="step-card__image" data-image-control><div class="image-placeholder" data-image-preview>Добавьте фото</div><label class="upload-button">Выбрать фото<input type="file" accept="image/jpeg,image/png,image/webp" data-image-upload data-image-kind="step"></label><input type="hidden" data-step-field="image" data-image-value required></div></article>
  </template>
  <div class="admin-toast" role="status" aria-live="polite"></div>
  <script src="/admin/admin.js?v=2"></script>
<?php endif; ?>
</body>
</html>
