<?php
declare(strict_types=1);

require_once __DIR__ . '/marine-rocket-catalog.php';

function page_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function motor_price(int $value): string
{
    return number_format($value, 0, ',', ' ') . ' ₽';
}

function render_catalog_unavailable(): void
{
    http_response_code(503);
    header('Retry-After: 900');
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html>
    <html lang="ru">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="robots" content="noindex">
      <meta name="theme-color" content="#071522">
      <title>Каталог обновляется — Бодрый Боцман</title>
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
      <main><section class="case-missing"><p class="eyebrow">Обновляем данные</p><h1>Каталог временно недоступен</h1><p>Позвоните нам по номеру +7 (921) 967-61-15 — подберём мотор вручную.</p><a class="button" href="/lodochnye-motory-marine-rocket/">Вернуться в каталог</a></section></main>
    </body>
    </html>
    <?php
    exit;
}

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));

try {
    $catalog = load_catalog_payload();
} catch (Throwable $error) {
    render_catalog_unavailable();
}

$product = null;
foreach ($catalog['products'] ?? [] as $candidate) {
    if (is_array($candidate) && ($candidate['slug'] ?? '') === $slug) {
        $product = $candidate;
        break;
    }
}

if ($product === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/404.html');
    exit;
}

$siteUrl = 'https://tuning.bodrbo.ru';
$canonicalUrl = $siteUrl . '/lodochnye-motory-marine-rocket/' . $product['slug'] . '/';
$title = 'Лодочный мотор Marine Rocket ' . $product['model'];
$price = motor_price((int) $product['price']);
$power = (string) ($product['specs']['Мощность'] ?? '');
$metaDescription = $title
    . ($power !== '' ? ', ' . $power : '')
    . '. Цена ' . $price
    . '. Официальный дилер в Санкт-Петербурге: подбор, установка и гарантийное обслуживание.';
$availabilityText = !empty($product['available']) ? 'Доступен к заказу' : 'Наличие уточняется';
$availabilitySchema = !empty($product['available']) ? 'https://schema.org/InStock' : 'https://schema.org/PreOrder';
$pictures = array_values(array_filter($product['pictures'] ?? [], 'is_string'));
if (!$pictures) {
    $pictures = [(string) $product['image']];
}
$primarySpecs = [];
foreach (['Мощность', 'Тактность', 'Управление', 'Высота транца'] as $label) {
    if (!empty($product['specs'][$label])) {
        $primarySpecs[$label] = (string) $product['specs'][$label];
    }
}

$schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Product',
            'name' => $title,
            'image' => $pictures,
            'sku' => (string) $product['id'],
            'brand' => ['@type' => 'Brand', 'name' => 'Marine Rocket'],
            'description' => $metaDescription,
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => 'RUB',
                'price' => (int) $product['price'],
                'availability' => $availabilitySchema,
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@type' => 'Organization', 'name' => 'Бодрый Боцман'],
            ],
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $siteUrl . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Лодочные моторы Marine Rocket', 'item' => $siteUrl . '/lodochnye-motory-marine-rocket/'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => (string) $product['model'], 'item' => $canonicalUrl],
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
  <meta name="description" content="<?= page_escape($metaDescription) ?>">
  <meta name="theme-color" content="#071522">
  <link rel="canonical" href="<?= page_escape($canonicalUrl) ?>">
  <meta property="og:locale" content="ru_RU">
  <meta property="og:type" content="product">
  <meta property="og:title" content="<?= page_escape($title) ?> — Бодрый Боцман">
  <meta property="og:description" content="<?= page_escape($metaDescription) ?>">
  <meta property="og:url" content="<?= page_escape($canonicalUrl) ?>">
  <meta property="og:image" content="<?= page_escape($pictures[0]) ?>">
  <meta property="product:price:amount" content="<?= (int) $product['price'] ?>">
  <meta property="product:price:currency" content="RUB">
  <title><?= page_escape($title) ?> — купить с установкой в СПб</title>
  <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <link rel="icon" href="/assets/boatswain-face-web.png" type="image/png">
  <link rel="stylesheet" href="/styles.css?v=12">
</head>
<body class="motor-product-page">
  <noscript><div><img src="https://mc.yandex.ru/watch/104372402" style="position:absolute; left:-9999px;" alt=""></div></noscript>
  <header class="site-header site-header--solid">
    <a class="brand" href="/" aria-label="Бодрый Боцман — на главную">
      <img class="brand__face" src="/assets/boatswain-face-web.png" alt="">
      <strong class="brand__name">Бодрый<br>Боцман</strong>
      <span class="brand__descriptor">Тюнинг, ремонт<br>и модернизация катеров</span>
    </a>
    <nav class="nav" aria-label="Основная навигация">
      <a href="/#services">Услуги</a><a href="/proekty/">Проекты</a><a href="/lodochnye-motory-marine-rocket/" aria-current="page">Моторы</a><a href="/#about">О компании</a><a href="/#contacts">Контакты</a>
    </nav>
    <div class="header-contact"><a href="tel:+79219676115">+7 (921) 967-61-15</a><small>Ежедневно с 10:00 до 20:00</small></div>
    <a class="button button--small" href="#motor-order">Заказать мотор</a>
    <button class="menu-toggle" aria-expanded="false" aria-controls="mobile-menu" aria-label="Открыть меню"><span></span><span></span></button>
  </header>
  <div class="mobile-menu" id="mobile-menu">
    <a href="/#services">Услуги</a><a href="/proekty/">Проекты</a><a href="/lodochnye-motory-marine-rocket/" aria-current="page">Моторы</a><a href="/#about">О компании</a><a href="/#contacts">Контакты</a>
    <a href="tel:+79219676115">+7 (921) 967-61-15</a>
  </div>

  <main>
    <nav class="motor-breadcrumbs" aria-label="Хлебные крошки">
      <a href="/">Главная</a><span>/</span><a href="/lodochnye-motory-marine-rocket/">Моторы Marine Rocket</a><span>/</span><span aria-current="page"><?= page_escape((string) $product['model']) ?></span>
    </nav>

    <section class="motor-product-hero" aria-labelledby="motor-product-title">
      <div class="motor-product-gallery">
        <div class="motor-product-gallery__stage">
          <div class="motor-product-gallery__brand">
            <img src="/assets/marine-rocket-logo-black.png" alt="Marine Rocket">
            <span>Официальный дилер</span>
          </div>
          <span class="motor-product-gallery__code" aria-hidden="true">MR<br><?= page_escape($power !== '' ? (string) preg_replace('/[^0-9,.]/', '', $power) : '—') ?></span>
          <img class="motor-product-gallery__image" id="motor-product-image" src="<?= page_escape($pictures[0]) ?>" alt="<?= page_escape($title) ?>">
          <span class="motor-product-gallery__status<?= empty($product['available']) ? ' motor-product-gallery__status--muted' : '' ?>"><?= page_escape($availabilityText) ?></span>
        </div>
        <?php if (count($pictures) > 1): ?>
          <div class="motor-product-gallery__thumbs" aria-label="Фотографии мотора">
            <?php foreach ($pictures as $index => $picture): ?>
              <button type="button" class="motor-product-gallery__thumb<?= $index === 0 ? ' is-active' : '' ?>" data-motor-picture="<?= page_escape($picture) ?>" aria-label="Показать фотографию <?= $index + 1 ?>">
                <img src="<?= page_escape($picture) ?>" alt="" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="motor-product-copy">
        <a class="motor-product-copy__back" href="/lodochnye-motory-marine-rocket/">← Вернуться в каталог</a>
        <p class="eyebrow"><?= page_escape((string) $product['category']) ?></p>
        <h1 id="motor-product-title"><small>Лодочный мотор</small>Marine Rocket<br><em><?= page_escape((string) $product['model']) ?></em></h1>
        <p class="motor-product-copy__lead">Подберём мотор под корпус и задачи, установим в собственном тюнинг-центре и возьмём на гарантийное обслуживание.</p>
        <?php if ($primarySpecs): ?>
          <dl class="motor-product-quick-specs">
            <?php foreach ($primarySpecs as $label => $value): ?>
              <div><dt><?= page_escape($label) ?></dt><dd><?= page_escape($value) ?></dd></div>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
        <div class="motor-product-price"><span>Цена</span><b><?= page_escape($price) ?></b><small>Окончательную комплектацию и наличие подтвердит менеджер</small></div>
        <div class="motor-product-actions">
          <a class="button" href="#motor-order">Получить расчёт с установкой</a>
          <button class="button button--ghost" type="button" id="motor-share" data-share-title="<?= page_escape($title) ?>">Поделиться</button>
        </div>
      </div>
    </section>

    <section class="motor-product-specifications" aria-labelledby="motor-specifications-title">
      <div class="motor-product-specifications__heading">
        <p class="eyebrow">Параметры модели</p>
        <h2 id="motor-specifications-title">Технические<br>характеристики</h2>
      </div>
      <dl class="motor-product-specifications__list">
        <?php foreach ($product['specs'] ?? [] as $label => $value): ?>
          <div><dt><?= page_escape((string) $label) ?></dt><dd><?= page_escape((string) $value) ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <aside class="motor-product-plate" aria-label="Карточка модели">
        <span>Marine Rocket / Product data</span>
        <strong><?= page_escape((string) $product['model']) ?></strong>
        <dl>
          <div><dt>Мощность</dt><dd><?= page_escape($power !== '' ? $power : 'Уточняется') ?></dd></div>
          <div><dt>Категория</dt><dd><?= page_escape((string) $product['category']) ?></dd></div>
          <div><dt>Дилер</dt><dd>Бодрый Боцман · СПб</dd></div>
        </dl>
      </aside>
    </section>

    <section class="dealer-proof motor-product-proof" aria-labelledby="motor-product-proof-title">
      <div class="dealer-proof__intro">
        <p class="eyebrow">Мотор под ключ</p>
        <h2 id="motor-product-proof-title">Купить — значит<br>правильно установить</h2>
      </div>
      <div class="dealer-proof__grid">
        <article><span>Подбор</span><h3>Проверим совместимость</h3><p>Учтём корпус, массу, высоту транца, загрузку и предполагаемую акваторию.</p></article>
        <article><span>Монтаж</span><h3>Установим и настроим</h3><p>Навеска, управление, топливная система, электрика и контрольный запуск — в одном центре.</p></article>
        <article><span>Поддержка</span><h3>Останемся рядом</h3><p>Документы официального дилера, заводская гарантия и дальнейшее техническое обслуживание.</p></article>
      </div>
    </section>

    <section class="request motor-request" id="motor-order">
      <div><p class="eyebrow">Расчёт комплекта</p><h2><?= page_escape((string) $product['model']) ?><br>для вашего катера</h2><p>Оставьте контакты и модель катера. Специалист подтвердит наличие, проверит совместимость и рассчитает установку.</p></div>
      <form id="lead-form" action="/submit-request.php" method="post">
        <input type="hidden" name="request_id" value="">
        <input type="hidden" name="source_url" value="">
        <label class="form-trap" aria-hidden="true">Сайт компании<input name="company_website" tabindex="-1" autocomplete="off"></label>
        <label>Ваше имя<input name="name" autocomplete="name" required placeholder="Александр"></label>
        <label>Телефон<input name="phone" autocomplete="tel" required placeholder="+7 999 000-00-00"></label>
        <label class="form-wide">Катер или выбранный мотор<input name="boat_model" autocomplete="off" value="Marine Rocket <?= page_escape((string) $product['model']) ?>"></label>
        <label class="form-wide">Что важно учесть<textarea name="message" rows="3">Интересует мотор Marine Rocket <?= page_escape((string) $product['model']) ?>. Нужен подбор под катер и установка.</textarea></label>
        <button class="button form-wide" type="submit">Получить подбор и расчёт</button>
        <p class="form-note form-wide">Нажимая кнопку отправки формы, вы даёте согласие на обработку персональных данных в соответствии с <a href="/privacy/" target="_blank" rel="noopener">политикой обработки персональных данных</a>.</p>
      </form>
    </section>
  </main>

  <footer id="contacts">
    <a class="footer-brand" href="/" aria-label="Бодрый Боцман — на главную"><img src="/assets/boatswain-face-web.png" alt=""><strong>Бодрый<br>Боцман</strong></a>
    <div><small>Позвонить</small><a href="tel:+79219676115">+7 (921) 967-61-15</a></div>
    <div><small>Написать</small><a href="mailto:info@bodrbo.ru">info@bodrbo.ru</a></div>
    <p><a href="/#contacts">Тюнинг-центр · Порзолово</a><br>Ежедневно, 10:00–20:00</p>
    <span>© Бодрый Боцман, 2026 · Официальный дилер Marine Rocket · <a href="/privacy/">Политика обработки персональных данных</a></span>
  </footer>
  <div class="toast" role="status" aria-live="polite">Спасибо! Заявка отправлена — скоро мы свяжемся с вами.</div>
  <script src="/motor-page.js?v=1"></script>
  <script src="/script.js?v=3"></script>
</body>
</html>
