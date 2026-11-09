<?php
declare(strict_types=1);

function get_setting(string $key, mixed $fallback = null): mixed
{
    $v = db_value('SELECT value FROM site_settings WHERE `key` = ?', [$key]);
    if ($v === null) {
        return $fallback;
    }
    $decoded = json_decode((string) $v, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
}

function set_setting(string $key, mixed $value, string $actor = 'system'): void
{
    db_exec(
        'INSERT INTO site_settings (`key`, value, updated_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
        [$key, json_encode($value, JSON_UNESCAPED_UNICODE), $actor],
    );
}

function hero_setting(): array
{
    $h = get_setting('home.hero', []);
    return [
        'catch' => is_string($h['catch'] ?? null) && $h['catch'] !== '' ? $h['catch'] : SITE_CATCH,
        'sub' => is_string($h['sub'] ?? null) && $h['sub'] !== '' ? $h['sub'] : SITE_SUB,
        'imageKey' => is_string($h['imageKey'] ?? null) ? $h['imageKey'] : null,
    ];
}

function sales_enabled(): bool
{
    return get_setting('sales.enabled', false) === true;
}
