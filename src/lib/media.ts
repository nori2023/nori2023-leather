import type { Env } from "../env";

/**
 * 公開画像の保存先
 *  - 既定：D1 の media_objects 表（Cloudflare 無料プランのみ・カード登録不要）
 *  - R2 を有効化して wrangler.jsonc に MEDIA を設定した場合：R2
 * D1 は1行2MBまでのため、画像はブラウザ側で縮小してから保存する（長辺1600px）。
 */
export const MAX_MEDIA_BYTES = 1_992_294; // 1.9MB

export type StoredMedia = { body: ReadableStream | ArrayBuffer; contentType: string; etag: string };

export async function putMedia(env: Env, key: string, bytes: ArrayBuffer, contentType: string) {
  if (bytes.byteLength > MAX_MEDIA_BYTES) throw new Error("画像が大きすぎます（1.9MBまで）");
  if (env.MEDIA) {
    await env.MEDIA.put(key, bytes, { httpMetadata: { contentType } });
    return;
  }
  await env.DB.prepare(
    `INSERT INTO media_objects (key, content_type, size, data) VALUES (?, ?, ?, ?)
     ON CONFLICT(key) DO UPDATE SET content_type = excluded.content_type, size = excluded.size, data = excluded.data`,
  ).bind(key, contentType, bytes.byteLength, bytes).run();
}

export async function getMedia(env: Env, key: string): Promise<StoredMedia | null> {
  if (env.MEDIA) {
    const obj = await env.MEDIA.get(key);
    if (!obj) return null;
    return { body: obj.body, contentType: obj.httpMetadata?.contentType ?? "application/octet-stream", etag: obj.httpEtag };
  }
  const row = await env.DB.prepare("SELECT content_type, data, size, created_at FROM media_objects WHERE key = ?")
    .bind(key)
    .first<{ content_type: string; data: number[] | ArrayBuffer; size: number; created_at: string }>();
  if (!row) return null;
  const data = row.data instanceof ArrayBuffer ? row.data : new Uint8Array(row.data).buffer;
  return { body: data, contentType: row.content_type, etag: `"${row.size}-${row.created_at}"` };
}

export async function deleteMedia(env: Env, keys: string[]) {
  if (keys.length === 0) return;
  if (env.MEDIA) {
    await env.MEDIA.delete(keys);
    return;
  }
  await env.DB.prepare(`DELETE FROM media_objects WHERE key IN (${keys.map(() => "?").join(",")})`).bind(...keys).run();
}
