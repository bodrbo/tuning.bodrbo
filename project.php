<?php
declare(strict_types=1);

require_once __DIR__ . '/content-functions.php';

function project_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? trim($_GET['slug']) : '';
$projects = content_load_projects();
$project = null;
$projectKey = '';

if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    foreach ($projects as $key => $candidate) {
        if (is_array($candidate) && ($candidate['slug'] ?? '') === $slug) {
            $project = $candidate;
            $projectKey = (string) $key;
            break;
        }
    }
}

if (!$project) {
    http_response_code(404);
    ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#071522">
  <title>Проект не найден — Бодрый Боцман</title>
  <link rel="icon" href="/assets/boatswain-face-web.png" type="image/png">
  <link rel="stylesheet" href="/styles.css?v=12">
</head>
<body class="case-page">
  <header class="site-header site-header--solid">
    <a class="brand" href="/" aria-label="Бодрый Боцман — на главную">
      <img class="brand__face" src="/assets/boatswain-face-web.png" alt="">
      <strong class="brand__name">Бодрый<br>Боцман</strong>
      <span class="brand__descriptor">Тюнинг, ремонт<br>и модернизация катеров</span>
    </a>
  </header>
  <main><section class="case-missing"><p class="eyebrow">Ошибка 404</p><h1>Такого проекта нет</h1><a class="button" href="/proekty/">Смотреть все проекты</a></section></main>
</body>
</html>
    <?php
    exit;
}

$siteUrl = 'https://bodrbo-tuning.ru';
$canonicalUrl = $siteUrl . '/proekty/' . $project['slug'] . '/';
$pageTitle = (string) $project['title'] . ' — Бодрый Боцман';
$description = (string) $project['subtitle'];
$coverUrl = $siteUrl . '/' . ltrim((string) $project['cover'], '/');
$schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Article',
            'headline' => (string) $project['title'],
            'description' => $description,
            'image' => [$coverUrl],
            'mainEntityOfPage' => $canonicalUrl,
            'author' => ['@type' => 'Organization', 'name' => 'Бодрый Боцман'],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Бодрый Боцман',
                'url' => $siteUrl . '/',
                'logo' => ['@type' => 'ImageObject', 'url' => $siteUrl . '/assets/boatswain-face-web.png'],
            ],
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $siteUrl . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Проекты', 'item' => $siteUrl . '/proekty/'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => (string) $project['shortTitle'], 'item' => $canonicalUrl],
            ],
        ],
    ],
];
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <!-- Yandex.Metrika counter -->
  <script type="text/javascript">
      (function(m,e,t,r,i,k,a){
          m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
          m[i].l=1*new Date();
          for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
          k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
      })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=104372402', 'ym');

      ym(104372402, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
  </script>
  <!-- /Yandex.Metrika counter -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= project_escape($description) ?>">
  <meta name="theme-color" content="#071522">
  <link rel="canonical" href="<?= project_escape($canonicalUrl) ?>">
  <meta property="og:locale" content="ru_RU">
  <meta property="og:type" content="article">
  <meta property="og:title" content="<?= project_escape($pageTitle) ?>">
  <meta property="og:description" content="<?= project_escape($description) ?>">
  <meta property="og:url" content="<?= project_escape($canonicalUrl) ?>">
  <meta property="og:image" content="<?= project_escape($coverUrl) ?>">
  <title><?= project_escape($pageTitle) ?></title>
  <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <link rel="icon" href="/assets/boatswain-face-web.png" type="image/png">
  <link rel="stylesheet" href="/styles.css?v=12">
</head>
<body class="case-page">
  <noscript><div><img src="https://mc.yandex.ru/watch/104372402" style="position:absolute; left:-9999px;" alt=""></div></noscript>
  <header class="site-header site-header--solid">
    <a class="brand" href="/" aria-label="Бодрый Боцман — на главную">
      <img class="brand__face" src="/assets/boatswain-face-web.png" alt="">
      <strong class="brand__name">Бодрый<br>Боцман</strong>
      <span class="brand__descriptor">Тюнинг, ремонт<br>и модернизация катеров</span>
    </a>
    <nav class="nav" aria-label="Основная навигация">
      <a href="/#services">Услуги</a><a href="/proekty/" aria-current="page">Проекты</a><a href="/lodochnye-motory-marine-rocket/">Моторы</a><a href="/#about">О компании</a><a href="/#contacts">Контакты</a>
    </nav>
    <div class="header-contact"><a href="tel:+79219676115" data-contact="phone">+7 (921) 967-61-15</a><small data-contact="hours-header">Ежедневно с 10:00 до 20:00</small></div>
    <a class="button button--small" href="/#request">Обсудить проект</a>
    <button class="menu-toggle" aria-expanded="false" aria-controls="mobile-menu" aria-label="Открыть меню"><span></span><span></span></button>
  </header>
  <div class="mobile-menu" id="mobile-menu">
    <a href="/#services">Услуги</a><a href="/proekty/" aria-current="page">Проекты</a><a href="/lodochnye-motory-marine-rocket/">Моторы</a><a href="/#about">О компании</a><a href="/#contacts">Контакты</a>
  </div>

  <main>
    <article>
      <header class="case-hero">
        <div class="case-hero__copy">
          <a class="case-back" href="/proekty/">← Все проекты</a>
          <p class="eyebrow"><?= project_escape((string) $project['category']) ?></p>
          <h1><?= project_escape((string) $project['title']) ?></h1>
          <p class="case-hero__lead"><?= project_escape((string) $project['subtitle']) ?></p>
          <div class="case-facts">
            <?php foreach ($project['facts'] as $fact): ?><span><?= project_escape((string) $fact) ?></span><?php endforeach; ?>
          </div>
        </div>
        <figure><img src="/<?= project_escape((string) $project['cover']) ?>" alt="<?= project_escape((string) $project['shortTitle']) ?>"></figure>
      </header>

      <section class="case-brief">
        <div><p class="eyebrow">Исходная задача</p><h2>Не замаскировать проблему,<br>а решить её инженерно</h2></div>
        <p><?= project_escape((string) $project['summary']) ?></p>
      </section>

      <section class="case-story">
        <div class="case-story__heading"><p class="eyebrow">Ход проекта</p><h2>От диагностики<br>до результата</h2></div>
        <div class="case-steps">
          <?php foreach ($project['steps'] as $index => $step): ?>
            <article class="case-step">
              <div class="case-step__copy">
                <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?> / <?= str_pad((string) count($project['steps']), 2, '0', STR_PAD_LEFT) ?></span>
                <h2><?= project_escape((string) $step[0]) ?></h2>
                <p><?= project_escape((string) $step[1]) ?></p>
              </div>
              <figure><img src="/assets/projects/<?= project_escape($projectKey) ?>/<?= project_escape((string) $step[2]) ?>" alt="<?= project_escape((string) $step[0]) ?> — <?= project_escape((string) $project['shortTitle']) ?>" loading="lazy"></figure>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="case-result">
        <p class="eyebrow">Результат</p>
        <h2><?= project_escape((string) $project['result']) ?></h2>
        <div><a class="button" href="/#request">Обсудить похожую задачу</a><a class="button button--ghost" href="/proekty/">Другие проекты</a></div>
      </section>
    </article>
  </main>

  <footer id="contacts">
    <a class="footer-brand" href="/" aria-label="Бодрый Боцман — на главную"><img src="/assets/boatswain-face-web.png" alt=""><strong>Бодрый<br>Боцман</strong></a>
    <div><small>Позвонить</small><a href="tel:+79219676115" data-contact="phone">+7 (921) 967-61-15</a></div>
    <div><small>Написать</small><a href="mailto:info@bodrbo.ru" data-contact="email">info@bodrbo.ru</a></div>
    <p><a href="/#contacts" data-contact="location-label">Тюнинг-центр · Порзолово</a><br><span data-contact="hours-short">Ежедневно, 10:00–20:00</span></p>
    <span>© Бодрый Боцман, 2026 · <a href="/privacy/">Политика обработки персональных данных</a></span>
  </footer>
  <script src="/site-content.php"></script>
  <script src="/script.js?v=3"></script>
</body>
</html>
