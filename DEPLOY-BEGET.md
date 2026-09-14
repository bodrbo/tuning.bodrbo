# Размещение `bodrbo-tuning.ru` на Beget

Главный публичный адрес сайта — `bodrbo-tuning.ru`. Директория на Beget может по-прежнему называться `tuning.bodrbo.ru`: её имя не влияет на адрес сайта. PHP используется для отправки формы, серверных страниц проектов и безопасного обновления каталога Marine Rocket; на сервере должны быть доступны расширения cURL и SimpleXML. Настройка формы описана в [`INTEGRATION-BODRY-BUSINESS.md`](INTEGRATION-BODRY-BUSINESS.md).

Если сайт будет обновляться из GitHub командой `git pull`, используйте отдельную инструкцию [`GIT-BEGET.md`](GIT-BEGET.md). Для постоянной работы это удобнее ручной загрузки ZIP-архивов.

## Готовая сборка

Для публикации используйте ZIP-архив из папки `release`. В корне архива уже находятся `index.html`, `.htaccess`, `robots.txt`, `sitemap.xml` и все ресурсы.

Если потребуется пересобрать сайт после изменений:

```bash
./build-release.sh
```

Скрипт каждый раз создаёт новую папку и новый ZIP с временной меткой, не удаляя предыдущие сборки.

## 1. Создать поддомен и сайт

1. Откройте панель Beget → **Домены и поддомены**.
2. Добавьте `bodrbo-tuning.ru` и `www.bodrbo-tuning.ru`.
3. Прикрепите оба домена к существующему сайту, директория которого содержит этот репозиторий.
4. В результате должна появиться директория примерно такого вида:

   ```text
   ~/tuning.bodrbo.ru/public_html/
   ```

Корневой домен и `www` должны указывать на IP сервера Beget. После `git pull` файл `.htaccess` перенаправит `www.bodrbo-tuning.ru` на главное зеркало `bodrbo-tuning.ru`.

## 2. Загрузить сборку

1. В разделе **Сайты** откройте файловый менеджер нужного сайта.
2. Перейдите именно в `public_html`.
3. Загрузите ZIP-архив из папки `release` и распакуйте его.
4. Проверьте, что файл лежит напрямую по пути `public_html/index.html`, а не во вложенной папке сборки.
5. Если Beget создал демонстрационный `index.php` или `index.html`, замените его файлом из сборки.

Альтернативный вариант — загрузить распакованное содержимое сборки по FTP/SFTP.

## 3. Подключить HTTPS

1. После того как поддомен начал открываться, перейдите в **Домены и поддомены**.
2. Откройте управление SSL для `bodrbo-tuning.ru` и `www.bodrbo-tuning.ru`.
3. Закажите бесплатный стандартный сертификат Let’s Encrypt.
4. Дождитесь выпуска и установки сертификата.
5. В разделе **Сайты** включите автоматическое перенаправление с HTTP на HTTPS.

Не включайте принудительный HTTPS до установки сертификата: иначе посетители могут увидеть предупреждение безопасности.

## 4. Проверить после публикации

Откройте:

- `https://bodrbo-tuning.ru/`
- `https://bodrbo-tuning.ru/proekty/`
- `https://bodrbo-tuning.ru/proekty/vosstanovlenie-katera-posle-utopleniya/`
- `https://bodrbo-tuning.ru/lodochnye-motory-marine-rocket/`
- `https://bodrbo-tuning.ru/lodochnye-motory-marine-rocket/mref90fel-t/`
- `https://bodrbo-tuning.ru/marine-rocket-catalog.php` — должен вернуть JSON с `"ok":true` и массивом `products`;
- `https://bodrbo-tuning.ru/robots.txt`
- `https://bodrbo-tuning.ru/sitemap.xml`
- `https://bodrbo-tuning.ru/sitemap-motory.xml` — должен вернуть XML со страницами всех актуальных моторов;
- `https://bodrbo-tuning.ru/submit-request.php` — запрос методом GET должен вернуть `405`;
- любой несуществующий адрес — должна показаться фирменная страница 404.

Проверьте мобильное меню, переходы по карточкам и страницам моторов, телефонные и почтовые ссылки, а также загрузку фотографий кейсов.

## Важно: форма заявки

До настройки общего секрета и файла `~/tuning.bodrbo.ru/integration-config.php` форма будет возвращать временную ошибку и не создаст заказ. Перед запуском рекламы отправьте тестовую заявку и проверьте её появление в разделе **Тюнинг-центр → Заказы** системы «Бодрый Бизнес». Также добавьте ссылку на утверждённую политику обработки персональных данных.

## Официальные инструкции Beget

- Домены и поддомены: https://beget.com/ru/kb/manual/domeny-i-poddomeny
- Управление сайтами: https://beget.com/ru/kb/manual/sajty
- DNS-записи: https://beget.com/ru/kb/manual/dns
- Подключение SSL: https://beget.com/ru/kb/how-to/sites/podklyuchenie-ssl-k-sajtu
- FTP-аккаунты: https://beget.com/ru/kb/manual/ftp
