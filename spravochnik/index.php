<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/content/boat-guide-functions.php';

$root = '/spravochnik/';
$path = parse_url($_SERVER['REQUEST_URI'] ?? $root, PHP_URL_PATH);
$slug = is_string($path) && str_starts_with($path, $root) ? trim(substr($path, strlen($root)), '/') : '';
$models = guide_models();
$model = $models[$slug] ?? null;
$missing = $slug !== '' && !$model;
$query = $slug === '' && is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
if (strlen($query) > 400 || !preg_match('//u', $query)) {
    $query = '';
}
$searchKey = guide_key($query);
$results = $slug === '' ? $models : [];
if ($query !== '') {
    $results = array_filter($results, static function (array $candidate) use ($searchKey): bool {
        if ($searchKey === '') {
            return false;
        }
        foreach ([$candidate['name'], $candidate['brand'], ...($candidate['aliases'] ?? [])] as $name) {
            if (str_contains(guide_key($name), $searchKey)) {
                return true;
            }
        }
        return false;
    });
}
uasort($results, static fn(array $a, array $b): int => strnatcmp(guide_key($a['name']), guide_key($b['name'])));
$pageSize = 36;
$pageCount = max(1, (int) ceil(count($results) / $pageSize));
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
if ($slug === '' && $page > $pageCount) {
    $missing = true;
}
if ($missing) {
    http_response_code(404);
}
$pageUrl = static function (int $number) use ($root, $query): string {
    $params = [];
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($number > 1) {
        $params['page'] = $number;
    }
    return $root . ($params ? '?' . http_build_query($params) : '');
};
$projects = [];
foreach (content_load_projects() as $project) {
    if (is_array($project) && is_string($project['slug'] ?? null) && guide_slug($project['slug'])) {
        $projects[$project['slug']] = $project;
    }
}
$coverFor = static function (array $entry) use ($projects): string {
    if (!empty($entry['photos'][0])) {
        return $entry['photos'][0];
    }
    $projectSlug = $entry['cases'][0] ?? '';
    $cover = $projects[$projectSlug]['cover'] ?? '';
    return $cover === '' ? '' : '/' . ltrim($cover, '/');
};
$heading = $missing ? 'Страница не найдена' : ($model['name'] ?? 'Справочник лодок и катеров');
$title = $heading . ($model ? ' — характеристики и обзор' : '') . ' | Бодрый Боцман';
$description = $model['intro'] ?? 'Модели лодок и катеров, характеристики, фотографии и проекты мастерской «Бодрый Боцман».';
$canonical = 'https://bodrbo-tuning.ru' . ($model ? $root . $slug . '/' : ($query === '' ? $pageUrl($page) : $root));
$noIndex = $missing || $query !== '' || ($model && !guide_indexable($model));
$cover = $model ? $coverFor($model) : '';
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#003454">
  <title><?= guide_escape($title) ?></title>
  <meta name="description" content="<?= guide_escape($description) ?>">
  <?php if (!$missing): ?><link rel="canonical" href="<?= guide_escape($canonical) ?>"><?php endif; ?>
  <?php if ($noIndex): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
  <link rel="icon" href="/assets/brand/current/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/styles.css?v=28">
  <link rel="stylesheet" href="/spravochnik/guide.css?v=2">
</head>
<body class="boat-guide">
  <header class="site-header site-header--solid">
    <a class="brand" href="/" aria-label="Бодрый Боцман — на главную">
      <img class="brand__logo" src="/assets/brand/current/bodry-botsman-combined-side-2lines-light.svg" alt="Бодрый Боцман">
      <span class="brand__descriptor">Тюнинг, ремонт<br>и модернизация катеров</span>
    </a>
    <nav class="nav nav--guide" aria-label="Основная навигация">
      <a href="/#services">Услуги</a><a href="/proekty/">Проекты</a><a href="<?= $root ?>" aria-current="page">Справочник</a><a href="/lodki-xmaran/">Лодки</a><a href="/lodochnye-motory-marine-rocket/">Моторы</a><a href="/#contacts">Контакты</a>
    </nav>
    <div class="header-contact"><a href="tel:+79219676115" data-contact="phone">+7 (921) 967-61-15</a><small data-contact="hours-header">Ежедневно с 10:00 до 20:00</small></div>
    <a class="button button--small" href="/#request">Прислать фото</a>
    <button class="menu-toggle" aria-expanded="false" aria-controls="mobile-menu" aria-label="Открыть меню"><span></span><span></span></button>
  </header>
  <div class="mobile-menu" id="mobile-menu">
    <a href="/#services">Услуги</a><a href="/proekty/">Проекты</a><a href="<?= $root ?>" aria-current="page">Справочник</a><a href="/lodki-xmaran/">Лодки</a><a href="/lodochnye-motory-marine-rocket/">Моторы</a><a href="/#contacts">Контакты</a>
    <a href="tel:+79219676115" data-contact="phone">+7 (921) 967-61-15</a>
  </div>
  <main class="guide-main">
    <nav class="guide-trail" aria-label="Хлебные крошки">
      <a href="/">Главная</a>
      <?php if ($slug !== '' || $missing): ?><a href="<?= $root ?>">Справочник</a><?php else: ?><span aria-current="page">Справочник</span><?php endif; ?>
      <?php if ($model): ?><span aria-current="page"><?= guide_escape($model['name']) ?></span><?php endif; ?>
    </nav>
    <?php if ($missing): ?>
      <h1><?= guide_escape($heading) ?></h1>
      <p><a href="<?= $root ?>">Вернуться к списку моделей</a></p>
    <?php elseif (!$model): ?>
      <h1><?= guide_escape($heading) ?></h1>
      <p class="guide-lead">Характеристики, фотографии и опыт работы с конкретными моделями.</p>
      <form class="guide-search" action="<?= $root ?>" method="get" role="search">
        <label for="guide-query">Поиск по марке или модели</label>
        <input id="guide-query" type="search" name="q" maxlength="100" value="<?= guide_escape($query) ?>" placeholder="Например, Салют 585 НТ">
        <button type="submit" class="button">Найти</button>
      </form>
      <?php if ($query !== ''): ?>
        <p class="guide-result">Найдено моделей: <?= count($results) ?> · <a href="<?= $root ?>">Все модели</a></p>
      <?php endif; ?>
      <?php if (!$results): ?><p>Попробуйте ввести марку или часть названия модели.</p><?php endif; ?>
      <ul class="guide-grid">
        <?php foreach (array_slice($results, ($page - 1) * $pageSize, $pageSize, true) as $entrySlug => $entry):
            $image = $coverFor($entry);
        ?>
          <li>
            <a class="guide-card" href="<?= $root . guide_escape($entrySlug) ?>/">
              <?php if ($image): ?><img src="<?= guide_escape($image) ?>" alt="" loading="lazy"><?php endif; ?>
              <div><h2><?= guide_escape($entry['name']) ?></h2>
              <?php if (!empty($entry['facts']['Длина корпуса'])): ?><span>Длина корпуса <?= guide_escape((string) $entry['facts']['Длина корпуса']) ?></span><?php endif; ?></div>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($pageCount > 1): ?>
        <nav class="guide-pagination" aria-label="Страницы справочника">
          <?php if ($page > 1): ?><a href="<?= guide_escape($pageUrl($page - 1)) ?>">Предыдущая</a><?php endif; ?>
          <span>Страница <?= $page ?> из <?= $pageCount ?></span>
          <?php if ($page < $pageCount): ?><a href="<?= guide_escape($pageUrl($page + 1)) ?>">Следующая</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <article>
        <header class="guide-profile">
          <div>
            <h1><?= guide_escape($heading) ?></h1>
            <?php if (!empty($model['intro'])): ?><p class="guide-lead"><?= guide_escape($model['intro']) ?></p><?php endif; ?>
            <?php if (!empty($model['facts'])): ?>
              <h2>Характеристики</h2>
              <dl class="guide-facts">
                <?php foreach ($model['facts'] as $label => $value): ?>
                  <div><dt><?= guide_escape($label) ?></dt><dd><?= guide_escape((string) $value) ?></dd></div>
                <?php endforeach; ?>
              </dl>
            <?php endif; ?>
          </div>
          <?php if ($cover): ?><figure><img src="<?= guide_escape($cover) ?>" alt="<?= guide_escape($heading) ?>" fetchpriority="high">
            <?php if (empty($model['photos'])): ?><figcaption>Фото из проекта «Бодрого Боцмана»</figcaption><?php endif; ?></figure><?php endif; ?>
        </header>
        <?php if (count($model['photos'] ?? []) > 1): ?>
          <section><h2>Фотографии</h2><div class="guide-gallery">
            <?php foreach (array_slice($model['photos'], 1) as $index => $photo): ?>
              <a href="<?= guide_escape($photo) ?>"><img src="<?= guide_escape($photo) ?>" alt="<?= guide_escape($heading) ?> — фото <?= $index + 2 ?>" loading="lazy"></a>
            <?php endforeach; ?>
          </div></section>
        <?php endif; ?>
        <?php foreach ($model['sections'] ?? [] as $label => $paragraph): ?>
          <section class="guide-notes"><h2><?= guide_escape($label) ?></h2><p><?= guide_escape($paragraph) ?></p></section>
        <?php endforeach; ?>
        <?php $caseSlugs = array_intersect($model['cases'] ?? [], array_keys($projects)); ?>
        <?php if ($caseSlugs): ?>
          <section class="guide-notes guide-cases"><h2>Работы «Бодрого Боцмана»</h2>
            <?php foreach ($caseSlugs as $caseSlug): $case = $projects[$caseSlug]; ?>
              <a href="/proekty/<?= guide_escape($caseSlug) ?>/"><?= guide_escape($case['title']) ?></a>
              <p><?= guide_escape($case['cardSummary'] ?? $case['subtitle'] ?? '') ?></p>
            <?php endforeach; ?>
          </section>
        <?php endif; ?>
        <?php if (!empty($model['sources'])): ?>
          <section class="guide-notes"><h2>Источники</h2><ul>
            <?php foreach ($model['sources'] as $name => $url): ?><li><a href="<?= guide_escape($url) ?>"><?= guide_escape($name) ?></a></li><?php endforeach; ?>
          </ul></section>
        <?php endif; ?>
      </article>
    <?php endif; ?>
  </main>
  <footer><a class="footer-brand" href="/"><img class="footer-brand__logo" src="/assets/brand/current/bodry-botsman-combined-side-2lines-light.svg" alt="Бодрый Боцман"></a><a href="<?= $root ?>">Все модели</a><a href="/privacy/">Политика обработки персональных данных</a></footer>
  <script src="/site-content.php"></script>
  <script src="/script.js?v=6"></script>
</body>
</html>
