# Админка сайта на Beget

Админка доступна по адресу `https://bodrbo-tuning.ru/admin/`. Для работы нужен PHP 8.1 или новее.

## 1. Создать пароль

В SSH-терминале Beget:

```bash
cd ~/tuning.bodrbo.ru
php -r '$p=bin2hex(random_bytes(12)); echo "PASSWORD=".$p.PHP_EOL."HASH=".password_hash($p,PASSWORD_DEFAULT).PHP_EOL;'
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
php -l public_html/admin/index.php
php -l public_html/admin/upload.php
php -l public_html/admin/bootstrap.php
php -l public_html/content-functions.php
php -l public_html/site-content.php
```

Во всех случаях должно появиться `No syntax errors detected`. Затем откройте `/admin/`, войдите с исходным паролем и сохраните тестовое изменение.

## Где хранятся данные

- `~/tuning.bodrbo.ru/content-data/site.json` — контакты и график;
- `~/tuning.bodrbo.ru/content-data/projects.json` — тексты и порядок этапов;
- `~/tuning.bodrbo.ru/content-data/backups/` — предыдущие версии;
- `public_html/assets/projects/<кейс>/uploads/` — загруженные фотографии.

Загруженные фотографии не удаляются автоматически. Поддерживаются JPG, PNG и WebP до 8 МБ.
