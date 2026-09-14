<?php
declare(strict_types=1);

function content_project_root(): string
{
    return __DIR__;
}

function content_runtime_dir(): string
{
    return dirname(content_project_root()) . '/content-data';
}

function content_projects_version(): string
{
    return 'tilda-exact-20260914';
}

function content_site_defaults(): array
{
    return [
        'phone_display' => '+7 (921) 967-61-15',
        'phone_href' => '+79219676115',
        'email' => 'info@bodrbo.ru',
        'hours_header' => 'Ежедневно с 10:00 до 20:00',
        'hours_short' => 'Ежедневно, 10:00–20:00',
        'opening_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        'opens' => '10:00',
        'closes' => '20:00',
        'location_label' => 'Тюнинг-центр · Порзолово',
        'location_heading' => "Тюнинг-центр\nв Порзолово",
        'address' => "Ленинградская область, Ломоносовский район,\nНизинское сельское поселение,\nпроизводственно-административная зона Порзолово",
        'route_url' => 'https://yandex.ru/maps/2/saint-petersburg/?ll=29.757679%2C59.835349&mode=routes&rtext=59.835158%2C29.745648~59.835474%2C29.759391&rtt=auto&ruri=~&z=15.17',
        'map_embed_url' => 'https://yandex.ru/map-widget/v1/?ll=29.752520%2C59.835316&mode=routes&rtext=59.835158%2C29.745648~59.835474%2C29.759391&rtt=auto&ruri=~&z=14.2',
        'latitude' => '59.835474',
        'longitude' => '29.759391',
        'telegram_url' => '',
        'whatsapp_url' => '',
    ];
}

function content_load_site(): array
{
    $defaults = content_site_defaults();
    $runtimePath = content_runtime_dir() . '/site.json';
    $defaultPath = content_project_root() . '/content/site.json';
    $source = @file_get_contents(is_file($runtimePath) ? $runtimePath : $defaultPath);
    if ($source === false) {
        return $defaults;
    }

    $data = json_decode($source, true);
    if (!is_array($data)) {
        return $defaults;
    }

    return array_merge($defaults, array_intersect_key($data, $defaults));
}

function content_load_projects(): array
{
    $runtimeDirectory = content_runtime_dir();
    $runtime = @file_get_contents($runtimeDirectory . '/projects.json');
    $runtimeVersion = @file_get_contents($runtimeDirectory . '/projects.version');
    if ($runtime !== false && trim((string) $runtimeVersion) === content_projects_version()) {
        $projects = json_decode($runtime, true);
        if (is_array($projects)) {
            return $projects;
        }
    }

    $source = @file_get_contents(content_project_root() . '/projects-data.js');
    if ($source === false) {
        return [];
    }

    $json = (string) preg_replace('/^\s*(?:const\s+PROJECTS|window\.PROJECTS)\s*=\s*/', '', $source, 1);
    $json = (string) preg_replace('/;\s*$/', '', $json);
    $projects = json_decode($json, true);
    if (is_array($projects)) {
        return $projects;
    }

    // Совместимость с исходным JS-файлом: некавыченные ключи и одинарные кавычки.
    $legacy = (string) preg_replace('/^(\s*)([A-Za-z][A-Za-z0-9_]*)\s*:/m', '$1"$2":', $json);
    $legacy = str_replace("'", '"', $legacy);
    $legacy = (string) preg_replace('/,\s*([}\]])/', '$1', $legacy);
    $projects = json_decode($legacy, true);

    return is_array($projects) ? $projects : [];
}

function content_json_flags(): int
{
    return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
}

function content_atomic_write(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Не удалось создать папку для данных.');
    }

    $temporary = $path . '.tmp-' . bin2hex(random_bytes(5));
    if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Не удалось записать временный файл.');
    }

    @chmod($temporary, 0664);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Не удалось заменить файл данных.');
    }
}

function content_backup(string $path, string $label): void
{
    if (!is_file($path)) {
        return;
    }

    $backupDirectory = content_runtime_dir() . '/backups';
    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0775, true) && !is_dir($backupDirectory)) {
        throw new RuntimeException('Не удалось создать папку резервных копий.');
    }

    $extension = pathinfo($path, PATHINFO_EXTENSION);
    $name = $label . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(2));
    $destination = $backupDirectory . '/' . $name . ($extension !== '' ? '.' . $extension : '');
    if (!copy($path, $destination)) {
        throw new RuntimeException('Не удалось создать резервную копию.');
    }
}

function content_save_site(array $data): void
{
    $path = content_runtime_dir() . '/site.json';
    content_backup($path, 'site');
    $encoded = json_encode($data, content_json_flags());
    if (!is_string($encoded)) {
        throw new RuntimeException('Не удалось собрать данные контактов.');
    }
    content_atomic_write($path, $encoded . "\n");
}

function content_save_projects(array $projects): void
{
    $path = content_runtime_dir() . '/projects.json';
    content_backup($path, 'projects');
    $encoded = json_encode($projects, content_json_flags());
    if (!is_string($encoded)) {
        throw new RuntimeException('Не удалось собрать данные кейсов.');
    }
    content_atomic_write($path, $encoded . "\n");
    content_atomic_write(content_runtime_dir() . '/projects.version', content_projects_version() . "\n");
}
