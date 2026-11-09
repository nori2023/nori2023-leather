import type { MiddlewareHandler } from "hono";
import { verifyWithJwks } from "hono/utils/jwt/jwt";
import type { AppEnv } from "../env";

/**
 * 管理画面の認証：Cloudflare Access（Zero Trust・無料枠）でログインし、
 * Worker 側でも Access が付与する JWT を検証する（二重の防御）。
 *  - ACCESS_TEAM_DOMAIN / ACCESS_AUD が未設定なら、誰も入れない（安全側）
 *  - ADMIN_EMAILS が設定されていれば、そのメールアドレスのみ許可
 *  - ローカル開発時のみ DEV_ADMIN_BYPASS=true で省略可（localhost 限定）
 */
export const requireAdmin: MiddlewareHandler<AppEnv> = async (c, next) => {
  const env = c.env;
  const host = new URL(c.req.url).hostname;

  if (env.DEV_ADMIN_BYPASS === "true" && (host === "localhost" || host === "127.0.0.1")) {
    c.set("adminEmail", "dev@localhost");
    return next();
  }

  if (!env.ACCESS_TEAM_DOMAIN || !env.ACCESS_AUD) {
    return c.text("管理画面の認証が未設定です（docs/DEPLOY.md を参照）。", 503);
  }

  const token = c.req.header("Cf-Access-Jwt-Assertion");
  if (!token) return c.text("ログインが必要です。", 401);

  try {
    const team = env.ACCESS_TEAM_DOMAIN.replace(/^https?:\/\//, "").replace(/\/$/, "");
    const payload = await verifyWithJwks(token, {
      jwks_uri: `https://${team}/cdn-cgi/access/certs`,
      allowedAlgorithms: ["RS256"],
      verification: { iss: `https://${team}`, aud: env.ACCESS_AUD },
    });
    const email = String(payload.email ?? "").toLowerCase();
    const allowed = env.ADMIN_EMAILS.split(",").map((s) => s.trim().toLowerCase()).filter(Boolean);
    if (!email || (allowed.length > 0 && !allowed.includes(email))) {
      return c.text("このアカウントには管理権限がありません。", 403);
    }
    c.set("adminEmail", email);
  } catch {
    return c.text("ログイン情報を確認できませんでした。もう一度ログインしてください。", 401);
  }
  return next();
};
