<?php
declare(strict_types=1);

/**
 * Instagram 最新投稿の同期
 *  - 新しいMetaアプリ：Unified login のシステムユーザートークン（EAA…、期限なし）→ graph.facebook.com
 *  - 旧来のアプリ：Instagram Login の長期トークン（IGAA…、60日）→ graph.instagram.com。残り30日を切ったら自動更新
 *  - 画像は Instagram 側URLに有効期限があるため uploads/instagram/ にコピーして表示
 *  - 実行タイミング：cron（任意）／トップページ表示時に6時間以上経っていれば裏で実行／管理画面の「今すぐ更新」
 */
const IG_API = 'https://graph.instagram.com';
const IG_FB_API = 'https://graph.facebook.com/v25.0';
const IG_KEEP = 12;
const IG_INTERVAL_SEC = 6 * 3600;

function ig_http_get(string $url, int $timeout = 15): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => is_string($body) ? $body : '', 'type' => $type, 'error' => $err];
}

function ig_token(): ?array
{
    return db_one("SELECT token, expires_at, refreshed_at FROM integration_tokens WHERE name = 'instagram'");
}

/** Unified login（Facebook側）のトークンか */
function ig_is_unified(string $token): bool
{
    return str_starts_with($token, 'EAA');
}

function ig_save_token(string $token, ?int $expiresInSec = null): void
{
    $expires = ig_is_unified($token) ? null : date('Y-m-d H:i:s', time() + ($expiresInSec ?? 60 * 24 * 3600));
    db_exec(
        "INSERT INTO integration_tokens (name, token, expires_at, refreshed_at) VALUES ('instagram', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), refreshed_at = NOW()",
        [$token, $expires],
    );
}

function ig_refresh_if_needed(array $t): string
{
    if (ig_is_unified($t['token'])) {
        return $t['token']; // 期限なし
    }
    $expires = $t['expires_at'] ? strtotime($t['expires_at']) : 0;
    $age = time() - (int) strtotime($t['refreshed_at']);
    if ($expires - time() > 30 * 24 * 3600 || $age < 24 * 3600) {
        return $t['token'];
    }
    $res = ig_http_get(IG_API . '/refresh_access_token?grant_type=ig_refresh_token&access_token=' . rawurlencode($t['token']));
    $json = json_decode($res['body'], true);
    if ($res['code'] === 200 && !empty($json['access_token'])) {
        ig_save_token($json['access_token'], isset($json['expires_in']) ? (int) $json['expires_in'] : null);
        return $json['access_token'];
    }
    return $t['token'];
}

function ig_api_error(array $res): string
{
    $msg = json_decode($res['body'], true)['error']['message'] ?? ($res['error'] ?: 'HTTP ' . $res['code']);
    return 'Instagram API: ' . mb_substr((string) $msg, 0, 200);
}

/** 投稿一覧のURL。Unified login はアカウントIDが必要なので、設定値か assigned_instagram_accounts から求める */
function ig_media_endpoint(string $token): string
{
    if (!ig_is_unified($token)) {
        return IG_API . '/me/media';
    }
    $acc = get_setting('instagram.account', []);
    $id = preg_replace('/\D/', '', (string) ($acc['id'] ?? ''));
    if ($id === '') {
        $res = ig_http_get(IG_FB_API . '/me/assigned_instagram_accounts?fields=id,username&access_token=' . rawurlencode($token));
        if ($res['code'] !== 200) {
            throw new RuntimeException(ig_api_error($res) . '（InstagramアカウントIDを入力すると解決する場合があります）');
        }
        $list = json_decode($res['body'], true)['data'] ?? [];
        $pick = null;
        foreach ($list as $a) {
            if (strcasecmp((string) ($a['username'] ?? ''), ltrim(INSTAGRAM_HANDLE, '@')) === 0) {
                $pick = $a;
            }
        }
        $pick ??= $list[0] ?? null;
        $id = preg_replace('/\D/', '', (string) ($pick['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('このトークンで見られるInstagramアカウントが見つかりませんでした。トークンを発行したアカウントを確認してください。');
        }
        set_setting('instagram.account', ['id' => $id, 'username' => $pick['username'] ?? null, 'manual' => false], 'system');
    }
    return IG_FB_API . '/' . $id . '/media';
}

function ig_sync(): array
{
    $t = ig_token();
    if (!$t) {
        return ['ok' => false, 'message' => 'アクセストークンが未登録です'];
    }
    try {
        $token = ig_refresh_if_needed($t);
        $fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp';
        $res = ig_http_get(ig_media_endpoint($token) . '?fields=' . $fields . '&limit=' . IG_KEEP . '&access_token=' . rawurlencode($token));
        if ($res['code'] !== 200) {
            throw new RuntimeException(ig_api_error($res));
        }
        $data = json_decode($res['body'], true)['data'] ?? [];
        $existing = [];
        // 手動で登録した投稿（m_ で始まるID）は同期の対象外
        foreach (db_all("SELECT id, image_key FROM instagram_posts WHERE id NOT LIKE 'm\\_%'") as $row) {
            $existing[$row['id']] = $row['image_key'];
        }
        $added = 0;
        foreach ($data as $m) {
            $id = preg_replace('/[^0-9A-Za-z_]/', '', (string) ($m['id'] ?? ''));
            if ($id === '' || empty($m['permalink']) || !str_starts_with((string) $m['permalink'], 'https://')) {
                continue;
            }
            $key = $existing[$id] ?? null;
            if (!$key) {
                $src = ($m['media_type'] ?? '') === 'VIDEO' ? ($m['thumbnail_url'] ?? null) : ($m['media_url'] ?? null);
                if ($src) {
                    $img = ig_http_get($src, 20);
                    $info = $img['code'] === 200 ? @getimagesizefromstring($img['body']) : false;
                    if ($info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_WEBP], true) && strlen($img['body']) <= 8 * 1024 * 1024) {
                        $key = 'instagram/' . $id . ($info[2] === IMAGETYPE_WEBP ? '.webp' : '.jpg');
                        store_upload($key, $img['body']);
                        $added++;
                    }
                }
            }
            $posted = date('Y-m-d H:i:s', strtotime((string) ($m['timestamp'] ?? 'now')) ?: time());
            db_exec(
                'INSERT INTO instagram_posts (id, permalink, caption, media_type, posted_at, image_key, synced_at) VALUES (?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE permalink = VALUES(permalink), caption = VALUES(caption), image_key = VALUES(image_key), synced_at = NOW()',
                [$id, $m['permalink'], $m['caption'] ?? null, (string) ($m['media_type'] ?? 'IMAGE'), $posted, $key],
            );
        }
        $keep = array_map(fn ($m) => (string) ($m['id'] ?? ''), $data);
        $removed = 0;
        foreach ($existing as $id => $key) {
            if (!in_array((string) $id, $keep, true)) {
                if ($key) {
                    delete_uploads([$key]);
                }
                db_exec('DELETE FROM instagram_posts WHERE id = ?', [$id]);
                $removed++;
            }
        }
        $result = ['ok' => true, 'message' => count($data) . "件を確認（新規{$added}件・削除{$removed}件）"];
    } catch (Throwable $e) {
        $result = ['ok' => false, 'message' => $e->getMessage()];
    }
    $prev = get_setting('instagram.sync', ['lastSyncAt' => null, 'lastError' => null]);
    set_setting('instagram.sync', $result['ok']
        ? ['lastSyncAt' => now(), 'lastError' => null]
        : ['lastSyncAt' => $prev['lastSyncAt'] ?? null, 'lastAttemptAt' => now(), 'lastError' => $result['message']]);
    return $result;
}

/** トップページ表示後に、前回から6時間以上経っていれば裏で同期する（cron が使えない場合の保険） */
function ig_sync_if_due_after_response(): void
{
    if (!ig_token()) {
        return;
    }
    $s = get_setting('instagram.sync', []);
    $last = max((int) strtotime((string) ($s['lastSyncAt'] ?? '')), (int) strtotime((string) ($s['lastAttemptAt'] ?? '')));
    if ($last && time() - $last < IG_INTERVAL_SEC) {
        return;
    }
    // 同時に何度も走らないよう、先に試行時刻を記録
    set_setting('instagram.sync', array_merge($s, ['lastAttemptAt' => now()]));
    register_shutdown_function(function (): void {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        ignore_user_abort(true);
        ig_sync();
    });
}
