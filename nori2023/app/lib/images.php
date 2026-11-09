<?php
declare(strict_types=1);

/**
 * 画像の保存（uploads/ フォルダ）
 * ブラウザ側で縮小・JPEG化した画像を受け取り、中身を確認してから保存する。
 */
const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

/** アップロードされた画像を検証し [bytes, ext, width, height] を返す。不正なら null */
function read_uploaded_image(string $field, int $max = MAX_IMAGE_BYTES): ?array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!is_uploaded_file($f['tmp_name']) || $f['size'] <= 0 || $f['size'] > $max) {
        return null;
    }
    $bytes = (string) file_get_contents($f['tmp_name']);
    $isJpeg = str_starts_with($bytes, "\xFF\xD8\xFF");
    $isWebp = substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP';
    if (!$isJpeg && !$isWebp) {
        return null;
    }
    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
        return null;
    }
    return ['bytes' => $bytes, 'ext' => $isJpeg ? 'jpg' : 'webp', 'width' => $info[0], 'height' => $info[1]];
}

/** uploads/ 以下に保存（$key は products/xxx/yyy.jpg のような相対パス） */
function store_upload(string $key, string $bytes): void
{
    if (!preg_match('#^(products|site|instagram)/[A-Za-z0-9/_-]+\.(jpg|webp)$#', $key)) {
        throw new RuntimeException('invalid upload key');
    }
    $path = ROOT_DIR . '/' . UPLOAD_DIR_NAME . '/' . $key;
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('cannot create upload dir');
    }
    if (file_put_contents($path, $bytes, LOCK_EX) === false) {
        throw new RuntimeException('cannot write upload');
    }
    @chmod($path, 0644);
}

function delete_uploads(array $keys): void
{
    foreach ($keys as $key) {
        if (!is_string($key) || !preg_match('#^(products|site|instagram)/[A-Za-z0-9/_-]+\.(jpg|webp)$#', $key)) {
            continue;
        }
        $path = ROOT_DIR . '/' . UPLOAD_DIR_NAME . '/' . $key;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
