import type { Env } from "../env";
import { getSetting, setSetting } from "./db";
import { MAX_MEDIA_BYTES, deleteMedia, putMedia } from "./media";

/**
 * Instagram 最新投稿の同期（Instagram API with Instagram Login）
 *  - 長期アクセストークン（60日）は D1 に保存し、残り30日を切ったら自動更新
 *  - 画像は Instagram 側URLに有効期限があるため R2 にコピーして配信
 *  - 6時間ごとの Cron と、管理画面の「今すぐ更新」から実行
 */
const API = "https://graph.instagram.com";
const KEEP = 12; // 保持する投稿数

type Media = {
  id: string;
  caption?: string;
  media_type: "IMAGE" | "VIDEO" | "CAROUSEL_ALBUM";
  media_url?: string;
  thumbnail_url?: string;
  permalink: string;
  timestamp: string;
};

export type SyncResult = { ok: boolean; added: number; removed: number; message: string };

export async function getInstagramToken(env: Env) {
  return env.DB.prepare("SELECT token, expires_at, refreshed_at FROM integration_tokens WHERE name = 'instagram'")
    .first<{ token: string; expires_at: string | null; refreshed_at: string }>();
}

export async function saveInstagramToken(env: Env, token: string, expiresInSec?: number) {
  const expiresAt = new Date(Date.now() + (expiresInSec ?? 60 * 24 * 3600) * 1000).toISOString();
  await env.DB.prepare(
    `INSERT INTO integration_tokens (name, token, expires_at) VALUES ('instagram', ?, ?)
     ON CONFLICT(name) DO UPDATE SET token = excluded.token, expires_at = excluded.expires_at,
       refreshed_at = strftime('%Y-%m-%dT%H:%M:%fZ','now')`,
  ).bind(token, expiresAt).run();
}

async function refreshIfNeeded(env: Env, t: { token: string; expires_at: string | null; refreshed_at: string }) {
  const expires = t.expires_at ? Date.parse(t.expires_at) : 0;
  const ageMs = Date.now() - Date.parse(t.refreshed_at);
  // 期限まで30日未満、かつ発行から24時間以上経過していれば更新
  if (expires - Date.now() > 30 * 24 * 3600 * 1000 || ageMs < 24 * 3600 * 1000) return t.token;
  const res = await fetch(`${API}/refresh_access_token?grant_type=ig_refresh_token&access_token=${encodeURIComponent(t.token)}`);
  if (!res.ok) return t.token;
  const json = (await res.json()) as { access_token?: string; expires_in?: number };
  if (json.access_token) {
    await saveInstagramToken(env, json.access_token, json.expires_in);
    return json.access_token;
  }
  return t.token;
}

export async function syncInstagram(env: Env): Promise<SyncResult> {
  const t = await getInstagramToken(env);
  if (!t) return { ok: false, added: 0, removed: 0, message: "アクセストークンが未登録です" };

  let result: SyncResult;
  try {
    const token = await refreshIfNeeded(env, t);
    const fields = "id,caption,media_type,media_url,thumbnail_url,permalink,timestamp";
    const res = await fetch(`${API}/me/media?fields=${fields}&limit=${KEEP}&access_token=${encodeURIComponent(token)}`);
    if (!res.ok) {
      const body = await res.text();
      throw new Error(`Instagram API ${res.status}: ${body.slice(0, 200)}`);
    }
    const { data } = (await res.json()) as { data: Media[] };

    const { results: existing } = await env.DB.prepare("SELECT id, image_key FROM instagram_posts").all<{ id: string; image_key: string | null }>();
    const have = new Map(existing.map((e) => [e.id, e.image_key]));
    let added = 0;

    for (const m of data) {
      let imageKey = have.get(m.id) ?? null;
      if (!imageKey) {
        const src = m.media_type === "VIDEO" ? m.thumbnail_url : m.media_url;
        if (src) {
          const img = await fetch(src);
          const type = img.headers.get("content-type") ?? "";
          if (img.ok && type.startsWith("image/")) {
            const bytes = await img.arrayBuffer();
            if (bytes.byteLength <= MAX_MEDIA_BYTES) {
              imageKey = `instagram/${m.id}.jpg`;
              await putMedia(env, imageKey, bytes, type);
              added++;
            }
          }
        }
      }
      await env.DB.prepare(
        `INSERT INTO instagram_posts (id, permalink, caption, media_type, posted_at, image_key) VALUES (?, ?, ?, ?, ?, ?)
         ON CONFLICT(id) DO UPDATE SET caption = excluded.caption, permalink = excluded.permalink,
           image_key = excluded.image_key, synced_at = strftime('%Y-%m-%dT%H:%M:%fZ','now')`,
      ).bind(m.id, m.permalink, m.caption ?? null, m.media_type, m.timestamp, imageKey).run();
    }

    // 取得範囲から外れた（削除・古い）投稿を片付ける
    const keep = new Set(data.map((m) => m.id));
    let removed = 0;
    for (const e of existing) {
      if (!keep.has(e.id)) {
        if (e.image_key) await deleteMedia(env, [e.image_key]);
        await env.DB.prepare("DELETE FROM instagram_posts WHERE id = ?").bind(e.id).run();
        removed++;
      }
    }
    result = { ok: true, added, removed, message: `${data.length}件を確認（新規${added}件・削除${removed}件）` };
  } catch (e) {
    result = { ok: false, added: 0, removed: 0, message: e instanceof Error ? e.message : String(e) };
  }

  const prev = await getSetting(env.DB, "instagram.sync", { lastSyncAt: null as string | null, lastError: null as string | null });
  await setSetting(
    env.DB,
    "instagram.sync",
    result.ok ? { lastSyncAt: new Date().toISOString(), lastError: null } : { ...prev, lastError: result.message },
    "system",
  );
  return result;
}
