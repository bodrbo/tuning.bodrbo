# GitHub → Beget: обновление `tuning.bodrbo.ru` через SSH

Ниже — рекомендуемая схема для этого статического сайта:

```text
рабочий компьютер → push в GitHub → SSH на Beget → git pull --ff-only
```

Репозиторий клонируется прямо в `public_html`, поэтому после первоначальной настройки каждое обновление занимает две команды. Доступ Beget к GitHub оформляется отдельным **read-only deploy key**: сервер сможет получать этот репозиторий, но не сможет отправлять в него изменения.

## Что понадобится

- репозиторий: `https://github.com/bodrbo/tuning.bodrbo`;
- ветка публикации: `main`;
- созданный в Beget сайт для `tuning.bodrbo.ru`;
- логин Beget и адрес SSH-сервера из блока **Тех. информация** в панели.

В командах ниже замените:

- `BEGET_LOGIN` — на логин аккаунта Beget;
- `BEGET_SERVER` — на SSH-адрес сервера, например `example.beget.tech`.

## 1. Включить SSH и подключиться к Beget

На виртуальном хостинге Beget SSH по умолчанию выключен. Включите его на главной странице панели управления, затем подключитесь из терминала:

```bash
ssh BEGET_LOGIN@BEGET_SERVER
```

Пароль используется тот же, что и для панели Beget. При первом подключении терминал попросит подтвердить ключ сервера.

## 2. Создать отдельный ключ для этого репозитория

Все следующие команды выполняются уже в SSH-терминале Beget:

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -t ed25519 -C "beget:tuning.bodrbo.ru" -f ~/.ssh/tuning_bodrbo_github
```

Для удобного `git pull` оставьте passphrase пустой: дважды нажмите Enter. Закрытый ключ останется на Beget, а в GitHub будет добавлена только его открытая часть.

Выведите открытый ключ и полностью скопируйте строку:

```bash
cat ~/.ssh/tuning_bodrbo_github.pub
```

Откройте репозиторий GitHub → **Settings → Deploy keys → Add deploy key**:

- **Title:** `Beget — tuning.bodrbo.ru`;
- **Key:** скопированная строка;
- **Allow write access:** не включать.

Прямая страница настройки: <https://github.com/bodrbo/tuning.bodrbo/settings/keys>.

## 3. Привязать ключ к репозиторию

Откройте SSH-конфигурацию на Beget:

```bash
nano ~/.ssh/config
```

Добавьте в конец файла:

```sshconfig
Host github-tuning-bodrbo
    HostName github.com
    User git
    IdentityFile ~/.ssh/tuning_bodrbo_github
    IdentitiesOnly yes
```

Сохранение в `nano`: Ctrl+O, Enter, затем Ctrl+X. После этого задайте правильные права:

```bash
chmod 600 ~/.ssh/config
chmod 600 ~/.ssh/tuning_bodrbo_github
```

Проверьте подключение:

```bash
ssh -T git@github-tuning-bodrbo
```

При первом обращении GitHub покажет fingerprint. Сверьте его с официальной документацией GitHub и подтвердите словом `yes`. Успешный результат содержит фразу `You've successfully authenticated, but GitHub does not provide shell access`. Код завершения `1` для этой проверки нормален.

## 4. Первый раз развернуть репозиторий

Сначала убедитесь, что каталог сайта действительно называется так, как ожидается:

```bash
cd ~/tuning.bodrbo.ru
pwd
ls -la
```

Если Beget создал другой каталог, используйте его реальное имя во всех следующих командах.

Не клонируйте репозиторий поверх уже заполненного `public_html`. Сначала создайте новую копию рядом:

```bash
git clone --branch main --single-branch git@github-tuning-bodrbo:bodrbo/tuning.bodrbo.git public_html.git-new
test -f public_html.git-new/index.html
```

Если вторая команда ничего не вывела, `index.html` найден. Теперь переключите каталоги, сохранив старую версию как резервную копию:

```bash
mv public_html "public_html.before-git-$(date +%Y%m%d-%H%M%S)"
mv public_html.git-new public_html
```

Проверьте результат:

```bash
cd ~/tuning.bodrbo.ru/public_html
git status
git log -1 --oneline
```

Откройте <https://tuning.bodrbo.ru/> в браузере. Старую папку `public_html.before-git-...` пока сохраните: это резервная копия для первого запуска.

## 5. Обычное обновление сайта

На рабочем компьютере изменения должны быть уже закоммичены и отправлены в ветку `main`. Затем подключитесь к Beget и выполните:

```bash
cd ~/tuning.bodrbo.ru/public_html
git pull --ff-only
```

Опция `--ff-only` не создаёт неожиданные merge-коммиты на сервере. При штатном обновлении вывод заканчивается сообщением `Fast-forward` или `Already up to date`.

Можно выполнить обновление одной строкой со своего компьютера:

```bash
ssh BEGET_LOGIN@BEGET_SERVER 'cd ~/tuning.bodrbo.ru/public_html && git pull --ff-only'
```

## 6. Проверка безопасности и результата

После первого развёртывания обязательно проверьте:

```bash
cd ~/tuning.bodrbo.ru/public_html
git status
git log -1 --oneline
```

Затем откройте в браузере:

- `https://tuning.bodrbo.ru/` — должна открыться новая версия;
- `https://tuning.bodrbo.ru/.git/config` — должен вернуться ответ 404, а не содержимое Git-конфигурации;
- `https://tuning.bodrbo.ru/proekty/` — должны открыться проекты;
- `https://tuning.bodrbo.ru/projects.html` — должен перенаправить на `/proekty/` с кодом 301.

Защита `.git` уже добавлена в `.htaccess` проекта. Не удаляйте это правило, пока репозиторий находится внутри `public_html`.

## Если `git pull` не выполняется

### `Permission denied (publickey)`

Проверьте:

```bash
ssh -T git@github-tuning-bodrbo
git remote -v
```

Адрес `origin` должен начинаться с `git@github-tuning-bodrbo:`. Если репозиторий когда-то был клонирован по HTTPS, переключите его:

```bash
git remote set-url origin git@github-tuning-bodrbo:bodrbo/tuning.bodrbo.git
```

### Git сообщает о локальных изменениях

Выполните `git status`. Не используйте `git reset --hard`, пока не убедитесь, что серверные изменения не нужны. Производственный каталог лучше не редактировать через файловый менеджер Beget: исправляйте файлы на рабочем компьютере, делайте commit и push, а на сервере только `git pull --ff-only`.

### Нужно отменить неудачное обновление

Самый безопасный рабочий процесс: на компьютере выполнить `git revert` проблемного коммита, отправить новый коммит в `main`, затем снова выполнить на Beget `git pull --ff-only`. Так история Git и состояние сервера останутся согласованными.

## Важные замечания

- `git pull` обновляет только файлы из Git. Каталог `release/` игнорируется и для публикации через Git не нужен.
- Этот сайт статический, поэтому после `git pull` не требуется `npm install` или серверная сборка.
- Deploy key предназначен только для `bodrbo/tuning.bodrbo`. Для другого репозитория на том же сервере нужен отдельный ключ.
- Если закрытый ключ на Beget будет скомпрометирован, удалите deploy key в настройках GitHub и создайте новый.

## Официальная документация

- Beget: подключение по SSH — <https://beget.com/ru/kb/how-to/ssh/kak-podklyuchitsya-po-ssh-iz-windows>
- Beget: доступ к серверам — <https://beget.com/ru/kb/faq/hosting/dostup-k-serveram>
- Beget: структура каталогов сайтов — <https://beget.com/ru/kb/manual/sajty>
- GitHub: управление deploy keys — <https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys>
- GitHub: проверка SSH-подключения — <https://docs.github.com/en/authentication/connecting-to-github-with-ssh/testing-your-ssh-connection>
