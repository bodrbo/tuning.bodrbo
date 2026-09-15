# Админка сайта на Beget

Админка доступна по адресу `https://bodrbo-tuning.ru/admin/`. Для работы нужен PHP 8.1 или новее.

## 1. Создать пароль

В SSH-терминале Beget:

```bash
cd ~/tuning.bodrbo.ru
php8.3 -r '$p=bin2hex(random_bytes(12)); echo "PASSWORD=".$p.PHP_EOL."HASH=".password_hash($p,PASSWORD_DEFAULT).PHP_EOL;'
```

Команда выведет случайный пароль в строке `PASSWORD` и его хеш в строке `HASH`. Сохраните пароль в менеджере паролей.

## 2. Создать конфигурацию

```bash
cp public_html/admin-config.example.php admin-config.php
chmod 600 admin-config.php
```

Откройте `~/tuning.bodrbo.ru/admin-config.php` в файловом менеджере Beget. Замените `PASTE_PASSWORD_HASH_HERE` на хеш из предыдущего шага. В файле хранится не сам пароль, а его безопасный хеш.

## 3. Подготовить папку данных

```bash
mkdir -p ~/tuning.bodrbo.ru/content-data
chmod 700 ~/tuning.bodrbo.ru/content-data
```

Папка находится вне Git-репозитория, поэтому `git pull` не перезаписывает изменения из админки.

## 4. Проверить PHP

```bash
php8.3 -l public_html/admin/index.php
php8.3 -l public_html/admin/new-case.php
php8.3 -l public_html/admin/upload.php
php8.3 -l public_html/admin/bootstrap.php
php8.3 -l public_html/content-functions.php
php8.3 -l public_html/site-content.php
php8.3 -l public_html/projects.php
php8.3 -l public_html/project.php
php8.3 -l public_html/sitemap.php
```

Во всех случаях должно появиться `No syntax errors detected`. Затем откройте `/admin/`, войдите с исходным паролем и сохраните тестовое изменение.

## Создание нового кейса

1. Откройте раздел «Кейсы» и нажмите «+ Новый кейс».
2. Заполните вводную часть статьи, загрузите обложку и добавьте первый этап с фотографией.
3. Проверьте справа поисковой предпросмотр и нажмите «Создать и открыть кейс».
4. В обычном редакторе добавьте остальные этапы, при необходимости поменяйте их местами и сохраните изменения.

SEO URL создаётся из заголовка автоматически и после публикации остаётся стабильным. Вместе со страницей автоматически формируются `title`, `description`, `canonical`, Open Graph, Twitter Card, разметка `Article` и `BreadcrumbList`. Новый адрес сразу появляется в архиве `/proekty/` и в `/sitemap.xml`.

Созданный кейс публикуется сразу. Перед нажатием кнопки проверьте тексты и обложку в предпросмотре.

## Где хранятся данные

- `~/tuning.bodrbo.ru/content-data/site.json` — контакты и график;
- `~/tuning.bodrbo.ru/content-data/projects.json` — тексты и порядок этапов;
- `~/tuning.bodrbo.ru/content-data/projects.version` — версия структуры кейсов;
- `~/tuning.bodrbo.ru/content-data/backups/` — предыдущие версии;
- `public_html/assets/projects/<кейс>/uploads/` — загруженные фотографии.

Загруженные фотографии не удаляются автоматически. Поддерживаются JPG, PNG и WebP до 8 МБ.
