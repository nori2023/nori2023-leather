<?php
declare(strict_types=1);

/*
 * お客さんから受け取るファイル（ロゴ・画像データなど）
 * - 置き場所は app/storage/customer_files（app/ はブラウザから見られない）
 * - ファイル名はこちらで付け直し、拡張子も付けない（サーバー上で実行されないように）
 * - 管理画面からだけ、ダウンロードで取り出せる
 */
const CUSTOMER_FILE_MAX_BYTES = 10 * 1024 * 1024;
const CUSTOMER_FILE_MAX_COUNT = 3;
const CUSTOMER_FILE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'pdf', 'ai', 'eps', 'svg', 'psd', 'zip'];
const CUSTOMER_FILE_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp,.heic,.heif,.pdf,.ai,.eps,.svg,.psd,.zip,image/*,application/pdf';

function customer_files_dir(): string
{
    return APP_DIR . '/storage/customer_files';
}

/**
 * フォームの files[] を確かめる。
 * 戻り値：['files' => [[tmp, name, size, ext, mime, is_image]...], 'error' => '...' or '']
 */
function customer_files_check(string $field = 'files'): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'] ?? null)) {
        return ['files' => [], 'error' => ''];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        $err = (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['files' => [], 'error' => 'ファイルが大きすぎます。1つ10MBまでにしてつかぁさい。大きいデータは公式LINEで送ってもらえます。'];
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'][$i])) {
            return ['files' => [], 'error' => 'ファイルをうまく受け取れませんでした。もう一度選んで送ってつかぁさい。'];
        }
        $size = (int) $f['size'][$i];
        $clean = customer_file_clean_name((string) $name);
        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
        if (!in_array($ext, CUSTOMER_FILE_EXTS, true)) {
            return ['files' => [], 'error' => '「' . $clean . '」は受け付けられん形式です。画像（JPG・PNGなど）、PDF、AI、EPS、SVG、PSD、ZIP で送ってつかぁさい。'];
        }
        if ($size <= 0 || $size > CUSTOMER_FILE_MAX_BYTES) {
            return ['files' => [], 'error' => '「' . $clean . '」が大きすぎます。1つ10MBまでにしてつかぁさい。大きいデータは公式LINEで送ってもらえます。'];
        }
        $tmp = (string) $f['tmp_name'][$i];
        $mime = (string) ((new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream');
        // 画像として中身まで確かめられたものだけ、管理画面で縮小表示する
        $info = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? @getimagesize($tmp) : false;
        $isImage = $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
        $out[] = ['tmp' => $tmp, 'name' => $clean, 'size' => $size, 'ext' => $ext, 'mime' => $isImage ? (string) $info['mime'] : $mime, 'is_image' => $isImage ? 1 : 0];
    }
    if (count($out) > CUSTOMER_FILE_MAX_COUNT) {
        return ['files' => [], 'error' => 'ファイルは' . CUSTOMER_FILE_MAX_COUNT . 'つまでにしてつかぁさい。ほかは公式LINEで送ってもらえます。'];
    }
    return ['files' => $out, 'error' => ''];
}

function customer_file_clean_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F"<>|:*?]/u', '', $name) ?? '';
    $name = mb_substr(trim($name), -120);
    return $name !== '' ? $name : 'file';
}

/** 確かめたファイルを保存して DB に記録する。保存できた件数を返す */
function customer_files_store(string $messageId, array $files): int
{
    if (!$files) {
        return 0;
    }
    $dir = customer_files_dir() . '/' . date('Ym');
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        error_log('[nori2023 files] cannot create ' . $dir);
        return 0;
    }
    $saved = 0;
    foreach ($files as $f) {
        $id = uuid();
        $rel = date('Ym') . '/' . $id; // 拡張子は付けない
        if (!move_uploaded_file($f['tmp'], customer_files_dir() . '/' . $rel)) {
            error_log('[nori2023 files] move failed');
            continue;
        }
        @chmod(customer_files_dir() . '/' . $rel, 0640);
        try {
            db_exec('INSERT INTO contact_files (id, message_id, original_name, stored_path, mime, size, is_image) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $messageId, $f['name'], $rel, mb_substr($f['mime'], 0, 100), $f['size'], $f['is_image']]);
            $saved++;
        } catch (Throwable $e) {
            error_log('[nori2023 files] db: ' . $e->getMessage());
            @unlink(customer_files_dir() . '/' . $rel);
        }
    }
    return $saved;
}

function customer_files_of(string $messageId): array
{
    try {
        return db_all('SELECT * FROM contact_files WHERE message_id = ? ORDER BY created_at, original_name', [$messageId]);
    } catch (Throwable) {
        return [];
    }
}

function customer_file_size_text(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . 'MB' : max(1, (int) round($bytes / 1024)) . 'KB';
}

/** 管理画面から取り出す（画像は inline 表示可、それ以外は必ずダウンロード） */
function customer_file_send(array $row, bool $inline): never
{
    $path = customer_files_dir() . '/' . $row['stored_path'];
    if (!preg_match('#^\d{6}/[0-9a-f-]{36}$#', (string) $row['stored_path']) || !is_file($path)) {
        http_response_code(404);
        exit('ファイルが見つかりません。');
    }
    $inline = $inline && (int) $row['is_image'] === 1;
    header('Content-Type: ' . ($inline ? $row['mime'] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"file\"; filename*=UTF-8''" . rawurlencode((string) $row['original_name']));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

function customer_file_delete(array $row): void
{
    @unlink(customer_files_dir() . '/' . $row['stored_path']);
    db_exec('DELETE FROM contact_files WHERE id = ?', [$row['id']]);
}
